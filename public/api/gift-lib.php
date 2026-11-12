<?php
declare(strict_types=1);

require_once __DIR__ . '/enquiries-lib.php';
require_once __DIR__ . '/mail-lib.php';

function gift_is_offer(string $offer): bool
{
    $name = strtolower(trim($offer));

    return $name === 'gavekort' || $name === 'gift card';
}

function gift_reference(int $id): string
{
    return 'GK-' . $id;
}

function gift_url(string $code): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'valbyguitarskole.dk';
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', $host) ?? 'valbyguitarskole.dk';

    return 'https://' . $host . '/gavekort/index.php?kode=' . rawurlencode($code);
}

function gift_payment_lines(): array
{
    $config = function_exists('content_config') ? content_config() : null;
    if (!is_array($config)) {
        return [];
    }

    $lines = [];
    $mobilepay = trim((string) ($config['mobilepay'] ?? ''));
    $reg = trim((string) ($config['bank_reg'] ?? ''));
    $account = trim((string) ($config['bank_account'] ?? ''));
    $name = trim((string) ($config['account_name'] ?? ''));
    if ($mobilepay !== '') {
        $lines[] = 'MobilePay: ' . $mobilepay;
    }
    if ($reg !== '' && $account !== '') {
        $bank = 'Bank: ';
        if ($name !== '') {
            $bank .= $name . ', ';
        }
        $lines[] = $bank . 'reg. ' . $reg . ' konto ' . $account;
    }

    return $lines;
}

function gift_order_reply(string $name, string $amount, string $recipient, string $language, int $id): array
{
    $reference = $id > 0 ? gift_reference($id) : '';
    $payment = gift_payment_lines();
    if ($language === 'en') {
        $lines = [
            'Hi ' . $name . ',',
            '',
            'Thank you for ordering a gift card of ' . $amount . ' for ' . $recipient . '.',
            '',
            'Pay ' . $amount . ' by MobilePay or bank transfer.',
        ];
        if ($reference !== '') {
            $lines[] = 'Write this reference in the payment message: ' . $reference;
        }
        if ($payment !== []) {
            $lines[] = '';
            array_push($lines, ...$payment);
        } else {
            $lines[] = 'Reply to this email if you need the account details.';
        }
        $lines[] = '';
        $lines[] = 'When the payment has arrived, we will email the gift card to this address so you can pass it on.';
        $lines[] = '';
        $lines[] = 'Kind regards';
        $lines[] = 'Valby Guitarskole';
        $lines[] = 'hej@valbyguitarskole.dk';
        $lines[] = '+45 26 32 18 40';

        return ['Pay for your Valby Guitarskole gift card', implode("\n", $lines)];
    }

    $lines = [
        'Hej ' . $name . ',',
        '',
        'Tak for din bestilling af et gavekort på ' . $amount . ' til ' . $recipient . '.',
        '',
        'Betal ' . $amount . ' med MobilePay eller bankoverførsel.',
    ];
    if ($reference !== '') {
        $lines[] = 'Skriv denne reference i beskeden: ' . $reference;
    }
    if ($payment !== []) {
        $lines[] = '';
        array_push($lines, ...$payment);
    } else {
        $lines[] = 'Svar på denne mail, hvis du mangler kontonummeret.';
    }
    $lines[] = '';
    $lines[] = 'Når betalingen er gået ind, sender vi gavekortet til denne e-mail, så du kan give det videre.';
    $lines[] = '';
    $lines[] = 'Venlig hilsen';
    $lines[] = 'Valby Guitarskole';
    $lines[] = 'hej@valbyguitarskole.dk';
    $lines[] = '+45 26 32 18 40';

    return ['Betal dit gavekort til Valby Guitarskole', implode("\n", $lines)];
}

function gift_by_code(string $code): ?array
{
    $statement = enquiries_pdo()->prepare('SELECT * FROM gift_cards WHERE code = :code');
    $statement->execute(['code' => $code]);
    $card = $statement->fetch();

    return is_array($card) ? $card : null;
}

