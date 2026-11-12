<?php
declare(strict_types=1);

function mail_config(): ?array
{
    static $loaded = false;
    static $config = null;
    if ($loaded) {
        return $config;
    }
    $loaded = true;

    $path = dirname(__DIR__, 2) . '/private/mail-config.php';
    if (!is_file($path)) {
        return null;
    }
    $file = require $path;
    if (!is_array($file) || !isset($file['username'], $file['password'], $file['from'], $file['to'])) {
        return null;
    }
    $config = $file;

    return $config;
}

function mail_send(string $to, string $replyTo, string $subject, string $body, bool $autoReply = false, string $contentType = 'text/plain'): void
{
    $config = mail_config();
    if ($config === null) {
        throw new RuntimeException('Booking mail is not configured');
    }

    $socket = smtpConnect($config);
    try {
        smtpDeliver($socket, (string) $config['from'], $to, $replyTo, $subject, $body, $autoReply, $contentType);
        try {
            smtpCommand($socket, 'QUIT', 221);
        } catch (Throwable $error) {
            error_log('SMTP quit failed: ' . $error->getMessage());
        }
    } finally {
        fclose($socket);
    }
}

function smtpConnect(array $config)
{
    $host = (string) ($config['host'] ?? 'websmtp.simply.com');
    $port = (int) ($config['port'] ?? 587);
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ],
    ]);
    $socket = stream_socket_client(
        'tcp://' . $host . ':' . $port,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT,
        $context,
    );
    if ($socket === false) {
        throw new RuntimeException('SMTP connection failed');
    }
    stream_set_timeout($socket, 20);

    smtpExpect($socket, 220);
    smtpCommand($socket, 'EHLO valbyguitarskole.dk', 250);
    smtpCommand($socket, 'STARTTLS', 220);
    $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
        $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    }
    if (!stream_socket_enable_crypto($socket, true, $crypto)) {
        throw new RuntimeException('STARTTLS failed');
    }
    smtpCommand($socket, 'EHLO valbyguitarskole.dk', 250);
    smtpCommand($socket, 'AUTH LOGIN', 334);
    smtpCommand($socket, base64_encode((string) $config['username']), 334);
    smtpCommand($socket, base64_encode((string) $config['password']), 235);

    return $socket;
}

function smtpDeliver($socket, string $from, string $to, string $replyTo, string $subject, string $body, bool $autoReply, string $contentType = 'text/plain'): void
{
    smtpCommand($socket, 'MAIL FROM:<' . $from . '>', 250);
    smtpCommand($socket, 'RCPT TO:<' . $to . '>', 250);
    smtpCommand($socket, 'DATA', 354);

    $headers = [
        'From: Valby Guitarskole <' . $from . '>',
        'To: <' . $to . '>',
        'Reply-To: <' . $replyTo . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: ' . $contentType . '; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    if ($autoReply) {
        $headers[] = 'Auto-Submitted: auto-replied';
    }
    $headers[] = '';
    $headers[] = trim(chunk_split(base64_encode($body), 76, "\r\n"));

    $message = implode("\r\n", $headers);
    $message = preg_replace('/^\./m', '..', $message) ?? $message;
    fwrite($socket, $message . "\r\n.\r\n");
    smtpExpect($socket, 250);
}

function smtpCommand($socket, string $command, int $expected): void
{
    fwrite($socket, $command . "\r\n");
    smtpExpect($socket, $expected);
}

function smtpExpect($socket, int $expected): void
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    if ($response === '' || (int) substr($response, 0, 3) !== $expected) {
        throw new RuntimeException('Unexpected SMTP response');
    }
}
