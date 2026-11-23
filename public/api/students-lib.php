<?php
declare(strict_types=1);

require_once __DIR__ . '/enquiries-lib.php';

function students_plans(): array
{
    return [
        'none' => 'Intet forløb',
        'trial' => 'Prøvetime',
        'card5' => '5 lektioner',
        'card10' => '10 lektioner',
        'halfyear' => 'Halvt år',
    ];
}

function students_class_sizes(): array
{
    return [
        1 => 'Enkeltundervisning',
        2 => 'Hold på to · 45 min.',
        3 => 'Hold på tre · 60 min.',
    ];
}

function students_class_size(string $value): int
{
    $size = (int) $value;

    return $size === 2 || $size === 3 ? $size : 1;
}

function students_offer_class_size(string $offer): int
{
    $name = strtolower($offer);
    if (str_contains($name, 'hold på tre') || str_contains($name, 'group of three')) {
        return 3;
    }
    if (str_contains($name, 'hold på to') || str_contains($name, 'group of two')) {
        return 2;
    }

    return 1;
}

function students_statuses(): array
{
    return [
        'trial' => 'Prøve',
        'active' => 'Aktiv',
        'pause' => 'Pause',
        'finished' => 'Afsluttet',
    ];
}

function students_weekdays(): array
{
    return [
        1 => 'Mandag',
        2 => 'Tirsdag',
        3 => 'Onsdag',
        4 => 'Torsdag',
        5 => 'Fredag',
        6 => 'Lørdag',
        7 => 'Søndag',
    ];
}

function students_today(): DateTimeImmutable
{
    return new DateTimeImmutable('today', new DateTimeZone('Europe/Copenhagen'));
}

function students_install(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS students (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            enquiry_id INT UNSIGNED NULL,
            name VARCHAR(200) NOT NULL,
            email VARCHAR(200) NOT NULL,
            phone VARCHAR(50) NOT NULL DEFAULT '',
            instrument VARCHAR(80) NOT NULL DEFAULT '',
            status ENUM('trial', 'active', 'pause', 'finished') NOT NULL DEFAULT 'active',
            plan ENUM('none', 'trial', 'card5', 'card10', 'halfyear') NOT NULL DEFAULT 'none',
            class_size TINYINT UNSIGNED NOT NULL DEFAULT 1,
            lessons_total INT UNSIGNED NOT NULL DEFAULT 0,
            lessons_used INT UNSIGNED NOT NULL DEFAULT 0,
            weekday TINYINT UNSIGNED NULL,
            lesson_time TIME NULL,
            period_start DATE NULL,
            period_end DATE NULL,
            note VARCHAR(2000) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY students_enquiry (enquiry_id),
            KEY students_status (status),
            KEY students_period_end (period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_events (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            kind ENUM('signup', 'lesson', 'purchase', 'status', 'note', 'change') NOT NULL,
            title VARCHAR(200) NOT NULL,
            detail VARCHAR(2000) NOT NULL DEFAULT '',
            happened_on DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY student_events_student (student_id, happened_on)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    students_class_size_ready($pdo);
    $done = true;
}

function students_class_size_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $found = $pdo->query("SHOW COLUMNS FROM students LIKE 'class_size'")->fetch();
    if ($found) {
        $ready = true;

        return true;
    }

    try {
        $pdo->exec('ALTER TABLE students ADD COLUMN class_size TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER plan');
        $ready = true;

        return true;
    } catch (Throwable $error) {
        error_log('Student class size column missing: ' . $error->getMessage());
        $ready = false;

        return false;
    }
}

function students_pdo(): PDO
{
    $pdo = enquiries_pdo();
    students_install($pdo);

    return $pdo;
}

function students_text(string $value, int $max): string
{
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }

    return substr($value, 0, $max);
}

function students_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Copenhagen'));
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Unknown date');
    }

    return $value;
}

function students_time(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
        throw new InvalidArgumentException('Unknown time');
    }

    return $value . ':00';
}

