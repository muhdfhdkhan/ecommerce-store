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

/* ---- Money math: integer paisa/cents, never floats (SECURITY.md §20.1, §38.2) ---- */

function money_to_cents(string $decimal): int
{
    return (int)round((float)$decimal * 100);
}

function money_from_cents(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

function money_fmt(int $cents): string
{
    return 'Rs ' . number_format($cents / 100, 0);
}

/** Server-side URL slug generator. */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

/** Read a validated positive int from request input. */
function int_in(mixed $v, int $min = 1, int $max = 2147483647): ?int
{
    $i = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    return $i === false ? null : $i;
}

/** Render + clear one-time session flashes (storefront + admin share session keys). */
function flash_render(): string
{
    $html = '';
    foreach (['success' => 'status', 'error' => 'alert'] as $kind => $role) {
        $key = 'flash_' . $kind;
        if (!empty($_SESSION[$key])) {
            $cls = $kind === 'success'
                ? 'border-success/30 bg-success/10 text-success'
                : 'border-danger/30 bg-danger/10 text-danger';
            $html .= '<div role="' . $role . '" class="mb-4 rounded-xl border px-4 py-3 text-sm ' . $cls . '">'
                . e((string)$_SESSION[$key]) . '</div>';
            unset($_SESSION[$key]);
        }
    }
    return $html;
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
