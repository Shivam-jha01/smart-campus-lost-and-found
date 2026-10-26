<?php
/**
 * AuditLog Model
 * Records administrative and user audit trails for security and activity tracking.
 */
class AuditLog extends BaseModel {
    public function log(string $action, string $description, ?int $userId = null): void {
        try {
            $st = $this->pdo->prepare('INSERT INTO audit_logs (user_id, action, description) VALUES (?, ?, ?)');
            $st->execute([$userId, $action, $description]);
        } catch (Throwable $e) {
            // Audit logging should not crash the main application flow
        }
    }

    public function getRecent(int $limit = 5): array {
        $st = $this->pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT ' . (int)$limit);
        return $st ? array_map([self::class, 'format'], $st->fetchAll()) : [];
    }

    public function getAll(int $limit = 200): array {
        $st = $this->pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT ' . (int)$limit);
        return $st ? array_map([self::class, 'format'], $st->fetchAll()) : [];
    }

    public static function format(?array $row): ?array {
        if (!$row) return null;
        return [
            'id' => (int)$row['id'],
            'userId' => $row['user_id'] ? (int)$row['user_id'] : null,
            'action' => $row['action'],
            'desc' => $row['description'],
            'timestamp' => $row['created_at']
        ];
    }
}
