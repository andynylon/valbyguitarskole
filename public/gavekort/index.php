<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/gift-lib.php';

header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

$code = strtoupper(trim((string) ($_GET['kode'] ?? '')));
$card = null;
if ($code !== '' && enquiries_config() !== null) {
    try {
        $card = gift_by_code($code);
    } catch (Throwable $error) {
        error_log('Gift card lookup failed: ' . $error->getMessage());
    }
}

$english = is_array($card) && ($card['language'] ?? '') === 'en';
$used = is_array($card) && ($card['status'] ?? '') === 'redeemed';
$title = $card === null
    ? 'Gavekort'
    : ($english ? 'Gift card' : 'Gavekort');
?>
<!doctype html>
<html lang="<?= $english ? 'en' : 'da' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= htmlspecialchars($title, ENT_QUOTES) ?> — Valby Guitarskole</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600&family=Newsreader:ital,opsz,wght@0,6..72,400;1,6..72,400&display=swap">
  <style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f5eedd; color: #18362f; font: 16px/1.5 "DM Sans", sans-serif; }
    main { width: min(440px, calc(100% - 32px)); margin: 32px auto; }
    .card { background: #fffaf0; border: 1px solid #173f37; padding: 40px 32px; }
    .mark { margin: 0; color: #da5944; font-size: 11px; letter-spacing: .18em; text-transform: uppercase; font-weight: 600; }
    h1 { margin: 14px 0 0; font: 400 48px/1 "Newsreader", serif; }
    .amount { margin: 20px 0 0; font: 400 32px/1 "Newsreader", serif; }
    .for { margin: 10px 0 0; }
    .greeting { margin: 18px 0 0; font: italic 20px/1.4 "Newsreader", serif; }
    .code { margin: 28px 0 0; letter-spacing: .16em; font-weight: 600; }
    .used { margin: 0 0 16px; background: #173f37; color: #f5eedd; padding: 10px 12px; font-size: 14px; }
    .missing { background: #fff; padding: 24px; }
    button { margin-top: 24px; background: transparent; border: 0; padding: 0; color: #173f37; font: 600 14px "DM Sans", sans-serif; text-decoration: underline; cursor: pointer; }
    @media print {
      body { background: #fff; }
      button { display: none; }
    }
  </style>
</head>
<body>
<main>
  <?php if (!is_array($card)): ?>
    <p class="missing">Gavekortet blev ikke fundet.</p>
  <?php else: ?>
    <?php if ($used): ?>
      <p class="used"><?= $english ? 'This gift card has been used.' : 'Dette gavekort er brugt.' ?></p>
    <?php endif; ?>
    <article class="card">
      <p class="mark">Valby Guitarskole</p>
      <h1><?= $english ? 'Gift card' : 'Gavekort' ?></h1>
      <p class="amount"><?= htmlspecialchars((string) $card['amount'], ENT_QUOTES) ?></p>
      <p class="for"><?= $english ? 'For' : 'Til' ?> <?= htmlspecialchars((string) $card['recipient'], ENT_QUOTES) ?></p>
      <?php if (trim((string) $card['greeting']) !== ''): ?>
        <p class="greeting"><?= nl2br(htmlspecialchars((string) $card['greeting'], ENT_QUOTES)) ?></p>
      <?php endif; ?>
      <p class="code"><?= htmlspecialchars((string) $card['code'], ENT_QUOTES) ?></p>
    </article>
    <button type="button" onclick="window.print()"><?= $english ? 'Print' : 'Udskriv' ?></button>
  <?php endif; ?>
</main>
</body>
</html>
