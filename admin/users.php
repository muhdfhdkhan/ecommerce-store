<?php
// admin/users.php — admin accounts: who can sign in, their role and status.
// SECURITY.md §11.2 (RBAC), §9.1/9.2 (password handling), §29 (audit).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

admin_require_permission($ADMIN, 'users.manage');

/** Number of active super admins, optionally excluding one account. */
function users_active_super_admins(?int $excludeId = null): int
{
    $sql = "SELECT COUNT(*)
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            WHERE r.name = 'super_admin' AND u.status = 'active' AND u.deleted_at IS NULL";
    $params = [];
    if ($excludeId !== null) {
        $sql .= ' AND u.id <> :id';
        $params[':id'] = $excludeId;
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int)$st->fetchColumn();
}

function users_find(int $id): ?array
{
    $st = db()->prepare(
        "SELECT u.id, u.name, u.email, u.status, u.last_login_at, u.created_at,
                (SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                  WHERE ur.user_id = u.id ORDER BY r.id LIMIT 1) AS role
         FROM users u
         WHERE u.id = :id AND u.deleted_at IS NULL
         LIMIT 1"
    );
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
}

/** Replace the account's role (the app models one role per admin). */
function users_set_role(int $userId, string $role): void
{
    db()->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute([':id' => $userId]);
    db()->prepare(
        'INSERT INTO user_roles (user_id, role_id)
         SELECT :id, r.id FROM roles r WHERE r.name = :role'
    )->execute([':id' => $userId, ':role' => $role]);
}

function users_password_problem(string $password, string $confirm): ?string
{
    if (strlen($password) < 12 || strlen($password) > 128) {
        return 'The password must be 12–128 characters.';
    }
    if ($password !== $confirm) {
        return 'The two passwords do not match.';
    }
    return null;
}

/** A super admin account may only be touched by another super admin. */
function users_may_manage(array $admin, array $target): bool
{
    if ((string)($target['role'] ?? '') !== SUPER_ADMIN_ROLE) {
        return true;
    }
    return (string)($admin['role'] ?? '') === SUPER_ADMIN_ROLE;
}

