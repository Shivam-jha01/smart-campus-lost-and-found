<?php
/**
 * User Model
 * Manages regular student/faculty user accounts and authentication queries.
 */
class User extends BaseModel {
    public function findByEmail(string $email): ?array {
        $st = $this->pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array {
        $st = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByUid(string $uid): ?array {
        $st = $this->pdo->prepare('SELECT * FROM users WHERE uid = ? LIMIT 1');
        $st->execute([$uid]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function create(array $data): int {
        return $this->insert('users', [
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'uid' => $data['uid'],
            'dept' => $data['dept'],
            'role' => $data['role'] ?? 'student',
            'status' => $data['status'] ?? 'active',
            'is_admin' => 0
        ]);
    }

    public function updateStatus(int $id, string $status): bool {
        return $this->update('users', ['status' => $status], 'id = ? AND is_admin = 0', [$id]);
    }

    public function getAllNonAdmin(): array {
        $st = $this->pdo->query('SELECT * FROM users WHERE is_admin = 0 ORDER BY id DESC');
        return array_map([self::class, 'format'], $st->fetchAll());
    }

    public function count(): int {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 0')->fetchColumn();
    }

    public static function format(?array $row): ?array {
        if (!$row) return null;
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'uid' => $row['uid'],
            'dept' => $row['dept'],
            'role' => $row['role'],
            'status' => $row['status'] ?? 'active',
            'isAdmin' => !empty($row['is_admin'])
        ];
    }
}
