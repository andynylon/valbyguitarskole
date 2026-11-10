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
            return $data;
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
    if (!is_file(content_config_path())) {
        return null;
    }
    $config = require content_config_path();

    return is_array($config) ? $config : null;
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

