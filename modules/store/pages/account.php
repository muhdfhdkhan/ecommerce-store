<?php
// modules/store/pages/account.php — customer auth + dashboard (FR-ACC-*).
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';

require_once BASE_PATH . '/modules/customers/service.php';
require_once BASE_PATH . '/includes/google_oauth.php';

$action = (string)($_GET['action'] ?? 'login');
$customer = customer_current();

// ---------------- Google Sign-In (OAuth 2.0 authorization-code flow) ----------------
// Step 1: /account/google — mint a single-use state token, then hand off to Google.
if ($action === 'google') {
    if (!google_oauth_configured()) {
        $_SESSION['flash_error'] = 'Google sign-in is not configured yet.';
        safe_redirect('/account');
    }
    $state = bin2hex(random_bytes(24));
    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_oauth_at']    = time();
    // Absolute URL to Google's own domain — safe_redirect() only allows relative paths.
    header('Location: ' . google_auth_url($state), true, 302);
    exit;
}

// Step 2: /account/google/callback — verify state, exchange the code, sign in.
if ($action === 'google_callback') {
    $state = (string)($_GET['state'] ?? '');
    $saved = (string)($_SESSION['google_oauth_state'] ?? '');
    $at    = (int)($_SESSION['google_oauth_at'] ?? 0);
    unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_at']);

    $fail = static function (string $msg): void {
        $_SESSION['flash_error'] = $msg;
        safe_redirect('/account');
    };

    // State protects the callback against CSRF/login-CSRF; 10-minute lifetime.
    if ($saved === '' || $state === '' || !hash_equals($saved, $state) || $at === 0 || (time() - $at) > 600) {
        $fail('Your Google sign-in session expired. Please try again.');
    }
    if (!empty($_GET['error'])) {
        $fail('Google sign-in was cancelled.');
    }

    $code = (string)($_GET['code'] ?? '');
    if ($code === '') {
        $fail('Google did not return an authorization code.');
    }

    $token = google_exchange_code($code);
    if ($token === null) {
        $fail('Could not verify your Google account. Please try again.');
    }

    $profile = google_profile($token);
    if ($profile === null) {
        $fail('Google did not provide a verified email address.');
    }

    [$ok, $info, $err] = google_customer_sign_in($profile);
    if ($ok !== true || !is_array($info)) {
        $fail($err ?: 'Could not sign you in with Google.');
    }

    record_login_attempt('customer_login', 'email:' . $profile['email'], true);
    customer_start_session($info);
    cart_merge_into_customer((int)$info['id']);
    $_SESSION['flash_success'] = 'Signed in with Google.';
    safe_redirect('/account');
}

// Signed-in customers see their dashboard.
if ($customer !== null && $action !== 'logout') {
    $orders = customer_orders((int)$customer['id']);
    store_layout('My account', function () use ($customer, $orders) {
        echo flash_render();
        ?>
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:py-12">
    <div class="gold-rule mb-5 w-20"></div>
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
            $badge = ['pending_confirmation' => 'bg-warning/10 text-warning', 'delivered' => 'bg-success/10 text-success', 'cancelled' => 'bg-danger/10 text-danger', 'on_hold' => 'bg-warning/10 text-warning'][(string)$o['status']] ?? 'bg-gold-soft text-gold-deep'; ?>
        <article class="rounded-3xl border border-line bg-surface p-5 transition hover:border-gold/50">
            <div class="flex flex-wrap items-center gap-3">
                <p class="font-bold tabular-nums"><?= e($o['order_number']) ?></p>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $badge ?>"><?= e(str_replace('_', ' ', (string)$o['status'])) ?></span>
                <span class="ml-auto font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$o['grand_total'])) ?></span>
            </div>
            <p class="mt-1 text-sm text-muted-ink">Placed UTC <?= e(substr((string)$o['placed_at'], 0, 16)) ?> · payment <?= e((string)$o['payment_status']) ?>
                <?php if (!empty($o['tracking_number'])): ?> · tracking <span class="font-mono"><?= e($o['tracking_number']) ?></span><?php endif; ?>
            </p>
            <a href="/track?number=<?= e(rawurlencode((string)$o['order_number'])) ?>"
               class="mt-3 inline-flex h-9 items-center rounded-full border border-gold/50 px-4 text-xs font-semibold text-gold-deep transition hover:bg-gold-soft">Track order &rarr;</a>
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
$googleEnabled = google_oauth_configured();
store_layout($isRegister ? 'Create account' : 'Sign in', function () use ($isRegister, $error, $googleEnabled) { ?>
<div class="mx-auto max-w-md px-4 py-12 sm:py-16">
    <div class="rounded-3xl border border-line bg-surface p-6 sm:p-8">
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl"><?= $isRegister ? 'Create your account' : 'Sign in' ?></h1>

        <?php if ($error): ?>
            <div role="alert" class="mt-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php echo flash_render(); ?>

        <?php if ($googleEnabled): ?>
        <a href="/account/google"
           class="mt-6 inline-flex h-12 w-full items-center justify-center gap-3 rounded-full border border-line bg-white font-semibold text-ink transition hover:bg-canvas">
            <svg viewBox="0 0 48 48" class="h-5 w-5" aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.7 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.2 17.7 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.6-4.8 7.3l7.7 6C43.9 38.1 46.5 31.9 46.5 24.5z"/>
                <path fill="#FBBC05" d="M10.5 28.7c-.5-1.4-.8-2.9-.8-4.7s.3-3.3.8-4.7l-7.9-6.1C1 16.3 0 20 0 24s1 7.7 2.6 10.8l7.9-6.1z"/>
                <path fill="#34A853" d="M24 48c6.3 0 11.7-2.1 15.6-5.7l-7.7-6c-2.1 1.4-4.8 2.3-7.9 2.3-6.3 0-11.6-3.7-13.5-9.1l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/>
            </svg>
            Continue with Google
        </a>
        <div class="my-6 flex items-center gap-3 text-xs font-semibold uppercase tracking-wide text-muted-ink">
            <span class="h-px flex-1 bg-line"></span> or <span class="h-px flex-1 bg-line"></span>
        </div>
        <?php endif; ?>

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
