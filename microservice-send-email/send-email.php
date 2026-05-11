<?php

require_once __DIR__.'/vendor/autoload.php';

use App\AMQP\AmqpConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPMailer\PHPMailer\PHPMailer;

$dotEnv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotEnv->load();

$ampqConnection = AmqpConnection::getInstance();

$channel = $ampqConnection->channel();

$exchange = 'notifcations';
$queue    = 'user.welcome_email';
$routingKey = 'user.welcome_email';

$channel->exchange_declare($exchange, 'topic', false, true, false);
$channel->queue_declare($queue, false, true, false, false);
$channel->queue_bind($queue, $exchange, $routingKey);

$dsn = parse_url($_ENV['MAIL_DSN']);

$callback = function (AMQPMessage $message) use ($dsn) {
    $data = json_decode($message->getBody(), true);
    $email = $data['email'] ?? null;

    echo "[x] Sending welcome email to: {$email}" . PHP_EOL;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Host = $dsn['host'];
        $mail->Port = $dsn['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $dsn['user'];
        $mail->Password = $dsn['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom($_ENV['MAIL_FROM']);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Bem-vindo!';
        $mail->Body = "<p>Olá! Sua conta foi criada com sucesso.</p>";

        $mail->send();
        echo "[x] Email enviado para: {$email}" . PHP_EOL;
    } catch (Exception $e) {
        echo "[!] Falha ao enviar email para {$email}: {$mail->ErrorInfo}" . PHP_EOL;
    }

    $message->ack();
};

$channel->basic_qos(null, 1, null);
$channel->basic_consume($queue, '', false, false, false, false, $callback);

echo "[*] Waiting for messages on queue '{$queue}'. Press CTRL+C to stop." . PHP_EOL;

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$ampqConnection->close();