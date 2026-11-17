<?php
// includes/csrf.php — CSRF token generation + verification (SECURITY.md §7.2, §38.1)
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Rotate after login/privilege change (SECURITY.md §7.2). */
function csrf_rotate(): void
{
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/**
 * Verify the token + Origin header for state-changing requests.
 * Call on every POST/PUT/PATCH/DELETE — never change state on GET (SECURITY.md §7.2 rule 3).
 */
function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Invalid request.');
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $host = parse_url($origin, PHP_URL_HOST);
        if (!hash_equals($_SERVER['HTTP_HOST'] ?? '', (string)($host ?? ''))) {
            http_response_code(403);
            exit('Bad origin.');
        }
    }
}
