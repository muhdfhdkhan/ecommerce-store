<?php
// modules/store/pages/account.php — customer auth + dashboard (FR-ACC-*).
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';

require_once BASE_PATH . '/modules/customers/service.php';

$action = (string)($_GET['action'] ?? 'login');
$customer = customer_current();

// Signed-in customers see their dashboard.
if ($customer !== null && $action !== 'logout') {
    $orders = customer_orders((int)$customer['id']);
    store_layout('My account', function () use ($customer, $orders) {
        echo flash_render();
        ?>
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:py-12">
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Hello, <?= e($customer['name']) ?></h1>

    <?php if (!$orders): ?>
        <div class="mt-8 rounded-3xl border border-line bg-surface p-10 text-center">
            <p class="text-lg font-semibold">No orders yet</p>
            <p class="mt-1 text-sm text-muted-ink">When you place an order it will show up here with live tracking.</p>
            <a href="/shop" class="mt-5 inline-flex h-11 items-center rounded-full bg-ink px-6 text-sm font-semibold text-white">Start shopping</a>
        </div>
    <?php else: ?>
    <h2 class="mt-8 font-bold">Your orders</h2>
    <div class="mt-4 space-y-4">
        <?php foreach ($orders as $o):
            $badge = ['pending_confirmation' => 'bg-warning/10 text-warning', 'delivered' => 'bg-success/10 text-success', 'cancelled' => 'bg-danger/10 text-danger', 'on_hold' => 'bg-warning/10 text-warning'][(string)$o['status']] ?? 'bg-accent-soft text-accent'; ?>
        <article class="rounded-3xl border border-line bg-surface p-5">
            <div class="flex flex-wrap items-center gap-3">
                <p class="font-bold tabular-nums"><?= e($o['order_number']) ?></p>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $badge ?>"><?= e(str_replace('_', ' ', (string)$o['status'])) ?></span>
                <span class="ml-auto font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$o['grand_total'])) ?></span>
            </div>
            <p class="mt-1 text-sm text-muted-ink">Placed UTC <?= e(substr((string)$o['placed_at'], 0, 16)) ?> · payment <?= e((string)$o['payment_status']) ?>
                <?php if (!empty($o['tracking_number'])): ?> · tracking <span class="font-mono"><?= e($o['tracking_number']) ?></span><?php endif; ?>
            </p>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" action="/account?action=logout" class="mt-10">
        <?= csrf_field() ?>
        <button class="inline-flex h-12 items-center rounded-full border border-line px-6 font-semibold hover:bg-canvas">Sign out</button>
    </form>
</div>
<?php });
    return;
}

// ---------- POST handlers ----------
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
        safe_redirect('/');
    }

    if ($action === 'register') {
        if (form_submitted_too_fast(2)) {
            $error = 'Please wait a moment and try again.';
        } else {
            [$ok, $msg] = customer_register(
                (string)($_POST['name'] ?? ''),
                (string)($_POST['email'] ?? ''),
                (string)($_POST['phone'] ?? ''),
                (string)($_POST['password'] ?? '')
            );
            if ($ok) {
                $_SESSION['flash_success'] = 'Account created. You can sign in now.';
                safe_redirect('/account');
            }
            $error = $msg;
        }
    }

    if ($action === 'login') {
        if (form_submitted_too_fast(2)) {
            $error = 'Please wait a moment and try again.';
        } else {
            [$ok, $info] = customer_login(
                (string)($_POST['email'] ?? ''),
                (string)($_POST['password'] ?? '')
            );
            if ($ok === true) {
                record_login_attempt('customer_login', 'email:' . strtolower(trim((string)$_POST['email'])), true);
                customer_start_session($info);
                cart_merge_into_customer((int)$info['id']);
                safe_redirect('/account');
            } elseif ($ok === 'blocked') {
                $error = 'This account is unavailable.'; // generic, no enumeration
            } elseif ($ok === 'rate_limited') {
                http_response_code(429);
                header('Retry-After: 900');
                $error = 'Too many attempts. Please try again later.';
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

// ---------- Login / Register forms ----------
$isRegister = $action === 'register';
store_layout($isRegister ? 'Create account' : 'Sign in', function () use ($isRegister, $error) { ?>
<div class="mx-auto max-w-md px-4 py-12 sm:py-16">
    <div class="rounded-3xl border border-line bg-surface p-6 sm:p-8">
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl"><?= $isRegister ? 'Create your account' : 'Sign in' ?></h1>

        <?php if ($error): ?>
            <div role="alert" class="mt-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php echo flash_render(); ?>

        <form method="post" action="/account?action=<?= $isRegister ? 'register' : 'login' ?>" class="mt-6 space-y-5" novalidate>
            <?= csrf_field() ?>
            <?php if ($isRegister): ?>
            <div>
                <label for="name" class="block text-sm font-semibold">Full name</label>
                <input id="name" name="name" required maxlength="120" autocomplete="name" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <div>
                <label for="phone" class="block text-sm font-semibold">Phone</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" required maxlength="24" placeholder="03xx-xxxxxxx" autocomplete="tel" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <?php endif; ?>
            <div>
                <label for="email" class="block text-sm font-semibold">Email</label>
                <input id="email" name="email" type="email" required maxlength="254" autocomplete="email" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold">Password</label>
                <input id="password" name="password" type="password" required <?= $isRegister ? 'minlength="10"' : '' ?> maxlength="128"
                       autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                <?php if ($isRegister): ?><p class="mt-1 text-xs text-muted-ink">At least 10 characters with letters and numbers.</p><?php endif; ?>
            </div>
            <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-full bg-ink font-semibold text-white hover:bg-ink/90">
                <?= $isRegister ? 'Create account' : 'Sign in' ?>
            </button>
        </form>

        <p class="mt-5 text-center text-sm text-muted-ink">
            <?php if ($isRegister): ?>
                Already have an account? <a href="/account" class="font-semibold text-accent hover:underline">Sign in</a>
            <?php else: ?>
                New here? <a href="/account?action=register" class="font-semibold text-accent hover:underline">Create an account</a>
            <?php endif; ?>
        </p>
    </div>
</div>
<?php });
