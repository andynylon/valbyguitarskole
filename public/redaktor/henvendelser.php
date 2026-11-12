<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/content-lib.php';
require dirname(__DIR__) . '/api/enquiries-lib.php';

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
            $error = 'Henvendelserne kunne ikke hentes. Tjek databaseoplysningerne i ~/private/db-config.php.';
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
              <td><?= htmlspecialchars((string) $enquiry['student'], ENT_QUOTES) ?></td>
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
