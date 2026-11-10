<?php
declare(strict_types=1);

require __DIR__ . '/content-lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$content = content_read();
if ($content === null) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
