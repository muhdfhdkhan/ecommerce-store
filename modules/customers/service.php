<?php
// modules/customers/service.php — customer auth (FR-AUTH-001..006).
declare(strict_types=1);

/** Register with validation; returns [ok, message]. */
function customer_register(string $name, string $email, string $phone, string $password): array
{
    $name = trim(mb_substr($name, 0, 120));
    $email = strtolower(trim(mb_substr($email, 0, 254)));
    $phone = trim(mb_substr($phone, 0, 24));

    if (mb_strlen($name) < 2) return [false, 'Please enter your full name.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Please enter a valid email.'];
    if (!preg_match('/^[0-9+\-\s]{7,24}$/', $phone)) return [false, 'Please enter a valid phone number.'];
    if (strlen($password) < 10 || strlen($password) > 128) return [false, 'Password must be 10–128 characters.'];
    if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/\d/', $password)) return [false, 'Password needs letters and numbers.'];

    $st = db()->prepare('SELECT id FROM customers WHERE email = :e AND deleted_at IS NULL');
    $st->execute([':e' => $email]);
    if ($st->fetch()) {
        return [false, 'If an account with this email exists, please sign in instead.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $st = db()->prepare('INSERT INTO customers (name, email, phone, password_hash) VALUES (:n, :e, :p, :h)');
    $st->execute([':n' => $name, ':e' => $email, ':p' => $phone, ':h' => $hash]);
    return [true, 'Account created.'];
}

/** Customer login with timing equalization and rate limits (SECURITY.md §9.2, §10.1). */
function customer_login(string $email, string $password): array
{
    $email = strtolower(trim(mb_substr($email, 0, 254)));
    $ip = client_ip();

    if (!rate_limit_ok('customer_login', 'ip:' . $ip, 20, 900)) {
        return [false, 'rate_limited'];
    }

    $st = db()->prepare('SELECT id, name, password_hash, status FROM customers WHERE email = :e AND deleted_at IS NULL LIMIT 1');
    $st->execute([':e' => $email]);
    $user = $st->fetch() ?: null;

    $dummy = '$2y$12$XlY0dq0yQzOq0zBT1zS9F.FdWJ3Rl0KjVKV5nCoWFCFqrjO0PMroi';
    $hash = is_array($user) ? (string)$user['password_hash'] : $dummy;
    $ok = password_verify($password, $hash) && $user !== null && $user['status'] === 'active';

    if (!$ok) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            record_login_attempt('customer_login', 'email:' . $email, false);
        }
        record_login_attempt('customer_login', 'ip:' . $ip, false);
        return [is_array($user) && $user['status'] === 'blocked' ? 'blocked' : false, null];
    }
    return [true, ['id' => (int)$user['id'], 'name' => (string)$user['name']]];
}

/** Start customer session (separate namespace keys from admin; same cookie is fine locally). */
function customer_start_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$user['id'];
    $_SESSION['customer_name'] = $user['name'];
    $_SESSION['ua_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    csrf_rotate();
}

function customer_current(): ?array
{
    if (empty($_SESSION['customer_id'])) return null;
    $uaOk = hash_equals((string)($_SESSION['ua_hash'] ?? ''), hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!$uaOk) return null;
    $st = db()->prepare('SELECT id, name, email, phone FROM customers WHERE id = :id AND status = :s AND deleted_at IS NULL');
    $st->execute([':id' => (int)$_SESSION['customer_id'], ':s' => 'active']);
    return $st->fetch() ?: null;
}

/** Own orders only — IDOR protection (SECURITY.md §11.2). */
function customer_orders(int $customerId): array
{
    $st = db()->prepare(
        'SELECT id, order_number, status, payment_status, grand_total, placed_at, tracking_number
         FROM orders WHERE customer_id = :cid ORDER BY id DESC LIMIT 50'
    );
    $st->execute([':cid' => $customerId]);
    return $st->fetchAll();
}

/** Password reset: raw token emailed, only sha256 stored (SECURITY.md §9.3). */
function customer_reset_request(string $email): array
{
    $email = strtolower(trim(mb_substr($email, 0, 254)));
    if (!rate_limit_ok('reset', 'email:' . $email, 3, 3600) || !rate_limit_ok('reset', 'ip:' . client_ip(), 10, 3600)) {
        return [true]; // same response when rate limited (no enumeration)
    }
    $st = db()->prepare('SELECT id FROM customers WHERE email = :e AND deleted_at IS NULL');
    $st->execute([':e' => $email]);
    if (!$st->fetch()) {
        return [true]; // same response when account doesn't exist
    }
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $st = db()->prepare(
        'INSERT INTO password_resets (scope, email, token_hash, expires_at)
         VALUES (:scope, :e, :h, UTC_TIMESTAMP() + INTERVAL 45 MINUTE)'
    );
    $st->execute([':scope' => 'customer', ':e' => $email, ':h' => $hash]);
    return [true, $token];
}