function students_weekday(string $value): ?int
{
    if ($value === '') {
        return null;
    }
    $day = (int) $value;
    if ($day < 1 || $day > 7) {
        throw new InvalidArgumentException('Unknown weekday');
    }

    return $day;
}

function students_count(string $value): int
{
    $count = (int) $value;
    if ($count < 0) {
        return 0;
    }
    if ($count > 500) {
        return 500;
    }

    return $count;
}

function students_remaining(array $student): int
{
    return (int) $student['lessons_total'] - (int) $student['lessons_used'];
}

function students_attention(array $student): array
{
    $none = ['kind' => 'none', 'label' => 'I gang', 'detail' => ''];
    $status = (string) ($student['status'] ?? '');
    if ($status === 'pause' || $status === 'finished') {
        return ['kind' => 'none', 'label' => '', 'detail' => ''];
    }

    $plan = (string) ($student['plan'] ?? 'none');
    $remaining = students_remaining($student);
    $daysLeft = null;
    $end = trim((string) ($student['period_end'] ?? ''));
    if ($end !== '' && $end !== '0000-00-00') {
        $periodEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $end, new DateTimeZone('Europe/Copenhagen'));
        if ($periodEnd instanceof DateTimeImmutable) {
            $daysLeft = (int) students_today()->diff($periodEnd)->format('%r%a');
        }
    }

    if ($plan === 'halfyear' && $daysLeft !== null && $daysLeft <= 28) {
        return [
            'kind' => 'renew',
            'label' => 'Forny',
            'detail' => $daysLeft < 0
                ? 'Halvårsaftalen er udløbet.'
                : 'Halvårsaftalen udløber om ' . $daysLeft . ' dage.',
        ];
    }

    if ($plan === 'halfyear' && $remaining <= 2) {
        return [
            'kind' => 'buy',
            'label' => 'Køb flere lektioner',
            'detail' => $remaining <= 0 ? 'Alle lektioner er brugt.' : $remaining . ' lektioner tilbage.',
        ];
    }

    if ($plan === 'trial' && $remaining <= 0) {
        return [
            'kind' => 'upgrade',
            'label' => 'Køb et forløb',
            'detail' => 'Prøvetimen er brugt.',
        ];
    }

    if ($plan === 'card5' && $remaining <= 1) {
        return [
            'kind' => 'upgrade',
            'label' => 'Opgrader forløbet',
            'detail' => $remaining <= 0
                ? 'Klippekortet er brugt. 10 lektioner eller et halvt år passer bedre nu.'
                : '1 lektion tilbage. Tid til 10 lektioner eller et halvt år.',
        ];
    }

    if ($plan === 'card10' && $remaining <= 2) {
        return [
            'kind' => 'buy',
            'label' => 'Køb flere lektioner',
            'detail' => $remaining <= 0
                ? 'Klippekortet er brugt. Et halvt år kan være næste skridt.'
                : $remaining . ' lektioner tilbage. Et halvt år kan være næste skridt.',
        ];
    }

    if ($plan === 'none') {
        return [
            'kind' => 'buy',
            'label' => 'Køb et forløb',
            'detail' => 'Eleven har ikke et forløb endnu.',
        ];
    }

    return $none;
}

function students_plan_from_offer(string $offer): array
{
    $name = strtolower($offer);
    if (str_contains($name, 'black friday') || str_contains($name, 'prøve') || str_contains($name, 'trial')) {
        return ['plan' => 'trial', 'lessons' => 1, 'status' => 'trial', 'months' => 0];
    }
    if (str_contains($name, 'halvt') || str_contains($name, 'six month')) {
        return ['plan' => 'halfyear', 'lessons' => 20, 'status' => 'active', 'months' => 6];
    }
    if (str_contains($name, '10')) {
        return ['plan' => 'card10', 'lessons' => 10, 'status' => 'active', 'months' => 0];
    }
    if (str_contains($name, '5')) {
        return ['plan' => 'card5', 'lessons' => 5, 'status' => 'active', 'months' => 0];
    }

    return ['plan' => 'none', 'lessons' => 0, 'status' => 'active', 'months' => 0];
}

