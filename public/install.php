<?php
// public/install.php — standalone web installer for Patches&Motifs.
//
// Deliberately does NOT load includes/bootstrap.php: the app needs a working .env
// and database before it can boot. This script only depends on PDO + the files
// shipped in database/. Delete it (or leave it — storage/installed.lock blocks
// re-runs) once the site is live.
//
// SECURITY.md: CSRF on the form, escaped output, no secrets in HTML after install,
// restrictive .env permissions, lock file prevents post-install reconfiguration.
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

const INSTALL_ROOT = __DIR__ . '/..';
const INSTALL_LOCK = INSTALL_ROOT . '/storage/installed.lock';

session_name('PMIDINSTALL');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

if (!isset($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['install_csrf'];

/** Escape for HTML output. */
function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function install_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function install_detect_url(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Host header is untrusted; keep only a conservative charset.
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }
    return (install_is_https() ? 'https' : 'http') . '://' . $host;
}

/** Hard requirements. Returns [key => [label, ok, detail]]. */
function install_requirements(): array
{
    $req = [];
    $req['php'] = ['PHP 8.1 or newer', version_compare(PHP_VERSION, '8.1.0', '>='), 'Detected ' . PHP_VERSION];
    foreach (['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'curl', 'openssl', 'json'] as $ext) {
        $req['ext_' . $ext] = ['PHP extension: ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'Loaded' : 'Missing'];
    }
    $req['schema'] = ['database/schema.sql readable', is_readable(INSTALL_ROOT . '/database/schema.sql'), INSTALL_ROOT . '/database/schema.sql'];
    $req['migrations'] = ['database/migrations readable', is_dir(INSTALL_ROOT . '/database/migrations'), INSTALL_ROOT . '/database/migrations'];
    $req['env'] = [
        '.env writable',
        is_writable(INSTALL_ROOT . '/.env') || (!file_exists(INSTALL_ROOT . '/.env') && is_writable(INSTALL_ROOT)),
        '.env in project root',
    ];
    $req['storage'] = ['storage/ writable', is_writable(INSTALL_ROOT . '/storage'), 'Logs and caches live here'];
    $req['uploads'] = ['public/uploads/ writable', is_writable(INSTALL_ROOT . '/public/uploads'), 'Product and brand images'];
    return $req;
}

function install_requirements_ok(array $req): bool
{
    foreach ($req as $r) {
        if (!$r[1]) {
            return false;
        }
    }
    return true;
}

/**
 * Split a .sql file into statements, respecting quotes, backticks and comments.
 * Avoids relying on multi-statement support (which varies by PDO build).
 */
function install_sql_statements(string $sql): array
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $quote = '';       // ' " or `
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($ch === "\n") {
                $lineComment = false;
                $buf .= "\n";
            }
            continue;
        }
        if ($blockComment) {
            if ($ch === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }
        if ($quote !== '') {
            $buf .= $ch;
            if ($ch === '\\' && $quote !== '`' && $next !== '') {
                $buf .= $next;
                $i++;
                continue;
            }
            if ($ch === $quote) {
                // Doubled quote escapes it ('It''s').
                if ($next === $quote) {
                    $buf .= $next;
                    $i++;
                } else {
                    $quote = '';
                }
            }
            continue;
        }

        // Not inside a quote/comment.
        if ($ch === '-' && $next === '-') {
            $lineComment = true;
            $i++;
            continue;
        }
        if ($ch === '#') {
            $lineComment = true;
            continue;
        }
        if ($ch === '/' && $next === '*') {
            $blockComment = true;
            $i++;
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buf .= $ch;
            continue;
        }
        if ($ch === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') {
                $out[] = $stmt;
            }
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    $stmt = trim($buf);
    if ($stmt !== '') {
        $out[] = $stmt;
    }
    return $out;
}

/** Execute every statement in a .sql file. Throws on the first failure. */
function install_run_sql_file(PDO $pdo, string $path): int
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Could not read ' . basename($path));
    }
    $count = 0;
    foreach (install_sql_statements($sql) as $stmt) {
        $pdo->exec($stmt);
        $count++;
    }
    return $count;
}

