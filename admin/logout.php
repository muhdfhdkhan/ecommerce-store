<?php
// admin/logout.php â€” POST only; never change state on GET (SECURITY.md Â§7.2 rule 3).
declare(strict_types=1);

require_once BASE_PATH . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

csrf_verify();

if (!empty($_SESSION['admin_id'])) {
    audit('logout', 'admin_user', (int)$_SESSION['admin_id']);
}

logout_admin();
safe_redirect('/admin/login');