function gift_issue(int $enquiryId): array
{
    $pdo = enquiries_pdo();
    $statement = $pdo->prepare('SELECT * FROM enquiries WHERE id = :id');
    $statement->execute(['id' => $enquiryId]);
    $enquiry = $statement->fetch();
    if (!is_array($enquiry) || !gift_is_offer((string) $enquiry['offer'])) {
        throw new InvalidArgumentException('Not a gift card order');
    }

    $existing = $pdo->prepare('SELECT * FROM gift_cards WHERE enquiry_id = :id');
    $existing->execute(['id' => $enquiryId]);
    $card = $existing->fetch();
    if (is_array($card)) {
        return $card;
    }

    $code = '';
    $insert = $pdo->prepare(
        'INSERT INTO gift_cards (enquiry_id, code, amount, buyer_name, buyer_email, recipient, greeting, language)
         VALUES (:enquiry_id, :code, :amount, :buyer_name, :buyer_email, :recipient, :greeting, :language)'
    );
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $code = gift_make_code();
        try {
            $insert->execute([
                'enquiry_id' => $enquiryId,
                'code' => $code,
                'amount' => $enquiry['student'],
                'buyer_name' => $enquiry['name'],
                'buyer_email' => $enquiry['email'],
                'recipient' => $enquiry['recipient'],
                'greeting' => $enquiry['greeting'],
                'language' => $enquiry['language'] === 'en' ? 'en' : 'da',
            ]);
            break;
        } catch (PDOException $error) {
            $duplicate = str_contains($error->getMessage(), '1062') || str_contains($error->getMessage(), 'Duplicate');
            if (!$duplicate || $attempt === 4) {
                throw $error;
            }
        }
    }

    $pdo->prepare("UPDATE enquiries SET status = 'booked' WHERE id = :id AND status <> 'closed'")
        ->execute(['id' => $enquiryId]);
    $created = gift_by_code($code);
    if ($created === null) {
        throw new RuntimeException('Gift card could not be read back');
    }

    return $created;
}

function gift_send(array $card): void
{
    $english = ($card['language'] ?? '') === 'en';
    $url = gift_url((string) $card['code']);
    $amount = (string) $card['amount'];
    $recipient = (string) $card['recipient'];
    $greeting = trim((string) ($card['greeting'] ?? ''));
    $code = (string) $card['code'];
    $subject = $english ? 'Your Valby Guitarskole gift card' : 'Dit gavekort fra Valby Guitarskole';
    $intro = $english
        ? 'The gift card is ready to pass on.'
        : 'Gavekortet er klar til at give videre.';
    $open = $english ? 'Open the gift card' : 'Åbn gavekortet';
    $for = $english ? 'For' : 'Til';
    $safe = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES);
    $greetingHtml = $greeting === ''
        ? ''
        : '<p style="margin:18px 0 0;font-style:italic;font-size:18px;">' . nl2br($safe($greeting)) . '</p>';

    $html = '<!doctype html><html><body style="margin:0;background:#f5eedd;">'
        . '<div style="max-width:460px;margin:0 auto;padding:32px 20px;font-family:Georgia,serif;color:#18362f;">'
        . '<p style="margin:0 0 24px;font-family:sans-serif;font-size:14px;">' . $safe($intro) . '</p>'
        . '<div style="background:#fffaf0;border:1px solid #173f37;padding:32px 28px;">'
        . '<p style="margin:0;font-family:sans-serif;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#da5944;">Valby Guitarskole</p>'
        . '<h1 style="margin:14px 0 0;font-weight:400;font-size:40px;">' . ($english ? 'Gift card' : 'Gavekort') . '</h1>'
        . '<p style="margin:18px 0 0;font-size:28px;">' . $safe($amount) . '</p>'
        . '<p style="margin:8px 0 0;font-family:sans-serif;font-size:14px;">' . $safe($for) . ' ' . $safe($recipient) . '</p>'
        . $greetingHtml
        . '<p style="margin:28px 0 0;font-family:sans-serif;letter-spacing:.16em;font-size:14px;">' . $safe($code) . '</p>'
        . '</div>'
        . '<p style="margin:24px 0 0;font-family:sans-serif;font-size:14px;"><a href="' . $safe($url) . '" style="color:#173f37;">' . $safe($open) . '</a></p>'
        . '</div></body></html>';

    $mail = mail_config();
    if ($mail === null) {
        throw new RuntimeException('Booking mail is not configured');
    }
    mail_send((string) $card['buyer_email'], (string) $mail['from'], $subject, $html, false, 'text/html');
}

function gift_redeem(string $code): void
{
    $statement = enquiries_pdo()->prepare(
        "UPDATE gift_cards
         SET status = 'redeemed', redeemed_at = COALESCE(redeemed_at, NOW())
         WHERE code = :code AND status = 'issued'"
    );
    $statement->execute(['code' => $code]);
    if ($statement->rowCount() < 1 && gift_by_code($code) === null) {
        throw new InvalidArgumentException('Unknown gift card');
    }
}

function gift_make_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $length = strlen($alphabet);
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, $length - 1)];
    }

    return 'VG-' . $code;
}
