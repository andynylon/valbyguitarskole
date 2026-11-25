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

function mail_send(string $to, string $replyTo, string $subject, string $body, bool $autoReply = false, string $contentType = 'text/plain', array $inline = []): void
{
    $config = mail_config();
    if ($config === null) {
        throw new RuntimeException('Booking mail is not configured');
    }

    $socket = smtpConnect($config);
    try {
        smtpDeliver($socket, (string) $config['from'], $to, $replyTo, $subject, $body, $autoReply, $contentType, $inline);
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

function smtpDeliver($socket, string $from, string $to, string $replyTo, string $subject, string $body, bool $autoReply, string $contentType = 'text/plain', array $inline = []): void
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
    ];
    if ($autoReply) {
        $headers[] = 'Auto-Submitted: auto-replied';
    }

    if ($inline === []) {
        $headers[] = 'Content-Type: ' . $contentType . '; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $headers[] = '';
        $headers[] = trim(chunk_split(base64_encode($body), 76, "\r\n"));
    } else {
        $boundary = 'mp_' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/related; boundary="' . $boundary . '"';
        $headers[] = '';
        $headers[] = '--' . $boundary;
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $headers[] = '';
        $headers[] = trim(chunk_split(base64_encode($body), 76, "\r\n"));
        foreach ($inline as $image) {
            $cid = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($image['cid'] ?? 'image')) ?? 'image';
            $data = (string) ($image['data'] ?? '');
            $headers[] = '--' . $boundary;
            $headers[] = 'Content-Type: image/png';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $headers[] = 'Content-ID: <' . $cid . '>';
            $headers[] = 'Content-Disposition: inline; filename="' . $cid . '.png"';
            $headers[] = '';
            $headers[] = trim(chunk_split(base64_encode($data), 76, "\r\n"));
        }
        $headers[] = '--' . $boundary . '--';
    }

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
    return "'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif";
}

function mail_serif(): string
{
    return "'Newsreader',Georgia,'Iowan Old Style','Palatino Linotype',Palatino,serif";
}

function mail_signature_bytes(): string
{
    static $bytes = null;
    if ($bytes !== null) {
        return $bytes;
    }
    $path = dirname(__DIR__, 2) . '/public/logos/email-signature-600x160.png';
    $loaded = is_file($path) ? file_get_contents($path) : false;
    $bytes = is_string($loaded) ? $loaded : '';

    return $bytes;
}

function mail_circle_png(): string
{
    static $png = null;
    if ($png !== null) {
        return $png;
    }
    $png = '';
    if (!function_exists('imagecreatetruecolor')) {
        return $png;
    }
    $size = 336;
    $image = imagecreatetruecolor($size, $size);
    if ($image === false) {
        return $png;
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $clear = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefilledrectangle($image, 0, 0, $size, $size, $clear);
    $coral = imagecolorallocatealpha($image, 239, 107, 81, 0);
    imagefilledellipse($image, intdiv($size, 2), intdiv($size, 2), $size - 1, $size - 1, $coral);
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();

    return $png;
}

function mail_parts(array $extra = []): array
{
    $parts = [];
    $signature = mail_signature_bytes();
    if ($signature !== '') {
        $parts[] = ['cid' => 'email-signature', 'data' => $signature];
    }
    $circle = mail_circle_png();
    if ($circle !== '') {
        $parts[] = ['cid' => 'sun-circle', 'data' => $circle];
    }
    foreach ($extra as $part) {
        if (is_array($part)) {
            $parts[] = $part;
        }
    }

    return $parts;
}

function mail_shell(string $lang, string $inner): string
{
    $lang = $lang === 'en' ? 'en' : 'da';
    $sans = mail_sans();
    $circle = mail_circle_png() === ''
        ? ''
        : '<tr><td align="right" style="padding:0 0 28px;line-height:0;font-size:0;">'
            . '<img src="cid:sun-circle" width="168" height="168" alt="" style="display:block;width:168px;height:168px;border:0;">'
            . '</td></tr>';
    $signature = mail_signature_bytes() === ''
        ? ''
        : '<img src="cid:email-signature" width="280" height="75" alt="Valby Guitarskole" style="display:block;width:280px;max-width:100%;height:auto;border:0;">';

    return '<!DOCTYPE html><html lang="' . $lang . '"><head>'
        . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com">'
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Newsreader:ital,opsz,wght@0,6..72,400;1,6..72,400;1,6..72,500&display=swap" rel="stylesheet">'
        . '<title>Valby Guitarskole</title></head>'
        . '<body style="margin:0;padding:0;background-color:#ffffff;-webkit-text-size-adjust:100%;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background-color:#ffffff;margin:0;padding:0;">'
        . '<tr><td align="center" style="padding:40px 28px 48px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" align="center" style="max-width:640px;margin-left:auto;margin-right:auto;">'
        . $circle
        . '<tr><td style="font-family:' . $sans . ';color:#18362f;">' . $inner . '</td></tr>'
        . '<tr><td style="padding-top:36px;border-top:1px solid #e3ebe8;">'
        . $signature
        . '<p style="margin:18px 0 0;font-family:' . $sans . ';font-size:14px;line-height:1.6;color:#45645c;">'
        . '<a href="mailto:hej@valbyguitarskole.dk" style="color:#173f37;text-decoration:none;">hej@valbyguitarskole.dk</a><br>'
        . '<a href="tel:+4542630966" style="color:#173f37;text-decoration:none;">+45 42 63 09 66</a>'
        . '</p></td></tr>'
        . '</table></td></tr></table></body></html>';
}

function mail_card(string $inner): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
        . '<tr><td style="padding:0;">' . $inner . '</td></tr>'
        . '</table>';
}

function mail_brand(): string
{
    return '';
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
    $closing = $english ? 'Kind regards' : 'Venlig hilsen';

    return '<p style="margin:32px 0 0;font-family:' . mail_sans() . ';font-size:16px;line-height:1.6;color:#18362f;">' . $closing . '</p>';
}
