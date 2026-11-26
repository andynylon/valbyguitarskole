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

$signup = field($payload, 'kind', 20) === 'newsletter';
$offer = $signup ? 'Nyhedsbrev' : field($payload, 'offer', 200);
$name = field($payload, 'name', 200);
$email = field($payload, 'email', 200);
$phone = $signup ? '' : field($payload, 'phone', 50);
$student = $signup ? '' : field($payload, 'student', 200);
$instrument = $signup ? '' : field($payload, 'instrument', 80);
$recipient = field($payload, 'recipient', 200);
$greeting = note_field($payload, 'greeting', 500);
$message = note_field($payload, 'message', 1000);
$language = field($payload, 'language', 2) === 'en' ? 'en' : 'da';
$gift = !$signup && gift_is_offer($offer);
$classSize = booking_class_size($payload, $signup, $gift);
$offer = booking_with_class($offer, $classSize, $language);
$newsletter = $signup || (($payload['newsletter'] ?? false) === true);
$rental = !$signup && (($payload['rental'] ?? false) === true);
$moms = !$signup && (($payload['moms'] ?? false) === true);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || (!$signup && ($offer === '' || $phone === '' || $student === '' || $instrument === '')) || ($gift && $recipient === '')) {
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
    'instrument' => $instrument,
    'rent_instrument' => $rental,
    'language' => $language,
    'newsletter' => $newsletter,
    'moms' => $moms,
    'recipient' => $gift ? $recipient : '',
    'greeting' => $gift ? $greeting : '',
    'message' => $message,
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
    $subject = $signup ? 'Ny tilmelding til nyhedsbrev' : (($gift ? 'Nyt gavekort: ' : 'Ny forespørgsel: ') . $offer);
    $body = enquiryMail($gift, $offer, $name, $email, $phone, $student, $instrument, $rental, $recipient, $greeting, $enquiryId, $newsletter, $classSize, $message, $moms);

    try {
        $from = (string) $config['from'];
        $school = (string) $config['to'];
        $socket = smtpConnect($config);
        try {
            smtpDeliver($socket, $from, $school, $email, $subject, $body, false, 'text/html', mail_parts());
            $mailed = true;
            $replyInline = [];
            try {
                if ($signup) {
                    [$replySubject, $replyBody] = newsletterReply($name, $language);
                } elseif ($gift) {
                    [$replySubject, $replyBody, $replyInline] = gift_order_reply($name, $student, $recipient, $language, $enquiryId, $newsletter, $moms);
                } else {
                    [$replySubject, $replyBody, $replyInline] = autoReply($name, $offer, $language, $newsletter, $moms);
                }
                smtpDeliver($socket, $from, $email, $from, $replySubject, $replyBody, true, 'text/html', mail_parts($replyInline));
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
    string $instrument,
    bool $rental,
    string $recipient,
    string $greeting,
    int $enquiryId,
    bool $newsletter,
    int $classSize,
    string $message,
    bool $moms = false
): string {
    $sans = mail_sans();
    $labelStyle = 'padding:10px 18px 10px 0;border-bottom:1px solid #e3ebe8;font-family:' . $sans . ';font-size:12px;line-height:1.4;letter-spacing:0.04em;text-transform:uppercase;color:#45645c;vertical-align:top;';
    $valueStyle = 'padding:10px 0;border-bottom:1px solid #e3ebe8;font-family:' . $sans . ';font-size:16px;line-height:1.5;color:#18362f;vertical-align:top;';
    $rows = [
        ['Tilbud', mail_esc($offer)],
        ['Navn', mail_esc($name)],
        ['E-mail', '<a href="mailto:' . mail_esc($email) . '" style="color:#173f37;text-decoration:underline;">' . mail_esc($email) . '</a>'],
    ];
    if ($phone !== '') {
        $rows[] = ['Telefon', mail_esc($phone)];
    }
    if ($detail !== '') {
        $rows[] = ['Detalje', mail_esc($detail)];
    }
    if (!$gift && $offer !== 'Nyhedsbrev') {
        $rows[] = ['Undervisning', booking_class_mail_label($classSize)];
    }
    if ($instrument !== '') {
        $rows[] = ['Instrument', mail_esc($instrument)];
    }
    if ($rental) {
        $rows[] = ['Leje', 'Ja'];
    }
    $rows[] = ['Nyhedsbrev', $newsletter ? 'Ja' : 'Nej'];
    if ($offer !== 'Nyhedsbrev') {
        $product = $gift ? $detail : $offer;
        $momsLabel = $moms ? 'Ja' : 'Nej';
        $arrow = $moms ? mobilepay_price_arrow($product, false) : '';
        if ($arrow !== '') {
            $momsLabel .= ' · ' . $arrow;
        }
        $rows[] = ['30 år eller derover', mail_esc($momsLabel)];
    }
    if ($gift) {
        $rows[] = ['Til', mail_esc($recipient)];
        if ($greeting !== '') {
            $rows[] = ['Hilsen', mail_lines($greeting, 'margin:0 0 8px;font-family:' . $sans . ';font-size:15px;line-height:1.45;color:#18362f;')];
        }
        if ($enquiryId > 0) {
            $rows[] = ['Reference', mail_esc(gift_reference($enquiryId))];
        }
    }
    if ($message !== '') {
        $rows[] = ['Andet', mail_lines($message, 'margin:0 0 8px;font-family:' . $sans . ';font-size:15px;line-height:1.45;color:#18362f;')];
    }

    $table = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
    foreach ($rows as [$label, $value]) {
        $table .= '<tr><td valign="top" style="' . $labelStyle . '">' . mail_esc($label) . '</td>'
            . '<td valign="top" style="' . $valueStyle . '">' . $value . '</td></tr>';
    }
    $table .= '</table>';

    $title = $offer === 'Nyhedsbrev' ? 'Ny tilmelding' : ($gift ? 'Nyt gavekort' : 'Ny forespørgsel');
    $heading = '<h1 style="margin:0 0 18px;font-family:' . mail_serif() . ';font-size:32px;line-height:1.15;font-weight:400;color:#173f37;">' . $title . '</h1>';

    return mail_shell('da', mail_card(mail_brand() . $heading . $table));
}

function autoReply(string $name, string $offer, string $language, bool $newsletter, bool $moms = false): array
{
    $safeName = mail_esc($name);
    $safeOffer = mail_esc($offer);
    $english = $language === 'en';
    if ($english) {
        $body = mail_brand()
            . mail_p('Hi ' . $safeName . ',')
            . mail_p('Thank you for your message. We have received your enquiry about ' . $safeOffer . ' and will get back to you as soon as we can, within one working day.')
            . mobilepay_moms_html($offer, $moms, true)
            . ($newsletter ? mail_p('You are also signed up for the newsletter.') : '')
            . mail_p('We look forward to playing with you.')
            . mail_signoff(true);

        return ['We have received your enquiry', mail_shell('en', mail_card($body)), []];
    }

    $body = mail_brand()
        . mail_p('Hej ' . $safeName . ',')
        . mail_p('Tak for din besked. Vi har modtaget din forespørgsel om ' . $safeOffer . ' og vender tilbage hurtigst muligt, senest inden for én hverdag.')
        . mobilepay_moms_html($offer, $moms, false)
        . ($newsletter ? mail_p('Du er også tilmeldt nyhedsbrevet.') : '')
        . mail_p('Vi glæder os til at spille med dig.')
        . mail_signoff(false);

    return ['Vi har modtaget din forespørgsel', mail_shell('da', mail_card($body)), []];
}

function newsletterReply(string $name, string $language): array
{
    $safeName = mail_esc($name);
    if ($language === 'en') {
        $body = mail_brand()
            . mail_p('Hi ' . $safeName . ',')
            . mail_p('Thank you for joining the newsletter. We will write when there is news from the school. You can unsubscribe at any time by replying to this email.')
            . mail_signoff(true);

        return ['You are signed up for the newsletter', mail_shell('en', mail_card($body))];
    }

    $body = mail_brand()
        . mail_p('Hej ' . $safeName . ',')
        . mail_p('Tak, fordi du tilmelder dig nyhedsbrevet. Vi skriver, når der er nyt fra skolen. Du kan altid framelde dig ved at svare på denne mail.')
        . mail_signoff(false);

    return ['Du er tilmeldt nyhedsbrevet', mail_shell('da', mail_card($body))];
}

function booking_class_size(array $payload, bool $signup, bool $gift): int
{
    if ($signup || $gift) {
        return 1;
    }
    $size = (int) ($payload['classSize'] ?? 1);

    return $size === 2 || $size === 3 ? $size : 1;
}

function booking_class_mail_label(int $classSize): string
{
    if ($classSize === 3) {
        return 'Hold på tre · 60 min.';
    }
    if ($classSize === 2) {
        return 'Hold på to · 45 min.';
    }

    return 'Enkeltundervisning';
}

function booking_class_label(int $classSize, string $language): string
{
    if ($classSize === 3) {
        return $language === 'en' ? 'Group of three' : 'Hold på tre';
    }
    if ($classSize === 2) {
        return $language === 'en' ? 'Group of two' : 'Hold på to';
    }

    return $language === 'en' ? 'One-to-one' : 'Enkeltundervisning';
}

function booking_strip_group(string $offer): string
{
    $trimmed = preg_replace(
        '/(?:^|\s*·\s*)(hold på (to|tre|fire)|group of (two|three)|group class of four)\s*$/iu',
        '',
        $offer
    );

    return trim(is_string($trimmed) ? $trimmed : $offer);
}

function booking_with_class(string $offer, int $classSize, string $language): string
{
    $base = booking_strip_group($offer);
    if ($classSize === 1) {
        if ($base === $offer) {
            return $offer;
        }
        if ($base === '') {
            return booking_class_label(1, $language);
        }

        return $base;
    }
    if ($base === '') {
        return booking_class_label($classSize, $language);
    }

    return $base . ' · ' . booking_class_label($classSize, $language);
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
