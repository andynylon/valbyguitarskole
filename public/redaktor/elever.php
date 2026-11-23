<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/content-lib.php';
require dirname(__DIR__) . '/api/students-lib.php';

session_start();
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

$config = content_config();
$error = '';
$students = [];
$student = null;
$events = [];
$databaseConfigured = enquiries_config() !== null;
$databaseReady = $databaseConfigured;
$filters = [
    '' => 'Alle',
    'followup' => 'Skal følges op',
    'trial' => 'Prøve',
    'active' => 'Aktive',
    'pause' => 'Pause',
    'finished' => 'Afsluttet',
];
$filter = (string) ($_GET['filter'] ?? '');
if (!isset($filters[$filter])) {
    $filter = '';
}
$studentId = (int) ($_GET['id'] ?? 0);
$followups = 0;

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function students_redirect(int $id, string $filter, string $notice): void
{
    $query = [];
    if ($id > 0) {
        $query['id'] = (string) $id;
    }
    if ($filter !== '') {
        $query['filter'] = $filter;
    }
    if ($notice !== '') {
        $query['notice'] = $notice;
    }
    $location = '/redaktor/elever.php';
    if ($query !== []) {
        $location .= '?' . http_build_query($query);
    }
    header('Location: ' . $location);
    exit;
}

