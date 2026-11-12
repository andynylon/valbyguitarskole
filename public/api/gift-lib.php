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
    $english = $language === 'en';
    $safeName = mail_esc($name);
    $safeAmount = mail_esc($amount);
    $safeRecipient = mail_esc($recipient);

    if ($english) {
        $subject = 'Pay for your Valby Guitarskole gift card';
        $paragraphs = [
            'Hi ' . $safeName . ',',
            'Thank you for ordering a gift card of ' . $safeAmount . ' for ' . $safeRecipient . '.',
            'Pay ' . $safeAmount . ' by MobilePay or bank transfer.',
        ];
        if ($reference !== '') {
            $paragraphs[] = 'Write this reference in the payment message: <span style="color:#173f37;">' . mail_esc($reference) . '</span>';
        }
        $fallback = 'Reply to this email if you need the account details.';
        $closing = 'When the payment has arrived, we will email the gift card to this address so you can pass it on.';
    } else {
        $subject = 'Betal dit gavekort til Valby Guitarskole';
        $paragraphs = [
            'Hej ' . $safeName . ',',
            'Tak for din bestilling af et gavekort på ' . $safeAmount . ' til ' . $safeRecipient . '.',
            'Betal ' . $safeAmount . ' med MobilePay eller bankoverførsel.',
        ];
        if ($reference !== '') {
            $paragraphs[] = 'Skriv denne reference i beskeden: <span style="color:#173f37;">' . mail_esc($reference) . '</span>';
        }
        $fallback = 'Svar på denne mail, hvis du mangler kontonummeret.';
        $closing = 'Når betalingen er gået ind, sender vi gavekortet til denne e-mail, så du kan give det videre.';
    }

    $body = mail_brand();
    foreach ($paragraphs as $paragraph) {
        $body .= mail_p($paragraph);
    }
    if ($payment !== []) {
        $last = count($payment) - 1;
        foreach ($payment as $index => $line) {
            $body .= mail_p(mail_esc($line), $index === $last ? '0 0 16px' : '0 0 6px');
        }
    } else {
        $body .= mail_p($fallback);
    }
    $body .= mail_p($closing) . mail_signoff($english);

    return [$subject, mail_shell($english ? 'en' : 'da', mail_card($body))];
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
    $title = $english ? 'Gift card' : 'Gavekort';
    $sans = mail_sans();
    $serif = mail_serif();
    $cardInner = mail_brand()
        . '<h1 style="margin:0;font-family:' . $serif . ';font-size:40px;line-height:1.05;font-weight:400;color:#173f37;">' . $title . '</h1>'
        . '<p style="margin:16px 0 0;font-family:' . $serif . ';font-size:22px;line-height:1.35;color:#18362f;">' . mail_esc($amount) . '</p>'
        . '<p style="margin:10px 0 0;font-family:' . $sans . ';font-size:15px;line-height:1.5;color:#18362f;">' . mail_esc($for) . ' ' . mail_esc($recipient) . '</p>'
        . mail_lines($greeting, 'margin:14px 0 0;font-family:' . $serif . ';font-style:italic;font-size:18px;line-height:1.45;color:#18362f;')
        . '<p style="margin:28px 0 0;padding-top:16px;border-top:1px solid #eadfcd;font-family:' . $sans . ';font-size:14px;line-height:1.4;letter-spacing:0.16em;color:#173f37;">' . mail_esc($code) . '</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;"><tr>'
        . '<td bgcolor="#173f37" style="background-color:#173f37;padding:12px 18px;">'
        . '<a href="' . mail_esc($url) . '" style="font-family:' . $sans . ';font-size:14px;line-height:1.2;color:#fffaf0;text-decoration:none;">' . mail_esc($open) . '</a>'
        . '</td></tr></table>';
    $html = mail_shell(
        $english ? 'en' : 'da',
        mail_p($intro, '0 0 20px') . mail_card($cardInner) . mail_signoff($english)
    );

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