/** Table names created by our schema/migrations (used for the reset option). */
function install_owned_tables(): array
{
    $files = array_merge(
        [INSTALL_ROOT . '/database/schema.sql'],
        glob(INSTALL_ROOT . '/database/migrations/*.sql') ?: []
    );
    $names = [];
    foreach ($files as $file) {
        $sql = (string)file_get_contents($file);
        if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i', $sql, $m)) {
            foreach ($m[1] as $name) {
                $names[$name] = true;
            }
        }
    }
    return array_keys($names);
}

/** Check whether the target database already contains any of our tables. */
function install_existing_tables(PDO $pdo, string $dbName, array $owned): array
{
    $st = $pdo->prepare(
        'SELECT table_name FROM information_schema.tables WHERE table_schema = :db'
    );
    $st->execute([':db' => $dbName]);
    $present = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
    return array_values(array_intersect($present, $owned));
}

function install_write_env(string $path, array $v): void
{
    $lines = [
        '# Patches&Motifs - generated by install.php on ' . gmdate('Y-m-d H:i:s') . ' UTC',
        'APP_ENV=' . $v['APP_ENV'],
        'APP_DEBUG=' . $v['APP_DEBUG'],
        'APP_URL=' . $v['APP_URL'],
        'APP_TIMEZONE=' . $v['APP_TIMEZONE'],
        'APP_KEY=' . $v['APP_KEY'],
        '',
        'DB_HOST=' . $v['DB_HOST'],
        'DB_PORT=' . $v['DB_PORT'],
        'DB_NAME=' . $v['DB_NAME'],
        'DB_USER=' . $v['DB_USER'],
        'DB_PASS=' . $v['DB_PASS'],
        '',
        'SESSION_SECURE_COOKIE=' . $v['SESSION_SECURE_COOKIE'],
        'SESSION_NAME=PMSID',
        '',
        '# Seed credentials (rotate before production)',
        'ADMIN_NAME=' . $v['ADMIN_NAME'],
        'ADMIN_EMAIL=' . $v['ADMIN_EMAIL'],
        'ADMIN_PASSWORD=' . $v['ADMIN_PASSWORD'],
        '',
    ];
    $ok = file_put_contents($path, implode("\n", $lines), LOCK_EX);
    if ($ok === false) {
        throw new RuntimeException('Could not write .env — check file permissions.');
    }
    @chmod($path, 0600);
}

// --------------------------------------------------------------------------
// Installed already?
// --------------------------------------------------------------------------
$alreadyInstalled = is_file(INSTALL_LOCK);