// ---------------- POST: create / edit / reset / status / delete ----------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();

    $do = (string)($_POST['do'] ?? '');
    $rolesSt = db()->query('SELECT name FROM roles ORDER BY id');
    $validRoles = array_column($rolesSt->fetchAll(), 'name');

    // ---- create ----
    if ($do === 'create') {
        $name  = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 120));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $role  = (string)($_POST['role'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        $problem = null;
        if (mb_strlen($name) < 2) {
            $problem = 'Please enter the admin’s name.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $problem = 'Please enter a valid email address.';
        } elseif (!in_array($role, $validRoles, true)) {
            $problem = 'Please choose a role.';
        } elseif ($role === SUPER_ADMIN_ROLE && (string)$ADMIN['role'] !== SUPER_ADMIN_ROLE) {
            $problem = 'Only a super admin can create another super admin.';
        } else {
            $problem = users_password_problem($pass, $confirm);
            if ($problem === null) {
                $st = db()->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
                $st->execute([':e' => $email]);
                if ($st->fetch()) {
                    $problem = 'An account with that email already exists.';
                }
            }
        }

        if ($problem !== null) {
            $_SESSION['flash_error'] = $problem;
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, status) VALUES (:n, :e, :h, :s)'
                )->execute([
                    ':n' => $name,
                    ':e' => $email,
                    ':h' => password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]),
                    ':s' => 'active',
                ]);
                $newId = (int)$pdo->lastInsertId();
                users_set_role($newId, $role);
                $pdo->commit();

                audit('user_created', 'admin_user', $newId, null, ['name' => $name, 'email' => $email, 'role' => $role]);
                $_SESSION['flash_success'] = 'Admin account created.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                app_log('error', 'admin user create failed: ' . $e->getMessage());
                $_SESSION['flash_error'] = 'Could not create that account. Please try again.';
            }
        }
        safe_redirect('/admin/users');
    }

    // ---- update details / role ----
    if ($do === 'update') {
        $id  = int_in($_POST['user_id'] ?? null);
        $row = $id ? users_find($id) : null;
        if ($row === null) {
            $_SESSION['flash_error'] = 'That account no longer exists.';
            safe_redirect('/admin/users');
        }

        $name  = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 120));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $role  = (string)($_POST['role'] ?? '');

        $problem = null;
        if (mb_strlen($name) < 2) {
            $problem = 'Please enter the admin’s name.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $problem = 'Please enter a valid email address.';
        } elseif (!in_array($role, $validRoles, true)) {
            $problem = 'Please choose a role.';
        } else {
            $st = db()->prepare('SELECT id FROM users WHERE email = :e AND id <> :id LIMIT 1');
            $st->execute([':e' => $email, ':id' => $id]);
            if ($st->fetch()) {
                $problem = 'Another account already uses that email.';
            }
        }

        // Never leave the store without a super admin.
        $wasSuper = ((string)$row['role'] === SUPER_ADMIN_ROLE);
        $staysSuper = ($role === SUPER_ADMIN_ROLE);
        if ($problem === null && !users_may_manage($ADMIN, $row)) {
            $problem = 'Only a super admin can manage a super admin account.';
        } elseif ($problem === null && $wasSuper && !$staysSuper && users_active_super_admins($id) === 0) {
            $problem = 'This is the only active super admin — promote another account first.';
        }

        if ($problem !== null) {
            $_SESSION['flash_error'] = $problem;
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE users SET name = :n, email = :e WHERE id = :id')
                    ->execute([':n' => $name, ':e' => $email, ':id' => $id]);
                if ($role !== (string)$row['role']) {
                    users_set_role($id, $role);
                }
                $pdo->commit();

                if ($id === (int)$ADMIN['id']) {
                    $_SESSION['admin_name'] = $name;
                }
                audit('user_updated', 'admin_user', $id,
                    ['name' => $row['name'], 'email' => $row['email'], 'role' => $row['role']],
                    ['name' => $name, 'email' => $email, 'role' => $role]);
                $_SESSION['flash_success'] = 'Account updated.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                app_log('error', 'admin user update failed: ' . $e->getMessage());
                $_SESSION['flash_error'] = 'Could not save those changes. Please try again.';
            }
        }
        safe_redirect('/admin/users');
    }

    // ---- password reset ----
    if ($do === 'reset') {
        $id = int_in($_POST['user_id'] ?? null);
        $row = $id ? users_find($id) : null;
        $pass = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        if ($row === null) {
            $_SESSION['flash_error'] = 'That account no longer exists.';
        } elseif (!users_may_manage($ADMIN, $row)) {
            $_SESSION['flash_error'] = 'Only a super admin can manage a super admin account.';
        } elseif (($problem = users_password_problem($pass, $confirm)) !== null) {
            $_SESSION['flash_error'] = $problem;
        } else {
            db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                ->execute([':h' => password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), ':id' => $id]);
            audit('user_password_reset', 'admin_user', $id, null, ['email' => $row['email']]);
            $_SESSION['flash_success'] = 'Password reset for ' . $row['name'] . '.';
        }
        safe_redirect('/admin/users');
    }

    // ---- enable / disable ----
    if ($do === 'status') {
        $id = int_in($_POST['user_id'] ?? null);
        $row = $id ? users_find($id) : null;
        $target = (string)($_POST['status'] ?? '');

        if ($row === null || !in_array($target, ['active', 'disabled'], true)) {
            $_SESSION['flash_error'] = 'That account no longer exists.';
        } elseif (!users_may_manage($ADMIN, $row)) {
            $_SESSION['flash_error'] = 'Only a super admin can manage a super admin account.';
        } elseif ($id === (int)$ADMIN['id']) {
            $_SESSION['flash_error'] = 'You cannot disable your own account.';
        } elseif ($target === 'disabled'
            && (string)$row['role'] === SUPER_ADMIN_ROLE
            && users_active_super_admins($id) === 0) {
            $_SESSION['flash_error'] = 'This is the only active super admin — it cannot be disabled.';
        } else {
            db()->prepare('UPDATE users SET status = :s WHERE id = :id')
                ->execute([':s' => $target, ':id' => $id]);
            audit($target === 'active' ? 'user_enabled' : 'user_disabled', 'admin_user', $id,
                ['status' => $row['status']], ['status' => $target]);
            $_SESSION['flash_success'] = $target === 'active' ? 'Account enabled.' : 'Account disabled.';
        }
        safe_redirect('/admin/users');
    }

    // ---- delete (soft) ----
    if ($do === 'delete') {
        $id = int_in($_POST['user_id'] ?? null);
        $row = $id ? users_find($id) : null;

        if ($row === null) {
            $_SESSION['flash_error'] = 'That account no longer exists.';
        } elseif (!users_may_manage($ADMIN, $row)) {
            $_SESSION['flash_error'] = 'Only a super admin can manage a super admin account.';
        } elseif ($id === (int)$ADMIN['id']) {
            $_SESSION['flash_error'] = 'You cannot delete your own account.';
        } elseif ((string)$row['role'] === SUPER_ADMIN_ROLE && users_active_super_admins($id) === 0) {
            $_SESSION['flash_error'] = 'This is the only active super admin — it cannot be deleted.';
        } else {
            // Release the email for reuse while keeping the original in the audit trail.
            $released = mb_substr('deleted.' . $id . '.' . (string)$row['email'], 0, 254);
            db()->prepare(
                'UPDATE users SET status = :s, deleted_at = UTC_TIMESTAMP(), email = :e WHERE id = :id'
            )->execute([':s' => 'disabled', ':e' => $released, ':id' => $id]);
            audit('user_deleted', 'admin_user', $id,
                ['name' => $row['name'], 'email' => $row['email'], 'role' => $row['role']],
                ['deleted' => true]);
            $_SESSION['flash_success'] = 'Account removed.';
        }
        safe_redirect('/admin/users');
    }

    safe_redirect('/admin/users');
}

