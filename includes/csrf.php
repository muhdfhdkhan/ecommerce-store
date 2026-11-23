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

/** Verify CSRF from the X-CSRF-Token header + Origin (used by fetch() JSON endpoints). */
function csrf_verify_header_only(bool $jsonResponse = false): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        if ($jsonResponse) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Your session expired. Please try again.']);
            exit;
        }
        http_response_code(403);
        exit('Invalid request.');
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $host = parse_url($origin, PHP_URL_HOST);
        if (!hash_equals($_SERVER['HTTP_HOST'] ?? '', (string)($host ?? ''))) {
            if ($jsonResponse) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Invalid request origin.']);
                exit;
            }
            http_response_code(403);
            exit('Bad origin.');
        }
    }
}

/**
 * Verify the token + Origin header for state-changing requests.
 * Call on every POST/PUT/PATCH/DELETE — never change state on GET (SECURITY.md §7.2 rule 3).
 */
function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(403);
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
