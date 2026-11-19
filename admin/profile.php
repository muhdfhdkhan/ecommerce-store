<?php
// admin/profile.php — the signed-in admin's own account: name, email and password.
// Password changes require the current password (SECURITY.md §9.2).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $do = (string)($_POST['do'] ?? '');

    if ($do === 'profile') {
        $name  = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 120));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));

        if (mb_strlen($name) < 2) {
            $_SESSION['flash_error'] = 'Please enter your name.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $_SESSION['flash_error'] = 'Please enter a valid email address.';
        } else {
            $st = db()->prepare('SELECT id FROM users WHERE email = :e AND id != :id LIMIT 1');
            $st->execute([':e' => $email, ':id' => (int)$ADMIN['id']]);
            if ($st->fetch()) {
                $_SESSION['flash_error'] = 'That email is already used by another admin.';
            } else {
                $st = db()->prepare('UPDATE users SET name = :n, email = :e WHERE id = :id');
                $st->execute([':n' => $name, ':e' => $email, ':id' => (int)$ADMIN['id']]);
                $_SESSION['admin_name'] = $name;
                audit('profile_updated', 'admin_user', (int)$ADMIN['id'], null, ['name' => $name, 'email' => $email]);
                $_SESSION['flash_success'] = 'Your account details were saved.';
            }
        }
        safe_redirect('/admin/profile');
    }

    if ($do === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $st = db()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $st->execute([':id' => (int)$ADMIN['id']]);
        $hash = (string)$st->fetchColumn();

        if (!password_verify($current, $hash)) {
            $_SESSION['flash_error'] = 'Your current password is not correct.';
        } elseif (strlen($new) < 12 || strlen($new) > 128) {
            $_SESSION['flash_error'] = 'The new password must be 12–128 characters.';
        } elseif ($new !== $confirm) {
            $_SESSION['flash_error'] = 'The new passwords do not match.';
        } elseif (password_verify($new, $hash)) {
            $_SESSION['flash_error'] = 'Choose a password you have not used here before.';
        } else {
            $newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                ->execute([':h' => $newHash, ':id' => (int)$ADMIN['id']]);

            // Fresh session after a credential change (SECURITY.md §7.2, §9.2).
            session_regenerate_id(true);
            csrf_rotate();
            audit('password_changed', 'admin_user', (int)$ADMIN['id']);
            $_SESSION['flash_success'] = 'Your password was changed.';
        }
        safe_redirect('/admin/profile');
    }

    safe_redirect('/admin/profile');
}

admin_layout('My account', function () use ($ADMIN) {
    $st = db()->prepare('SELECT id, name, email, status, last_login_at, created_at FROM users WHERE id = :id LIMIT 1');
    $st->execute([':id' => (int)$ADMIN['id']]);
    $me = $st->fetch() ?: [
        'name' => $ADMIN['name'], 'email' => $ADMIN['email'], 'status' => 'active',
        'last_login_at' => null, 'created_at' => null,
    ];

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">My account</h1>
    <p class="mt-1 text-sm text-muted-ink">Your profile and sign-in password. Role: <span class="font-semibold text-ink"><?= e(role_label((string)$ADMIN['role'])) ?></span>.</p>

    <div class="mt-6 grid max-w-4xl gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <h2 class="font-bold">Profile</h2>
            <form method="post" class="mt-4 space-y-4">
                <input type="hidden" name="do" value="profile">
                <?= csrf_field() ?>
                <div>
                    <label for="name" class="block text-sm font-semibold">Name</label>
                    <input id="name" name="name" required maxlength="120" value="<?= e((string)$me['name']) ?>"
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div>
                    <label for="email" class="block text-sm font-semibold">Email</label>
                    <input id="email" name="email" type="email" required maxlength="254" value="<?= e((string)$me['email']) ?>"
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <button type="submit" class="inline-flex h-11 items-center rounded-xl bg-ink px-6 text-sm font-semibold text-white hover:bg-ink/90">Save profile</button>
            </form>
            <dl class="mt-6 space-y-2 border-t border-line pt-4 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted-ink">Status</dt><dd class="font-semibold"><?= e(ucfirst((string)$me['status'])) ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-ink">Last sign-in</dt><dd class="font-semibold tabular-nums"><?= e($me['last_login_at'] ? $me['last_login_at'] . ' UTC' : '—') ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-ink">Account created</dt><dd class="font-semibold tabular-nums"><?= e($me['created_at'] ? substr((string)$me['created_at'], 0, 10) : '—') ?></dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <h2 class="font-bold">Change password</h2>
            <p class="mt-1 text-xs text-muted-ink">Use at least 12 characters. You will stay signed in on this device.</p>
            <form method="post" class="mt-4 space-y-4" autocomplete="off">
                <input type="hidden" name="do" value="password">
                <?= csrf_field() ?>
                <div>
                    <label for="current_password" class="block text-sm font-semibold">Current password</label>
                    <input id="current_password" name="current_password" type="password" required maxlength="128" autocomplete="current-password"
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div>
                    <label for="new_password" class="block text-sm font-semibold">New password</label>
                    <input id="new_password" name="new_password" type="password" required minlength="12" maxlength="128" autocomplete="new-password"
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div>
                    <label for="confirm_password" class="block text-sm font-semibold">Confirm new password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required minlength="12" maxlength="128" autocomplete="new-password"
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <button type="submit" class="inline-flex h-11 items-center rounded-xl bg-accent px-6 text-sm font-semibold text-white hover:bg-accent/90">Change password</button>
            </form>
        </section>
    </div>
<?php });
