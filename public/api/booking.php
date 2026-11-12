<?php
declare(strict_types=1);

require __DIR__ . '/enquiries-lib.php';
require __DIR__ . '/mail-lib.php';
require __DIR__ . '/gift-lib.php';
require __DIR__ . '/content-lib.php';

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
$recipient = field($payload, 'recipient', 200);
$greeting = note_field($payload, 'greeting', 500);
$language = field($payload, 'language', 2) === 'en' ? 'en' : 'da';
$gift = gift_is_offer($offer);

if ($offer === '' || $name === '' || $phone === '' || $student === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($gift && $recipient === '')) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$enquiry = [
    'offer' => $offer,
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'student' => $student,
    'language' => $language,
    'recipient' => $gift ? $recipient : '',
    'greeting' => $gift ? $greeting : '',
];

$saved = false;
$enquiryId = 0;
if (enquiries_config() !== null) {
    try {
        $enquiryId = enquiries_save($enquiry);
        $saved = $enquiryId > 0;
    } catch (Throwable $error) {
        error_log('Enquiry save failed: ' . $error->getMessage());
    }
}

$mailed = false;
$configFile = dirname(__DIR__, 2) . '/private/mail-config.php';
$config = is_file($configFile) ? require $configFile : null;
if (is_array($config) && isset($config['username'], $config['password'], $config['from'], $config['to'])) {
    $subject = ($gift ? 'Nyt gavekort: ' : 'Ny forespørgsel: ') . $offer;
    $lines = [
        'Tilbud: ' . $offer,
        'Navn: ' . $name,
        'E-mail: ' . $email,
        'Telefon: ' . $phone,
        'Detalje: ' . $student,
    ];
    if ($gift) {
        $lines[] = 'Til: ' . $recipient;
        if ($greeting !== '') {
            $lines[] = 'Hilsen: ' . $greeting;
        }
        if ($enquiryId > 0) {
            $lines[] = 'Reference: ' . gift_reference($enquiryId);
        }
    }
    $body = implode("\n", $lines);

    try {
        $from = (string) $config['from'];
        $school = (string) $config['to'];
        $socket = smtpConnect($config);
        try {
            smtpDeliver($socket, $from, $school, $email, $subject, $body, false);
            $mailed = true;
            try {
                if ($gift) {
                    [$replySubject, $replyBody] = gift_order_reply($name, $student, $recipient, $language, $enquiryId);
                } else {
                    [$replySubject, $replyBody] = autoReply($name, $offer, $language);
                }
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
    } catch (Throwable $error) {
        error_log('Booking mail failed: ' . $error->getMessage());
    }
} else {
    error_log('Booking mail is not configured');
}

if (!$saved && !$mailed) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true]);

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

function note_field(array $payload, string $key, int $max): string
{
    $value = trim((string) ($payload[$key] ?? ''));
    $value = str_replace("\r\n", "\n", $value);
    $value = str_replace("\r", "\n", $value);
    if (strlen($value) > $max) {
        $value = substr($value, 0, $max);
    }

    return $value;
}