function students_next_lesson(array $student): ?DateTimeImmutable
{
    $day = (int) ($student['weekday'] ?? 0);
    if ($day < 1 || $day > 7) {
        return null;
    }
    $today = students_today();
    $add = ($day - (int) $today->format('N') + 7) % 7;

    return $today->modify('+' . $add . ' days');
}

function students_date_label(DateTimeImmutable $date): string
{
    $months = [1 => 'jan.', 2 => 'feb.', 3 => 'mar.', 4 => 'apr.', 5 => 'maj', 6 => 'jun.', 7 => 'jul.', 8 => 'aug.', 9 => 'sep.', 10 => 'okt.', 11 => 'nov.', 12 => 'dec.'];

    return (int) $date->format('j') . '. ' . $months[(int) $date->format('n')];
}

function students_slot(array $student): string
{
    $day = (int) ($student['weekday'] ?? 0);
    $days = students_weekdays();
    $time = substr((string) ($student['lesson_time'] ?? ''), 0, 5);
    $label = isset($days[$day]) ? trim($days[$day] . ' ' . $time) : $time;
    if ($label === '') {
        return 'Ingen fast tid';
    }
    $next = students_next_lesson($student);
    if ($next === null) {
        return $label;
    }
    $when = $next->format('Y-m-d') === students_today()->format('Y-m-d') ? 'i dag' : students_date_label($next);

    return $label . ' · ' . $when;
}

function students_event(PDO $pdo, int $studentId, string $kind, string $title, string $detail, string $date): void
{
    $statement = $pdo->prepare(
        'INSERT INTO student_events (student_id, kind, title, detail, happened_on)
         VALUES (:student_id, :kind, :title, :detail, :happened_on)'
    );
    $statement->execute([
        'student_id' => $studentId,
        'kind' => $kind,
        'title' => students_text($title, 200),
        'detail' => students_text($detail, 2000),
        'happened_on' => $date,
    ]);
}

function students_list(): array
{
    $rows = students_pdo()->query('SELECT * FROM students ORDER BY name, id')->fetchAll();
    $rank = ['renew' => 0, 'upgrade' => 1, 'buy' => 2, 'none' => 3];
    foreach ($rows as &$row) {
        $row['attention'] = students_attention($row);
    }
    unset($row);
    usort($rows, static function (array $a, array $b) use ($rank): int {
        $left = $rank[$a['attention']['kind']] ?? 9;
        $right = $rank[$b['attention']['kind']] ?? 9;
        if ($left !== $right) {
            return $left <=> $right;
        }

        return strcasecmp((string) $a['name'], (string) $b['name']);
    });

    return $rows;
}

function students_followup_count(): int
{
    if (enquiries_config() === null) {
        return 0;
    }
    try {
        $count = 0;
        foreach (students_list() as $student) {
            if ($student['attention']['kind'] !== 'none') {
                $count++;
            }
        }

        return $count;
    } catch (Throwable $error) {
        error_log('Student follow-up count failed: ' . $error->getMessage());

        return 0;
    }
}

