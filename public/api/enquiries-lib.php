<?php
declare(strict_types=1);

function enquiries_config_path(): string
{
    return dirname(__DIR__, 2) . '/private/db-config.php';
}

function enquiries_config(): ?array
{
    static $loaded = false;
    static $config = null;
    if ($loaded) {
        return $config;
    }
    $loaded = true;

    if (!is_file(enquiries_config_path())) {
        return null;
    }
    $file = require enquiries_config_path();
    if (!is_array($file) || !isset($file['host'], $file['database'], $file['username'], $file['password'])) {
        return null;
    }
    foreach (['host', 'database', 'username', 'password'] as $key) {
        if (trim((string) $file[$key]) === '') {
            return null;
        }
    }

    $config = $file;

    return $config;
}

function enquiries_pdo(): PDO
{
    $config = enquiries_config();
    if ($config === null) {
        throw new RuntimeException('Enquiry database is not configured');
    }

    $port = (int) ($config['port'] ?? 3306);
    $dsn = 'mysql:host=' . $config['host']
        . ';port=' . $port
        . ';dbname=' . $config['database']
        . ';charset=utf8mb4';

    return new PDO($dsn, (string) $config['username'], (string) $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function enquiries_ensure_column(PDO $pdo, string $column, string $definition): bool
{
    static $cache = [];
    if (($cache[$column] ?? false) === true) {
        return true;
    }

    $found = $pdo->query('SHOW COLUMNS FROM enquiries LIKE ' . $pdo->quote($column))->fetch();
    if ($found) {
        $cache[$column] = true;

        return true;
    }

    try {
        $pdo->exec('ALTER TABLE enquiries ADD COLUMN ' . $column . ' ' . $definition);
        $cache[$column] = true;

        return true;
    } catch (Throwable $error) {
        error_log('Enquiry column ' . $column . ' missing: ' . $error->getMessage());
        $cache[$column] = false;

        return false;
    }
}

function enquiries_newsletter_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === true) {
        return true;
    }

    $found = $pdo->query("SHOW COLUMNS FROM enquiries LIKE 'newsletter'")->fetch();
    if ($found) {
        $ready = true;

        return true;
    }

    try {
        $pdo->exec('ALTER TABLE enquiries ADD COLUMN newsletter TINYINT(1) NOT NULL DEFAULT 0 AFTER language');
        $ready = true;

        return true;
    } catch (Throwable $error) {
        error_log('Newsletter column missing: ' . $error->getMessage());
        $ready = false;

        return false;
    }
}

function enquiries_save(array $enquiry): int
{
    $pdo = enquiries_pdo();
    $values = [
        'offer' => $enquiry['offer'],
        'name' => $enquiry['name'],
        'email' => $enquiry['email'],
        'phone' => $enquiry['phone'],
        'student' => $enquiry['student'],
        'language' => $enquiry['language'] === 'en' ? 'en' : 'da',
        'recipient' => $enquiry['recipient'] ?? '',
        'greeting' => $enquiry['greeting'] ?? '',
    ];
    $columns = ['offer', 'name', 'email', 'phone', 'student', 'language', 'recipient', 'greeting'];
    if (enquiries_newsletter_ready($pdo)) {
        $columns[] = 'newsletter';
        $values['newsletter'] = !empty($enquiry['newsletter']) ? 1 : 0;
    }
    if (enquiries_ensure_column($pdo, 'instrument', "VARCHAR(80) NOT NULL DEFAULT ''")) {
        $columns[] = 'instrument';
        $values['instrument'] = (string) ($enquiry['instrument'] ?? '');
    }
    if (enquiries_ensure_column($pdo, 'rent_instrument', 'TINYINT(1) NOT NULL DEFAULT 0')) {
        $columns[] = 'rent_instrument';
        $values['rent_instrument'] = !empty($enquiry['rent_instrument']) ? 1 : 0;
    }
    if (enquiries_ensure_column($pdo, 'message', "VARCHAR(1000) NOT NULL DEFAULT ''")) {
        $columns[] = 'message';
        $values['message'] = (string) ($enquiry['message'] ?? '');
    }
    if (enquiries_ensure_column($pdo, 'moms', 'TINYINT(1) NOT NULL DEFAULT 0')) {
        $columns[] = 'moms';
        $values['moms'] = !empty($enquiry['moms']) ? 1 : 0;
    }
    $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
    $statement = $pdo->prepare(
        'INSERT INTO enquiries (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
    );
    $statement->execute($values);

    return (int) $pdo->lastInsertId();
}

function enquiries_statuses(): array
{
    return ['new', 'contacted', 'booked', 'closed'];
}

function enquiries_list(?string $status, bool $newsletterOnly = false): array
{
    $pdo = enquiries_pdo();
    $newsletter = enquiries_newsletter_ready($pdo) ? 'e.newsletter' : '0 AS newsletter';
    $instrument = enquiries_ensure_column($pdo, 'instrument', "VARCHAR(80) NOT NULL DEFAULT ''")
        ? 'e.instrument'
        : "'' AS instrument";
    $rental = enquiries_ensure_column($pdo, 'rent_instrument', 'TINYINT(1) NOT NULL DEFAULT 0')
        ? 'e.rent_instrument'
        : '0 AS rent_instrument';
    $message = enquiries_ensure_column($pdo, 'message', "VARCHAR(1000) NOT NULL DEFAULT ''")
        ? 'e.message'
        : "'' AS message";
    $moms = enquiries_ensure_column($pdo, 'moms', 'TINYINT(1) NOT NULL DEFAULT 0')
        ? 'e.moms'
        : '0 AS moms';
    $where = [];
    $params = [];
    if ($status !== null && in_array($status, enquiries_statuses(), true)) {
        $where[] = 'e.status = :status';
        $params['status'] = $status;
    }
    if ($newsletterOnly && $newsletter !== '0 AS newsletter') {
        $where[] = 'e.newsletter = 1';
    }

    $sql = 'SELECT e.id, e.created_at, e.offer, e.name, e.email, e.phone, e.student, e.language, '
        . $newsletter . ', ' . $instrument . ', ' . $rental . ', ' . $message . ', ' . $moms . ', e.recipient, e.greeting, e.status, g.code AS gift_code, g.status AS gift_status
         FROM enquiries e
         LEFT JOIN gift_cards g ON g.enquiry_id = e.id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY e.created_at DESC, e.id DESC LIMIT 500';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function enquiries_get(int $id): ?array
{
    if ($id < 1) {
        return null;
    }

    $pdo = enquiries_pdo();
    $instrument = enquiries_ensure_column($pdo, 'instrument', "VARCHAR(80) NOT NULL DEFAULT ''")
        ? 'instrument'
        : "'' AS instrument";
    $message = enquiries_ensure_column($pdo, 'message', "VARCHAR(1000) NOT NULL DEFAULT ''")
        ? 'message'
        : "'' AS message";
    $statement = $pdo->prepare(
        'SELECT id, offer, name, email, phone, student, ' . $instrument . ', ' . $message . ' FROM enquiries WHERE id = :id'
    );
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();

    return $row ?: null;
}

function enquiries_count_new(): int
{
    return (int) enquiries_pdo()->query("SELECT COUNT(*) FROM enquiries WHERE status = 'new'")->fetchColumn();
}

function enquiries_set_status(int $id, string $status): void
{
    if ($id < 1 || !in_array($status, enquiries_statuses(), true)) {
        throw new InvalidArgumentException('Unknown enquiry status');
    }

    $statement = enquiries_pdo()->prepare('UPDATE enquiries SET status = :status WHERE id = :id');
    $statement->execute(['status' => $status, 'id' => $id]);
}
