<?php
declare(strict_types=1);

require_once __DIR__ . '/qr.php';

/**
 * Placeholder MobilePay product links. Replace VALBY-PLACEHOLDER with the real box id.
 * The -moms suffix is the price with 25% VAT for a student who is 30 or older.
 */
function mobilepay_url(string $offer, bool $moms): string
{
    $slug = mobilepay_product_slug($offer);
    if ($moms) {
        $slug .= '-moms';
    }

    return 'https://pay.mobilepay.dk/VALBY-PLACEHOLDER/' . $slug;
}

function mobilepay_product_slug(string $offer): string
{
    $name = function_exists('mb_strtolower') ? mb_strtolower($offer, 'UTF-8') : strtolower($offer);
    if (str_contains($name, 'halv pris') || str_contains($name, 'half price')) {
        return 'proevetime-kampagne';
    }
    if (preg_match('/\b20\b/u', $name) === 1 || str_contains($name, 'halvt') || str_contains($name, 'six month')) {
        return 'halvt-aar';
    }
    if (preg_match('/\b10\b/u', $name) === 1) {
        return '10-lektioner';
    }
    if (preg_match('/\b5\b/u', $name) === 1) {
        return '5-lektioner';
    }
    if (
        str_contains($name, 'prøvetime')
        || str_contains($name, 'proevetime')
        || str_contains($name, 'trial')
        || preg_match('/\b1\b/u', $name) === 1
    ) {
        return 'proevetime';
    }

    return 'ukendt';
}

function mobilepay_price_arrow(string $offer, bool $english): string
{
    $prices = [
        'proevetime' => ['300 kr. → 375 kr.', 'DKK 300 → DKK 375'],
        '5-lektioner' => ['1.500 kr. → 1.875 kr.', 'DKK 1,500 → DKK 1,875'],
        '10-lektioner' => ['2.850 kr. → 3.562,50 kr.', 'DKK 2,850 → DKK 3,562.50'],
        'halvt-aar' => ['5.750 kr. → 7.187,50 kr.', 'DKK 5,750 → DKK 7,187.50'],
        'proevetime-kampagne' => ['150 kr. → 187,50 kr.', 'DKK 150 → DKK 187.50'],
    ];
    $slug = mobilepay_product_slug($offer);
    if (!isset($prices[$slug])) {
        return '';
    }

    return $prices[$slug][$english ? 1 : 0];
}

function mobilepay_moms_html(string $offer, bool $moms, bool $english): string
{
    if ($moms) {
        $arrow = mobilepay_price_arrow($offer, $english);
        $text = $english
            ? 'You told us the student is 30 or older. 25% VAT is added to the price'
            : 'Du har angivet, at eleven er 30 år eller derover. 25% moms lægges oven i prisen';
        if ($arrow !== '') {
            $text .= ' (' . $arrow . ')';
        }
        $text .= $english ? '. It is tax deductible.' : '. Det er fradragsberettiget.';
    } else {
        $text = $english
            ? 'You told us the student is under 30.'
            : 'Du har angivet, at eleven er under 30 år.';
    }

    return mail_p(mail_esc($text));
}

/**
 * @return array{0: string, 1: array<int, array{cid: string, data: string}>}
 */
function mobilepay_payment_parts(string $offer, bool $moms, bool $english): array
{
    $url = mobilepay_url($offer, $moms);
    $safe = mail_esc($url);
    $intro = $english
        ? 'Pay with MobilePay by scanning this code or opening the link:'
        : 'Betal med MobilePay ved at scanne koden eller åbne linket:';
    $html = mail_p($intro);
    $inline = [];
    try {
        $png = qr_png($url);
        $html .= '<p style="margin:0 0 12px;"><img src="cid:mobilepay-qr" width="180" height="180" alt="MobilePay" style="display:block;width:180px;height:180px;border:0;"></p>';
        $inline[] = ['cid' => 'mobilepay-qr', 'data' => $png];
    } catch (Throwable $error) {
        error_log('MobilePay QR failed: ' . $error->getMessage());
    }
    $html .= mail_p('<a href="' . $safe . '" style="color:#173f37;text-decoration:underline;">' . $safe . '</a>');

    return [$html, $inline];
}