// --------------------------------------------------------------------------
// POST: run the installer
// --------------------------------------------------------------------------
$errors = [];
$success = null;
$req = install_requirements();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$alreadyInstalled) {
    if (!hash_equals($csrf, (string)($_POST['_csrf'] ?? ''))) {
        $errors[] = 'Security token expired. Reload the page and try again.';
    }

    $in = [
        'APP_URL'      => rtrim(trim((string)($_POST['app_url'] ?? '')), '/'),
        'APP_TIMEZONE' => trim((string)($_POST['app_timezone'] ?? 'Asia/Karachi')),
        'DB_HOST'      => trim((string)($_POST['db_host'] ?? '127.0.0.1')),
        'DB_PORT'      => trim((string)($_POST['db_port'] ?? '3306')),
        'DB_NAME'      => trim((string)($_POST['db_name'] ?? '')),
        'DB_USER'      => trim((string)($_POST['db_user'] ?? '')),
        'DB_PASS'      => (string)($_POST['db_pass'] ?? ''),
        'ADMIN_NAME'   => trim((string)($_POST['admin_name'] ?? 'Administrator')),
        'ADMIN_EMAIL'  => strtolower(trim((string)($_POST['admin_email'] ?? ''))),
        'ADMIN_PASSWORD' => (string)($_POST['admin_password'] ?? ''),
    ];
    $adminPass2 = (string)($_POST['admin_password_confirm'] ?? '');
    $allowReset = !empty($_POST['allow_reset']);

    if (!preg_match('~^https?://[A-Za-z0-9.\-]+(?::\d+)?(?:/[^\s]*)?$~', $in['APP_URL'])) {
        $errors[] = 'App URL must be a full http:// or https:// address.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $in['DB_NAME'])) {
        $errors[] = 'Database name may only contain letters, numbers and underscores (max 64).';
    }
    if (!preg_match('/^\d{1,5}$/', $in['DB_PORT']) || (int)$in['DB_PORT'] < 1 || (int)$in['DB_PORT'] > 65535) {
        $errors[] = 'Database port must be between 1 and 65535.';
    }
    if ($in['DB_HOST'] === '' || $in['DB_USER'] === '') {
        $errors[] = 'Database host and user are required.';
    }
    if ($in['APP_TIMEZONE'] === '' || !in_array($in['APP_TIMEZONE'], timezone_identifiers_list(), true)) {
        $errors[] = 'Choose a valid timezone.';
    }
    if (mb_strlen($in['ADMIN_NAME']) < 2) {
        $errors[] = 'Enter the administrator name.';
    }
    if (!filter_var($in['ADMIN_EMAIL'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid administrator email.';
    }
    if (strlen($in['ADMIN_PASSWORD']) < 12) {
        $errors[] = 'Administrator password must be at least 12 characters.';
    } elseif ($in['ADMIN_PASSWORD'] !== $adminPass2) {
        $errors[] = 'Administrator passwords do not match.';
    }
    if (!install_requirements_ok($req)) {
        $errors[] = 'One or more server requirements are not met.';
    }

    if (!$errors) {
        try {
            // 1) Connect to the server and create the database if needed.
            error_log('install: connecting to server');
            $dsnServer = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $in['DB_HOST'], $in['DB_PORT']);
            $pdo = new PDO($dsnServer, $in['DB_USER'], $in['DB_PASS'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec(
                'CREATE DATABASE IF NOT EXISTS `' . $in['DB_NAME'] . '` '
                . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );

            // 2) Reconnect to the target database.

            error_log('install: database ensured');
            $dsnDb = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $in['DB_HOST'],
                $in['DB_PORT'],
                $in['DB_NAME']
            );
            $pdo = new PDO($dsnDb, $in['DB_USER'], $in['DB_PASS'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // 3) Existing tables? Only continue when the operator opts into a reset.

            error_log('install: checking existing tables');
            $owned = install_owned_tables();
            $existing = install_existing_tables($pdo, $in['DB_NAME'], $owned);
            if ($existing) {
                if (!$allowReset) {
                    throw new RuntimeException(
                        'The database already contains Patches&Motifs tables (' . implode(', ', array_slice($existing, 0, 6))
                        . (count($existing) > 6 ? '…' : '') . '). '
                        . 'Use an empty database or tick "Reset" to drop and recreate them.'
                    );
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                foreach ($existing as $table) {
                    if (preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                        $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
                    }
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }

            // 4) Import the core schema, then every migration in filename order.

            error_log('install: importing schema');
            //    No wrapping transaction: MySQL DDL (CREATE/ALTER) commits implicitly.
            $files = [INSTALL_ROOT . '/database/schema.sql'];
            $migrations = glob(INSTALL_ROOT . '/database/migrations/*.sql') ?: [];
            sort($migrations, SORT_STRING);
            $files = array_merge($files, $migrations);

            foreach ($files as $file) {
                install_run_sql_file($pdo, $file);
            }

            // 5) Roles, permissions, Super Admin and store defaults.

            error_log('install: schema imported; seeding');
            require_once INSTALL_ROOT . '/database/seed_core.php';
            $pdo->beginTransaction();
            try {
                seed_database($pdo, $in['ADMIN_EMAIL'], $in['ADMIN_PASSWORD'], $in['ADMIN_NAME']);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            // 6) Runtime directories.

            error_log('install: seeded; creating directories');
            foreach (['storage/logs', 'storage/cache', 'storage/backups', 'storage/private', 'public/uploads'] as $dir) {
                $path = INSTALL_ROOT . '/' . $dir;
                if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
                    throw new RuntimeException('Could not create ' . $dir . ' — check folder permissions.');
                }
            }

            // 7) Write .env (back up any existing file first).

            error_log('install: writing env');
            $envPath = INSTALL_ROOT . '/.env';
            if (is_file($envPath)) {
                @copy($envPath, $envPath . '.bak-' . gmdate('Ymd-His'));
            }
            install_write_env($envPath, [
                'APP_ENV'      => 'production',
                'APP_DEBUG'    => 'false',
                'APP_URL'      => $in['APP_URL'],
                'APP_TIMEZONE' => $in['APP_TIMEZONE'],
                'APP_KEY'      => bin2hex(random_bytes(32)),
                'DB_HOST'      => $in['DB_HOST'],
                'DB_PORT'      => $in['DB_PORT'],
                'DB_NAME'      => $in['DB_NAME'],
                'DB_USER'      => $in['DB_USER'],
                'DB_PASS'      => $in['DB_PASS'],
                'SESSION_SECURE_COOKIE' => str_starts_with($in['APP_URL'], 'https://') ? '1' : '0',
                'ADMIN_NAME'   => $in['ADMIN_NAME'],
                'ADMIN_EMAIL'  => $in['ADMIN_EMAIL'],
                'ADMIN_PASSWORD' => $in['ADMIN_PASSWORD'],
            ]);

            // 8) Lock file stops the installer from running again.

            error_log('install: writing lock');
            $lockData = [
                'installed_at' => gmdate('c'),
                'app_url'      => $in['APP_URL'],
                'php'          => PHP_VERSION,
                'admin_email'  => $in['ADMIN_EMAIL'],
            ];
            if (file_put_contents(INSTALL_LOCK, json_encode($lockData, JSON_PRETTY_PRINT) . "\n", LOCK_EX) === false) {
                throw new RuntimeException('Could not create storage/installed.lock — check permissions.');
            }

            $success = [
                'app_url'     => $in['APP_URL'],
                'admin_email' => $in['ADMIN_EMAIL'],
                'db_name'     => $in['DB_NAME'],
            ];
            // New token so a stale form cannot be replayed.
            $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
            $csrf = (string)$_SESSION['install_csrf'];
        } catch (PDOException $e) {
            error_log('install.php DB error: ' . $e->getMessage());
            $errors[] = 'Database error: ' . $e->getMessage();
        } catch (Throwable $e) {
            error_log('install.php error: ' . $e->getMessage());
            $errors[] = $e->getMessage();
        }
    }
}

$post = static function (string $key, string $default = '') {
    return (string)($_POST[$key] ?? $default);
};
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Install — Patches&amp;Motifs</title>
<style>
    :root {
        --canvas: #F7F7F3; --surface: #FFFFFF; --ink: #111210; --muted: #666A63;
        --line: #E3E4DE; --accent: #30351B; --accent-bright: #C7D84A;
        --success: #26734A; --danger: #B33C36; --warning: #A66B16;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; background: var(--canvas); color: var(--ink);
        font: 15px/1.55 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        display: flex; flex-direction: column;
    }
    header { border-bottom: 1px solid var(--line); background: var(--surface); }
    .wrap { width: 100%; max-width: 880px; margin: 0 auto; padding: 0 20px; }
    .brand { display: flex; align-items: center; gap: 12px; height: 64px; }
    .mark { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 999px; background: var(--ink); color: #fff; font-weight: 700; font-size: 12px; letter-spacing: .5px; }
    .brand h1 { font-size: 17px; margin: 0; letter-spacing: -0.01em; }
    .brand small { color: var(--muted); font-size: 12px; }
    main { flex: 1; padding: 32px 0 56px; }
    .card { background: var(--surface); border: 1px solid var(--line); border-radius: 20px; padding: 24px; }
    .card + .card { margin-top: 20px; }
    h2 { margin: 0 0 8px; font-size: 18px; letter-spacing: -0.01em; }
    h3 { margin: 0 0 4px; font-size: 15px; }
    p.lead { margin: 0; color: var(--muted); font-size: 14px; }
    .req { list-style: none; margin: 14px 0 0; padding: 0; display: grid; gap: 6px; }
    .req li { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; }
    .pill { flex: 0 0 auto; display: inline-block; min-width: 52px; text-align: center; border-radius: 999px; padding: 1px 8px; font-size: 11px; font-weight: 700; }
    .ok { background: #E4F1EA; color: var(--success); }
    .bad { background: #F7E4E3; color: var(--danger); }
    .detail { color: var(--muted); }
    .grid { display: grid; gap: 14px; }
    @media (min-width: 680px) { .grid.two { grid-template-columns: 1fr 1fr; } }
    label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
    input[type=text], input[type=url], input[type=email], input[type=password], input[type=number], select {
        width: 100%; height: 44px; border: 1px solid var(--line); border-radius: 12px;
        background: #FCFCFA; padding: 0 12px; font-size: 14px; color: var(--ink);
    }
    input:focus, select:focus { outline: 2px solid var(--accent-bright); outline-offset: 1px; border-color: var(--accent); }
    .hint { margin: 4px 0 0; font-size: 12px; color: var(--muted); }
    .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; font-size: 13px; }
    .check input { margin-top: 3px; }
    button {
        display: inline-flex; align-items: center; justify-content: center; height: 46px;
        padding: 0 24px; border: 0; border-radius: 999px; background: var(--ink); color: #fff;
        font-size: 14px; font-weight: 600; cursor: pointer;
    }
    button:hover { background: #000; }
    button[disabled] { opacity: .45; cursor: not-allowed; }
    .alert { border-radius: 12px; padding: 12px 14px; font-size: 13px; margin-bottom: 14px; border: 1px solid; }
    .alert.error { border-color: #E8BEBB; background: #FBF0EF; color: var(--danger); }
    .alert.note { border-color: #E6D9B8; background: #FBF6E8; color: var(--warning); }
    .alert.good { border-color: #BFDCCB; background: #EEF7F2; color: var(--success); }
    ul.plain { margin: 8px 0 0; padding-left: 18px; font-size: 13px; }
    code { background: #EFEFE9; border-radius: 6px; padding: 1px 6px; font-size: 12px; }
    a.btn { display: inline-flex; align-items: center; height: 44px; padding: 0 20px; border-radius: 999px; background: var(--accent); color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; }
    a.link { color: var(--accent); font-weight: 600; }
    footer { border-top: 1px solid var(--line); color: var(--muted); font-size: 12px; padding: 18px 0; }
    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
</style>
</head>
<body>
<header>
    <div class="wrap brand">
        <span class="mark">P&amp;M</span>
        <div>
            <h1>Patches&amp;Motifs</h1>
            <small>Setup installer</small>
        </div>
    </div>
</header>

<main class="wrap">
<?php if ($alreadyInstalled): ?>
    <div class="card">
        <h2>Already installed</h2>
        <p class="lead">This copy has been installed. The installer is locked to prevent accidental reconfiguration.</p>
        <p class="hint" style="margin-top:12px">To reinstall, remove <code>storage/installed.lock</code> on the server first.</p>
        <div class="actions">
            <a class="btn" href="/">Open storefront</a>
            <a class="btn" href="/admin/login" style="background:var(--surface);color:var(--ink);border:1px solid var(--line)">Admin sign in</a>
        </div>
    </div>
<?php elseif ($success): ?>
    <div class="card">
        <div class="alert good">Installation complete. Your store is ready.</div>
        <h2>What was set up</h2>
        <ul class="plain">
            <li>Database <code><?= h($success['db_name']) ?></code> created or updated.</li>
            <li>All tables, migrations and default settings imported.</li>
            <li>Super Admin account created for <code><?= h($success['admin_email']) ?></code>.</li>
            <li><code>.env</code> written and <code>storage/installed.lock</code> created.</li>
        </ul>
        <div class="actions">
            <a class="btn" href="/">Open storefront</a>
            <a class="btn" href="/admin/login" style="background:var(--surface);color:var(--ink);border:1px solid var(--line)">Admin sign in</a>
        </div>
        <p class="hint" style="margin-top:16px">For safety, delete <code>public/install.php</code> once you have verified the site. Keep your admin password in a password manager.</p>
    </div>
<?php else: ?>
    <div class="card">
        <h2>1. Server requirements</h2>
        <p class="lead">Everything below must pass before installation can continue.</p>
        <ul class="req">
            <?php foreach ($req as [$label, $ok, $detail]): ?>
                <li>
                    <span class="pill <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'PASS' : 'FAIL' ?></span>
                    <span><?= h($label) ?> <span class="detail">— <?= h($detail) ?></span></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!install_requirements_ok($req)): ?>
            <div class="alert note" style="margin-top:14px">Fix the failed requirements, then reload this page.</div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>2. Store and database</h2>
        <p class="lead">The installer creates the database if it does not exist. Use an empty database name for a fresh install.</p>

        <?php if ($errors): ?>
            <div class="alert error" style="margin-top:14px">
                <?php foreach ($errors as $err): ?>
                    <div><?= h($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="grid" style="margin-top:18px" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

            <div>
                <label for="app_url">Store URL</label>
                <input id="app_url" name="app_url" type="url" required value="<?= h($post('app_url', install_detect_url())) ?>">
                <p class="hint">Use the final public address, e.g. <code>https://yourdomain.com</code>. Google sign-in redirects are built from this.</p>
            </div>

            <div class="grid two">
                <div>
                    <label for="db_host">Database host</label>
                    <input id="db_host" name="db_host" type="text" required value="<?= h($post('db_host', '127.0.0.1')) ?>">
                </div>
                <div>
                    <label for="db_port">Port</label>
                    <input id="db_port" name="db_port" type="number" min="1" max="65535" required value="<?= h($post('db_port', '3306')) ?>">
                </div>
            </div>

            <div class="grid two">
                <div>
                    <label for="db_name">Database name</label>
                    <input id="db_name" name="db_name" type="text" required maxlength="64" value="<?= h($post('db_name', 'patches_motifs')) ?>">
                </div>
                <div>
                    <label for="db_user">Database user</label>
                    <input id="db_user" name="db_user" type="text" required maxlength="120" value="<?= h($post('db_user', 'root')) ?>">
                </div>
            </div>

            <div>
                <label for="db_pass">Database password</label>
                <input id="db_pass" name="db_pass" type="password" value="<?= h($post('db_pass')) ?>">
                <p class="hint">Leave blank for a default local MySQL root with no password.</p>
            </div>

            <div>
                <label for="app_timezone">Timezone</label>
                <select id="app_timezone" name="app_timezone">
                    <?php foreach (['Asia/Karachi', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Kolkata', 'Europe/London', 'America/New_York', 'UTC'] as $tz): ?>
                        <option value="<?= h($tz) ?>" <?= $post('app_timezone', 'Asia/Karachi') === $tz ? 'selected' : '' ?>><?= h($tz) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label class="check" style="border:1px solid var(--line);border-radius:12px;padding:12px">
                <input type="checkbox" name="allow_reset" value="1">
                <span><strong>Reset existing Patches&amp;Motifs tables</strong><br>
                <span class="hint">Only needed when retrying a failed install. Drops the store's own tables; unrelated tables are left untouched.</span></span>
            </label>

            <h2 style="margin-top:6px">3. Administrator account</h2>
            <div class="grid two">
                <div>
                    <label for="admin_name">Name</label>
                    <input id="admin_name" name="admin_name" type="text" required maxlength="120" value="<?= h($post('admin_name', 'Administrator')) ?>">
                </div>
                <div>
                    <label for="admin_email">Email</label>
                    <input id="admin_email" name="admin_email" type="email" required maxlength="254" value="<?= h($post('admin_email')) ?>">
                </div>
            </div>
            <div class="grid two">
                <div>
                    <label for="admin_password">Password</label>
                    <input id="admin_password" name="admin_password" type="password" required minlength="12" maxlength="128" autocomplete="new-password">
                    <p class="hint">At least 12 characters. A long passphrase works best.</p>
                </div>
                <div>
                    <label for="admin_password_confirm">Confirm password</label>
                    <input id="admin_password_confirm" name="admin_password_confirm" type="password" required minlength="12" maxlength="128" autocomplete="new-password">
                </div>
            </div>

            <div class="actions">
                <button type="submit" <?= install_requirements_ok($req) ? '' : 'disabled' ?>>Install Patches&amp;Motifs</button>
            </div>
        </form>
    </div>
<?php endif; ?>
</main>

<footer>
    <div class="wrap">Patches&amp;Motifs installer · runs once · remove <code>install.php</code> when finished.</div>
</footer>
</body>
</html>
