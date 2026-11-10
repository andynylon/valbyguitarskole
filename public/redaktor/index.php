<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/content-lib.php';

session_start();
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

$config = content_config();
$content = content_read();
$message = '';
$error = '';

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
        header('Location: /redaktor/');
        exit;
    } elseif (!($_SESSION['editor'] ?? false) && isset($_POST['password'])) {
        $password = (string) ($config['password'] ?? '');
        if ($password !== '' && hash_equals($password, (string) $_POST['password'])) {
            session_regenerate_id(true);
            $_SESSION['editor'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        } else {
            $error = 'Forkert adgangskode.';
        }
    } elseif (($_SESSION['editor'] ?? false) && isset($_POST['fields']) && is_array($_POST['fields']) && $content !== null) {
        try {
            $content = content_apply_fields($content, $_POST['fields']);
            content_write($content);
            $rebuild = content_trigger_rebuild($config);
            $message = $rebuild ?? 'Gemt. Et nyt build er startet, og siden opdaterer sig med det samme for besøgende.';
        } catch (Throwable $failure) {
            $error = 'Indholdet kunne ikke gemmes.';
            error_log('CMS save failed: ' . $failure->getMessage());
        }
    }
}

$signedIn = (bool) ($_SESSION['editor'] ?? false);

function editor_input_name(string $path): string
{
    return htmlspecialchars('fields[' . implode('][', explode('.', $path)) . ']', ENT_QUOTES);
}

function editor_value(array $content, string $path): string
{
    $cursor = $content;
    foreach (explode('.', $path) as $part) {
        if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
            return '';
        }
        $cursor = $cursor[$part];
    }

    return is_string($cursor) ? $cursor : '';
}

function editor_fields(array $content): string
{
    $allowed = [];
    content_collect_strings($content, '', $allowed);
    $paths = array_keys($allowed);
    $seen = [];
    $html = '';
    $current = '';

    foreach ($paths as $path) {
        if (isset($seen[$path]) || str_ends_with($path, '.en')) {
            continue;
        }
        $section = explode('.', $path)[0];
        if ($section !== $current) {
            if ($current !== '') {
                $html .= '</section>';
            }
            $current = $section;
            $title = [
                'meta' => 'Søgetekst',
                'nav' => 'Menu',
                'hero' => 'Forside',
                'marquee' => 'Bånd',
                'audience' => 'Undervisning',
                'pricing' => 'Priser',
                'gift' => 'Gavekort',
                'about' => 'Om skolen',
                'contact' => 'Kontakt',
                'footer' => 'Sidefod',
            ][$section] ?? $section;
            $html .= '<section><h2>' . htmlspecialchars($title, ENT_QUOTES) . '</h2>';
        }

        $english = str_ends_with($path, '.da') ? substr($path, 0, -3) . '.en' : null;
        $label = htmlspecialchars(preg_replace('/\.(da|en)$/', '', $path) ?? $path, ENT_QUOTES);
        $html .= '<label><span>' . $label . '</span>';
        if ($english !== null && isset($allowed[$english])) {
            $seen[$english] = true;
            $html .= '<span class="pair"><textarea name="' . editor_input_name($path) . '">'
                . htmlspecialchars(editor_value($content, $path), ENT_QUOTES)
                . '</textarea><textarea name="' . editor_input_name($english) . '">'
                . htmlspecialchars(editor_value($content, $english), ENT_QUOTES)
                . '</textarea></span>';
        } else {
            $html .= '<input name="' . editor_input_name($path) . '" value="'
                . htmlspecialchars(editor_value($content, $path), ENT_QUOTES) . '">';
        }
        $html .= '</label>';
    }
    if ($current !== '') {
        $html .= '</section>';
    }

    return $html;
}
?>
<!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Rediger Valby Guitarskole</title>
  <style>
    body { margin: 0; background: #f5eedd; color: #18362f; font: 16px/1.4 system-ui, sans-serif; }
    main { width: min(920px, calc(100% - 32px)); margin: 32px auto 64px; }
    h1 { font-size: 32px; margin: 0 0 8px; }
    h2 { font-size: 20px; margin: 28px 0 12px; }
    form, .notice { background: #fff; padding: 20px; }
    label { display: grid; gap: 6px; margin: 0 0 14px; font-size: 13px; }
    input, textarea { width: 100%; box-sizing: border-box; border: 1px solid #18362f33; padding: 10px; font: inherit; }
    textarea { min-height: 72px; }
    .pair { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    button { background: #173f37; color: white; border: 0; min-height: 44px; padding: 0 16px; font: 600 14px system-ui, sans-serif; }
    .row { display: flex; gap: 8px; margin-top: 16px; }
    .error { background: #f8d7d1; padding: 12px; }
    .ok { background: #e7f0ea; padding: 12px; }
    @media (max-width: 700px) { .pair { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<main>
  <h1>Rediger siden</h1>
  <?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p><?php endif; ?>
  <?php if ($message !== ''): ?><p class="ok"><?= htmlspecialchars($message, ENT_QUOTES) ?></p><?php endif; ?>
  <?php if ($config === null): ?>
    <p>Opret <code>~/private/cms-config.php</code> ud fra <code>cms-config.example.php</code> i projektet.</p>
  <?php elseif (!$signedIn): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
      <label>Adgangskode <input type="password" name="password" required autofocus></label>
      <button type="submit">Log ind</button>
    </form>
  <?php elseif ($content === null): ?>
    <p>Der er endnu intet indhold at redigere.</p>
  <?php else: ?>
    <p>Dansk står til venstre og engelsk til højre. Når du gemmer, opdateres siden med det samme, og GitHub bygger HTML-filen igen.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
      <?= editor_fields($content) ?>
      <div class="row">
        <button type="submit">Gem og byg siden</button>
      </div>
    </form>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
      <button type="submit" name="logout" value="1">Log ud</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