function students_filter(array $filters): string
{
    $filter = (string) ($_POST['filter'] ?? '');

    return isset($filters[$filter]) ? $filter : '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    $nextFilter = students_filter($filters);
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        $error = 'Prøv igen.';
    } elseif ($config === null) {
        $error = 'Opret ~/private/cms-config.php før du kan logge ind.';
    } elseif (isset($_POST['logout'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: /redaktor/elever.php');
        exit;
    } elseif (!($_SESSION['editor'] ?? false) && isset($_POST['password'])) {
        $password = (string) ($config['password'] ?? '');
        if ($password !== '' && hash_equals($password, (string) $_POST['password'])) {
            session_regenerate_id(true);
            $_SESSION['editor'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: /redaktor/elever.php');
            exit;
        }
        $error = 'Forkert adgangskode.';
    } elseif (($_SESSION['editor'] ?? false) && isset($_POST['create_student'])) {
        try {
            $fields = students_input($_POST);
            $id = students_create($fields);
            students_redirect($id, $nextFilter, 'created');
        } catch (Throwable $failure) {
            $error = 'Eleven kunne ikke oprettes. Navn og e-mail skal udfyldes. Kør private/students.sql i phpMyAdmin, hvis tabellerne mangler.';
            error_log('Student create failed: ' . $failure->getMessage());
        }
    } elseif (($_SESSION['editor'] ?? false) && isset($_POST['save_student'])) {
        $id = (int) ($_POST['student_id'] ?? 0);
        try {
            students_save($id, $_POST);
            students_redirect($id, $nextFilter, 'saved');
        } catch (Throwable $failure) {
            $error = 'Eleven kunne ikke gemmes.';
            error_log('Student save failed: ' . $failure->getMessage());
            $studentId = $id;
        }
    } elseif (($_SESSION['editor'] ?? false) && isset($_POST['add_lesson'])) {
        $id = (int) ($_POST['student_id'] ?? 0);
        try {
            students_add_lesson($id, (string) ($_POST['happened_on'] ?? ''), (string) ($_POST['detail'] ?? ''));
            students_redirect($id, $nextFilter, 'lesson');
        } catch (Throwable $failure) {
            $error = 'Lektionen kunne ikke registreres.';
            error_log('Student lesson failed: ' . $failure->getMessage());
            $studentId = $id;
        }
    } elseif (($_SESSION['editor'] ?? false) && isset($_POST['add_purchase'])) {
        $id = (int) ($_POST['student_id'] ?? 0);
        try {
            students_add_purchase($id, $_POST);
            students_redirect($id, $nextFilter, 'purchase');
        } catch (Throwable $failure) {
            $error = 'Købet kunne ikke registreres. Vælg et forløb og mindst én lektion.';
            error_log('Student purchase failed: ' . $failure->getMessage());
            $studentId = $id;
        }
    } elseif (($_SESSION['editor'] ?? false) && isset($_POST['add_note'])) {
        $id = (int) ($_POST['student_id'] ?? 0);
        try {
            students_add_note($id, (string) ($_POST['happened_on'] ?? ''), (string) ($_POST['detail'] ?? ''));
            students_redirect($id, $nextFilter, 'note');
        } catch (Throwable $failure) {
            $error = 'Noten kunne ikke gemmes.';
            error_log('Student note failed: ' . $failure->getMessage());
            $studentId = $id;
        }
    }
}

$signedIn = (bool) ($_SESSION['editor'] ?? false);
if ($signedIn && $databaseConfigured) {
    try {
        $students = students_list();
        foreach ($students as $row) {
            if ($row['attention']['kind'] !== 'none') {
                $followups++;
            }
        }
        if ($studentId > 0) {
            $student = students_get($studentId);
            $events = $student === null ? [] : students_events($studentId);
        }
    } catch (Throwable $failure) {
        $databaseReady = false;
        if ($error === '') {
            $error = 'Eleverne kunne ikke hentes. Tjek ~/private/db-config.php, og kør private/students.sql i phpMyAdmin hvis tabellerne mangler.';
        }
        error_log('Student list failed: ' . $failure->getMessage());
    }
}

$visible = array_values(array_filter($students, static function (array $row) use ($filter): bool {
    if ($filter === '') {
        return true;
    }
    if ($filter === 'followup') {
        return $row['attention']['kind'] !== 'none';
    }

    return (string) $row['status'] === $filter;
}));
$today = students_today()->format('Y-m-d');
$eventKinds = [
    'signup' => 'Start',
    'lesson' => 'Lektion',
    'purchase' => 'Køb',
    'status' => 'Status',
    'note' => 'Note',
    'change' => 'Rettelse',
];
?>
<!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Elever — Valby Guitarskole</title>
  <style>
    body { margin: 0; background: #f5eedd; color: #18362f; font: 16px/1.4 system-ui, sans-serif; }
    main { width: min(1100px, calc(100% - 32px)); margin: 32px auto 64px; }
    h1 { font-size: 32px; margin: 0 0 8px; }
    h2 { font-size: 20px; margin: 0 0 12px; }
    a { color: #173f37; }
    nav, .filters { display: flex; flex-wrap: wrap; gap: 8px 16px; margin: 0 0 20px; }
    .filters a[aria-current="true"] { font-weight: 700; }
    form.login, .notice, .panel { background: #fff; padding: 20px; }
    .panel { margin: 0 0 16px; }
    label { display: grid; gap: 6px; margin: 0 0 14px; font-size: 13px; }
    input, select, textarea { border: 1px solid #18362f33; padding: 8px; font: inherit; background: #fff; color: inherit; width: 100%; box-sizing: border-box; }
    textarea { min-height: 72px; }
    button { background: #173f37; color: white; border: 0; min-height: 40px; padding: 0 14px; font: 600 14px system-ui, sans-serif; cursor: pointer; }
    .error { background: #f8d7d1; padding: 12px; }
    .ok { background: #e7f0ea; padding: 12px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
    .split { display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 16px; align-items: start; }
    table { width: 100%; border-collapse: collapse; background: #fff; }
    th, td { text-align: left; vertical-align: top; padding: 12px; border-bottom: 1px solid #18362f22; font-size: 14px; }
    th { font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; }
    .badge { display: inline-block; padding: 2px 8px; font-size: 12px; font-weight: 700; }
    .renew { background: #f4c7a1; }
    .upgrade { background: #f3d36b; }
    .buy { background: #f8d7d1; }
    .none { background: #e7f0ea; }
    .muted { color: #18362fbb; font-size: 14px; }
    .logout { margin-top: 20px; }
    @media (max-width: 800px) {
      .grid, .split { grid-template-columns: 1fr; }
      table, thead, tbody, tr, th, td { display: block; }
      thead { display: none; }
      tr { border-bottom: 8px solid #f5eedd; }
      td { border-bottom: 0; }
    }
  </style>
</head>
<body>
<main>
  <h1><?= $student ? htmlspecialchars((string) $student['name'], ENT_QUOTES) : 'Elever' ?></h1>
  <?php if ($signedIn): ?>
    <nav>
      <a href="/redaktor/">Rediger siden</a>
      <a href="/redaktor/henvendelser.php">Henvendelser</a>
      <a href="/redaktor/elever.php">Elever<?php if ($followups > 0): ?> (<?= $followups ?> opfølgning)<?php endif; ?></a>
    </nav>
  <?php endif; ?>
  <?php
    $notices = [
        'created' => 'Eleven er oprettet.',
        'saved' => 'Eleven er gemt.',
        'lesson' => 'Lektionen er registreret.',
        'purchase' => 'Købet er registreret.',
        'note' => 'Noten er gemt.',
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
    <p class="notice">Databasen er ikke sat op endnu. Opret en MySQL-database i Simply, og kopier <code>private/db-config.example.php</code> til <code>~/private/db-config.php</code>.</p>
  <?php elseif (!$databaseReady): ?>
  <?php elseif ($student): ?>
    <p><a href="/redaktor/elever.php<?= $filter !== '' ? '?filter=' . rawurlencode($filter) : '' ?>">Alle elever</a></p>
    <?php if ($student['attention']['kind'] !== 'none' && $student['attention']['label'] !== ''): ?>
      <p class="panel">
        <span class="badge <?= htmlspecialchars((string) $student['attention']['kind'], ENT_QUOTES) ?>"><?= htmlspecialchars((string) $student['attention']['label'], ENT_QUOTES) ?></span>
        <?= htmlspecialchars((string) $student['attention']['detail'], ENT_QUOTES) ?>
      </p>
    <?php endif; ?>
    <p class="muted"><?= htmlspecialchars(students_slot($student), ENT_QUOTES) ?> · <?= max(0, students_remaining($student)) ?> lektioner tilbage · <?= htmlspecialchars(students_class_sizes()[students_class_size((string) ($student['class_size'] ?? 1))], ENT_QUOTES) ?></p>
    <div class="split">
      <form class="panel" method="post">
        <h2>Elev</h2>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
        <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES) ?>">
        <div class="grid">
          <label>Navn <input name="name" value="<?= htmlspecialchars((string) $student['name'], ENT_QUOTES) ?>" required></label>
          <label>E-mail <input type="email" name="email" value="<?= htmlspecialchars((string) $student['email'], ENT_QUOTES) ?>" required></label>
          <label>Telefon <input name="phone" value="<?= htmlspecialchars((string) $student['phone'], ENT_QUOTES) ?>"></label>
          <label>Instrument <input name="instrument" value="<?= htmlspecialchars((string) $student['instrument'], ENT_QUOTES) ?>"></label>
          <label>Status
            <select name="status">
              <?php foreach (students_statuses() as $value => $label): ?>
                <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= $student['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Forløb
            <select name="plan">
              <?php foreach (students_plans() as $value => $label): ?>
                <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= $student['plan'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Undervisning
            <select name="class_size">
              <?php foreach (students_class_sizes() as $value => $label): ?>
                <option value="<?= $value ?>"<?= students_class_size((string) ($student['class_size'] ?? 1)) === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Lektioner i alt <input type="number" min="0" max="500" name="lessons_total" value="<?= (int) $student['lessons_total'] ?>"></label>
          <label>Lektioner brugt <input type="number" min="0" max="500" name="lessons_used" value="<?= (int) $student['lessons_used'] ?>"></label>
          <label>Ugedag
            <select name="weekday">
              <option value="">Ingen fast dag</option>
              <?php foreach (students_weekdays() as $value => $label): ?>
                <option value="<?= $value ?>"<?= (int) $student['weekday'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Tidspunkt <input type="time" name="lesson_time" value="<?= htmlspecialchars(substr((string) $student['lesson_time'], 0, 5), ENT_QUOTES) ?>"></label>
          <label>Periode fra <input type="date" name="period_start" value="<?= htmlspecialchars((string) $student['period_start'], ENT_QUOTES) ?>"></label>
          <label>Periode til <input type="date" name="period_end" value="<?= htmlspecialchars((string) $student['period_end'], ENT_QUOTES) ?>"></label>
        </div>
        <label>Note <textarea name="note"><?= htmlspecialchars((string) $student['note'], ENT_QUOTES) ?></textarea></label>
        <button type="submit" name="save_student" value="1">Gem elev</button>
      </form>
      <div>
        <form class="panel" method="post">
          <h2>Lektion</h2>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
          <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
          <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES) ?>">
          <label>Dato <input type="date" name="happened_on" value="<?= htmlspecialchars($today, ENT_QUOTES) ?>" required></label>
          <label>Note <input name="detail" placeholder="Hvad I arbejdede med"></label>
          <button type="submit" name="add_lesson" value="1">Registrer lektion</button>
        </form>
        <form class="panel" method="post">
          <h2>Køb</h2>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
          <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
          <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES) ?>">
          <label>Forløb
            <select name="plan">
              <?php foreach (students_plans() as $value => $label): ?>
                <?php if ($value === 'none') continue; ?>
                <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= ($student['plan'] !== 'none' ? $student['plan'] : 'card5') === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Lektioner der lægges til <input type="number" min="1" max="500" name="lessons" value="5" required></label>
          <label>Periode fra <input type="date" name="period_start"></label>
          <label>Periode til <input type="date" name="period_end"></label>
          <p class="muted">Et halvt år uden datoer sættes til seks måneder fra i dag. Ubrugte lektioner bliver stående.</p>
          <button type="submit" name="add_purchase" value="1">Registrer køb</button>
        </form>
        <form class="panel" method="post">
          <h2>Note</h2>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
          <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
          <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES) ?>">
          <label>Dato <input type="date" name="happened_on" value="<?= htmlspecialchars($today, ENT_QUOTES) ?>" required></label>
          <label>Note <textarea name="detail" required></textarea></label>
          <button type="submit" name="add_note" value="1">Gem note</button>
        </form>
      </div>
    </div>
    <h2>Historik</h2>
    <?php if ($events === []): ?>
      <p class="notice">Der er ingen historik endnu.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr><th>Dato</th><th>Type</th><th>Hvad</th></tr>
        </thead>
        <tbody>
          <?php foreach ($events as $event): ?>
            <tr>
              <td><?= htmlspecialchars((string) $event['happened_on'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars($eventKinds[(string) $event['kind']] ?? (string) $event['kind'], ENT_QUOTES) ?></td>
              <td>
                <?= htmlspecialchars((string) $event['title'], ENT_QUOTES) ?>
                <?php if (trim((string) $event['detail']) !== ''): ?>
                  <div class="muted"><?= nl2br(htmlspecialchars((string) $event['detail'], ENT_QUOTES)) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php else: ?>
    <p>Her ligger eleverne, der er i gang. Et halvt år skal fornyes den sidste måned. Fem lektioner skal opgraderes, når der er én tilbage. Ti lektioner skal fyldes op, når der er to tilbage. En brugt prøvetime skal blive til et forløb.</p>
    <div class="filters">
      <?php foreach ($filters as $value => $label): ?>
        <a href="/redaktor/elever.php<?= $value === '' ? '' : '?filter=' . rawurlencode($value) ?>"<?= $filter === $value ? ' aria-current="true"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if ($visible === []): ?>
      <p class="notice">Der er ingen elever her.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Navn</th>
            <th>Forløb</th>
            <th>Lektion</th>
            <th>Tilbage</th>
            <th>Status</th>
            <th>Opfølgning</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($visible as $row): ?>
            <?php $remaining = students_remaining($row); ?>
            <tr>
              <td><a href="/redaktor/elever.php?id=<?= (int) $row['id'] ?><?= $filter !== '' ? '&filter=' . rawurlencode($filter) : '' ?>"><?= htmlspecialchars((string) $row['name'], ENT_QUOTES) ?></a></td>
              <td>
                <?= htmlspecialchars(students_plans()[(string) $row['plan']] ?? '', ENT_QUOTES) ?>
                <?php $classSize = students_class_size((string) ($row['class_size'] ?? 1)); ?>
                <?php if ($classSize > 1): ?>
                  <div class="muted"><?= htmlspecialchars(students_class_sizes()[$classSize], ENT_QUOTES) ?></div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars(students_slot($row), ENT_QUOTES) ?></td>
              <td><?= max(0, $remaining) ?><?php if ($row['period_end']): ?><div class="muted">til <?= htmlspecialchars((string) $row['period_end'], ENT_QUOTES) ?></div><?php endif; ?></td>
              <td><?= htmlspecialchars(students_statuses()[(string) $row['status']] ?? '', ENT_QUOTES) ?></td>
              <td>
                <?php if ($row['attention']['label'] !== ''): ?>
                  <span class="badge <?= htmlspecialchars((string) $row['attention']['kind'], ENT_QUOTES) ?>"><?= htmlspecialchars((string) $row['attention']['label'], ENT_QUOTES) ?></span>
                  <?php if ($row['attention']['detail'] !== ''): ?><div class="muted"><?= htmlspecialchars((string) $row['attention']['detail'], ENT_QUOTES) ?></div><?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <form class="panel" method="post" style="margin-top: 20px;">
      <h2>Ny elev</h2>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES) ?>">
      <div class="grid">
        <label>Navn <input name="name" required></label>
        <label>E-mail <input type="email" name="email" required></label>
        <label>Telefon <input name="phone"></label>
        <label>Instrument <input name="instrument"></label>
        <label>Status
          <select name="status">
            <?php foreach (students_statuses() as $value => $label): ?>
              <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= $value === 'active' ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Forløb
          <select name="plan">
            <?php foreach (students_plans() as $value => $label): ?>
              <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Undervisning
          <select name="class_size">
            <?php foreach (students_class_sizes() as $value => $label): ?>
              <option value="<?= $value ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Lektioner <input type="number" min="0" max="500" name="lessons_total" value="0"></label>
        <label>Ugedag
          <select name="weekday">
            <option value="">Ingen fast dag</option>
            <?php foreach (students_weekdays() as $value => $label): ?>
              <option value="<?= $value ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Tidspunkt <input type="time" name="lesson_time"></label>
      </div>
      <button type="submit" name="create_student" value="1">Opret elev</button>
    </form>
  <?php endif; ?>
  <?php if ($signedIn): ?>
    <form class="logout" method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
      <button type="submit" name="logout" value="1">Log ud</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
