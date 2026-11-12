<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/content-lib.php';
require dirname(__DIR__) . '/api/enquiries-lib.php';
require dirname(__DIR__) . '/api/gift-lib.php';

session_start();
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

$config = content_config();
$error = '';
$enquiries = [];
$newCount = 0;
$databaseConfigured = enquiries_config() !== null;
$databaseReady = $databaseConfigured;
$statuses = [
    'new' => 'Ny',
    'contacted' => 'Kontaktet',
    'booked' => 'Booket',
    'closed' => 'Lukket',
];
$filter = (string) ($_GET['status'] ?? '');
if (!isset($statuses[$filter])) {
    $filter = '';
}

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function editor_back(string $filter, string $notice): void
{
    $query = [];
    if ($filter !== '') {
        $query['status'] = $filter;
    }
    if ($notice !== '') {
        $query['notice'] = $notice;
    }
    $location = '/redaktor/henvendelser.php';
    if ($query !== []) {
        $location .= '?' . http_build_query($query);
    }
    header('Location: ' . $location);
    exit;
}

function editor_filter(array $statuses): string
{
    $filter = (string) ($_POST['filter'] ?? '');

    return isset($statuses[$filter]) ? $filter : '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        $error = 'Prøv igen.';
    } elseif ($config === null) {
        $error = 'Opret ~/private/cms-config.php før du kan logge ind.';
    } elseif (isset($_POST['logout'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: /redaktor/henvendelser.php');
        exit;
    } elseif (!($_SESSION['editor'] ?? false) && isset($_POST['password'])) {
        $password = (string) ($config['password'] ?? '');
        if ($password !== '' && hash_equals($password, (string) $_POST['password'])) {
            session_regenerate_id(true);
            $_SESSION['editor'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: /redaktor/henvendelser.php');
            exit;
        }
        $error = 'Forkert adgangskode.';
    } elseif (($_SESSION['editor'] ?? false) && isset($_POST['issue_gift'])) {
        $nextFilter = editor_filter($statuses);
        try {
            $card = gift_issue((int) $_POST['enquiry_id']);
            try {
                gift_send($card);
                editor_back($nextFilter, 'sent');
            } catch (Throwable $failure) {
                error_log('Gift card mail failed: ' . $failure->getMessage());
                editor_back($nextFilter, 'saved');
            }
        } catch (Throwable $failure) {
            $error = 'Gavekortet kunne ikke oprettes. Kør private/gift-cards.sql i phpMyAdmin, hvis tabellen mangler.';
            error_log('Gift card issue failed: ' . $failure->getMessage());
        }
    } elseif (($_SESSION['editor'] ?? false) && isset($_POST['resend_gift'])) {
        $nextFilter = editor_filter($statuses);
        try {
            $card = gift_by_code((string) $_POST['resend_gift']);
            if ($card === null) {
                throw new InvalidArgumentException('Unknown gift card');
            }
            gift_send($card);
            editor_back($nextFilter, 'sent');
        } catch (Throwable $failure) {
            $error = 'Gavekortet kunne ikke sendes igen.';
            error_log('Gift card resend failed: ' . $failure->getMessage());
        }
    } elseif (($_SESSION['editor'] ?? false) && isset($_POST['redeem_gift'])) {
        $nextFilter = editor_filter($statuses);
        try {
            gift_redeem((string) $_POST['redeem_gift']);
            editor_back($nextFilter, 'redeemed');
        } catch (Throwable $failure) {
            $error = 'Gavekortet kunne ikke markeres som brugt.';
            error_log('Gift card redeem failed: ' . $failure->getMessage());
        }
    } elseif (($_SESSION['editor'] ?? false) && isset($_POST['enquiry_id'], $_POST['status'])) {
        $nextFilter = (string) ($_POST['filter'] ?? '');
        if (!isset($statuses[$nextFilter])) {
            $nextFilter = '';
        }
        try {
            enquiries_set_status((int) $_POST['enquiry_id'], (string) $_POST['status']);
            $location = '/redaktor/henvendelser.php';
            if ($nextFilter !== '') {
                $location .= '?status=' . rawurlencode($nextFilter);
            }
            header('Location: ' . $location);
            exit;
        } catch (Throwable $failure) {
            $error = 'Status kunne ikke gemmes.';
            error_log('Enquiry status failed: ' . $failure->getMessage());
        }
    }
}

$signedIn = (bool) ($_SESSION['editor'] ?? false);
if ($signedIn && $databaseConfigured) {
    try {
        $enquiries = enquiries_list($filter === '' ? null : $filter);
        $newCount = enquiries_count_new();
    } catch (Throwable $failure) {
        $databaseReady = false;
        if ($error === '') {
            $error = 'Henvendelserne kunne ikke hentes. Tjek ~/private/db-config.php, og kør private/gift-cards.sql i phpMyAdmin hvis tabellerne mangler.';
        }
        error_log('Enquiry list failed: ' . $failure->getMessage());
    }
}
?>
<!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Henvendelser — Valby Guitarskole</title>
  <style>
    body { margin: 0; background: #f5eedd; color: #18362f; font: 16px/1.4 system-ui, sans-serif; }
    main { width: min(1100px, calc(100% - 32px)); margin: 32px auto 64px; }
    h1 { font-size: 32px; margin: 0 0 8px; }
    a { color: #173f37; }
    nav { display: flex; gap: 16px; margin: 0 0 20px; }
    form.login, .notice { background: #fff; padding: 20px; }
    label { display: grid; gap: 6px; margin: 0 0 14px; font-size: 13px; }
    input, select { border: 1px solid #18362f33; padding: 8px; font: inherit; background: #fff; color: inherit; }
    button { background: #173f37; color: white; border: 0; min-height: 40px; padding: 0 14px; font: 600 14px system-ui, sans-serif; cursor: pointer; }
    .error { background: #f8d7d1; padding: 12px; }
    .filters { display: flex; flex-wrap: wrap; gap: 8px 16px; margin: 16px 0; }
    .filters a[aria-current="true"] { font-weight: 700; }
    table { width: 100%; border-collapse: collapse; background: #fff; }
    th, td { text-align: left; vertical-align: top; padding: 12px; border-bottom: 1px solid #18362f22; font-size: 14px; }
    th { font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; }
    td a { display: block; }
    .status { display: flex; gap: 8px; align-items: center; }
    .logout { margin-top: 20px; }
    .gift { margin-top: 8px; }
    .gift form { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .ok { background: #e7f0ea; padding: 12px; }
    @media (max-width: 800px) {
      table, thead, tbody, tr, th, td { display: block; }
      thead { display: none; }
      tr { border-bottom: 8px solid #f5eedd; }
      td { border-bottom: 0; }
    }
  </style>
</head>
<body>
<main>
  <h1>Henvendelser</h1>
  <?php if ($signedIn): ?>
    <nav>
      <a href="/redaktor/">Rediger siden</a>
      <a href="/redaktor/henvendelser.php">Henvendelser<?php if ($newCount > 0): ?> (<?= $newCount ?> nye)<?php endif; ?></a>
    </nav>
  <?php endif; ?>
  <?php
    $notices = [
        'sent' => 'Gavekortet er sendt til køberen.',
        'saved' => 'Gavekortet er oprettet, men e-mailen kunne ikke sendes. Brug Send igen.',
        'redeemed' => 'Gavekortet er markeret som brugt.',
    ];
    $notice = (string) ($_GET['notice'] ?? '');
  ?>
  <?php if (isset($notices[$notice])): ?><p class="ok"><?= htmlspecialchars($notices[$notice], ENT_QUOTES) ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p><?php endif; ?>
  <?php if ($config === null): ?>
    <p>Opret <code>~/private/cms-config.php</code> ud fra <code>cms-config.example.php</code> i projektet.</p>
  <?php elseif (!$signedIn): ?>
    <form class="login" method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
      <label>Adgangskode <input type="password" name="password" required autofocus></label>
      <button type="submit">Log ind</button>
    </form>
  <?php elseif (!$databaseConfigured): ?>
    <p class="notice">Databasen er ikke sat op endnu. Opret en MySQL-database i Simply, kopier <code>private/db-config.example.php</code> til <code>~/private/db-config.php</code>, og kør <code>private/enquiries.sql</code> i phpMyAdmin.</p>
  <?php elseif ($databaseReady): ?>
    <p>Her ligger de forespørgsler, som er sendt fra siden. Mailen til skolen sendes stadig.</p>
    <div class="filters">
      <a href="/redaktor/henvendelser.php"<?= $filter === '' ? ' aria-current="true"' : '' ?>>Alle</a>
      <?php foreach ($statuses as $value => $label): ?>
        <a href="/redaktor/henvendelser.php?status=<?= rawurlencode($value) ?>"<?= $filter === $value ? ' aria-current="true"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if ($enquiries === []): ?>
      <p class="notice">Der er ingen henvendelser her.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Tid</th>
            <th>Tilbud</th>
            <th>Navn</th>
            <th>Kontakt</th>
            <th>Detalje</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($enquiries as $enquiry): ?>
            <tr>
              <td><?= htmlspecialchars((string) $enquiry['created_at'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars((string) $enquiry['offer'], ENT_QUOTES) ?></td>
              <td>
                <?= htmlspecialchars((string) $enquiry['name'], ENT_QUOTES) ?>
                <br><?= htmlspecialchars((string) $enquiry['language'], ENT_QUOTES) ?>
              </td>
              <td>
                <a href="mailto:<?= htmlspecialchars((string) $enquiry['email'], ENT_QUOTES) ?>"><?= htmlspecialchars((string) $enquiry['email'], ENT_QUOTES) ?></a>
                <a href="tel:<?= htmlspecialchars(preg_replace('/[^\d+]/', '', (string) $enquiry['phone']) ?? '', ENT_QUOTES) ?>"><?= htmlspecialchars((string) $enquiry['phone'], ENT_QUOTES) ?></a>
              </td>
              <td>
                <?= htmlspecialchars((string) $enquiry['student'], ENT_QUOTES) ?>
                <?php if (gift_is_offer((string) $enquiry['offer'])): ?>
                  <div class="gift">
                    <div>Reference: <?= htmlspecialchars(gift_reference((int) $enquiry['id']), ENT_QUOTES) ?></div>
                    <?php if (trim((string) ($enquiry['recipient'] ?? '')) !== ''): ?>
                      <div>Til: <?= htmlspecialchars((string) $enquiry['recipient'], ENT_QUOTES) ?></div>
                    <?php endif; ?>
                    <?php if (trim((string) ($enquiry['greeting'] ?? '')) !== ''): ?>
                      <div>Hilsen: <?= nl2br(htmlspecialchars((string) $enquiry['greeting'], ENT_QUOTES)) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($enquiry['gift_code'])): ?>
                      <div>
                        <a href="/gavekort/index.php?kode=<?= rawurlencode((string) $enquiry['gift_code']) ?>"><?= htmlspecialchars((string) $enquiry['gift_code'], ENT_QUOTES) ?></a>
                        <?= ($enquiry['gift_status'] ?? '') === 'redeemed' ? 'Brugt' : 'Sendt' ?>
                      </div>
                      <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES) ?>">
                        <button type="submit" name="resend_gift" value="<?= htmlspecialchars((string) $enquiry['gift_code'], ENT_QUOTES) ?>">Send igen</button>
                        <?php if (($enquiry['gift_status'] ?? '') !== 'redeemed'): ?>
                          <button type="submit" name="redeem_gift" value="<?= htmlspecialchars((string) $enquiry['gift_code'], ENT_QUOTES) ?>">Marker som brugt</button>
                        <?php endif; ?>
                      </form>
                    <?php else: ?>
                      <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
                        <input type="hidden" name="enquiry_id" value="<?= (int) $enquiry['id'] ?>">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES) ?>">
                        <button type="submit" name="issue_gift" value="1">Opret gavekort</button>
                      </form>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <form class="status" method="post">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
                  <input type="hidden" name="enquiry_id" value="<?= (int) $enquiry['id'] ?>">
                  <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES) ?>">
                  <select name="status">
                    <?php foreach ($statuses as $value => $label): ?>
                      <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= $enquiry['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit">Gem</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <form class="logout" method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
      <button type="submit" name="logout" value="1">Log ud</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
