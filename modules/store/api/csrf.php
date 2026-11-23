<?php
// modules/store/api/csrf.php — hand the current session CSRF token to same-origin
// fetch() callers. Used to recover quietly when a long-open page (or an admin
// login in another tab) rotated the token, so add-to-cart never dead-ends.
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

echo json_encode(['ok' => true, 'token' => csrf_token()]);
