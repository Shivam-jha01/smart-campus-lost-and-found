<?php
/**
 * LostItem Model
 * Handles reporting, searching, status resolution, and tracking for lost items.
 */
class LostItem extends BaseModel {
    public function findById(int $id): ?array {
        $st = $this->pdo->prepare('SELECT * FROM lost_items WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByUser(int $userId): array {
        $st = $this->pdo->prepare('SELECT * FROM lost_items WHERE user_id = ? ORDER BY id DESC');
        $st->execute([$userId]);
        return array_map([self::class, 'format'], $st->fetchAll());
    }

    public function findActiveByUser(int $userId, int $limit = 20): array {
        $st = $this->pdo->prepare('SELECT * FROM lost_items WHERE user_id = ? AND status = \'Active\' ORDER BY id DESC LIMIT ' . (int)$limit);
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    public function findRecent(int $limit = 10): array {
        $st = $this->pdo->query('SELECT * FROM lost_items ORDER BY id DESC LIMIT ' . (int)$limit);
        return array_map([self::class, 'format'], $st->fetchAll());
    }

    public function exists(int $userId, string $name, string $category): bool {
        $st = $this->pdo->prepare('SELECT id FROM lost_items WHERE user_id = ? AND status = \'Active\' AND LOWER(TRIM(item_name)) = LOWER(TRIM(?)) AND category = ? LIMIT 1');
        $st->execute([$userId, $name, $category]);
        return (bool)$st->fetch();
    }

    public function create(array $data): int {
        return $this->insert('lost_items', [
            'user_id' => $data['user_id'],
            'user_name' => $data['user_name'],
            'item_name' => $data['item_name'],
            'category' => $data['category'],
            'description' => $data['description'] ?? '',
            'keywords' => $data['keywords'] ?? '',
            'image_path' => $data['image_path'] ?? '',
            'date_lost' => $data['date_lost'],
            'location' => $data['location'],
            'status' => 'Active'
        ]);
    }

    public function deleteByUser(int $id, int $userId): bool {
        return $this->delete('lost_items', 'id = ? AND user_id = ?', [$id, $userId]);
    }

    public function adminDelete(int $id): bool {
        return $this->delete('lost_items', 'id = ?', [$id]);
    }

    public function resolve(int $userId, string $itemName, ?int $lostItemId = null): bool {
        if (!empty($lostItemId)) {
            $st = $this->pdo->prepare('UPDATE lost_items SET status = \'Resolved\' WHERE id = ? AND user_id = ? AND status = \'Active\'');
            return $st->execute([$lostItemId, $userId]);
        }
        $st = $this->pdo->prepare('UPDATE lost_items SET status = \'Resolved\' WHERE user_id = ? AND LOWER(TRIM(item_name)) = LOWER(TRIM(?)) AND status = \'Active\'');
        return $st->execute([$userId, $itemName]);
    }

    public function count(): int {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM lost_items')->fetchColumn();
    }

    public function countByUser(int $userId): int {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM lost_items WHERE user_id = ?');
        $st->execute([$userId]);
        return (int)$st->fetchColumn();
    }

    public static function format(?array $row): ?array {
        if (!$row) return null;
        return [
            'id' => (int)$row['id'],
            'userId' => (int)($row['user_id'] ?? 0),
            'userName' => $row['user_name'] ?? '',
            'itemName' => $row['item_name'],
            'category' => $row['category'],
            'description' => $row['description'] ?? '',
            'keywords' => $row['keywords'] ?? '',
            'dateLost' => $row['date_lost'] ?? null,
            'dateFound' => $row['date_found'] ?? null,
            'location' => $row['location'] ?? '',
            'status' => $row['status'] ?? 'Active',
            'timestamp' => $row['created_at'] ?? '',
            'imagePath' => $row['image_path'] ?? ''
        ];
    }
}
