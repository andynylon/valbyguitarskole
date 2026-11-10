<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $host) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

if (trim((string) ($payload['company'] ?? '')) !== '') {
    echo json_encode(['ok' => true]);
    exit;
}

$offer = field($payload, 'offer', 200);
$name = field($payload, 'name', 200);
$email = field($payload, 'email', 200);
$phone = field($payload, 'phone', 50);
$student = field($payload, 'student', 200);
$language = field($payload, 'language', 2) === 'en' ? 'en' : 'da';

if ($offer === '' || $name === '' || $phone === '' || $student === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$configFile = dirname(__DIR__, 2) . '/private/mail-config.php';
$config = is_file($configFile) ? require $configFile : null;
if (!is_array($config) || !isset($config['username'], $config['password'], $config['from'], $config['to'])) {
    error_log('Booking mail is not configured');
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}

$subject = 'Ny forespørgsel: ' . $offer;
$body = implode("\n", [
    'Tilbud: ' . $offer,
    'Navn: ' . $name,
    'E-mail: ' . $email,
    'Telefon: ' . $phone,
    'Detalje: ' . $student,
]);

try {
    $from = (string) $config['from'];
    $school = (string) $config['to'];
    $socket = smtpConnect($config);
    try {
        smtpDeliver($socket, $from, $school, $email, $subject, $body, false);
        try {
            [$replySubject, $replyBody] = autoReply($name, $offer, $language);
            smtpDeliver($socket, $from, $email, $from, $replySubject, $replyBody, true);
        } catch (Throwable $error) {
            error_log('Booking auto-reply failed: ' . $error->getMessage());
        }
        try {
            smtpCommand($socket, 'QUIT', 221);
        } catch (Throwable $error) {
            error_log('SMTP quit failed: ' . $error->getMessage());
        }
    } finally {
        fclose($socket);
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $error) {
    error_log('Booking mail failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}

function autoReply(string $name, string $offer, string $language): array
{
    if ($language === 'en') {
        return [
            'We have received your enquiry',
            implode("\n", [
                'Hi ' . $name . ',',
                '',
                'Thank you for your message. We have received your enquiry about ' . $offer . ' and will get back to you as soon as we can, within one working day.',
                '',
                'We look forward to playing with you.',
                '',
                'Kind regards',
                'Valby Guitarskole',
                'hej@valbyguitarskole.dk',
                '+45 26 32 18 40',
            ]),
        ];
    }

    return [
        'Vi har modtaget din forespørgsel',
        implode("\n", [
            'Hej ' . $name . ',',
            '',
            'Tak for din besked. Vi har modtaget din forespørgsel om ' . $offer . ' og vender tilbage hurtigst muligt, senest inden for én hverdag.',
            '',
            'Vi glæder os til at spille med dig.',
            '',
            'Venlig hilsen',
            'Valby Guitarskole',
            'hej@valbyguitarskole.dk',
            '+45 26 32 18 40',
        ]),
    ];
}

function field(array $payload, string $key, int $max): string
{
    $value = trim((string) ($payload[$key] ?? ''));
    $value = str_replace(["\r", "\n"], ' ', $value);
    if (strlen($value) > $max) {
        $value = substr($value, 0, $max);
    }
    return $value;
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

function smtpDeliver($socket, string $from, string $to, string $replyTo, string $subject, string $body, bool $autoReply): void
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
        'Content-Type: text/plain; charset=UTF-8',
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
