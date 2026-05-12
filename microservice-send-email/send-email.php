<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\AMQP\AmqpConnection;
use App\Tracing\ZipkinTracer;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

$dotEnv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotEnv->load();

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

$tracer = new ZipkinTracer();

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

// --- Fila de retry — TTL expira e devolve para fila principal ---
$channel->queue_declare($retryQueue, false, true, false, false, false, new AMQPTable([
    'x-dead-letter-exchange'    => $exchange,
    'x-dead-letter-routing-key' => $routingKey,
    'x-message-ttl'             => RETRY_TTL_MS,
]));
$channel->queue_bind($retryQueue, $retryExchange, $retryQueue);

// --- Dead Letter Queue — destino final das falhas permanentes ---
$channel->queue_declare($dlQueue, false, true, false, false);
$channel->queue_bind($dlQueue, $dlx, $queue);

// --- Consumer ---
$callback = function (AMQPMessage $message) use ($dsn, $channel, $retryExchange, $retryQueue, $tracer, $queue): void {
    $span = $tracer->startFromMessage($message, "amqp.consume {$queue}");
    $span->tag('messaging.system', 'rabbitmq');
    $span->tag('messaging.destination', $queue);
    $span->tag('messaging.operation', 'process');

    try {
        $data  = json_decode($message->getBody(), true);
        $email = $data['email'] ?? null;

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $span->tag('error', 'invalid_payload');
            logMessage('ERROR', 'Payload inválido, descartando', ['body' => $message->getBody()]);
            $message->nack(false, false);
            return;
        }

        $span->tag('email.to', $email);
        $retryCount = resolveRetryCount($message);
        $span->tag('retry.count', (string) $retryCount);

        try {
            sendWelcomeEmail($dsn, $email);
            logMessage('INFO', 'Email enviado', ['email' => $email, 'attempt' => $retryCount + 1]);
            $message->ack();
        } catch (MailerException $e) {
            $span->tag('error', 'true');
            $span->tag('error.message', $e->getMessage());
            logMessage('WARNING', 'Falha ao enviar email', [
                'email'   => $email,
                'attempt' => $retryCount + 1,
                'error'   => $e->getMessage(),
            ]);

            if ($retryCount >= MAX_RETRIES) {
                $span->tag('outcome', 'dlq');
                logMessage('ERROR', 'Máximo de tentativas atingido, enviando para DLQ', ['email' => $email]);
                $message->nack(false, false);
                return;
            }

            $span->tag('outcome', 'retry');
            scheduleRetry($channel, $retryExchange, $retryQueue, $message, $retryCount + 1);
            $message->ack();
        }
    } finally {
        $span->finish();
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

function sendWelcomeEmail(array $dsn, string $email): void
{
    $mail             = new PHPMailer(true); // true = lança Exception em falha
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
    // Preserva os headers originais (B3, correlation_id) e atualiza x-retry-count.
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
