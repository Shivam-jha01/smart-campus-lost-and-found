<?php
/**
 * Database Schema Manager
 * Handles database table initialization and migrations for MySQL & PostgreSQL
 */
class Schema {
    public static function isInitialized(PDO $pdo): bool {
        try {
            $pdo->query('SELECT 1 FROM users LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function initialize(PDO $pdo, string $driver): void {
        if ($driver === 'pgsql') {
            self::initPostgres($pdo);
        } else {
            self::initMySQL($pdo);
        }

        self::seedDefaultData($pdo, $driver);
    }

    public static function ensureMigrations(PDO $pdo, string $driver): void {
        // Run safe non-breaking alter checks if tables already exist
        try {
            if ($driver === 'pgsql') {
                $pdo->exec('ALTER TABLE claims ADD COLUMN IF NOT EXISTS lost_item_id INT REFERENCES lost_items(id) ON DELETE SET NULL');
                $pdo->exec('ALTER TABLE lost_items ADD COLUMN IF NOT EXISTS image_path TEXT');
                $pdo->exec('ALTER TABLE found_items ADD COLUMN IF NOT EXISTS image_path TEXT');
                $pdo->exec('ALTER TABLE claims ADD COLUMN IF NOT EXISTS image_path TEXT');
            } else {
                // MySQL safe column additions
                $st = $pdo->query('SHOW COLUMNS FROM claims LIKE "lost_item_id"');
                if (!$st->fetch()) {
                    $pdo->exec('ALTER TABLE claims ADD lost_item_id INT NULL AFTER item_id');
                }
                foreach (['lost_items', 'found_items'] as $table) {
                    $st = $pdo->query('SHOW COLUMNS FROM ' . $table . ' LIKE "image_path"');
                    if (!$st->fetch()) {
                        $pdo->exec('ALTER TABLE ' . $table . ' ADD image_path TEXT NULL AFTER keywords');
                    }
                }
                $st = $pdo->query('SHOW COLUMNS FROM claims LIKE "image_path"');
                if (!$st->fetch()) {
                    $pdo->exec('ALTER TABLE claims ADD image_path TEXT NULL AFTER details');
                }
            }
        } catch (Throwable $e) {
            // Non-critical if table structure is already up to date
        }
    }

    private static function initPostgres(PDO $pdo): void {
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(120) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                uid VARCHAR(50) NOT NULL UNIQUE,
                dept VARCHAR(100) NOT NULL,
                role VARCHAR(30) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                is_admin SMALLINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS admins (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(120) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                uid VARCHAR(50) NOT NULL UNIQUE,
                dept VARCHAR(100) NOT NULL,
                role VARCHAR(30) NOT NULL DEFAULT 'admin',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS lost_items (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                user_name VARCHAR(100) NOT NULL,
                item_name VARCHAR(150) NOT NULL,
                category VARCHAR(50) NOT NULL,
                description TEXT,
                keywords VARCHAR(255),
                image_path TEXT,
                date_lost DATE NOT NULL,
                location VARCHAR(150) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS found_items (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                user_name VARCHAR(100) NOT NULL,
                item_name VARCHAR(150) NOT NULL,
                category VARCHAR(50) NOT NULL,
                description TEXT,
                keywords VARCHAR(255),
                image_path TEXT,
                date_found DATE NOT NULL,
                location VARCHAR(150) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Available',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS claims (
                id SERIAL PRIMARY KEY,
                item_id INT NOT NULL REFERENCES found_items(id) ON DELETE CASCADE,
                lost_item_id INT REFERENCES lost_items(id) ON DELETE SET NULL,
                item_name VARCHAR(150) NOT NULL,
                user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                user_name VARCHAR(100) NOT NULL,
                details TEXT NOT NULL,
                image_path TEXT,
                status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS item_match_notifications (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                lost_item_id INT NOT NULL REFERENCES lost_items(id) ON DELETE CASCADE,
                found_item_id INT NOT NULL REFERENCES found_items(id) ON DELETE CASCADE,
                match_score INT NOT NULL DEFAULT 0,
                match_reason VARCHAR(255),
                is_read SMALLINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT unique_match UNIQUE (user_id, lost_item_id, found_item_id)
            )",
            "CREATE TABLE IF NOT EXISTS audit_logs (
                id SERIAL PRIMARY KEY,
                user_id INT REFERENCES users(id) ON DELETE SET NULL,
                action VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS email_notifications (
                id SERIAL PRIMARY KEY,
                user_id INT REFERENCES users(id) ON DELETE SET NULL,
                recipient_email VARCHAR(120) NOT NULL,
                subject VARCHAR(180) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'queued',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($queries as $q) {
            $pdo->exec($q);
        }
    }

    private static function initMySQL(PDO $pdo): void {
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(120) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                uid VARCHAR(50) NOT NULL UNIQUE,
                dept VARCHAR(100) NOT NULL,
                role VARCHAR(30) NOT NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                is_admin TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(120) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                uid VARCHAR(50) NOT NULL UNIQUE,
                dept VARCHAR(100) NOT NULL,
                role VARCHAR(30) NOT NULL DEFAULT 'admin',
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS lost_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                user_name VARCHAR(100) NOT NULL,
                item_name VARCHAR(150) NOT NULL,
                category VARCHAR(50) NOT NULL,
                description TEXT,
                keywords VARCHAR(255),
                image_path TEXT,
                date_lost DATE NOT NULL,
                location VARCHAR(150) NOT NULL,
                status ENUM('Active','Resolved') NOT NULL DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS found_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                user_name VARCHAR(100) NOT NULL,
                item_name VARCHAR(150) NOT NULL,
                category VARCHAR(50) NOT NULL,
                description TEXT,
                keywords VARCHAR(255),
                image_path TEXT,
                date_found DATE NOT NULL,
                location VARCHAR(150) NOT NULL,
                status ENUM('Available','Claimed') NOT NULL DEFAULT 'Available',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS claims (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                lost_item_id INT NULL,
                item_name VARCHAR(150) NOT NULL,
                user_id INT NOT NULL,
                user_name VARCHAR(100) NOT NULL,
                details TEXT NOT NULL,
                image_path TEXT,
                status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES found_items(id) ON DELETE CASCADE,
                FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE SET NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS item_match_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                lost_item_id INT NOT NULL,
                found_item_id INT NOT NULL,
                match_score INT NOT NULL DEFAULT 0,
                match_reason VARCHAR(255),
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_match (user_id,lost_item_id,found_item_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE CASCADE,
                FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS email_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                recipient_email VARCHAR(120) NOT NULL,
                subject VARCHAR(180) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'queued',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        ];

        foreach ($queries as $q) {
            $pdo->exec($q);
        }
    }

    private static function seedDefaultData(PDO $pdo, string $driver): void {
        // 1. Seed Admin
        $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($adminCount === 0) {
            $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
            $st = $pdo->prepare('INSERT INTO admins (name, email, password, uid, dept, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $st->execute(['Admin User', 'admin@demo.com', $adminPass, 'ADMIN001', 'Administration', 'admin', 'active']);
        }

        // 2. Seed Demo User
        $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($userCount === 0) {
            $userPass = password_hash('password', PASSWORD_DEFAULT);
            $st = $pdo->prepare('INSERT INTO users (name, email, password, uid, dept, role, status, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $st->execute(['Demo User', 'user@demo.com', $userPass, '24102535', 'Information Technology', 'student', 'active', 0]);
        }
    }
}
