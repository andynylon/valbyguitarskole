<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/content-lib.php';
require dirname(__DIR__) . '/api/students-lib.php';

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
$studentFollowups = $signedIn ? students_followup_count() : 0;

function editor_input_name(string $path): string
{
    return htmlspecialchars('fields[' . implode('][', explode('.', $path)) . ']', ENT_QUOTES);
}

function editor_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES);
}

function editor_label(string $key): string
{
    $labels = [
        'meta' => 'Søgetekst',
        'nav' => 'Menu',
        'hero' => 'Forside',
        'marquee' => 'Bånd',
        'audience' => 'Undervisning',
        'instruments' => 'Instrumenter',
        'pricing' => 'Priser',
        'gift' => 'Gavekort',
        'teachers' => 'Undervisere',
        'about' => 'Om skolen',
        'contact' => 'Kontakt',
        'newsletter' => 'Nyhedsbrev',
        'campaign' => 'Kampagne',
        'cookies' => 'Cookies',
        'footer' => 'Sidefod',
        'title' => 'Titel',
        'titleLine1' => 'Overskrift, første linje',
        'titleLine2' => 'Overskrift, anden linje',
        'description' => 'Beskrivelse',
        'eyebrow' => 'Lille overskrift',
        'intro' => 'Introduktion',
        'text' => 'Tekst',
        'note' => 'Note',
        'name' => 'Navn',
        'price' => 'Pris',
        'button' => 'Knap',
        'lessons' => 'Undervisning',
        'book' => 'Bookingknap',
        'prices' => 'Link til priser',
        'video' => 'Video',
        'image' => 'Billede',
        'imageAlt' => 'Billedtekst',
        'play' => 'Afspil-knap',
        'photoCredit' => 'Kreditering',
        'label' => 'Etiket',
        'items' => 'Punkter',
        'noteBefore' => 'Note før det fremhævede ord',
        'noteStrong' => 'Fremhævet ord',
        'noteAfter' => 'Note efter det fremhævede ord',
        'instrumentNote' => 'Tekst om instrumenter',
        'groups' => 'Målgrupper',
        'rental' => 'Tekst om leje',
        'options' => 'Instrumenter',
        'choose' => 'Vælg-tekst',
        'offers' => 'Forløb',
        'group' => 'Holdundervisning',
        'noteThree' => 'Note, hold på tre',
        'bookingName' => 'Navn i booking',
        'dismiss' => 'Afvis-knap',
        'close' => 'Luk-knap',
        'people' => 'Undervisere',
        'educationLabel' => 'Overskrift, uddannelse',
        'teachingLabel' => 'Overskrift, undervisning',
        'liveLabel' => 'Overskrift, på scenen',
        'instrument' => 'Instrument',
        'education' => 'Uddannelse',
        'teaching' => 'Undervisning',
        'artists' => 'Kunstnere',
        'since' => 'Siden',
        'year' => 'Årstal',
        'place' => 'Sted',
        'lead' => 'Indledning',
        'points' => 'Punkter',
        'checkbox' => 'Afkrydsning',
        'submit' => 'Send-knap',
        'successTitle' => 'Overskrift efter tilmelding',
        'successText' => 'Tekst efter tilmelding',
        'teaser' => 'Kort teaser',
        'teaserClose' => 'Luk teaser',
        'active' => 'Aktiv',
        'until' => 'Gælder til',
        'was' => 'Førpris',
        'formTitle' => 'Overskrift i formular',
        'formIntro' => 'Introduktion i formular',
        'tagline1' => 'Tagline, første linje',
        'tagline2' => 'Tagline, anden linje',
        'findUs' => 'Find os',
        'address1' => 'Adresse, linje 1',
        'address2' => 'Adresse, linje 2',
        'email' => 'E-mail',
        'phone' => 'Telefon',
        'follow' => 'Følg med',
    ];

    return $labels[$key] ?? $key;
}

function editor_is_localized(mixed $value): bool
{
    return is_array($value)
        && array_key_exists('da', $value)
        && array_key_exists('en', $value)
        && is_string($value['da'])
        && is_string($value['en'])
        && count($value) === 2;
}