admin_layout('Users', function () use ($ADMIN) {
    $rows = db()->query(
        "SELECT u.id, u.name, u.email, u.status, u.last_login_at, u.created_at,
                (SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                  WHERE ur.user_id = u.id ORDER BY r.id LIMIT 1) AS role
         FROM users u
         WHERE u.deleted_at IS NULL
         ORDER BY u.id"
    )->fetchAll();

    $roles = db()->query('SELECT name, description FROM roles ORDER BY id')->fetchAll();
    $counts = ['active' => 0, 'disabled' => 0];
    foreach ($rows as $r) {
        $counts[$r['status'] === 'active' ? 'active' : 'disabled']++;
    }

    admin_flash();
    ?>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Users</h1>
            <p class="mt-1 text-sm text-muted-ink">Admin accounts and their roles. Customers are managed separately.</p>
        </div>
        <p class="text-xs text-muted-ink tabular-nums"><?= (int)$counts['active'] ?> active &middot; <?= (int)$counts['disabled'] ?> disabled</p>
    </div>

    <div class="mt-6 space-y-3">
        <?php foreach ($rows as $u):
            $isSelf = ((int)$u['id'] === (int)$ADMIN['id']);
            $isActive = ($u['status'] === 'active');
        ?>
        <div class="rounded-2xl border border-line bg-surface">
            <div class="flex flex-wrap items-center gap-3 p-4 sm:p-5">
                <span aria-hidden="true" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-accent text-sm font-bold text-white">
                    <?= e(strtoupper(mb_substr((string)$u['name'], 0, 1))) ?>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">
                        <?= e($u['name']) ?>
                        <?php if ($isSelf): ?><span class="ml-1 rounded-full bg-accent-soft px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-ink">You</span><?php endif; ?>
                    </p>
                    <p class="truncate text-xs text-muted-ink"><?= e($u['email']) ?></p>
                </div>
                <span class="rounded-full bg-canvas px-3 py-1 text-xs font-semibold text-muted-ink"><?= e(role_label((string)($u['role'] ?? ''))) ?></span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $isActive ? 'bg-success/10 text-success' : 'bg-line text-muted-ink' ?>"><?= $isActive ? 'Active' : 'Disabled' ?></span>
                <span class="hidden text-xs text-muted-ink tabular-nums sm:inline">
                    <?= $u['last_login_at'] ? 'Last in ' . e(substr((string)$u['last_login_at'], 0, 10)) : 'Never signed in' ?>
                </span>
            </div>

            <details class="border-t border-line px-4 py-3 sm:px-5">
                <summary class="cursor-pointer text-sm font-semibold text-muted-ink hover:text-ink">Manage account</summary>
                <div class="mt-4 grid gap-5 lg:grid-cols-2">
                    <form method="post" class="space-y-3">
                        <input type="hidden" name="do" value="update">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <?= csrf_field() ?>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="n<?= (int)$u['id'] ?>" class="block text-sm font-semibold">Name</label>
                                <input id="n<?= (int)$u['id'] ?>" name="name" required maxlength="120" value="<?= e($u['name']) ?>"
                                       class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                            </div>
                            <div>
                                <label for="e<?= (int)$u['id'] ?>" class="block text-sm font-semibold">Email</label>
                                <input id="e<?= (int)$u['id'] ?>" name="email" type="email" required maxlength="254" value="<?= e($u['email']) ?>"
                                       class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                            </div>
                        </div>
                        <div>
                            <label for="r<?= (int)$u['id'] ?>" class="block text-sm font-semibold">Role</label>
                            <select id="r<?= (int)$u['id'] ?>" name="role" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= e($role['name']) ?>" <?= $role['name'] === $u['role'] ? 'selected' : '' ?>><?= e(role_label((string)$role['name'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-ink px-5 text-sm font-semibold text-white hover:bg-ink/90">Save</button>
                            <?php if (!$isSelf): ?>
                            <button type="submit" form="st<?= (int)$u['id'] ?>" class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">
                                <?= $isActive ? 'Disable' : 'Enable' ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>

                    <form method="post" class="space-y-3" autocomplete="off">
                        <input type="hidden" name="do" value="reset">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <?= csrf_field() ?>
                        <p class="text-sm font-semibold">Reset password</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="p<?= (int)$u['id'] ?>" class="block text-xs font-semibold text-muted-ink">New password</label>
                                <input id="p<?= (int)$u['id'] ?>" name="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password"
                                       class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                            </div>
                            <div>
                                <label for="pc<?= (int)$u['id'] ?>" class="block text-xs font-semibold text-muted-ink">Confirm password</label>
                                <input id="pc<?= (int)$u['id'] ?>" name="password_confirm" type="password" required minlength="12" maxlength="128" autocomplete="new-password"
                                       class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">Set password</button>
                            <?php if (!$isSelf): ?>
                            <button type="submit" form="dl<?= (int)$u['id'] ?>" class="inline-flex h-10 items-center rounded-xl border border-danger/40 px-4 text-sm font-semibold text-danger hover:bg-danger/10">Delete</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <?php if (!$isSelf): ?>
                <form id="st<?= (int)$u['id'] ?>" method="post">
                    <input type="hidden" name="do" value="status">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="status" value="<?= $isActive ? 'disabled' : 'active' ?>">
                    <?= csrf_field() ?>
                </form>
                <form id="dl<?= (int)$u['id'] ?>" method="post">
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <?= csrf_field() ?>
                </form>
                <?php endif; ?>
            </details>
        </div>
        <?php endforeach; ?>
    </div>

    <form method="post" class="mt-8 max-w-3xl rounded-2xl border border-line bg-surface p-5 sm:p-6" autocomplete="off">
        <input type="hidden" name="do" value="create">
        <?= csrf_field() ?>
        <h2 class="font-bold">Add an admin</h2>
        <p class="mt-1 text-xs text-muted-ink">They can sign in immediately. Ask them to change the password from My account after their first sign-in.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="new_name" class="block text-sm font-semibold">Name</label>
                <input id="new_name" name="name" required maxlength="120" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <div>
                <label for="new_email" class="block text-sm font-semibold">Email</label>
                <input id="new_email" name="email" type="email" required maxlength="254" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <div class="sm:col-span-2">
                <label for="new_role" class="block text-sm font-semibold">Role</label>
                <select id="new_role" name="role" required class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                    <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role['name']) ?>" <?= $role['name'] === 'store_manager' ? 'selected' : '' ?>><?= e(role_label((string)$role['name'])) ?> &mdash; <?= e((string)$role['description']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="new_password" class="block text-sm font-semibold">Password</label>
                <input id="new_password" name="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password"
                       class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                <p class="mt-1 text-xs text-muted-ink">12&ndash;128 characters.</p>
            </div>
            <div>
                <label for="new_password_confirm" class="block text-sm font-semibold">Confirm password</label>
                <input id="new_password_confirm" name="password_confirm" type="password" required minlength="12" maxlength="128" autocomplete="new-password"
                       class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
        </div>
        <button type="submit" class="mt-5 inline-flex h-11 items-center rounded-xl bg-accent px-6 text-sm font-semibold text-white hover:bg-accent/90">Create admin</button>
    </form>
<?php });
