<?php
declare(strict_types=1);

const STAGING_HOST = 'staging.valbyguitarskole.dk';

function staging_host(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? '';

    return $host;
}

function staging_https(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    return ($https !== '' && $https !== 'off') || $forwarded === 'https' || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function staging_config_path(): string
{
    return dirname(__DIR__) . '/private/staging-config.php';
}

function staging_config(): ?array
{
    $path = staging_config_path();
    if (!is_file($path)) {
        return null;
    }
    $file = require $path;
    if (!is_array($file)) {
        return null;
    }
    $username = trim((string) ($file['username'] ?? ''));
    $hash = trim((string) ($file['password_hash'] ?? ''));
    if ($username === '' || $hash === '') {
        return null;
    }

    return ['username' => $username, 'password_hash' => $hash];
}

function staging_request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }
    $path = rawurldecode($path);
    if (str_contains($path, "\0") || preg_match('/[\r\n]/', $path) === 1 || !str_starts_with($path, '/') || str_starts_with($path, '//') || $path === '/staging-gate.php') {
        return '/';
    }

    return $path;
}

function staging_resolve(string $root, string $path): ?string
{
    $root = realpath($root);
    if ($root === false) {
        return null;
    }
    $relative = str_replace('\\', '/', $path);
    $relative = ltrim($relative, '/');
    if ($relative === '' || str_ends_with($relative, '/')) {
        $relative .= 'index.html';
    }
    $candidate = $root . '/' . $relative;
    if (is_dir($candidate)) {
        $candidate = rtrim($candidate, '/') . '/index.html';
    }
    $file = realpath($candidate);
    if ($file === false || !is_file($file)) {
        return null;
    }
    if ($file !== $root && !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $file;
}

function staging_headers(): void
{
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('Cache-Control: private, no-store');
}

function staging_session(): void
{
    session_name('vg_staging');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => staging_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
}

function staging_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: public, max-age=3600');
    echo "User-agent: *\nDisallow: /\n";
}

function staging_login(string $error = ''): void
{
    $csrf = htmlspecialchars((string) $_SESSION['csrf'], ENT_QUOTES);
    $message = $error === '' ? '' : '<p class="error">' . htmlspecialchars($error, ENT_QUOTES) . '</p>';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="da"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow, noarchive">'
        . '<title>Staging - Valby Guitarskole</title>'
        . '<style>'
        . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5eedd;color:#18362f;font:16px/1.5 system-ui,sans-serif}'
        . 'main{width:min(420px,calc(100% - 32px));background:#fff;padding:28px}'
        . 'h1{margin:0 0 8px;font-size:28px}p{margin:0 0 16px}label{display:grid;gap:6px;margin:0 0 14px;font-size:13px}'
        . 'input{border:1px solid #18362f33;padding:10px;font:inherit}'
        . 'button{background:#173f37;color:#fff;border:0;min-height:44px;padding:0 16px;font:600 14px system-ui,sans-serif}'
        . '.error{background:#f8d7d1;padding:12px}'
        . '</style></head><body><main>'
        . '<h1>Staging</h1><p>Denne prøveversion er lukket for søgemaskiner og kræver login.</p>'
        . $message
        . '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '">'
        . '<label>Navn <input name="username" autocomplete="username" required autofocus></label>'
        . '<label>Adgangskode <input type="password" name="password" autocomplete="current-password" required></label>'
        . '<button type="submit" name="login" value="1">Log ind</button></form>'
        . '</main></body></html>';
}

function staging_attempt(array $config): bool
{
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($username) > 80 || strlen($password) > 200) {
        return false;
    }
    $dummy = '$2y$10$usesomesillystringfore7hnbRJKhXVLeakoG8K30oukPsA.ztMG';
    $userOk = hash_equals($config['username'], $username);
    $passwordOk = password_verify($password, $userOk ? (string) $config['password_hash'] : $dummy);

    return $userOk && $passwordOk;
}

function staging_content_type(string $file): string
{
    $types = [
        'css' => 'text/css; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'ico' => 'image/x-icon',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'text/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'mp4' => 'video/mp4',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'txt' => 'text/plain; charset=utf-8',
        'webmanifest' => 'application/manifest+json',
        'webp' => 'image/webp',
        'woff2' => 'font/woff2',
        'xml' => 'application/xml',
    ];
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    return $types[$extension] ?? 'application/octet-stream';
}

function staging_serve_file(string $file, string $csrf): void
{
    $type = staging_content_type($file);
    header('Content-Type: ' . $type);
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
    if (str_starts_with($type, 'text/html')) {
        header('Cache-Control: private, no-store');
        $html = (string) file_get_contents($file);
        $meta = '<meta name="robots" content="noindex, nofollow, noarchive">';
        if (!str_contains($html, 'name="robots"')) {
            $html = preg_replace('/<head\b[^>]*>/i', '$0' . $meta, $html, 1) ?? $html;
        }
        $logout = '<form method="post" action="/" style="position:fixed;right:12px;bottom:12px;z-index:80">'
            . '<input type="hidden" name="csrf" value="' . htmlspecialchars($csrf, ENT_QUOTES) . '">'
            . '<button type="submit" name="logout" value="1" style="background:#173f37;color:#fff;border:0;min-height:36px;padding:0 12px;font:600 13px system-ui,sans-serif">Log ud</button>'
            . '</form>';
        $html = preg_replace('/<\/body>/i', $logout . '</body>', $html, 1) ?? ($html . $logout);
        echo $html;

        return;
    }
    header('Cache-Control: private, max-age=3600');
    header('Content-Length: ' . (string) filesize($file));
    readfile($file);
}

function staging_serve(string $root, string $path, string $csrf): void
{
    if (preg_match('#^/gavekort/([A-Za-z0-9-]+)/?$#', $path, $match) === 1) {
        $_GET['kode'] = $match[1];
        require $root . '/gavekort/index.php';

        return;
    }
    $file = staging_resolve($root, $path);
    if ($file === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Ikke fundet';

        return;
    }
    if (str_ends_with($file, '.php')) {
        require $file;

        return;
    }
    staging_serve_file($file, $csrf);
}

function staging_main(): void
{
    if (staging_host() !== STAGING_HOST) {
        http_response_code(404);
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        exit;
    }
    $path = staging_request_path();
    if ($path === '/robots.txt') {
        staging_robots();
        exit;
    }

    staging_headers();
    $config = staging_config();
    staging_session();

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['logout']) && hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $_SESSION = [];
        session_destroy();
        header('Location: /', true, 303);
        exit;
    }

    if ($config === null) {
        http_response_code(503);
        staging_login('Staging-login er ikke sat op endnu.');
        exit;
    }

    $signedIn = isset($_SESSION['staging_user']) && hash_equals($config['username'], (string) $_SESSION['staging_user']);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['login'])) {
        $failures = (int) ($_SESSION['failures'] ?? 0);
        if ($failures > 0) {
            usleep(min($failures, 5) * 200000);
        }
        $csrfOk = hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''));
        if ($csrfOk && staging_attempt($config)) {
            session_regenerate_id(true);
            $_SESSION['staging_user'] = $config['username'];
            $_SESSION['failures'] = 0;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: ' . $path, true, 303);
            exit;
        }
        $_SESSION['failures'] = $failures + 1;
        staging_login('Forkert navn eller adgangskode.');
        exit;
    }

    if (!$signedIn) {
        staging_login();
        exit;
    }

    $csrf = (string) $_SESSION['csrf'];
    session_write_close();
    staging_serve(__DIR__, $path, $csrf);
}

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    staging_main();
}
