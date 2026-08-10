<?php

namespace App\Config;

use Dotenv\Dotenv;
use R;

class Database
{
    private static bool $initialized = false;

    /**
     * Initialize the database connection using environment variables.
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // Load environment variables if available
        if (class_exists(Dotenv::class)) {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
            $dotenv->safeLoad();
        }

        $dbhost = $_ENV['DB_HOST'] ?? $_ENV['dbhost'] ?? 'localhost';
        $dbuser = $_ENV['DB_USER'] ?? $_ENV['dbuser'] ?? 'root';
        $dbpass = $_ENV['DB_PASS'] ?? $_ENV['dbpass'] ?? '';
        $dbname = $_ENV['DB_NAME'] ?? $_ENV['dbname'] ?? 'airx';

        if (!R::testConnection()) {
            R::setup("mysql:host={$dbhost};dbname={$dbname}", $dbuser, $dbpass);
        }

        self::$initialized = true;
    }

    /**
     * Get the configured external/main database name (default: lifebank_plus).
     */
    public static function getMainDbName(): string
    {
        return $_ENV['MAIN_DB_NAME'] ?? $_ENV['LIFEBANK_DB_NAME'] ?? 'lifebank_plus';
    }

    /**
     * Safely close the database connection.
     */
    public static function close(): void
    {
        R::close();
    }
}
