<?php
// public/install.php — standalone web installer and reconfigurator for Patches&Motifs.
//
// Deliberately does NOT load includes/bootstrap.php: the app needs a working .env
// and database before it can boot. This script only depends on PDO + the files
// shipped in database/.
//
// Two modes:
//   * Fresh install  — no storage/installed.lock. Creates the database, imports
//                      the schema, seeds the Super Admin and writes .env.
//   * Reconfigure    — lock present. Requires a current Super Admin password,
//                      then updates the URL, database credentials, store identity,
//                      admin account and .env in place (no data loss unless the
//                      destructive reset is explicitly ticked).
//
// SECURITY.md §39.1 covers the controls: CSRF, escaped output, no secrets echoed,
// a Super Admin gate for reconfiguration, restrictive .env permissions, and a lock
// file. Delete this file once the site is live.
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

const INSTALL_ROOT = __DIR__ . '/..';
const INSTALL_LOCK = INSTALL_ROOT . '/storage/installed.lock';
const INSTALL_ENV  = INSTALL_ROOT . '/.env';
const INSTALL_LOG  = INSTALL_ROOT . '/storage/logs/install.log';
const INSTALL_AUTH_MAX_FAILS = 5;
const INSTALL_AUTH_LOCK_SECONDS = 900;

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
header('Cache-Control: no-store');