function students_get(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $statement = students_pdo()->prepare('SELECT * FROM students WHERE id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }
    $row['attention'] = students_attention($row);

    return $row;
}

function students_events(int $id): array
{
    $statement = students_pdo()->prepare(
        'SELECT * FROM student_events WHERE student_id = :id ORDER BY happened_on DESC, id DESC'
    );
    $statement->execute(['id' => $id]);

    return $statement->fetchAll();
}

function students_enquiry_links(): array
{
    if (enquiries_config() === null) {
        return [];
    }
    try {
        $rows = students_pdo()->query('SELECT id, enquiry_id FROM students WHERE enquiry_id IS NOT NULL')->fetchAll();
    } catch (Throwable $error) {
        error_log('Student links failed: ' . $error->getMessage());

        return [];
    }
    $links = [];
    foreach ($rows as $row) {
        $links[(int) $row['enquiry_id']] = (int) $row['id'];
    }

    return $links;
}

function students_id_for_enquiry(PDO $pdo, int $enquiryId): ?int
{
    $statement = $pdo->prepare('SELECT id FROM students WHERE enquiry_id = :enquiry_id');
    $statement->execute(['enquiry_id' => $enquiryId]);
    $id = $statement->fetchColumn();

    return $id === false ? null : (int) $id;
}

function students_create(array $fields): int
{
    $pdo = students_pdo();
    $plan = (string) ($fields['plan'] ?? 'none');
    $status = (string) ($fields['status'] ?? 'active');
    if (!isset(students_plans()[$plan]) || !isset(students_statuses()[$status])) {
        throw new InvalidArgumentException('Unknown student plan');
    }
    $name = students_text((string) ($fields['name'] ?? ''), 200);
    $email = students_text((string) ($fields['email'] ?? ''), 200);
    if ($name === '' || $email === '') {
        throw new InvalidArgumentException('Student name and email are required');
    }
    $enquiryId = (int) ($fields['enquiry_id'] ?? 0);
    $today = students_today();
    if ($plan === 'halfyear' && ($fields['period_start'] ?? null) === null && ($fields['period_end'] ?? null) === null) {
        $fields['period_start'] = $today->format('Y-m-d');
        $fields['period_end'] = $today->modify('+6 months')->format('Y-m-d');
    }
    $today = $today->format('Y-m-d');
    $classSize = students_class_size((string) ($fields['class_size'] ?? '1'));
    $columns = 'enquiry_id, name, email, phone, instrument, status, plan, lessons_total, lessons_used, weekday, lesson_time, period_start, period_end, note';
    $placeholders = ':enquiry_id, :name, :email, :phone, :instrument, :status, :plan, :lessons_total, 0, :weekday, :lesson_time, :period_start, :period_end, :note';
    $values = [
        'enquiry_id' => $enquiryId > 0 ? $enquiryId : null,
        'name' => $name,
        'email' => $email,
        'phone' => students_text((string) ($fields['phone'] ?? ''), 50),
        'instrument' => students_text((string) ($fields['instrument'] ?? ''), 80),
        'status' => $status,
        'plan' => $plan,
        'lessons_total' => students_count((string) ($fields['lessons_total'] ?? '0')),
        'weekday' => $fields['weekday'] ?? null,
        'lesson_time' => $fields['lesson_time'] ?? null,
        'period_start' => $fields['period_start'] ?? null,
        'period_end' => $fields['period_end'] ?? null,
        'note' => students_text((string) ($fields['note'] ?? ''), 2000),
    ];
    if (students_class_size_ready($pdo)) {
        $columns .= ', class_size';
        $placeholders .= ', :class_size';
        $values['class_size'] = $classSize;
    }
    $statement = $pdo->prepare(
        'INSERT INTO students (' . $columns . ') VALUES (' . $placeholders . ')'
    );
    $statement->execute($values);
    $id = (int) $pdo->lastInsertId();
    $signup = students_plans()[$plan];
    if ($classSize > 1) {
        $signup .= ' · ' . students_class_sizes()[$classSize];
    }
    if ((string) ($fields['history'] ?? '') !== '') {
        $signup .= ' · ' . (string) $fields['history'];
    }
    students_event($pdo, $id, 'signup', 'Elev oprettet', $signup, $today);

    return $id;
}

function students_create_from_enquiry(array $enquiry): int
{
    $pdo = students_pdo();
    $enquiryId = (int) $enquiry['id'];
    $existing = students_id_for_enquiry($pdo, $enquiryId);
    if ($existing !== null) {
        return $existing;
    }
    $offer = students_plan_from_offer((string) $enquiry['offer']);
    $start = null;
    $end = null;
    if ($offer['months'] > 0) {
        $today = students_today();
        $start = $today->format('Y-m-d');
        $end = $today->modify('+' . $offer['months'] . ' months')->format('Y-m-d');
    }
    $history = trim((string) $enquiry['offer'] . (trim((string) $enquiry['student']) !== '' ? ' · ' . $enquiry['student'] : ''));

    try {
        return students_create([
            'enquiry_id' => $enquiryId,
            'name' => (string) $enquiry['name'],
            'email' => (string) $enquiry['email'],
            'phone' => (string) $enquiry['phone'],
            'instrument' => (string) ($enquiry['instrument'] ?? ''),
            'status' => $offer['status'],
            'plan' => $offer['plan'],
            'class_size' => students_offer_class_size((string) $enquiry['offer']),
            'lessons_total' => $offer['lessons'],
            'period_start' => $start,
            'period_end' => $end,
            'note' => (string) ($enquiry['message'] ?? ''),
            'history' => $history,
        ]);
    } catch (PDOException $error) {
        $existing = students_id_for_enquiry($pdo, $enquiryId);
        if ($existing !== null) {
            return $existing;
        }
        throw $error;
    }
}

function students_input(array $input): array
{
    $plan = (string) ($input['plan'] ?? '');
    $status = (string) ($input['status'] ?? '');
    if (!isset(students_plans()[$plan]) || !isset(students_statuses()[$status])) {
        throw new InvalidArgumentException('Unknown student value');
    }

    return [
        'name' => students_text((string) ($input['name'] ?? ''), 200),
        'email' => students_text((string) ($input['email'] ?? ''), 200),
        'phone' => students_text((string) ($input['phone'] ?? ''), 50),
        'instrument' => students_text((string) ($input['instrument'] ?? ''), 80),
        'status' => $status,
        'plan' => $plan,
        'class_size' => students_class_size((string) ($input['class_size'] ?? '1')),
        'lessons_total' => students_count((string) ($input['lessons_total'] ?? '0')),
        'lessons_used' => students_count((string) ($input['lessons_used'] ?? '0')),
        'weekday' => students_weekday((string) ($input['weekday'] ?? '')),
        'lesson_time' => students_time((string) ($input['lesson_time'] ?? '')),
        'period_start' => students_date((string) ($input['period_start'] ?? '')),
        'period_end' => students_date((string) ($input['period_end'] ?? '')),
        'note' => students_text((string) ($input['note'] ?? ''), 2000),
    ];
}

function students_save(int $id, array $input): void
{
    $current = students_get($id);
    if ($current === null) {
        throw new InvalidArgumentException('Unknown student');
    }
    $next = students_input($input);
    if ($next['name'] === '' || $next['email'] === '') {
        throw new InvalidArgumentException('Student name and email are required');
    }
    $pdo = students_pdo();
    $classSizeReady = students_class_size_ready($pdo);
    $sql = 'UPDATE students SET
            name = :name, email = :email, phone = :phone, instrument = :instrument,
            status = :status, plan = :plan, lessons_total = :lessons_total, lessons_used = :lessons_used,
            weekday = :weekday, lesson_time = :lesson_time, period_start = :period_start,
            period_end = :period_end, note = :note';
    if ($classSizeReady) {
        $sql .= ', class_size = :class_size';
    }
    $sql .= ' WHERE id = :id';
    $values = $next + ['id' => $id];
    if (!$classSizeReady) {
        unset($values['class_size']);
    }
    $statement = $pdo->prepare($sql);
    $statement->execute($values);

    $changes = [];
    if ((string) $current['status'] !== $next['status']) {
        students_event($pdo, $id, 'status', 'Status: ' . students_statuses()[$next['status']], '', students_today()->format('Y-m-d'));
    }
    if ((string) $current['plan'] !== $next['plan']) {
        $changes[] = 'Forløb: ' . students_plans()[$next['plan']];
    }
    if ($classSizeReady && (int) ($current['class_size'] ?? 1) !== $next['class_size']) {
        $changes[] = 'Undervisning: ' . students_class_sizes()[$next['class_size']];
    }
    if ((int) $current['lessons_total'] !== $next['lessons_total'] || (int) $current['lessons_used'] !== $next['lessons_used']) {
        $changes[] = 'Lektioner: ' . $next['lessons_used'] . ' brugt af ' . $next['lessons_total'];
    }
    if ((string) ($current['period_start'] ?? '') !== (string) ($next['period_start'] ?? '')
        || (string) ($current['period_end'] ?? '') !== (string) ($next['period_end'] ?? '')) {
        $changes[] = 'Periode: ' . ($next['period_start'] ?? '—') . ' – ' . ($next['period_end'] ?? '—');
    }
    if ($changes !== []) {
        students_event($pdo, $id, 'change', 'Oplysninger rettet', implode('. ', $changes), students_today()->format('Y-m-d'));
    }
}

function students_add_lesson(int $id, string $date, string $detail): void
{
    $student = students_get($id);
    if ($student === null) {
        throw new InvalidArgumentException('Unknown student');
    }
    $happened = students_date($date) ?? students_today()->format('Y-m-d');
    $pdo = students_pdo();
    $statement = $pdo->prepare('UPDATE students SET lessons_used = lessons_used + 1 WHERE id = :id');
    $statement->execute(['id' => $id]);
    students_event($pdo, $id, 'lesson', 'Lektion', students_text($detail, 2000), $happened);
}

function students_add_purchase(int $id, array $input): void
{
    $student = students_get($id);
    if ($student === null) {
        throw new InvalidArgumentException('Unknown student');
    }
    $plan = (string) ($input['plan'] ?? '');
    if (!isset(students_plans()[$plan]) || $plan === 'none') {
        throw new InvalidArgumentException('Unknown purchase');
    }
    $added = students_count((string) ($input['lessons'] ?? '0'));
    if ($added < 1) {
        throw new InvalidArgumentException('A purchase needs at least one lesson');
    }
    $start = students_date((string) ($input['period_start'] ?? ''));
    $end = students_date((string) ($input['period_end'] ?? ''));
    if ($plan === 'halfyear' && $start === null && $end === null) {
        $today = students_today();
        $start = $today->format('Y-m-d');
        $end = $today->modify('+6 months')->format('Y-m-d');
    }
    $status = $plan === 'trial' ? 'trial' : 'active';
    $pdo = students_pdo();
    $statement = $pdo->prepare(
        'UPDATE students SET
            plan = :plan,
            status = :status,
            lessons_total = lessons_total + :added,
            period_start = COALESCE(:period_start, period_start),
            period_end = COALESCE(:period_end, period_end)
         WHERE id = :id'
    );
    $statement->execute([
        'plan' => $plan,
        'status' => $status,
        'added' => $added,
        'period_start' => $start,
        'period_end' => $end,
        'id' => $id,
    ]);
    $detail = students_plans()[$plan] . ' · ' . $added . ' lektioner';
    if ($end !== null) {
        $detail .= ' · til ' . $end;
    }
    $note = students_text((string) ($input['detail'] ?? ''), 2000);
    if ($note !== '') {
        $detail .= ' · ' . $note;
    }
    students_event($pdo, $id, 'purchase', 'Køb', $detail, $start ?? students_today()->format('Y-m-d'));
}

function students_add_note(int $id, string $date, string $detail): void
{
    if (students_get($id) === null) {
        throw new InvalidArgumentException('Unknown student');
    }
    $note = students_text($detail, 2000);
    if ($note === '') {
        throw new InvalidArgumentException('Empty note');
    }
    students_event(
        students_pdo(),
        $id,
        'note',
        'Note',
        $note,
        students_date($date) ?? students_today()->format('Y-m-d')
    );
}
