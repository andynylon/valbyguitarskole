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

function mail_esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mail_sans(): string
{
    return "-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif";
}

function mail_serif(): string
{
    return "Georgia,'Times New Roman',Times,serif";
}

function mail_shell(string $lang, string $inner): string
{
    $lang = $lang === 'en' ? 'en' : 'da';
    $sans = mail_sans();

    return '<!DOCTYPE html><html lang="' . $lang . '"><head>'
        . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Valby Guitarskole</title></head>'
        . '<body style="margin:0;padding:0;background-color:#f5eedd;-webkit-text-size-adjust:100%;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5eedd" style="background-color:#f5eedd;margin:0;padding:0;">'
        . '<tr><td align="center" style="padding:28px 16px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" align="center" style="max-width:480px;margin-left:auto;margin-right:auto;">'
        . '<tr><td style="font-family:' . $sans . ';color:#18362f;">' . $inner . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function mail_card(string $inner): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fffaf0" style="background-color:#fffaf0;border:1px solid #173f37;">'
        . '<tr><td height="4" bgcolor="#da5944" style="height:4px;line-height:4px;font-size:0;background-color:#da5944;">&nbsp;</td></tr>'
        . '<tr><td style="padding:28px 28px 32px;">' . $inner . '</td></tr>'
        . '</table>';
}

function mail_brand(): string
{
    return '<p style="margin:0 0 18px;font-family:' . mail_sans() . ';font-size:11px;line-height:1.2;letter-spacing:0.18em;text-transform:uppercase;color:#da5944;font-weight:700;">Valby Guitarskole</p>';
}

function mail_p(string $safeHtml, string $margin = '0 0 16px'): string
{
    return '<p style="margin:' . $margin . ';font-family:' . mail_sans() . ';font-size:16px;line-height:1.6;color:#18362f;">' . $safeHtml . '</p>';
}

function mail_lines(string $text, string $style): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') {
        return '';
    }

    $html = '';
    foreach (preg_split("/\n+/", $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $html .= '<p style="' . $style . '">' . mail_esc($line) . '</p>';
    }

    return $html;
}

function mail_signoff(bool $english): string
{
    $sans = mail_sans();
    $contact = 'font-family:' . $sans . ';font-size:14px;line-height:1.5;color:#18362f;';
    $closing = $english ? 'Kind regards' : 'Venlig hilsen';

    return '<p style="margin:28px 0 0;font-family:' . $sans . ';font-size:16px;line-height:1.6;color:#18362f;">' . $closing . '</p>'
        . '<p style="margin:4px 0 0;font-family:' . mail_serif() . ';font-size:22px;line-height:1.3;color:#173f37;">Valby Guitarskole</p>'
        . '<p style="margin:14px 0 0;' . $contact . '"><a href="mailto:hej@valbyguitarskole.dk" style="color:#173f37;text-decoration:underline;">hej@valbyguitarskole.dk</a></p>'
        . '<p style="margin:0;' . $contact . '"><a href="tel:+4526321840" style="color:#173f37;text-decoration:none;">+45&nbsp;26&nbsp;32&nbsp;18&nbsp;40</a></p>';
}
