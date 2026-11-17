<?php
// includes/database.php — PDO layer with real prepared statements (SECURITY.md §5.2)
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            config('db.host'),
            config('db.port'),
            config('db.name')
        );
        try {
            $pdo = new PDO($dsn, (string)config('db.user'), (string)config('db.pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,           // real prepared statements
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            // No connection details to the user; log privately.
            app_log('error', 'DB connection failed');
            http_response_code(500);
            exit('Service temporarily unavailable.');
        }
    }
    return $pdo;
}
