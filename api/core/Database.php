<?php
/**
 * Database Singleton Class
 * Exclusively connects to PostgreSQL using PDO (pgsql driver)
 * Supports Vercel Postgres (Neon), Supabase, and custom PostgreSQL URLs
 */
class Database {
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private string $driver = 'pgsql';

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
        return 'pgsql';
    }

    private function connect(): void {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        // 1. Check for PostgreSQL connection URL strings
        $postgresUrl = getenv('POSTGRES_URL')
            ?: getenv('POSTGRES_URL_NON_POOLING')
            ?: getenv('POSTGRES_PRISMA_URL')
            ?: getenv('DATABASE_URL');

        // 2. Check for individual PostgreSQL environment variables
        $postgresHost = getenv('POSTGRES_HOST') ?: getenv('PGHOST');

        if (!$postgresUrl && !$postgresHost) {
            throw new Exception(
                "PostgreSQL connection error: Missing database configuration. " .
                "Please configure POSTGRES_URL or DATABASE_URL in your Vercel project environment variables and redeploy."
            );
        }

        $this->driver = 'pgsql';

        if ($postgresUrl) {
            $parts = parse_url($postgresUrl);
            if (!$parts || empty($parts['host'])) {
                throw new Exception("PostgreSQL connection error: Invalid database URL format in environment variables.");
            }

            $host = $parts['host'];
            $port = $parts['port'] ?? 5432;
            $db   = ltrim($parts['path'] ?? 'verceldb', '/');
            $user = isset($parts['user']) ? urldecode($parts['user']) : '';
            $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';

            $sslmode = 'require';
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
                if (isset($query['sslmode'])) {
                    $sslmode = $query['sslmode'];
                }
            }

            $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}";
        } else {
            $host = $postgresHost;
            $port = getenv('POSTGRES_PORT') ?: getenv('PGPORT') ?: 5432;
            $db   = getenv('POSTGRES_DATABASE') ?: getenv('PGDATABASE') ?: 'verceldb';
            $user = getenv('POSTGRES_USER') ?: getenv('PGUSER') ?: 'default';
            $pass = getenv('POSTGRES_PASSWORD') ?: getenv('PGPASSWORD') ?: '';
            $sslmode = getenv('POSTGRES_SSLMODE') ?: getenv('PGSSLMODE') ?: 'require';

            $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}";
        }

        // Extract Neon compute endpoint ID for libpq runtimes lacking TLS SNI
        $endpointId = getenv('POSTGRES_ENDPOINT_ID') ?: getenv('PGENDPOINT') ?: '';
        if (empty($endpointId) && isset($parts['query'])) {
            if (preg_match('/endpoint(?:%3D|=)([a-z0-9\-]+)/i', $parts['query'], $qm)) {
                $endpointId = $qm[1];
            }
        }
        if (empty($endpointId)) {
            if (preg_match('/^(ep-[a-z0-9\-]+?)(-pooler)?(\.|$)/i', $host, $m)) {
                $endpointId = $m[1];
            } elseif (strpos($host, 'neon.tech') !== false) {
                $firstPart = explode('.', $host)[0];
                $endpointId = preg_replace('/-pooler$/i', '', $firstPart);
            }
        }

        // Build list of connection candidate strategies
        $candidates = [];

        if (!empty($endpointId)) {
            // Neon Workaround 1: DSN options parameter (URL encoded)
            $candidates[] = [
                'dsn'  => "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode};options=endpoint%3D{$endpointId}",
                'user' => $user,
                'pass' => $pass,
                'desc' => "DSN with options=endpoint%3D{$endpointId}"
            ];

            // Neon Workaround 2: Password prefix with semicolon (Neon proxy level routing)
            if (!empty($pass) && strpos($pass, 'endpoint=') !== 0) {
                $candidates[] = [
                    'dsn'  => "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}",
                    'user' => $user,
                    'pass' => "endpoint={$endpointId};{$pass}",
                    'desc' => "Password prefix with semicolon"
                ];
                // Neon Workaround 3: Password prefix with dollar sign
                $candidates[] = [
                    'dsn'  => "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}",
                    'user' => $user,
                    'pass' => "endpoint={$endpointId}\${$pass}",
                    'desc' => "Password prefix with dollar"
                ];
            }

            // Neon Workaround 4: DSN options parameter with single quotes (libpq key=value style)
            $candidates[] = [
                'dsn'  => "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode};options='endpoint={$endpointId}'",
                'user' => $user,
                'pass' => $pass,
                'desc' => "DSN with options='endpoint={$endpointId}'"
            ];
        }

        // Standard connection without endpoint options (works for native SNI or non-Neon Postgres)
        $candidates[] = [
            'dsn'  => "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}",
            'user' => $user,
            'pass' => $pass,
            'desc' => "Standard DSN"
        ];

        $lastException = null;
        foreach ($candidates as $cand) {
            try {
                $this->pdo = new PDO($cand['dsn'], $cand['user'], $cand['pass'], $options);
                break;
            } catch (PDOException $e) {
                $lastException = $e;
                continue;
            }
        }

        if ($this->pdo === null && $lastException !== null) {
            throw new Exception("PostgreSQL connection failed: " . $lastException->getMessage(), (int)$lastException->getCode(), $lastException);
        }
    }
}
