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

function enquiries_save(array $enquiry): void
{
    $statement = enquiries_pdo()->prepare(
        'INSERT INTO enquiries (offer, name, email, phone, student, language)
         VALUES (:offer, :name, :email, :phone, :student, :language)'
    );
    $statement->execute([
        'offer' => $enquiry['offer'],
        'name' => $enquiry['name'],
        'email' => $enquiry['email'],
        'phone' => $enquiry['phone'],
        'student' => $enquiry['student'],
        'language' => $enquiry['language'] === 'en' ? 'en' : 'da',
    ]);
}

function enquiries_statuses(): array
{
    return ['new', 'contacted', 'booked', 'closed'];
}

function enquiries_list(?string $status): array
{
    $pdo = enquiries_pdo();
    if ($status !== null && in_array($status, enquiries_statuses(), true)) {
        $statement = $pdo->prepare(
            'SELECT id, created_at, offer, name, email, phone, student, language, status
             FROM enquiries
             WHERE status = :status
             ORDER BY created_at DESC, id DESC
             LIMIT 500'
        );
        $statement->execute(['status' => $status]);

        return $statement->fetchAll();
    }

    return $pdo->query(
        'SELECT id, created_at, offer, name, email, phone, student, language, status
         FROM enquiries
         ORDER BY created_at DESC, id DESC
         LIMIT 500'
    )->fetchAll();
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
