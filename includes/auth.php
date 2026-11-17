<?php
// includes/auth.php — admin authentication, RBAC helpers, audit trail.
// SECURITY.md §8.2/§8.3 (sessions), §9.2 (login flow), §11.2 (RBAC), §29 (audit).
declare(strict_types=1);

const SUPER_ADMIN_ROLE = 'super_admin';

/** Start an authenticated session: regenerate ID, bind loosely to client, fresh CSRF token. */
function login_admin(array $user): void
{
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['admin_id']   = (int)$user['id'];
    $_SESSION['admin_name'] = (string)$user['name'];
    $_SESSION['admin_role'] = $user['role_name'] ?? null;
    $_SESSION['ua_hash']    = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['login_at']   = time();
    $_SESSION['last_seen']  = time();
    csrf_rotate();               // rotate token after login (SECURITY.md §7.2)
}

/** Full logout: clear session, expire cookie, destroy (SECURITY.md §8.3). */
function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** True when an admin session is alive and passes UA-binding + both timeouts. */
function admin_session_valid(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    $uaOk = hash_equals((string)($_SESSION['ua_hash'] ?? ''), hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!$uaOk) {
        return false;
    }
    $now = time();
    if (($now - (int)($_SESSION['last_seen'] ?? 0)) > (int)config('session.admin_idle', 1800)) {
        return false; // idle timeout — 30 min
    }
    if (($now - (int)($_SESSION['login_at'] ?? 0)) > (int)config('session.admin_absolut', 8 * 3600)) {
        return false; // absolute timeout — 8 h
    }
    return true;
}

/** Enforce session on an authenticated page; refresh idle timer when valid. */
function admin_touch_session(): void
{
    if (!admin_session_valid()) {
        logout_admin();
        safe_redirect('/admin/login');
    }
    $_SESSION['last_seen'] = time();
}

/** Central permission check — deny by default (SECURITY.md §11.2). */
function admin_user_can(array $user, string $permission): bool
{
    if (($user['role'] ?? null) === SUPER_ADMIN_ROLE) {
        return true;
    }
    $permissions = $user['permissions'] ?? [];
    return in_array($permission, $permissions, true);
}

/** abort(403) unless the current admin holds the permission. */
function admin_require_permission(array $user, string $permission): void
{
    if (!admin_user_can($user, $permission)) {
        audit('permission_denied', null, null, null, ['permission' => $permission]);
        http_response_code(403);
        exit('Forbidden');
    }
}

/** Audit trail entry (SECURITY.md §29.1, §38.1). Never log secrets. */
function audit(string $action, ?string $entity = null, ?int $entityId = null, ?array $before = null, ?array $after = null): void
{
    try {
        $st = db()->prepare(
            'INSERT INTO activity_logs
                (user_id, action, entity, entity_id, before_json, after_json, ip, user_agent, created_at)
             VALUES (:u, :a, :e, :eid, :b, :af, :ip, :ua, UTC_TIMESTAMP())'
        );
        $st->execute([
            ':u'   => $_SESSION['admin_id'] ?? null,
            ':a'   => $action,
            ':e'   => $entity,
            ':eid' => $entityId,
            ':b'   => $before !== null ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            ':af'  => $after !== null ? json_encode($after, JSON_THROW_ON_ERROR) : null,
            ':ip'  => substr(client_ip(), 0, 45),
            ':ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        app_log('error', 'audit write failed for action ' . $action);
    }
}
