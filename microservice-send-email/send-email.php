<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\AMQP\AmqpConnection;
use App\Tracing\OtelBootstrap;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

$dotEnv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotEnv->load();

OtelBootstrap::register();

// --- Configuração ---
const MAX_RETRIES  = 3;
const RETRY_TTL_MS = 10000; // 10 segundos

$exchange      = 'notifications';
$queue         = 'user.welcome_email';
$routingKey    = 'user.welcome_email';
$retryExchange = 'notifications.retry';
$retryQueue    = 'user.welcome_email.retry';
$dlx           = 'notifications.dlx';
$dlQueue       = 'user.welcome_email.failed';

$dsn = parse_url($_ENV['MAIL_DSN']);

// --- Conexão ---
$connection = AmqpConnection::getInstance();
$channel    = $connection->channel();

$tracer     = Globals::tracerProvider()->getTracer('send-email-worker');
$propagator = Globals::propagator();

// --- Exchanges ---
$channel->exchange_declare($exchange,      'topic',  false, true, false);
$channel->exchange_declare($retryExchange, 'direct', false, true, false);
$channel->exchange_declare($dlx,           'direct', false, true, false);

// --- Fila principal — nack sem requeue vai para DLQ via DLX ---
$channel->queue_declare($queue, false, true, false, false, false, new AMQPTable([
    'x-dead-letter-exchange'    => $dlx,
    'x-dead-letter-routing-key' => $queue,
]));
$channel->queue_bind($queue, $exchange, $routingKey);

// --- Fila de retry ---
$channel->queue_declare($retryQueue, false, true, false, false, false, new AMQPTable([
    'x-dead-letter-exchange'    => $exchange,
    'x-dead-letter-routing-key' => $routingKey,
    'x-message-ttl'             => RETRY_TTL_MS,
]));
$channel->queue_bind($retryQueue, $retryExchange, $retryQueue);

// --- Dead Letter Queue ---
$channel->queue_declare($dlQueue, false, true, false, false);
$channel->queue_bind($dlQueue, $dlx, $queue);

// --- Consumer ---
$callback = function (AMQPMessage $message) use ($dsn, $channel, $retryExchange, $retryQueue, $tracer, $propagator, $queue): void {
    // Extrai W3C traceparent dos headers AMQP — continua o trace do publisher.
    $carrier = extractHeaders($message);
    $parentContext = $propagator->extract($carrier);

    $span = $tracer->spanBuilder("amqp.consume {$queue}")
        ->setSpanKind(SpanKind::KIND_CONSUMER)
        ->setParent($parentContext)
        ->setAttribute(TraceAttributes::MESSAGING_SYSTEM, 'rabbitmq')
        ->setAttribute(TraceAttributes::MESSAGING_DESTINATION_NAME, $queue)
        ->setAttribute(TraceAttributes::MESSAGING_OPERATION, 'process')
        ->startSpan();

    $scope = $span->activate();

    try {
        $data  = json_decode($message->getBody(), true);
        $email = $data['email'] ?? null;

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $span->setStatus(StatusCode::STATUS_ERROR, 'invalid payload');
            logMessage('ERROR', 'Payload inválido, descartando', ['body' => $message->getBody()]);
            $message->nack(false, false);
            return;
        }

        $span->setAttribute('email.to', $email);
        $retryCount = resolveRetryCount($message);
        $span->setAttribute('retry.count', $retryCount);

        try {
            sendWelcomeEmail($dsn, $email);
            logMessage('INFO', 'Email enviado', ['email' => $email, 'attempt' => $retryCount + 1]);
            $message->ack();
        } catch (MailerException $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            logMessage('WARNING', 'Falha ao enviar email', [
                'email'   => $email,
                'attempt' => $retryCount + 1,
                'error'   => $e->getMessage(),
            ]);

            if ($retryCount >= MAX_RETRIES) {
                $span->setAttribute('outcome', 'dlq');
                logMessage('ERROR', 'Máximo de tentativas atingido, enviando para DLQ', ['email' => $email]);
                $message->nack(false, false);
                return;
            }

            $span->setAttribute('outcome', 'retry');
            scheduleRetry($channel, $retryExchange, $retryQueue, $message, $retryCount + 1);
            $message->ack();
        }
    } finally {
        $scope->detach();
        $span->end();
    }
};

$channel->basic_qos(null, 1, null);
$channel->basic_consume($queue, '', false, false, false, false, $callback);

logMessage('INFO', "Aguardando mensagens na fila '{$queue}'. CTRL+C para encerrar.");

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();

// --- Funções auxiliares ---

function extractHeaders(AMQPMessage $message): array
{
    $props = $message->get_properties();
    $raw   = ($props['application_headers'] ?? null)?->getNativeData() ?? [];

    $out = [];
    foreach ($raw as $k => $v) {
        $out[strtolower((string) $k)] = is_scalar($v) ? (string) $v : $v;
    }
    return $out;
}

function sendWelcomeEmail(array $dsn, string $email): void
{
    $mail             = new PHPMailer(true);
    $mail->isSMTP();
    $mail->CharSet    = PHPMailer::CHARSET_UTF8;
    $mail->Host       = $dsn['host'];
    $mail->Port       = $dsn['port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $dsn['user'];
    $mail->Password   = $dsn['pass'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom($_ENV['MAIL_FROM']);
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Bem-vindo!';
    $mail->Body    = '<p>Olá! Sua conta foi criada com sucesso.</p>';

    $mail->send();
}

function scheduleRetry(
    \PhpAmqpLib\Channel\AMQPChannel $channel,
    string $retryExchange,
    string $retryQueue,
    AMQPMessage $original,
    int $nextRetryCount
): void {
    $origProps   = $original->get_properties();
    $origHeaders = ($origProps['application_headers'] ?? null)?->getNativeData() ?? [];
    $origHeaders['x-retry-count'] = $nextRetryCount;

    $retryMessage = new AMQPMessage(
        $original->getBody(),
        [
            'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'application_headers' => new AMQPTable($origHeaders),
        ]
    );

    $channel->basic_publish($retryMessage, $retryExchange, $retryQueue);

    logMessage('INFO', 'Retry agendado', [
        'attempt'  => $nextRetryCount,
        'delay_ms' => RETRY_TTL_MS,
    ]);
}

function resolveRetryCount(AMQPMessage $message): int
{
    $props = $message->get_properties();
    return ($props['application_headers'] ?? null)?->getNativeData()['x-retry-count'] ?? 0;
}

function logMessage(string $level, string $text, array $context = []): void
{
    $timestamp = date('Y-m-d H:i:s');
    $ctx       = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    echo "[{$timestamp}] [{$level}] {$text}{$ctx}" . PHP_EOL;
}
