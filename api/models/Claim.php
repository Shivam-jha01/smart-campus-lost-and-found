<?php
/**
 * Claim Model
 * Handles item claims, verification details, admin approval/rejections, and relations.
 */
class Claim extends BaseModel {
    public function findById(int $id): ?array {
        $st = $this->pdo->prepare('SELECT * FROM claims WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByUser(int $userId): array {
        $sql = 'SELECT c.*, c.image_path AS claim_image_path, u.name AS claim_user_name, f.image_path AS item_image_path, l.item_name AS lost_item_name 
                FROM claims c 
                LEFT JOIN users u ON u.id = c.user_id 
                LEFT JOIN found_items f ON f.id = c.item_id 
                LEFT JOIN lost_items l ON l.id = c.lost_item_id 
                WHERE c.user_id = ? ORDER BY c.id DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$userId]);
        return array_map([self::class, 'format'], $st->fetchAll());
    }

    public function getAll(): array {
        $sql = 'SELECT c.*, c.image_path AS claim_image_path, u.name AS claim_user_name, f.image_path AS item_image_path, l.item_name AS lost_item_name 
                FROM claims c 
                LEFT JOIN users u ON u.id = c.user_id 
                LEFT JOIN found_items f ON f.id = c.item_id 
                LEFT JOIN lost_items l ON l.id = c.lost_item_id 
                ORDER BY c.id DESC';
        $st = $this->pdo->query($sql);
        return array_map([self::class, 'format'], $st->fetchAll());
    }

    public function hasPending(int $itemId, int $userId): bool {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM claims WHERE item_id = ? AND user_id = ? AND status = \'Pending\'');
        $st->execute([$itemId, $userId]);
        return (int)$st->fetchColumn() > 0;
    }

    public function create(array $data): int {
        return $this->insert('claims', [
            'item_id' => $data['item_id'],
            'lost_item_id' => $data['lost_item_id'] ?? null,
            'item_name' => $data['item_name'],
            'user_id' => $data['user_id'],
            'user_name' => $data['user_name'],
            'details' => $data['details'],
            'image_path' => $data['image_path'] ?? '',
            'status' => 'Pending'
        ]);
    }

    public function updateStatus(int $id, string $status): bool {
        return $this->update('claims', ['status' => $status], 'id = ?', [$id]);
    }

    public function countPending(): int {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM claims WHERE status = \'Pending\'')->fetchColumn();
    }

    public function countByUser(int $userId): int {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM claims WHERE user_id = ?');
        $st->execute([$userId]);
        return (int)$st->fetchColumn();
    }

    public static function format(?array $row): ?array {
        if (!$row) return null;
        return [
            'id' => (int)$row['id'],
            'itemId' => (int)$row['item_id'],
            'lostItemId' => !empty($row['lost_item_id']) ? (int)$row['lost_item_id'] : null,
            'lostItemName' => $row['lost_item_name'] ?? null,
            'itemName' => $row['item_name'],
            'userId' => (int)$row['user_id'],
            'userName' => $row['claim_user_name'] ?? $row['user_name'] ?? '',
            'details' => $row['details'],
            'imagePath' => $row['claim_image_path'] ?? $row['image_path'] ?? '',
            'itemImagePath' => $row['item_image_path'] ?? '',
            'status' => $row['status'],
            'timestamp' => $row['created_at']
        ];
    }
}
