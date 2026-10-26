<?php
/**
 * PostgreSQL Database Schema Manager
 * Handles database table initialization, safe migrations, and seeding for PostgreSQL
 */
class Schema {
    public static function isInitialized(PDO $pdo): bool {
        try {
            $st = $pdo->query("SELECT to_regclass('public.users')");
            $res = $st ? $st->fetchColumn() : null;
            return !empty($res);
        } catch (Throwable $e) {
            try {
                $st = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users' LIMIT 1");
                return (bool)($st && $st->fetchColumn());
            } catch (Throwable $t) {
                return false;
            }
        }
    }

    public static function initialize(PDO $pdo): void {
        self::initPostgres($pdo);
        self::seedDefaultData($pdo);
    }

    public static function ensureMigrations(PDO $pdo): void {
        try {
            $pdo->exec('ALTER TABLE claims ADD COLUMN IF NOT EXISTS lost_item_id INT REFERENCES lost_items(id) ON DELETE SET NULL');
            $pdo->exec('ALTER TABLE lost_items ADD COLUMN IF NOT EXISTS image_path TEXT');
            $pdo->exec('ALTER TABLE found_items ADD COLUMN IF NOT EXISTS image_path TEXT');
            $pdo->exec('ALTER TABLE claims ADD COLUMN IF NOT EXISTS image_path TEXT');
            $pdo->exec('ALTER TABLE email_notifications ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT \'queued\'');
        } catch (Throwable $e) {
            // Non-breaking if columns exist
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
            )",
            "CREATE INDEX IF NOT EXISTS idx_lost_items_user ON lost_items(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_found_items_user ON found_items(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_claims_user ON claims(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_claims_item ON claims(item_id)",
            "CREATE INDEX IF NOT EXISTS idx_match_user ON item_match_notifications(user_id)"
        ];

        foreach ($queries as $q) {
            $pdo->exec($q);
        }
    }

    private static function seedDefaultData(PDO $pdo): void {
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

        // 3. Seed Sample Found Items
        $foundCount = (int)$pdo->query('SELECT COUNT(*) FROM found_items')->fetchColumn();
        if ($foundCount === 0) {
            $sampleFound = [
                ['Demo User', 'Samsung Galaxy S22', 'Phone', 'A black Samsung phone with cracked screen protector', 'phone samsung black', '2026-05-12', 'Library Block'],
                ['Demo User', 'Brown Leather Wallet', 'Wallet', 'Contains ID cards and some cash', 'wallet brown leather', '2026-05-14', 'Canteen'],
                ['Demo User', 'Blue Backpack', 'Bag', 'Adidas bag with laptop compartment', 'bag blue adidas', '2026-05-15', 'Seminar Hall'],
                ['Demo User', 'Dell Laptop Bag', 'Laptop', 'Black bag with charger inside', 'dell laptop charger', '2026-05-17', 'IT Lab'],
                ['Demo User', 'Engineering Drawing Book', 'Books', 'Has name written inside cover', 'drawing book', '2026-05-18', 'Sports Ground']
            ];
            $st = $pdo->prepare('INSERT INTO found_items (user_id, user_name, item_name, category, description, keywords, date_found, location, status) VALUES (1, ?, ?, ?, ?, ?, ?, ?, \'Available\')');
            foreach ($sampleFound as $row) {
                $st->execute($row);
            }
        }

        // 4. Seed Sample Lost Items
        $lostCount = (int)$pdo->query('SELECT COUNT(*) FROM lost_items')->fetchColumn();
        if ($lostCount === 0) {
            $sampleLost = [
                ['Demo User', 'iPhone 14 Pro', 'Phone', 'Space grey, has CUCEK sticker on back', 'iphone grey sticker', '2026-05-11', 'Library Block'],
                ['Demo User', 'Blue Water Bottle', 'Others', '1 litre Nalgene bottle', 'blue bottle', '2026-05-13', 'Canteen'],
                ['Demo User', 'Student ID Card', 'ID Card', 'Laminated CUCEK ID', 'id card student', '2026-05-16', 'Seminar Hall']
            ];
            $st = $pdo->prepare('INSERT INTO lost_items (user_id, user_name, item_name, category, description, keywords, date_lost, location, status) VALUES (1, ?, ?, ?, ?, ?, ?, ?, \'Active\')');
            foreach ($sampleLost as $row) {
                $st->execute($row);
            }
        }

        // 5. Seed Initial System Audit Log
        $logCount = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        if ($logCount === 0) {
            $pdo->exec("INSERT INTO audit_logs (user_id, action, description) VALUES (NULL, 'SYSTEM', 'PostgreSQL database initialized successfully')");
        }
    }
}
