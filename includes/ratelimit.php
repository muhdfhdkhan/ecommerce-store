<?php
// includes/ratelimit.php — database-backed rate limiting (SECURITY.md §10.1).
// Redis is not available on XAMPP; the login_attempts table plays the same role locally.
declare(strict_types=1);

/**
 * Count recent failures. Returns true when the request is allowed.
 * @param string $scope      logical bucket, e.g. 'admin_login'
 * @param string $identifier email, IP, or "email|ip"
 * @param int    $max        max failures within the window
 * @param int    $windowSec  window in seconds
 */
function rate_limit_ok(string $scope, string $identifier, int $max, int $windowSec): bool
{
    $st = db()->prepare(
        'SELECT COUNT(*) AS c FROM login_attempts
         WHERE scope = :scope AND identifier = :identifier
           AND success = 0 AND attempted_at > (UTC_TIMESTAMP() - INTERVAL :window SECOND)'
    );
    $st->execute([':scope' => $scope, ':identifier' => $identifier, ':window' => $windowSec]);
    $row = $st->fetch();
    return ((int)($row['c'] ?? 0)) < $max;
}

function record_login_attempt(string $scope, string $identifier, bool $success): void
{
    $st = db()->prepare(
        'INSERT INTO login_attempts (scope, identifier, ip, success, attempted_at)
         VALUES (:scope, :identifier, :ip, :success, UTC_TIMESTAMP())'
    );
    $st->execute([
        ':scope'      => $scope,
        ':identifier' => substr($identifier, 0, 254),
        ':ip'         => substr(client_ip(), 0, 45),
        ':success'    => $success ? 1 : 0,
    ]);
}

/** Housekeeping: prune attempts older than 30 days. */
function prune_login_attempts(): void
{
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < (UTC_TIMESTAMP() - INTERVAL 30 DAY)');
}
