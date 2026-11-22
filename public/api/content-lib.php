<?php
declare(strict_types=1);

function content_private_path(): string
{
    return dirname(__DIR__, 2) . '/private/content.json';
}

function content_default_path(): string
{
    return __DIR__ . '/default-content.json';
}

function content_config_path(): string
{
    return dirname(__DIR__, 2) . '/private/cms-config.php';
}

function content_read(): ?array
{
    foreach ([content_private_path(), content_default_path()] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (is_array($data) && isset($data['updated'], $data['hero']['titleLine1']['da'], $data['pricing']['offers'])) {
            return content_fill_missing($data);
        }
    }

    return null;
}

function content_write(array $data): void
{
    $directory = dirname(content_private_path());
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the content directory');
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents(content_private_path(), $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not save content');
    }
}

function content_config(): ?array
{
    static $loaded = false;
    static $config = null;
    if ($loaded) {
        return $config;
    }
    $loaded = true;

    if (!is_file(content_config_path())) {
        return null;
    }
    $file = require content_config_path();
    $config = is_array($file) ? $file : null;

    return $config;
}

function content_trigger_rebuild(array $config): ?string
{
    $token = (string) ($config['github_token'] ?? '');
    $repository = (string) ($config['github_repository'] ?? '');
    if ($token === '' || !preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository)) {
        return 'Indholdet er gemt. GitHub er ikke sat op, så der blev ikke startet et nyt build.';
    }

    $curl = curl_init('https://api.github.com/repos/' . $repository . '/dispatches');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['event_type' => 'content_updated']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'User-Agent: valbyguitarskole-cms',
            'X-GitHub-Api-Version: 2022-11-28',
        ],
    ]);
    curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($status === 204) {
        return null;
    }

    return 'Indholdet er gemt, men GitHub startede ikke et nyt build (HTTP ' . $status . ').';
}

function content_fill_missing(array $content): array
{
    if (!is_file(content_default_path())) {
        return $content;
    }
    $defaults = json_decode((string) file_get_contents(content_default_path()), true);
    if (!is_array($defaults)) {
        return $content;
    }

    return content_fill_missing_keys($content, $defaults);
}

function content_offer_tone(mixed $item): ?string
{
    if (!is_array($item) || array_is_list($item)) {
        return null;
    }
    $tone = $item['tone'] ?? null;

    return is_string($tone) && $tone !== '' ? $tone : null;
}

function content_is_offer_list(array $current, array $defaults): bool
{
    if ($defaults === [] || !array_is_list($current) || !array_is_list($defaults)) {
        return false;
    }
    foreach ($defaults as $item) {
        if (content_offer_tone($item) === null) {
            return false;
        }
    }
    foreach ($current as $item) {
        if (content_offer_tone($item) === null) {
            return false;
        }
    }

    return true;
}

function content_fill_offer_list(array $current, array $defaults): array
{
    $byTone = [];
    foreach ($current as $item) {
        $tone = content_offer_tone($item);
        if ($tone !== null) {
            $byTone[$tone] = $item;
        }
    }

    $merged = [];
    $used = [];
    foreach ($defaults as $fallbackItem) {
        $tone = content_offer_tone($fallbackItem);
        if ($tone === null || !is_array($fallbackItem)) {
            continue;
        }
        $used[$tone] = true;
        $existing = $byTone[$tone] ?? null;
        $merged[] = is_array($existing)
            ? content_fill_missing_keys($existing, $fallbackItem)
            : $fallbackItem;
    }
    foreach ($current as $item) {
        $tone = content_offer_tone($item);
        if ($tone !== null && !isset($used[$tone])) {
            $merged[] = $item;
        }
    }

    return $merged;
}

function content_fill_missing_keys(array $content, array $defaults): array
{
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $content)) {
            $content[$key] = $value;
            continue;
        }
        if (is_array($value) && is_array($content[$key]) && content_is_offer_list($content[$key], $value)) {
            $content[$key] = content_fill_offer_list($content[$key], $value);
            continue;
        }
        if (is_array($value) && is_array($content[$key]) && array_is_list($value) && array_is_list($content[$key])) {
            foreach ($content[$key] as $index => $item) {
                $fallbackItem = $value[$index] ?? null;
                if (
                    is_array($item)
                    && is_array($fallbackItem)
                    && !array_is_list($item)
                    && !array_is_list($fallbackItem)
                ) {
                    $content[$key][$index] = content_fill_missing_keys($item, $fallbackItem);
                }
            }
            continue;
        }
        if (
            is_array($value)
            && is_array($content[$key])
            && !array_is_list($value)
            && !array_is_list($content[$key])
        ) {
            $content[$key] = content_fill_missing_keys($content[$key], $value);
        }
    }

    return $content;
}

function content_apply_fields(array $content, array $fields): array
{
    $content = content_merge_strings($content, $fields);
    $content['updated'] = gmdate('c');

    return $content;
}

function content_merge_strings(array $base, array $incoming): array
{
    foreach ($base as $key => $value) {
        if (in_array($key, ['updated', 'tone', 'featured'], true) || !array_key_exists($key, $incoming)) {
            continue;
        }
        if (is_string($value) && is_string($incoming[$key])) {
            $clean = trim(str_replace(["\r", "\n"], ' ', $incoming[$key]));
            if (mb_strlen($clean) > 2000) {
                $clean = mb_substr($clean, 0, 2000);
            }
            $base[$key] = $clean;
        } elseif (is_array($value) && is_array($incoming[$key])) {
            $base[$key] = content_merge_strings($value, $incoming[$key]);
        }
    }

    return $base;
}

function content_collect_strings(array $data, string $prefix, array &$allowed): void
{
    foreach ($data as $key => $value) {
        if ($key === 'updated' || $key === 'tone' || $key === 'featured') {
            continue;
        }
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_string($value)) {
            $allowed[$path] = true;
        } elseif (is_array($value)) {
            content_collect_strings($value, $path, $allowed);
        }
    }
}

