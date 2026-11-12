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
    $body = enquiryMail($gift, $offer, $name, $email, $phone, $student, $recipient, $greeting, $enquiryId);

    try {
        $from = (string) $config['from'];
        $school = (string) $config['to'];
        $socket = smtpConnect($config);
        try {
            smtpDeliver($socket, $from, $school, $email, $subject, $body, false, 'text/html');
            $mailed = true;
            try {
                if ($gift) {
                    [$replySubject, $replyBody] = gift_order_reply($name, $student, $recipient, $language, $enquiryId);
                } else {
                    [$replySubject, $replyBody] = autoReply($name, $offer, $language);
                }
                smtpDeliver($socket, $from, $email, $from, $replySubject, $replyBody, true, 'text/html');
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

function enquiryMail(
    bool $gift,
    string $offer,
    string $name,
    string $email,
    string $phone,
    string $detail,
    string $recipient,
    string $greeting,
    int $enquiryId
): string {
    $sans = mail_sans();
    $labelStyle = 'padding:9px 16px 9px 0;border-bottom:1px solid #eadfcd;font-family:' . $sans . ';font-size:12px;line-height:1.4;letter-spacing:0.04em;text-transform:uppercase;color:#da5944;vertical-align:top;';
    $valueStyle = 'padding:9px 0;border-bottom:1px solid #eadfcd;font-family:' . $sans . ';font-size:15px;line-height:1.45;color:#18362f;vertical-align:top;';
    $rows = [
        ['Tilbud', mail_esc($offer)],
        ['Navn', mail_esc($name)],
        ['E-mail', '<a href="mailto:' . mail_esc($email) . '" style="color:#173f37;text-decoration:underline;">' . mail_esc($email) . '</a>'],
        ['Telefon', mail_esc($phone)],
        ['Detalje', mail_esc($detail)],
    ];
    if ($gift) {
        $rows[] = ['Til', mail_esc($recipient)];
        if ($greeting !== '') {
            $rows[] = ['Hilsen', mail_lines($greeting, 'margin:0 0 8px;font-family:' . $sans . ';font-size:15px;line-height:1.45;color:#18362f;')];
        }
        if ($enquiryId > 0) {
            $rows[] = ['Reference', mail_esc(gift_reference($enquiryId))];
        }
    }

    $table = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
    foreach ($rows as [$label, $value]) {
        $table .= '<tr><td valign="top" style="' . $labelStyle . '">' . mail_esc($label) . '</td>'
            . '<td valign="top" style="' . $valueStyle . '">' . $value . '</td></tr>';
    }
    $table .= '</table>';

    $title = $gift ? 'Nyt gavekort' : 'Ny forespørgsel';
    $heading = '<h1 style="margin:0 0 18px;font-family:' . mail_serif() . ';font-size:32px;line-height:1.15;font-weight:400;color:#173f37;">' . $title . '</h1>';

    return mail_shell('da', mail_card(mail_brand() . $heading . $table));
}

function autoReply(string $name, string $offer, string $language): array
{
    $safeName = mail_esc($name);
    $safeOffer = mail_esc($offer);
    if ($language === 'en') {
        $body = mail_brand()
            . mail_p('Hi ' . $safeName . ',')
            . mail_p('Thank you for your message. We have received your enquiry about ' . $safeOffer . ' and will get back to you as soon as we can, within one working day.')
            . mail_p('We look forward to playing with you.')
            . mail_signoff(true);

        return ['We have received your enquiry', mail_shell('en', mail_card($body))];
    }

    $body = mail_brand()
        . mail_p('Hej ' . $safeName . ',')
        . mail_p('Tak for din besked. Vi har modtaget din forespørgsel om ' . $safeOffer . ' og vender tilbage hurtigst muligt, senest inden for én hverdag.')
        . mail_p('Vi glæder os til at spille med dig.')
        . mail_signoff(false);

    return ['Vi har modtaget din forespørgsel', mail_shell('da', mail_card($body))];
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