if (!isset($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['install_csrf'];

/** Escape for HTML output. */
function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Append a line to storage/logs/install.log (never includes secrets). */
function install_log(string $level, string $message): void
{
    $line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . strtoupper($level) . ' ' . $message . "\n";
    $dir = dirname(INSTALL_LOG);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (@file_put_contents(INSTALL_LOG, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log(rtrim($line));
    }
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
    $req['schema'] = ['database/schema.sql readable', is_readable(INSTALL_ROOT . '/database/schema.sql'), 'database/schema.sql'];
    $req['migrations'] = ['database/migrations readable', is_dir(INSTALL_ROOT . '/database/migrations'), 'database/migrations'];
    $req['env'] = [
        '.env writable',
        is_writable(INSTALL_ENV) || (!file_exists(INSTALL_ENV) && is_writable(INSTALL_ROOT)),
        '.env in project root',
    ];
    $req['storage'] = ['storage/ writable', is_writable(INSTALL_ROOT . '/storage'), 'Logs, caches and the install lock live here'];
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

/** Parse a .env file without loading the application bootstrap. */
function install_parse_env(string $path): array
{
    $out = [];
    if (!is_readable($path)) {
        return $out;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        if ($key === '') {
            continue;
        }
        $value = trim(substr($line, $pos + 1));
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' || $first === "'") && $first === $last) {
                $value = substr($value, 1, -1);
            }
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * Write .env, keeping any keys the installer does not manage.
 * Values must not contain line breaks (validated before we get here).
 */
function install_write_env(string $path, array $v): void
{
    $managed = array_keys($v);
    $preserved = [];
    foreach (install_parse_env($path) as $key => $value) {
        if (!in_array($key, $managed, true)) {
            $preserved[$key] = $value;
        }
    }

    $lines = [
        '# Patches&Motifs - generated by install.php on ' . gmdate('Y-m-d H:i:s') . ' UTC',
        '# Secrets. Never commit this file. Reconfigure via /install.php.',
        '',
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
        'SESSION_NAME=' . ($v['SESSION_NAME'] ?? 'PMSID'),
        '',
        '# Seed credentials (used by database/seed.php; rotate before production)',
        'ADMIN_NAME=' . $v['ADMIN_NAME'],
        'ADMIN_EMAIL=' . $v['ADMIN_EMAIL'],
        'ADMIN_PASSWORD=' . $v['ADMIN_PASSWORD'],
        '',
    ];

    if ($preserved) {
        $lines[] = '# Preserved settings (not managed by the installer)';
        foreach ($preserved as $key => $value) {
            $lines[] = $key . '=' . $value;
        }
        $lines[] = '';
    }

    $ok = file_put_contents($path, implode("\n", $lines), LOCK_EX);
    if ($ok === false) {
        throw new RuntimeException('Could not write .env — check file permissions.');
    }
    @chmod($path, 0600);
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

/** Build a PDO connection; optionally without a database for CREATE DATABASE. */
function install_pdo(string $host, string $port, string $db, string $user, string $pass, bool $withDb): PDO
{
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
    if ($withDb) {
        $dsn .= ';dbname=' . $db;
    }
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 8,
    ]);
}

/** The oldest active Super Admin, or null. */
function install_primary_super_admin(PDO $pdo): ?array
{
    $st = $pdo->query(
        "SELECT u.id, u.name, u.email
           FROM users u
           JOIN user_roles ur ON ur.user_id = u.id
           JOIN roles r ON r.id = ur.role_id
          WHERE r.name = 'super_admin' AND u.status = 'active' AND u.deleted_at IS NULL
          ORDER BY u.id ASC
          LIMIT 1"
    );
    $row = $st->fetch();
    return $row ?: null;
}

/** Verify a current Super Admin by email + password against the existing .env DB. */
function install_verify_current_admin(array $env, string $email, string $password): bool
{
    $name = (string)($env['DB_NAME'] ?? '');
    $user = (string)($env['DB_USER'] ?? '');
    if ($name === '' || $user === '') {
        throw new RuntimeException('The current .env has no database name or user.');
    }
    $pdo = install_pdo(
        (string)($env['DB_HOST'] ?? '127.0.0.1'),
        (string)($env['DB_PORT'] ?? '3306'),
        $name,
        $user,
        (string)($env['DB_PASS'] ?? ''),
        true
    );
    $st = $pdo->prepare(
        "SELECT u.password_hash
           FROM users u
           JOIN user_roles ur ON ur.user_id = u.id
           JOIN roles r ON r.id = ur.role_id
          WHERE u.email = :email AND r.name = 'super_admin'
            AND u.status = 'active' AND u.deleted_at IS NULL
          LIMIT 1"
    );
    $st->execute([':email' => $email]);
    $hash = $st->fetchColumn();
    return is_string($hash) && $hash !== '' && password_verify($password, $hash);
}

/** Read the values the reconfiguration screen shows (never the DB password). */
function install_snapshot(array $env): array
{
    $snap = [
        'app_url'       => (string)($env['APP_URL'] ?? ''),
        'app_timezone'  => (string)($env['APP_TIMEZONE'] ?? 'Asia/Karachi'),
        'db_host'       => (string)($env['DB_HOST'] ?? ''),
        'db_port'       => (string)($env['DB_PORT'] ?? ''),
        'db_name'       => (string)($env['DB_NAME'] ?? ''),
        'db_user'       => (string)($env['DB_USER'] ?? ''),
        'admin_name'    => (string)($env['ADMIN_NAME'] ?? ''),
        'admin_email'   => (string)($env['ADMIN_EMAIL'] ?? ''),
        'store_name'    => '',
        'store_email'   => '',
        'store_phone'   => '',
        'store_address' => '',
        'connect_error' => '',
    ];
    try {
        $pdo = install_pdo(
            $snap['db_host'] !== '' ? $snap['db_host'] : '127.0.0.1',
            $snap['db_port'] !== '' ? $snap['db_port'] : '3306',
            $snap['db_name'],
            $snap['db_user'],
            (string)($env['DB_PASS'] ?? ''),
            true
        );
        $st = $pdo->query(
            "SELECT setting_key, setting_value FROM store_settings
              WHERE setting_key IN ('store_name','store_email','store_phone','store_address')"
        );
        foreach ($st->fetchAll() as $row) {
            $key = (string)$row['setting_key'];
            if (array_key_exists($key, $snap)) {
                $snap[$key] = (string)$row['setting_value'];
            }
        }
        $admin = install_primary_super_admin($pdo);
        if ($admin) {
            $snap['admin_name']  = (string)$admin['name'];
            $snap['admin_email'] = (string)$admin['email'];
        }
    } catch (Throwable $e) {
        $snap['connect_error'] = 'Could not read the current database with the existing .env credentials.';
        install_log('warning', 'snapshot failed: ' . $e->getMessage());
    }
    return $snap;
}

/** Write a JSON summary that is safe to display (no passwords). */
function install_lock_write(array $existing, array $data): void
{
    $data = array_merge($existing, $data);
    $data['updated_at'] = gmdate('c');
    if (file_put_contents(INSTALL_LOCK, json_encode($data, JSON_PRETTY_PRINT) . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not create storage/installed.lock — check permissions.');
    }
}

// --------------------------------------------------------------------------
// Current state
// --------------------------------------------------------------------------
$alreadyInstalled = is_file(INSTALL_LOCK);
$current = install_parse_env(INSTALL_ENV);
$lockData = [];
if ($alreadyInstalled) {
    $decoded = json_decode((string)@file_get_contents(INSTALL_LOCK), true);
    if (is_array($decoded)) {
        $lockData = $decoded;
    }
}
$snapshot = $current !== [] ? install_snapshot($current) : [
    'app_url' => '', 'app_timezone' => 'Asia/Karachi', 'db_host' => '', 'db_port' => '',
    'db_name' => '', 'db_user' => '', 'admin_name' => '', 'admin_email' => '',
    'store_name' => '', 'store_email' => '', 'store_phone' => '', 'store_address' => '',
    'connect_error' => '',
];

// --------------------------------------------------------------------------
// POST: install or reconfigure
// --------------------------------------------------------------------------
$errors = [];
$success = null;
$req = install_requirements();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['_csrf'] ?? ''))) {
        $errors[] = 'Security token expired. Reload the page and try again.';
    }

    $in = [
        'APP_URL'        => rtrim(trim((string)($_POST['app_url'] ?? '')), '/'),
        'APP_TIMEZONE'   => trim((string)($_POST['app_timezone'] ?? 'Asia/Karachi')),
        'STORE_NAME'     => trim((string)($_POST['store_name'] ?? '')),
        'STORE_EMAIL'    => trim((string)($_POST['store_email'] ?? '')),
        'STORE_PHONE'    => trim((string)($_POST['store_phone'] ?? '')),
        'STORE_ADDRESS'  => trim((string)($_POST['store_address'] ?? '')),
        'DB_HOST'        => trim((string)($_POST['db_host'] ?? '127.0.0.1')),
        'DB_PORT'        => trim((string)($_POST['db_port'] ?? '3306')),
        'DB_NAME'        => trim((string)($_POST['db_name'] ?? '')),
        'DB_USER'        => trim((string)($_POST['db_user'] ?? '')),
        'DB_PASS'        => (string)($_POST['db_pass'] ?? ''),
        'ADMIN_NAME'     => trim((string)($_POST['admin_name'] ?? 'Administrator')),
        'ADMIN_EMAIL'    => strtolower(trim((string)($_POST['admin_email'] ?? ''))),
        'ADMIN_PASSWORD' => (string)($_POST['admin_password'] ?? ''),
    ];
    $adminPass2  = (string)($_POST['admin_password_confirm'] ?? '');
    $authEmail   = strtolower(trim((string)($_POST['auth_email'] ?? '')));
    $authPass    = (string)($_POST['auth_password'] ?? '');
    $allowReset    = !empty($_POST['allow_reset']);
    $allowExisting = !empty($_POST['allow_existing']);
    $replaceAdmin  = !empty($_POST['admin_replace']);
    $confirmReconf = !empty($_POST['confirm_reconfigure']);

    foreach ($in as $value) {
        if (preg_match('/[\r\n\0]/', $value)) {
            $errors[] = 'Fields cannot contain line breaks.';
            break;
        }
    }
    // .env values are trimmed when read back, so surrounding whitespace in a
    // secret would silently change it at the next seed.
    foreach (['DB_PASS', 'ADMIN_PASSWORD'] as $secretKey) {
        if ($in[$secretKey] !== trim($in[$secretKey])) {
            $errors[] = 'Passwords cannot start or end with spaces.';
        }
    }

    if (!preg_match('~^https?://(?:[A-Za-z0-9.\-]+|\[[0-9A-Fa-f:.]+\])(?::\d{1,5})?$~', $in['APP_URL'])) {
        $errors[] = 'App URL must be a full http:// or https:// origin (for example https://yourdomain.com), without a path.';
    }
    if ($in['APP_TIMEZONE'] === '' || !in_array($in['APP_TIMEZONE'], timezone_identifiers_list(), true)) {
        $errors[] = 'Choose a valid timezone.';
    }
    if (mb_strlen($in['STORE_NAME']) < 2 || mb_strlen($in['STORE_NAME']) > 120) {
        $errors[] = 'Store name must be between 2 and 120 characters.';
    }
    if ($in['STORE_EMAIL'] !== '' && !filter_var($in['STORE_EMAIL'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid store contact email, or leave it blank.';
    }
    if (mb_strlen($in['STORE_PHONE']) > 32) {
        $errors[] = 'Store phone must be 32 characters or fewer.';
    }
    if (mb_strlen($in['STORE_ADDRESS']) > 500) {
        $errors[] = 'Store address must be 500 characters or fewer.';
    }
    if (!preg_match('/^[A-Za-z0-9._\-:\[\]]+$/', $in['DB_HOST'])) {
        $errors[] = 'Database host may only contain letters, numbers, dots, dashes, colons and brackets.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $in['DB_NAME'])) {
        $errors[] = 'Database name may only contain letters, numbers and underscores (max 64).';
    }
    if (!preg_match('/^\d{1,5}$/', $in['DB_PORT']) || (int)$in['DB_PORT'] < 1 || (int)$in['DB_PORT'] > 65535) {
        $errors[] = 'Database port must be between 1 and 65535.';
    }
    if ($in['DB_USER'] === '') {
        $errors[] = 'Database user is required.';
    }
    if (mb_strlen($in['ADMIN_NAME']) < 2 || mb_strlen($in['ADMIN_NAME']) > 120) {
        $errors[] = 'Enter the administrator name (2–120 characters).';
    }
    if (!filter_var($in['ADMIN_EMAIL'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid administrator email.';
    }
    if (!$alreadyInstalled) {
        if (strlen($in['ADMIN_PASSWORD']) < 12 || strlen($in['ADMIN_PASSWORD']) > 128) {
            $errors[] = 'Administrator password must be between 12 and 128 characters.';
        } elseif ($in['ADMIN_PASSWORD'] !== $adminPass2) {
            $errors[] = 'Administrator passwords do not match.';
        }
    } elseif ($in['ADMIN_PASSWORD'] !== '') {
        if (strlen($in['ADMIN_PASSWORD']) < 12 || strlen($in['ADMIN_PASSWORD']) > 128) {
            $errors[] = 'The new administrator password must be between 12 and 128 characters.';
        } elseif ($in['ADMIN_PASSWORD'] !== $adminPass2) {
            $errors[] = 'The new administrator passwords do not match.';
        }
    }
    if ($allowReset && $allowExisting) {
        $errors[] = 'Choose either “keep existing data” or “reset”, not both.';
    }
    if (!$alreadyInstalled && !install_requirements_ok($req)) {
        $errors[] = 'One or more server requirements are not met.';
    }

    // Reconfiguration is gated by a current Super Admin password. If the old
    // database is unreachable the documented recovery is to delete the lock.
    if ($alreadyInstalled) {
        if (!$confirmReconf) {
            $errors[] = 'Tick the confirmation box to reconfigure the live site.';
        }
        if ($authEmail === '' || $authPass === '') {
            $errors[] = 'Enter a current Super Admin email and password to authorize reconfiguration.';
        }
        $lockedUntil = (int)($_SESSION['install_auth_until'] ?? 0);
        if ($lockedUntil > time()) {
            $errors[] = 'Too many failed authorization attempts. Wait a few minutes and try again.';
        } elseif (!$errors) {
            try {
                $ok = install_verify_current_admin($current, $authEmail, $authPass);
            } catch (Throwable $e) {
                install_log('warning', 'authorization check failed: ' . $e->getMessage());
                $errors[] = 'Could not reach the current database to verify your account. '
                    . 'If the database credentials changed, delete storage/installed.lock on the server and reload this page.';
                $ok = false;
            }
            if (!$ok && !$errors) {
                $fails = (int)($_SESSION['install_auth_fails'] ?? 0) + 1;
                $_SESSION['install_auth_fails'] = $fails;
                if ($fails >= INSTALL_AUTH_MAX_FAILS) {
                    $_SESSION['install_auth_until'] = time() + INSTALL_AUTH_LOCK_SECONDS;
                    $_SESSION['install_auth_fails'] = 0;
                }
                $errors[] = 'Those Super Admin credentials were not accepted.';
                install_log('warning', 'reconfiguration authorization rejected for ' . $authEmail);
            } elseif ($ok) {
                $_SESSION['install_auth_fails'] = 0;
                unset($_SESSION['install_auth_until']);
            }
        }
    }

    if (!$errors) {
        try {
            $dbPass = $in['DB_PASS'];
            // Blank keeps the current password on reconfiguration so it is never
            // echoed back into the HTML form.
            if ($alreadyInstalled && $dbPass === '') {
                $dbPass = (string)($current['DB_PASS'] ?? '');
            }

            // 1) Connect to the target database. Only fall back to CREATE DATABASE
            //    when MySQL reports "unknown database" (1049), so an app user with
            //    no CREATE privilege can still reconfigure an existing database.
            install_log('info', 'connecting to MySQL at ' . $in['DB_HOST'] . ':' . $in['DB_PORT']);
            try {
                $pdo = install_pdo($in['DB_HOST'], $in['DB_PORT'], $in['DB_NAME'], $in['DB_USER'], $dbPass, true);
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) !== 1049) {
                    throw $e;
                }
                install_log('info', 'database missing; attempting CREATE DATABASE');
                $server = install_pdo($in['DB_HOST'], $in['DB_PORT'], '', $in['DB_USER'], $dbPass, false);
                $server->exec(
                    'CREATE DATABASE IF NOT EXISTS `' . $in['DB_NAME'] . '` '
                    . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                );
            }

            // 2) Reconnect to the target database.
            $pdo = install_pdo($in['DB_HOST'], $in['DB_PORT'], $in['DB_NAME'], $in['DB_USER'], $dbPass, true);

            // 3) Existing tables? Reset is destructive and must be explicit. On a
            //    reconfiguration the default is to keep the data in place.
            $owned = install_owned_tables();
            $existing = install_existing_tables($pdo, $in['DB_NAME'], $owned);
            $adopted = false;
            if ($existing) {
                if ($allowReset) {
                    install_log('warning', 'resetting ' . count($existing) . ' store tables');
                    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                    foreach ($existing as $table) {
                        if (preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
                        }
                    }
                    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                    $existing = [];
                } elseif ($allowExisting || $alreadyInstalled) {
                    $adopted = true;
                    install_log('info', 'keeping ' . count($existing) . ' existing store tables');
                } else {
                    throw new RuntimeException(
                        'The database already contains Patches&Motifs tables (' . implode(', ', array_slice($existing, 0, 6))
                        . (count($existing) > 6 ? '…' : '') . '). '
                        . 'Tick “Keep existing data” to update the configuration without dropping anything, '
                        . 'or “Reset” to drop and recreate the store tables.'
                    );
                }
            }

            // 4) Empty database: import the core schema, then every migration.
            //    Never re-run schema/migrations over existing tables (DDL is not idempotent).
            if (!$existing) {
                install_log('info', 'importing schema and migrations');
                $files = [INSTALL_ROOT . '/database/schema.sql'];
                $migrations = glob(INSTALL_ROOT . '/database/migrations/*.sql') ?: [];
                sort($migrations, SORT_STRING);
                foreach (array_merge($files, $migrations) as $file) {
                    install_run_sql_file($pdo, $file);
                }
            }

            // 5) Seed roles/permissions, store identity and the admin account.
            require_once INSTALL_ROOT . '/database/seed_core.php';
            $store = [
                'store_name'    => $in['STORE_NAME'],
                'store_email'   => $in['STORE_EMAIL'],
                'store_phone'   => $in['STORE_PHONE'],
                'store_address' => $in['STORE_ADDRESS'],
                'timezone'      => $in['APP_TIMEZONE'],
            ];
            $passwordForSeed = $in['ADMIN_PASSWORD'] !== '' ? $in['ADMIN_PASSWORD'] : null;

            $pdo->beginTransaction();
            try {
                $primary = install_primary_super_admin($pdo);
                if ($primary && $replaceAdmin && strcasecmp((string)$primary['email'], $in['ADMIN_EMAIL']) !== 0) {
                    if ($passwordForSeed === null) {
                        throw new RuntimeException('Enter a new password when changing the Super Admin email address.');
                    }
                    $dup = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                    $dup->execute([':email' => $in['ADMIN_EMAIL']]);
                    $dupId = $dup->fetchColumn();
                    if ($dupId !== false && (int)$dupId !== (int)$primary['id']) {
                        throw new RuntimeException('That email already belongs to another account. Choose a different email or remove the other account first.');
                    }
                    $up = $pdo->prepare(
                        'UPDATE users
                            SET name = :name, email = :email, password_hash = :hash,
                                status = :status, deleted_at = NULL
                          WHERE id = :id'
                    );
                    $up->execute([
                        ':name'   => $in['ADMIN_NAME'],
                        ':email'  => $in['ADMIN_EMAIL'],
                        ':hash'   => password_hash((string)$passwordForSeed, PASSWORD_BCRYPT, ['cost' => 12]),
                        ':status' => 'active',
                        ':id'     => (int)$primary['id'],
                    ]);
                }

                seed_database($pdo, $in['ADMIN_EMAIL'], $passwordForSeed, $in['ADMIN_NAME'], $store);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            // 6) Record the configuration change in the in-app activity log.
            try {
                $audit = $pdo->prepare(
                    'INSERT INTO activity_logs (user_id, action, entity, entity_id, before_json, after_json, ip, user_agent, created_at)
                     VALUES (NULL, :action, :entity, NULL, NULL, :after, :ip, :ua, NOW())'
                );
                $audit->execute([
                    ':action' => $alreadyInstalled ? 'installer_reconfigured' : 'installer_installed',
                    ':entity' => 'installer',
                    ':after'  => json_encode([
                        'app_url' => $in['APP_URL'],
                        'db_name' => $in['DB_NAME'],
                        'mode'    => $alreadyInstalled ? 'reconfigure' : ($adopted ? 'adopt' : 'install'),
                    ], JSON_UNESCAPED_SLASHES),
                    ':ip'     => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                    ':ua'     => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                ]);
            } catch (Throwable $e) {
                install_log('warning', 'activity log write skipped: ' . $e->getMessage());
            }

            // 7) Runtime directories.
            foreach (['storage/logs', 'storage/cache', 'storage/backups', 'storage/private', 'public/uploads'] as $dir) {
                $path = INSTALL_ROOT . '/' . $dir;
                if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
                    throw new RuntimeException('Could not create ' . $dir . ' — check folder permissions.');
                }
            }

            // 8) Write .env (back up any existing file first). APP_KEY is preserved
            //    so existing encrypted data and sessions stay valid.
            install_log('info', 'writing .env');
            if (is_file(INSTALL_ENV)) {
                @copy(INSTALL_ENV, INSTALL_ENV . '.bak-' . gmdate('Ymd-His'));
            }
            $appKey = trim((string)($current['APP_KEY'] ?? ''));
            if ($appKey === '') {
                $appKey = bin2hex(random_bytes(32));
            }
            $appEnv = (string)($current['APP_ENV'] ?? 'production');
            if (!in_array($appEnv, ['local', 'production'], true)) {
                $appEnv = 'production';
            }
            $appDebug = (string)($current['APP_DEBUG'] ?? 'false');
            $appDebug = in_array($appDebug, ['true', 'false'], true) ? $appDebug : 'false';

            $envValues = [
                'APP_ENV'      => $appEnv,
                'APP_DEBUG'    => $appDebug,
                'APP_URL'      => $in['APP_URL'],
                'APP_TIMEZONE' => $in['APP_TIMEZONE'],
                'APP_KEY'      => $appKey,
                'DB_HOST'      => $in['DB_HOST'],
                'DB_PORT'      => $in['DB_PORT'],
                'DB_NAME'      => $in['DB_NAME'],
                'DB_USER'      => $in['DB_USER'],
                'DB_PASS'      => $dbPass,
                'SESSION_SECURE_COOKIE' => str_starts_with($in['APP_URL'], 'https://') ? '1' : '0',
                'SESSION_NAME' => (string)($current['SESSION_NAME'] ?? 'PMSID'),
                'ADMIN_NAME'   => $in['ADMIN_NAME'],
                'ADMIN_EMAIL'  => $in['ADMIN_EMAIL'],
                'ADMIN_PASSWORD' => $in['ADMIN_PASSWORD'] !== ''
                    ? $in['ADMIN_PASSWORD']
                    : (string)($current['ADMIN_PASSWORD'] ?? ''),
            ];
            install_write_env(INSTALL_ENV, $envValues);

            // 9) Lock file records the mode and the new public address.
            install_lock_write($lockData, [
                'installed_at' => (string)($lockData['installed_at'] ?? gmdate('c')),
                'app_url'      => $in['APP_URL'],
                'php'          => PHP_VERSION,
                'admin_email'  => $in['ADMIN_EMAIL'],
                'db_name'      => $in['DB_NAME'],
                'mode'         => $alreadyInstalled ? 'reconfigured' : ($adopted ? 'adopted' : 'installed'),
            ]);

            $success = [
                'mode'        => $alreadyInstalled ? 'reconfigured' : ($adopted ? 'adopted' : 'installed'),
                'app_url'     => $in['APP_URL'],
                'admin_email' => $in['ADMIN_EMAIL'],
                'db_name'     => $in['DB_NAME'],
            ];
            install_log('info', 'completed (' . $success['mode'] . ') for ' . $in['APP_URL']);

            // New token so a stale form cannot be replayed.
            $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
            $csrf = (string)$_SESSION['install_csrf'];
        } catch (PDOException $e) {
            install_log('error', 'DB error: ' . $e->getMessage());
            $errors[] = 'Database error. Check the host, port, database name, user, password and that the user can create the database. Details were written to storage/logs/install.log.';
        } catch (Throwable $e) {
            install_log('error', $e->getMessage());
            $errors[] = $e->getMessage();
        }
    }
}

/** POST value with a fallback to the current configuration. */
$post = static function (string $key, string $default = '') {
    return (string)($_POST[$key] ?? $default);
};
$prefill = static function (string $key, string $fallback = '') use ($snapshot, $current): string {
    if (isset($_POST[$key])) {
        return (string)$_POST[$key];
    }
    $envKey = strtoupper($key);
    if (isset($current[$envKey])) {
        return (string)$current[$envKey];
    }
    return (string)($snapshot[$key] ?? $fallback);
};
$tzOptions = ['Asia/Karachi', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Kolkata', 'Asia/Kathmandu', 'Asia/Dhaka', 'Europe/London', 'Europe/Berlin', 'America/New_York', 'America/Los_Angeles', 'Australia/Sydney', 'UTC'];
$tzCurrent = $prefill('app_timezone', 'Asia/Karachi');
if (!in_array($tzCurrent, $tzOptions, true)) {
    array_unshift($tzOptions, $tzCurrent);
}
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $alreadyInstalled ? 'Reconfigure' : 'Install' ?> — Patches&amp;Motifs</title>
<style>
    :root {
        --canvas: #F7F7F3; --surface: #FFFFFF; --ink: #111210; --muted: #666A63;
        --line: #E3E4DE; --accent: #30351B; --accent-bright: #C7D84A;
        --success: #26734A; --danger: #B33C36; --warning: #A66B16;
        --gold: #C8A96A; --gold-deep: #7E6029; --gold-soft: #F4EBDB;
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
    .alert.gold { border-color: var(--gold); background: var(--gold-soft); color: var(--gold-deep); }
    ul.plain { margin: 8px 0 0; padding-left: 18px; font-size: 13px; }
    code { background: #EFEFE9; border-radius: 6px; padding: 1px 6px; font-size: 12px; }
    a.btn { display: inline-flex; align-items: center; height: 44px; padding: 0 20px; border-radius: 999px; background: var(--accent); color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; }
    a.link { color: var(--accent); font-weight: 600; }
    footer { border-top: 1px solid var(--line); color: var(--muted); font-size: 12px; padding: 18px 0; }
    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .summary { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
    .summary th, .summary td { text-align: left; padding: 7px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
    .summary th { color: var(--muted); font-weight: 600; width: 42%; }
    .summary tr:last-child th, .summary tr:last-child td { border-bottom: 0; }
    .section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
    .tag { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--gold-deep); background: var(--gold-soft); border-radius: 999px; padding: 2px 9px; }
    .divider { height: 1px; background: var(--line); margin: 6px 0; }
</style>
</head>
<body>
<header>
    <div class="wrap brand">
        <span class="mark">P&amp;M</span>
        <div>
            <h1>Patches&amp;Motifs</h1>
            <small><?= $alreadyInstalled ? 'Reconfiguration console' : 'Setup installer' ?></small>
        </div>
    </div>
</header>

<main class="wrap">
<?php if ($success): ?>
    <div class="card">
        <div class="alert good">
            <?= $success['mode'] === 'reconfigured' ? 'Reconfiguration complete. The new settings are live.' : 'Installation complete. Your store is ready.' ?>
        </div>
        <h2>What changed</h2>
        <ul class="plain">
            <li>Store URL set to <code><?= h($success['app_url']) ?></code> (used by Google redirects and absolute links).</li>
            <li>Database <code><?= h($success['db_name']) ?></code> connected and configured.</li>
            <li>Store identity and timezone applied in <code>store_settings</code>.</li>
            <li>Super Admin <code><?= h($success['admin_email']) ?></code> saved<?= $success['mode'] === 'reconfigured' ? ' (password kept unless you entered a new one)' : '' ?>.</li>
            <li><code>.env</code> rewritten with the URL, database credentials and session settings. A backup was kept.</li>
            <li>Installation lock updated.</li>
        </ul>
        <div class="actions">
            <a class="btn" href="<?= h(rtrim($success['app_url'], '/')) ?>/">Open storefront</a>
            <a class="btn" href="<?= h(rtrim($success['app_url'], '/')) ?>/admin/login" style="background:var(--surface);color:var(--ink);border:1px solid var(--line)">Admin sign in</a>
        </div>
        <p class="hint" style="margin-top:16px">For safety, delete <code>public/install.php</code> once you have verified the site. Keep your admin password in a password manager.</p>
    </div>
<?php elseif ($alreadyInstalled && ($current === [] || $snapshot['connect_error'] !== '')): ?>
    <div class="card">
        <h2>Reconfiguration is available, but the current database is unreachable</h2>
        <p class="lead">The installer could not read the existing configuration, so it cannot verify a Super Admin password.</p>
        <div class="alert note" style="margin-top:16px">
            <strong>Recovery path.</strong> Delete <code>storage/installed.lock</code> on the server (hosting file manager,
            FTP or SSH), then reload this page. You can then enter the new database credentials, tick
            <strong>“Keep existing data”</strong> and the installer will update <code>.env</code> without dropping your tables.
        </div>
        <p class="hint">This prevents anyone who can merely reach the URL from taking over a live store.</p>
        <div class="actions">
            <a class="btn" href="<?= h((string)($current['APP_URL'] ?? '/')) ?>/">Open storefront</a>
        </div>
    </div>
<?php else: ?>
    <?php if ($alreadyInstalled): ?>
        <div class="card">
            <div class="section-head">
                <h2>Reconfiguration mode</h2>
                <span class="tag">Live site</span>
            </div>
            <p class="lead">Submitting this form rewrites the application URL, database credentials, store identity, Super Admin account and <code>.env</code>. Existing store data is kept unless you tick the destructive reset.</p>
            <table class="summary">
                <tr><th>Current store URL</th><td><?= h($snapshot['app_url'] !== '' ? $snapshot['app_url'] : '—') ?></td></tr>
                <tr><th>Current database</th><td><?= h($snapshot['db_name'] !== '' ? $snapshot['db_name'] : '—') ?> on <?= h($snapshot['db_host'] !== '' ? $snapshot['db_host'] : '—') ?><?= $snapshot['db_port'] !== '' ? ':' . h($snapshot['db_port']) : '' ?> (user <?= h($snapshot['db_user'] !== '' ? $snapshot['db_user'] : '—') ?>)</td></tr>
                <tr><th>Current Super Admin</th><td><?= h($snapshot['admin_email'] !== '' ? $snapshot['admin_email'] : '—') ?></td></tr>
                <tr><th>Store name</th><td><?= h($snapshot['store_name'] !== '' ? $snapshot['store_name'] : '—') ?></td></tr>
            </table>
        </div>

        <div class="card">
            <h2>Authorize the change</h2>
            <p class="lead">Only a current Super Admin can reconfigure a live installation. This check runs before anything is modified.</p>
            <div class="grid two" style="margin-top:16px">
                <div>
                    <label for="auth_email">Current Super Admin email</label>
                    <input id="auth_email" name="auth_email" type="email" form="install-form" required maxlength="254"
                           value="<?= h($post('auth_email', (string)($snapshot['admin_email'] !== '' ? $snapshot['admin_email'] : ($current['ADMIN_EMAIL'] ?? '')))) ?>">
                </div>
                <div>
                    <label for="auth_password">Current Super Admin password</label>
                    <input id="auth_password" name="auth_password" type="password" form="install-form" required autocomplete="current-password">
                </div>
            </div>
            <label class="check" style="margin-top:14px;border:1px solid #E6D9B8;border-radius:12px;padding:12px;background:#FBF6E8">
                <input type="checkbox" name="confirm_reconfigure" value="1" form="install-form" required>
                <span><strong>I understand this changes the live site.</strong><br>
                <span class="hint">The URL and database credentials in <code>.env</code> will be replaced. A timestamped backup of the old <code>.env</code> is kept.</span></span>
            </label>
            <?php if (!empty($_SESSION['install_auth_until']) && (int)$_SESSION['install_auth_until'] > time()): ?>
                <div class="alert error" style="margin-top:14px">Too many failed attempts. Authorization is paused for a few minutes.</div>
            <?php endif; ?>
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
    <?php endif; ?>

    <div class="card">
        <h2><?= $alreadyInstalled ? 'Store, URL and database' : '2. Store, URL and database' ?></h2>
        <p class="lead">These values are written to <code>.env</code> and the store settings table, so the whole application moves with them.</p>

        <?php if ($errors): ?>
            <div class="alert error" style="margin-top:14px">
                <?php foreach ($errors as $err): ?>
                    <div><?= h($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form id="install-form" method="post" class="grid" style="margin-top:18px" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

            <div>
                <label for="app_url">Store URL</label>
                <input id="app_url" name="app_url" type="url" required value="<?= h($post('app_url', (string)($snapshot['app_url'] !== '' ? $snapshot['app_url'] : install_detect_url()))) ?>">
                <p class="hint">Final public origin, e.g. <code>https://yourdomain.com</code> (no subfolder or trailing slash). Google sign-in redirects are built from it.</p>
            </div>

            <div class="grid two">
                <div>
                    <label for="store_name">Store name</label>
                    <input id="store_name" name="store_name" type="text" required maxlength="120"
                           value="<?= h($post('store_name', (string)($snapshot['store_name'] !== '' ? $snapshot['store_name'] : 'Patches&Motifs'))) ?>">
                    <p class="hint">Shown in the header, footer and page titles.</p>
                </div>
                <div>
                    <label for="app_timezone">Timezone</label>
                    <select id="app_timezone" name="app_timezone">
                        <?php foreach ($tzOptions as $tz): ?>
                            <option value="<?= h($tz) ?>" <?= $tzCurrent === $tz ? 'selected' : '' ?>><?= h($tz) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid two">
                <div>
                    <label for="store_email">Contact email</label>
                    <input id="store_email" name="store_email" type="email" maxlength="254"
                           value="<?= h($post('store_email', (string)($snapshot['store_email'] ?? ''))) ?>">
                </div>
                <div>
                    <label for="store_phone">Contact phone</label>
                    <input id="store_phone" name="store_phone" type="text" maxlength="32"
                           value="<?= h($post('store_phone', (string)($snapshot['store_phone'] ?? ''))) ?>">
                </div>
            </div>

            <div>
                <label for="store_address">Business address</label>
                <input id="store_address" name="store_address" type="text" maxlength="500"
                       value="<?= h($post('store_address', (string)($snapshot['store_address'] ?? ''))) ?>">
            </div>

            <div class="divider"></div>

            <div class="grid two">
                <div>
                    <label for="db_host">Database host</label>
                    <input id="db_host" name="db_host" type="text" required value="<?= h($post('db_host', (string)($snapshot['db_host'] !== '' ? $snapshot['db_host'] : '127.0.0.1'))) ?>">
                </div>
                <div>
                    <label for="db_port">Port</label>
                    <input id="db_port" name="db_port" type="number" min="1" max="65535" required value="<?= h($post('db_port', (string)($snapshot['db_port'] !== '' ? $snapshot['db_port'] : '3306'))) ?>">
                </div>
            </div>

            <div class="grid two">
                <div>
                    <label for="db_name">Database name</label>
                    <input id="db_name" name="db_name" type="text" required maxlength="64" value="<?= h($post('db_name', (string)($snapshot['db_name'] !== '' ? $snapshot['db_name'] : 'patches_motifs'))) ?>">
                </div>
                <div>
                    <label for="db_user">Database user</label>
                    <input id="db_user" name="db_user" type="text" required maxlength="120" value="<?= h($post('db_user', (string)($snapshot['db_user'] !== '' ? $snapshot['db_user'] : 'root'))) ?>">
                </div>
            </div>

            <div>
                <label for="db_pass">Database password</label>
                <input id="db_pass" name="db_pass" type="password" autocomplete="new-password"
                       placeholder="<?= $alreadyInstalled ? 'Leave blank to keep the current password' : '' ?>">
                <p class="hint">
                    <?php if ($alreadyInstalled): ?>
                        Blank keeps the current database password. The old value is never printed into this page.
                    <?php else: ?>
                        Leave blank for a local MySQL root with no password. The installer can create the database, but the MySQL user must already exist.
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($alreadyInstalled): ?>
                <label class="check" style="border:1px solid var(--line);border-radius:12px;padding:12px">
                    <input type="checkbox" name="allow_reset" value="1">
                    <span><strong>Reset the store tables (destructive)</strong><br>
                    <span class="hint">Drops only the tables created by Patches&amp;Motifs, then imports a clean schema. Orders, products and customers are deleted. Leave unticked to keep your data.</span></span>
                </label>
            <?php else: ?>
                <label class="check" style="border:1px solid var(--line);border-radius:12px;padding:12px">
                    <input type="checkbox" name="allow_existing" value="1">
                    <span><strong>Keep existing Patches&amp;Motifs data</strong><br>
                    <span class="hint">Use this when re-attaching to a database that already has the tables. Updates the configuration without dropping anything.</span></span>
                </label>
                <label class="check" style="border:1px solid var(--line);border-radius:12px;padding:12px">
                    <input type="checkbox" name="allow_reset" value="1">
                    <span><strong>Reset existing Patches&amp;Motifs tables (destructive)</strong><br>
                    <span class="hint">Only needed for a clean reinstall. Drops the store's own tables; unrelated tables are left untouched.</span></span>
                </label>
            <?php endif; ?>

            <h2 style="margin-top:6px"><?= $alreadyInstalled ? 'Administrator account' : '3. Administrator account' ?></h2>
            <p class="hint" style="margin-top:-8px"><?= $alreadyInstalled ? 'Leave the password blank to keep the current one. Entering a new email requires a new password.' : 'This account is created with full Super Admin access.' ?></p>

            <div class="grid two">
                <div>
                    <label for="admin_name">Name</label>
                    <input id="admin_name" name="admin_name" type="text" required maxlength="120" value="<?= h($post('admin_name', (string)($snapshot['admin_name'] !== '' ? $snapshot['admin_name'] : 'Administrator'))) ?>">
                </div>
                <div>
                    <label for="admin_email">Email</label>
                    <input id="admin_email" name="admin_email" type="email" required maxlength="254" value="<?= h($post('admin_email', (string)($snapshot['admin_email'] !== '' ? $snapshot['admin_email'] : ($current['ADMIN_EMAIL'] ?? '')))) ?>">
                </div>
            </div>
            <div class="grid two">
                <div>
                    <label for="admin_password"><?= $alreadyInstalled ? 'New password (optional)' : 'Password' ?></label>
                    <input id="admin_password" name="admin_password" type="password" <?= $alreadyInstalled ? '' : 'required' ?> minlength="12" maxlength="128" autocomplete="new-password">
                    <p class="hint">At least 12 characters. A long passphrase works best.</p>
                </div>
                <div>
                    <label for="admin_password_confirm">Confirm <?= $alreadyInstalled ? 'new ' : '' ?>password</label>
                    <input id="admin_password_confirm" name="admin_password_confirm" type="password" <?= $alreadyInstalled ? '' : 'required' ?> minlength="12" maxlength="128" autocomplete="new-password">
                </div>
            </div>

            <?php if ($alreadyInstalled): ?>
                <label class="check" style="border:1px solid var(--line);border-radius:12px;padding:12px">
                    <input type="checkbox" name="admin_replace" value="1" checked>
                    <span><strong>Apply this to the current primary Super Admin</strong><br>
                    <span class="hint">When the email changes, the oldest active Super Admin is renamed instead of creating a second account. Untick to add a new admin alongside existing ones.</span></span>
                </label>
            <?php endif; ?>

            <div class="actions">
                <button type="submit" <?= (!$alreadyInstalled && !install_requirements_ok($req)) ? 'disabled' : '' ?>>
                    <?= $alreadyInstalled ? 'Apply reconfiguration' : 'Install Patches&amp;Motifs' ?>
                </button>
            </div>
            <?php if ($alreadyInstalled): ?>
                <p class="hint">After a successful change, all admins should sign in again. Existing sessions expire automatically (30 min idle / 8 h absolute).</p>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>
</main>

<footer>
    <div class="wrap">Patches&amp;Motifs installer · <?= $alreadyInstalled ? 'reconfiguration is Super Admin gated' : 'runs once' ?> · remove <code>install.php</code> when finished.</div>
</footer>
</body>
</html>
