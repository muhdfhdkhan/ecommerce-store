<?php
// includes/helpers.php — output escaping and small shared helpers (SECURITY.md §6.2)
declare(strict_types=1);

/** HTML body & quoted attribute escaping — use for ALL dynamic output. */
function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** JavaScript data context (inside <script> as data). */
function js(mixed $v): string
{
    return json_encode(
        $v,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
    );
}

/** URL query component. */
function url_param(string $v): string
{
    return rawurlencode($v);
}

/** Validate URL scheme before rendering href/src (blocks javascript:, data:). */
function safe_url(string $url): string
{
    $p = parse_url($url);
    if ($p === false) {
        return '#';
    }
    $scheme = strtolower($p['scheme'] ?? '');
    if ($scheme !== '' && !in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
        return '#';
    }
    return $url;
}

/** Structured error log line (SECURITY.md §29 — never log secrets). */
function app_log(string $level, string $message): void
{
    $line = sprintf(
        "[%s] %s: %s\n",
        gmdate('Y-m-d\TH:i:s\Z'),
        strtoupper($level),
        $message
    );
    @file_put_contents(STORAGE_PATH . '/logs/app-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/** Fetch one config value by dotted path, e.g. config('db.name'). */
function config(string $key, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require CONFIG_PATH . '/app.php';
    }
    $node = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return $default;
        }
        $node = $node[$part];
    }
    return $node;
}
