<?php
/**
 * Database Singleton Class
 * Supports both PostgreSQL (Vercel Postgres / Neon) and MySQL (Railway / Local / TiDB)
 */
class Database {
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private string $driver = 'mysql';

    private function __construct() {
        $this->connect();
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    public function getDriver(): string {
        return $this->driver;
    }

    private function connect(): void {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        $databaseUrl = getenv('DATABASE_URL');
        $postgresUrl = getenv('POSTGRES_URL') ?: getenv('POSTGRES_PRISMA_URL');
        $postgresHost = getenv('POSTGRES_HOST');

        $isPostgres = false;
        if ($postgresUrl || $postgresHost) {
            $isPostgres = true;
        } elseif ($databaseUrl && (strpos($databaseUrl, 'postgres://') === 0 || strpos($databaseUrl, 'postgresql://') === 0)) {
            $isPostgres = true;
            $postgresUrl = $databaseUrl;
        }

        // 1. Check for PostgreSQL (Vercel Postgres / Neon)
        if ($isPostgres) {
            $this->driver = 'pgsql';
            try {
                if ($postgresUrl) {
                    $parts = parse_url($postgresUrl);
                    $host = $parts['host'] ?? 'localhost';
                    $port = $parts['port'] ?? 5432;
                    $db   = ltrim($parts['path'] ?? 'verceldb', '/');
                    $user = isset($parts['user']) ? urldecode($parts['user']) : '';
                    $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
                    $sslmode = 'require';
                    if (!empty($parts['query'])) {
                        parse_str($parts['query'], $query);
                        if (isset($query['sslmode'])) $sslmode = $query['sslmode'];
                    }
                } else {
                    $host = getenv('POSTGRES_HOST');
                    $port = getenv('POSTGRES_PORT') ?: 5432;
                    $db   = getenv('POSTGRES_DATABASE') ?: 'verceldb';
                    $user = getenv('POSTGRES_USER') ?: 'default';
                    $pass = getenv('POSTGRES_PASSWORD') ?: '';
                    $sslmode = getenv('POSTGRES_SSLMODE') ?: 'require';
                }

                $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}";
                $this->pdo = new PDO($dsn, $user, $pass, $options);
                return;
            } catch (PDOException $e) {
                error_log("PostgreSQL connection failed: " . $e->getMessage());
                // If explicit pgsql fails, rethrow
                if (!$databaseUrl || strpos($databaseUrl, 'postgres') !== false) {
                    throw new Exception("PostgreSQL connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
                }
            }
        }

        // 2. MySQL Connection (Railway / Localhost / TiDB Cloud)
        $this->driver = 'mysql';

        $mysqlUrl = getenv('MYSQL_URL') ?: getenv('MYSQL_PUBLIC_URL') ?: ($databaseUrl && strpos($databaseUrl, 'mysql://') === 0 ? $databaseUrl : null);

        if ($mysqlUrl) {
            $parts = parse_url($mysqlUrl);
            $host = $parts['host'] ?? 'localhost';
            $port = (int)($parts['port'] ?? 3306);
            $dbname = ltrim($parts['path'] ?? 'railway', '/');
            $username = isset($parts['user']) ? urldecode($parts['user']) : 'root';
            $password = isset($parts['pass']) ? urldecode($parts['pass']) : '';
        } else {
            $host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: 'localhost';
            $port = (int)(getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: 3306);
            $dbname = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: 'railway';
            $username = getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: 'root';
            $password = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
        }

        // Add SSL options for remote cloud MySQL
        if ($host !== 'localhost' && $host !== '127.0.0.1' && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new Exception("MySQL database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
}