function editor_is_long(string $key, string $danish, string $english): bool
{
    $long = [
        'description', 'intro', 'text', 'lead', 'note', 'instrumentNote', 'rental',
        'artists', 'education', 'teaching', 'checkbox', 'successText', 'formIntro',
        'noteBefore', 'noteAfter',
    ];

    return in_array($key, $long, true) || mb_strlen($danish) > 70 || mb_strlen($english) > 70;
}

function editor_skip(string $key): bool
{
    return in_array($key, ['updated', 'tone', 'featured'], true);
}

function editor_card_title(mixed $item, int $index, string $listKey): string
{
    if (editor_is_localized($item) && $item['da'] !== '') {
        return $item['da'];
    }
    if (is_array($item)) {
        foreach (['name', 'title', 'instrument'] as $key) {
            $value = $item[$key] ?? null;
            if (is_array($value) && is_string($value['da'] ?? null) && $value['da'] !== '') {
                return $value['da'];
            }
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
    }

    return editor_label($listKey) . ' ' . ($index + 1);
}

function editor_section_hint(array $node): string
{
    $parts = [];
    $walk = static function (mixed $value, string $key) use (&$walk, &$parts): void {
        if (!is_array($value) || editor_is_localized($value)) {
            return;
        }
        if (array_is_list($value)) {
            $parts[] = count($value) . ' ' . mb_strtolower(editor_label($key));
            return;
        }
        foreach ($value as $childKey => $child) {
            if (is_string($childKey)) {
                $walk($child, $childKey);
            }
        }
    };
    foreach ($node as $key => $value) {
        if (is_string($key)) {
            $walk($value, $key);
        }
    }

    return implode(' · ', $parts);
}

function editor_control(string $path, string $danish, string $english, bool $long): string
{
    $name = editor_input_name($path);
    if ($long) {
        return '<textarea name="' . $name . '">' . editor_escape($danish) . '</textarea>';
    }

    return '<input name="' . $name . '" value="' . editor_escape($danish) . '">';
}

function editor_localized_field(string $path, string $key, array $value, bool $showLabel): string
{
    $danish = (string) $value['da'];
    $english = (string) $value['en'];
    $long = editor_is_long($key, $danish, $english);
    $html = '<label class="field">';
    if ($showLabel) {
        $html .= '<span>' . editor_escape(editor_label($key)) . '</span>';
    }
    $html .= '<span class="pair">'
        . editor_control($path . '.da', $danish, $english, $long)
        . editor_control($path . '.en', $english, $english, $long)
        . '</span></label>';

    return $html;
}

function editor_string_field(string $path, string $key, string $value): string
{
    return '<label class="field single"><span>' . editor_escape(editor_label($key)) . '</span>'
        . '<input name="' . editor_input_name($path) . '" value="' . editor_escape($value) . '">'
        . '</label>';
}

function editor_is_repeatable(string $path): bool
{
    return $path === 'teachers.people';
}

function editor_blank_item(array $sample): array
{
    $blank = [];
    foreach ($sample as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if (is_string($value)) {
            $blank[$key] = '';
        } elseif (editor_is_localized($value)) {
            $blank[$key] = ['da' => '', 'en' => ''];
        }
    }

    return $blank;
}

function editor_repeatable_sample(array $items, string $path): ?array
{
    foreach ($items as $item) {
        if (is_array($item) && !array_is_list($item)) {
            return $item;
        }
    }

    $sample = content_repeatable_sample($path);

    return is_array($sample) ? $sample : null;
}

function editor_render_card(mixed $item, string $childPath, string $key, string $title, bool $repeatable): string
{
    $remove = $repeatable
        ? '<button type="button" class="remove-item" data-remove>Fjern</button>'
        : '';
    $html = '<li><details class="card"><summary><span>' . editor_escape($title) . '</span>'
        . $remove . '<i></i></summary><article>';
    if (editor_is_localized($item)) {
        $html .= editor_fields_block(editor_localized_field($childPath, $key, $item, false), true);
    } elseif (is_array($item)) {
        $html .= editor_render_object($item, $childPath);
    }
    $html .= '</article></details></li>';

    return $html;
}

function editor_render_list(array $items, string $path, string $key): string
{
    $repeatable = editor_is_repeatable($path);
    $html = '<section class="group"' . ($repeatable ? ' data-repeatable' : '') . '>'
        . '<h3>' . editor_escape(editor_label($key)) . '</h3>';
    if ($repeatable) {
        $html .= '<p class="hint">Tilføj og fjern undervisere her. Et kort uden navn gemmes, men vises ikke på siden.</p>'
            . '<input type="hidden" name="' . editor_input_name($path . '._present') . '" value="1">';
    }
    $html .= '<ol class="cards">';
    foreach ($items as $index => $item) {
        $title = editor_card_title($item, (int) $index, $key);
        $html .= editor_render_card($item, $path . '.' . $index, $key, $title, $repeatable);
    }
    $html .= '</ol>';
    if ($repeatable) {
        $sample = editor_repeatable_sample($items, $path);
        if ($sample !== null) {
            $html .= '<template>'
                . editor_render_card(editor_blank_item($sample), $path . '.__INDEX__', $key, 'Ny underviser', true)
                . '</template>';
            $html .= '<button type="button" class="add-item" data-add>Tilføj underviser</button>';
        }
    }
    $html .= '</section>';

    return $html;
}

function editor_render_object(array $node, string $path): string
{
    $html = '';
    $fields = '';
    $hasLocalized = false;
    foreach ($node as $key => $value) {
        if (!is_string($key) || editor_skip($key)) {
            continue;
        }
        $childPath = $path === '' ? $key : $path . '.' . $key;
        if (editor_is_localized($value) || is_string($value)) {
            if (editor_is_localized($value)) {
                $hasLocalized = true;
                $fields .= editor_localized_field($childPath, $key, $value, true);
            } else {
                $fields .= editor_string_field($childPath, $key, $value);
            }
            continue;
        }
        if ($fields !== '') {
            $html .= editor_fields_block($fields, $hasLocalized);
            $fields = '';
            $hasLocalized = false;
        }
        if (is_array($value) && array_is_list($value)) {
            $html .= editor_render_list($value, $childPath, $key);
        } elseif (is_array($value)) {
            $html .= '<section class="group"><h3>' . editor_escape(editor_label($key)) . '</h3>'
                . editor_render_object($value, $childPath) . '</section>';
        }
    }
    if ($fields !== '') {
        $html .= editor_fields_block($fields, $hasLocalized);
    }

    return $html;
}

function editor_fields_block(string $fields, bool $hasLocalized): string
{
    $columns = $hasLocalized
        ? '<div class="langs" aria-hidden="true"><span>Dansk</span><span>Engelsk</span></div>'
        : '';

    return '<div class="fields">' . $columns . $fields . '</div>';
}

function editor_fields(array $content): string
{
    $html = '';
    $index = 0;
    foreach ($content as $key => $value) {
        if (!is_string($key) || editor_skip($key) || !is_array($value)) {
            continue;
        }
        $title = editor_label($key);
        $hint = editor_section_hint($value);
        $id = 'section-' . preg_replace('/[^a-z0-9_-]/i', '', $key);
        $html .= '<details class="section" id="' . editor_escape($id) . '"' . ($index === 0 ? ' open' : '') . '>'
            . '<summary><span>' . editor_escape($title) . '</span>'
            . ($hint !== '' ? '<small>' . editor_escape($hint) . '</small>' : '')
            . '<i></i></summary><div class="panel">'
            . editor_render_object($value, $key)
            . '</div></details>';
        $index++;
    }

    return $html;
}

function editor_toc(array $content): string
{
    $html = '';
    foreach ($content as $key => $value) {
        if (!is_string($key) || editor_skip($key) || !is_array($value)) {
            continue;
        }
        $id = 'section-' . preg_replace('/[^a-z0-9_-]/i', '', $key);
        $html .= '<button type="button" data-section="' . editor_escape($id) . '">'
            . editor_escape(editor_label($key)) . '</button>';
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
    body { margin: 0; background: #f3ecdf; color: #18362f; font: 16px/1.45 system-ui, sans-serif; padding-bottom: 84px; }
    main { width: min(980px, calc(100% - 32px)); margin: 28px auto 80px; }
    h1 { font-size: 32px; line-height: 1.1; margin: 0 0 8px; }
    h3 { font-size: 13px; letter-spacing: .04em; text-transform: uppercase; margin: 0 0 10px; color: #18362fbf; }
    p.lead { margin: 0 0 16px; max-width: 62ch; }
    a { color: #173f37; }
    nav.links { display: flex; gap: 16px; margin: 0 0 18px; }
    .error, .ok, .gate { padding: 14px 16px; }
    .error { background: #f8d7d1; }
    .ok { background: #e7f0ea; }
    .gate { background: #fff; max-width: 420px; }
    .gate label { display: grid; gap: 6px; margin: 0 0 14px; }
    button, .text-button { background: #173f37; color: white; border: 0; min-height: 44px; padding: 0 16px; font: 600 14px system-ui, sans-serif; cursor: pointer; }
    .text-button { background: transparent; color: #173f37; padding: 0; min-height: 0; }
    .tools { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px; }
    .tools button, .toc button { background: transparent; color: #173f37; border: 1px solid #18362f33; min-height: 36px; }
    .toc { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 18px; }
    .section, .card { background: #fff; }
    .section { margin: 0 0 10px; }
    .section > summary, .card > summary { cursor: pointer; list-style: none; display: flex; align-items: center; gap: 12px; }
    .section > summary::-webkit-details-marker, .card > summary::-webkit-details-marker { display: none; }
    .section > summary { padding: 16px 18px; font-size: 18px; font-weight: 650; }
    .section > summary small { margin-left: auto; color: #18362fa6; font-size: 13px; font-weight: 500; }
    .section > summary i, .card > summary i { width: 8px; height: 8px; border-right: 2px solid currentColor; border-bottom: 2px solid currentColor; transform: rotate(45deg); flex: none; }
    .section[open] > summary i, .card[open] > summary i { transform: rotate(-135deg); }
    .section[open] > summary { border-bottom: 1px solid #18362f1f; }
    .panel { padding: 16px 18px 6px; }
    .fields { display: grid; gap: 12px; margin: 0 0 16px; }
    .langs, .pair { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .langs { font-size: 11px; letter-spacing: .04em; text-transform: uppercase; color: #18362fa6; }
    .field { display: grid; gap: 6px; font-size: 13px; }
    .field.single { max-width: 100%; }
    input, textarea { width: 100%; box-sizing: border-box; border: 1px solid #18362f33; background: #fff; color: inherit; padding: 10px; font: inherit; }
    textarea { min-height: 84px; resize: vertical; }
    .group { margin: 4px 0 18px; }
    .cards { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
    .card { border: 1px solid #18362f24; background: #fbf7ee; }
    .card > summary { padding: 12px 14px; font-weight: 650; }
    .card > summary span { margin-right: auto; }
    .card article { padding: 4px 14px 8px; }
    .hint { margin: -2px 0 12px; color: #18362fa6; font-size: 14px; }
    .add-item, .remove-item { background: transparent; color: #173f37; border: 1px solid #18362f33; min-height: 36px; padding: 0 12px; }
    .remove-item { min-height: 32px; padding: 0 10px; font-size: 13px; }
    .add-item { margin: 4px 0 8px; }
    .savebar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 2; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 12px 18px; background: #173f37; color: #f5eedd; }
    .savebar p { margin: 0; font-size: 14px; }
    .savebar button { background: #f3ecdf; color: #173f37; }
    .logout { margin-top: 18px; }
    @media (max-width: 700px) {
      .langs, .pair { grid-template-columns: 1fr; }
      .section > summary { align-items: flex-start; }
      .section > summary small { margin-left: 0; }
      .savebar { align-items: stretch; flex-direction: column; }
    }
  </style>
</head>
<body>
<main>
  <h1>Rediger siden</h1>
  <?php if ($signedIn): ?>
    <nav class="links">
      <a href="/redaktor/">Rediger siden</a>
      <a href="/redaktor/henvendelser.php">Henvendelser</a>
      <a href="/redaktor/elever.php">Elever<?php if ($studentFollowups > 0): ?> (<?= $studentFollowups ?> opfølgning)<?php endif; ?></a>
    </nav>
  <?php endif; ?>
  <?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p><?php endif; ?>
  <?php if ($message !== ''): ?><p class="ok"><?= htmlspecialchars($message, ENT_QUOTES) ?></p><?php endif; ?>
  <?php if ($config === null): ?>
    <p>Opret <code>~/private/cms-config.php</code> ud fra <code>cms-config.example.php</code> i projektet.</p>
  <?php elseif (!$signedIn): ?>
    <form class="gate" method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
      <label><span>Adgangskode</span><input type="password" name="password" required autofocus></label>
      <button type="submit">Log ind</button>
    </form>
  <?php elseif ($content === null): ?>
    <p>Der er endnu intet indhold at redigere.</p>
  <?php else: ?>
    <p class="lead">Åbn en del af siden, og derefter det kort du vil rette. Dansk står til venstre og engelsk til højre. Når du gemmer, opdateres siden med det samme, og GitHub bygger HTML-filen igen.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
      <div class="toc"><?= editor_toc($content) ?></div>
      <div class="tools">
        <button type="button" id="open-sections">Åbn alle dele</button>
        <button type="button" id="close-sections">Luk alle dele</button>
      </div>
      <?= editor_fields($content) ?>
      <div class="savebar">
        <p>Dansk til venstre, engelsk til højre.</p>
        <button type="submit">Gem og byg siden</button>
      </div>
    </form>
    <form class="logout" method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
      <button class="text-button" type="submit" name="logout" value="1">Log ud</button>
    </form>
    <script>
      const sections = () => Array.from(document.querySelectorAll("details.section"))
      document.getElementById("open-sections")?.addEventListener("click", () => {
        sections().forEach((section) => { section.open = true })
      })
      document.getElementById("close-sections")?.addEventListener("click", () => {
        sections().forEach((section) => { section.open = false })
      })
      document.querySelectorAll("[data-section]").forEach((button) => {
        button.addEventListener("click", () => {
          const section = document.getElementById(button.getAttribute("data-section") || "")
          if (!section) return
          section.open = true
          section.scrollIntoView({ behavior: "smooth", block: "start" })
        })
      })
      document.querySelectorAll("[data-repeatable]").forEach((group) => {
        const list = group.querySelector("ol.cards")
        const template = group.querySelector("template")
        const nextIndex = () => {
          let max = -1
          list?.querySelectorAll("[name]").forEach((field) => {
            const match = field.name.match(/\[people\]\[(\d+)\]/)
            if (match) max = Math.max(max, Number(match[1]))
          })
          return max + 1
        }
        group.querySelector("[data-add]")?.addEventListener("click", () => {
          if (!list || !template || list.querySelectorAll(":scope > li").length >= 24) return
          list.insertAdjacentHTML("beforeend", template.innerHTML.replaceAll("__INDEX__", String(nextIndex())))
          const card = list.lastElementChild?.querySelector("details")
          const name = list.lastElementChild?.querySelector('input[name$="[name]"]')
          if (card) card.open = true
          window.setTimeout(() => name?.focus(), 0)
        })
        list?.addEventListener("click", (event) => {
          const button = event.target.closest("[data-remove]")
          if (!button || !list.contains(button)) return
          event.preventDefault()
          event.stopPropagation()
          if (!window.confirm("Fjern denne underviser fra listen? Husk at gemme bagefter.")) return
          button.closest("li")?.remove()
        })
        list?.addEventListener("input", (event) => {
          const field = event.target
          if (!(field instanceof HTMLInputElement) || !field.name.endsWith("[name]")) return
          const title = field.closest("details")?.querySelector("summary span")
          const name = field.value.trim()
          if (title && name !== "") title.textContent = name
        })
      })
    </script>
  <?php endif; ?>
</main>
</body>
</html>
