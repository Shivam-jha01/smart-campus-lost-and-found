<?php
/**
 * Admin Model
 * Manages administrator accounts and authentication queries.
 */
class Admin extends BaseModel {
    public function findByEmail(string $email): ?array {
        $st = $this->pdo->prepare('SELECT * FROM admins WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array {
        $st = $this->pdo->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function count(): int {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    }

    public static function format(?array $row): ?array {
        if (!$row) return null;
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'uid' => $row['uid'],
            'dept' => $row['dept'] ?? 'Administration',
            'role' => 'admin',
            'status' => 'active',
            'isAdmin' => true
        ];
    }
}
