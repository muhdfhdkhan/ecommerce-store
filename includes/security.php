<?php
// includes/security.php — secure session bootstrap, security headers, redirects.
// Implements SECURITY.md §7.2 (SameSite), §8.2/§8.3 (session hardening), §23.1 (headers).
declare(strict_types=1);

/** Start a hardened session. Admin and storefront future cookies share one name for now. */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(config('session.name', 'PMID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (bool)config('session.secure_cookie', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Security headers on every response (SECURITY.md §23.1). */
function send_security_headers(string $nonce): void
{
    header_remove('X-Powered-By');

    // HSTS only when HTTPS is active (off on local HTTP XAMPP).
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self)');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Content-Security-Policy: '
        . "default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'");
}

/** Private/admin response — never stored in any cache (SECURITY.md §23.4). */
function send_no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** Client IP (no trusting proxy headers on XAMPP/local). */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Generic abort, no details leaked (SECURITY.md §29/§4). */
function abort(int $code = 404, string $msg = 'Not found'): never
{
    http_response_code($code);
    exit(e($msg));
}

/**
 * Only same-site relative paths may be used for redirects (SECURITY.md §16.2).
 * Blocks //host and \ tricks.
 */
function safe_redirect(string $target, string $fallback = '/'): never
{
    if (!preg_match('~^/(?!/)[^\r\n\\\\]*(?:\?[^\x7E]*)?$~', $target)) {
        $target = $fallback;
    }
    header('Location: ' . $target, true, 302);
    exit;
}

/** Minimum time-to-submit check (honeypot support, SECURITY.md §10.3). */
function form_submitted_too_fast(int $minSeconds = 2): bool
{
    $ts = (int)($_SESSION['form_ts'] ?? 0);
    return $ts !== 0 && (time() - $ts) < $minSeconds;
}

function mark_form_seen(): void
{
    $_SESSION['form_ts'] = time();
}
