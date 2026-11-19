<?php
// admin/login.php â€” admin authentication.
// SECURITY.md: Â§7 (CSRF), Â§9.1/9.2 (passwords, generic errors, timing), Â§10 (rate limiting),
// Â§38.3 (login endpoint pattern).
declare(strict_types=1);

require_once BASE_PATH . '/includes/bootstrap.php';

/** @var string $CSP_NONCE */

// Already signed in? Straight to the dashboard.
if (admin_session_valid()) {
    safe_redirect('/admin/dashboard');
}

$error = null;
$emailPrefill = '';

// ---------------- POST: process login ----------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();

    // Honeypot â€” real users never fill this hidden field (SECURITY.md Â§10.3).
    $honeypot = trim((string)($_POST['website'] ?? ''));
    if ($honeypot !== '') {
        record_login_attempt('admin_login', 'ip:' . client_ip(), false);
        $error = 'Invalid email or password.';
    } elseif (form_submitted_too_fast(2)) {
        record_login_attempt('admin_login', 'ip:' . client_ip(), false);
        $error = 'Too many attempts. Please try again later.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');
        $emailPrefill = $email;

        $validEmail = filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254;

        // Rate limits: per identity AND per IP (SECURITY.md Â§10.1).
        // IP-bucket limit: applies to every submission, valid or malformed input.
        $okIp = rate_limit_ok('admin_login', 'ip:' . client_ip(), 20, 900);

        // Identity-bucket limit only makes sense for a syntactically valid email;
        // malformed input falls through to the generic credentials path below.
        $okIdentity = !$validEmail || rate_limit_ok('admin_login', 'email:' . $email, 5, 900);

        if (!$okIp || !$okIdentity) {
            if (!$okIdentity && $okIp) {
                audit('login_rate_limited', null, null, null, ['identity_bucket' => true]);
            }
            http_response_code(429);
            header('Retry-After: 900');
            $error = 'Too many attempts. Please try again later.';
        } else {
            $user = null;
            if ($validEmail) {
                $st = db()->prepare(
                    'SELECT u.id, u.name, u.email, u.password_hash, u.status, r.name AS role_name
                     FROM users u
                     LEFT JOIN user_roles ur ON ur.user_id = u.id
                     LEFT JOIN roles r ON r.id = ur.role_id
                     WHERE u.email = :email AND u.deleted_at IS NULL
                     LIMIT 1'
                );
                $st->execute([':email' => $email]);
                $user = $st->fetch() ?: null;
            }

            // Timing equalization: verify against a dummy hash when user not found
            // (SECURITY.md Â§9.2 â€” same response time for existing/non-existing accounts).
            $dummy = '$2y$12$XlY0dq0yQzOq0zBT1zS9F.FdWJ3Rl0KjVKV5nCoWFCFqrjO0PMroi';
            $hash  = is_array($user) ? (string)$user['password_hash'] : $dummy;
            $ok    = password_verify($pass, $hash) && $user !== null && $user['status'] === 'active';

            if (!$ok) {
                if ($validEmail) {
                    record_login_attempt('admin_login', 'email:' . $email, false);
                }
                record_login_attempt('admin_login', 'ip:' . client_ip(), false);
                audit('login_failed', 'admin_user', is_array($user) ? (int)$user['id'] : null);
                $error = 'Invalid email or password.';
            } else {
                // Re-hash when the algorithm/cost has moved on (SECURITY.md Â§9.1).
                if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
                    $new = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                    $up  = db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
                    $up->execute([':h' => $new, ':id' => (int)$user['id']]);
                }

                record_login_attempt('admin_login', 'email:' . $email, true);
                login_admin([
                    'id'        => (int)$user['id'],
                    'name'      => (string)$user['name'],
                    'role_name' => $user['role_name'],
                ]);

                $ll = db()->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id');
                $ll->execute([':id' => (int)$user['id']]);

                audit('login_success', 'admin_user', (int)$user['id']);
                safe_redirect('/admin/dashboard');
            }
        }
    }
} else {
    // Record the visit (throttled to once per 10 minutes per session) so the
    // activity log can answer "who opened the admin sign-in screen".
    if (empty($_SESSION['login_view_logged']) || (time() - (int)$_SESSION['login_view_logged']) > 600) {
        $_SESSION['login_view_logged'] = time();
        audit('login_page_view', 'admin_login', null, null, ['screen' => 'admin_login']);
    }
    mark_form_seen();
}

$emailValue = e($emailPrefill);
$errorHtml  = '';
if ($error !== null) {
    $errorHtml = '<div role="alert" class="mt-1 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">'
        . e($error) . '</div>';
}
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin Sign In &mdash; Patches&amp;Motifs</title>
<link rel="stylesheet" href="<?= asset_url('/assets/css/app.css') ?>">
<script type="module" src="<?= asset_url('/assets/js/admin.js') ?>"></script>
</head>
<body class="min-h-full bg-canvas text-ink antialiased flex flex-col">
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6">
            <span aria-hidden="true" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-[13px] font-bold tracking-tight text-white">P&amp;M</span>
            <span class="text-base font-bold tracking-tight">Patches&amp;Motifs</span>
            <span class="ml-1 hidden rounded-full bg-line/70 px-2.5 py-1 text-[11px] font-semibold text-muted-ink sm:inline">Admin Portal</span>
        </div>
    </header>

    <main class="flex flex-1 items-center justify-center px-4 py-10 sm:py-16">
        <div class="w-full max-w-md">
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-sm sm:p-8">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Sign in to your workspace</h1>
                <p class="mt-1 text-sm text-muted-ink">Manage products, orders and the store.</p>

                <?= $errorHtml ?>

                <form method="post" action="/admin/login" class="mt-6 space-y-5" novalidate>
                    <?= csrf_field() ?>
                    <p class="hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </p>

                    <div>
                        <label for="email" class="block text-sm font-semibold">Email</label>
                        <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
                               required maxlength="254" value="<?= $emailValue ?>"
                               class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 text-base outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/25">
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="password" class="block text-sm font-semibold">Password</label>
                            <button type="button" data-toggle-password="#password" class="text-xs font-semibold text-accent underline-offset-4 hover:underline">
                                Show
                            </button>
                        </div>
                        <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="128"
                               class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 text-base outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/25">
                    </div>

                    <button type="submit"
                            class="inline-flex h-12 w-full items-center justify-center rounded-full bg-ink text-base font-semibold text-white transition hover:bg-ink/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent active:scale-[0.99]">
                        Sign in
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-muted-ink">
                Protected area. All sign-in attempts are logged.
            </p>
        </div>
    </main>

    <footer class="border-t border-line bg-surface">
        <div class="mx-auto max-w-6xl px-4 py-4 text-center text-xs text-muted-ink sm:px-6">
            &copy; <?= e(gmdate('Y')) ?> Patches&amp;Motifs
        </div>
    </footer>
</body>
</html>
