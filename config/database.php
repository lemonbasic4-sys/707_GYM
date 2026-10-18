<?php
/**
 * 707 GYM - Database Connection
 * PDO singleton via static getConnection().
 */

require_once __DIR__ . '/config.php';

class Database
{
    /** @var PDO|null */
    private static ?PDO $instance = null;

    /** Prevent direct instantiation */
    private function __construct() {}
    private function __clone() {}

    /**
     * Return a shared PDO instance (singleton).
     *
     * @return PDO
     * @throws RuntimeException on connection failure
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true, // Enables reuse of named placeholders across queries
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Never expose credentials in production
                $msg = (APP_ENV === 'development')
                    ? 'Database connection failed: ' . $e->getMessage()
                    : 'Database connection failed. Please contact the administrator.';

                throw new RuntimeException($msg, (int) $e->getCode(), $e);
            }
        }

        return self::$instance;
    }

    /**
     * Reset the singleton (useful in tests).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
