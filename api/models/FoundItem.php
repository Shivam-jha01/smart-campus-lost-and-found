<?php
/**
 * FoundItem Model
 * Handles reporting, searching, filtering, and status changes for found items.
 */
class FoundItem extends BaseModel {
    public function findById(int $id): ?array {
        $st = $this->pdo->prepare('SELECT * FROM found_items WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByUser(int $userId): array {
        $st = $this->pdo->prepare('SELECT * FROM found_items WHERE user_id = ? ORDER BY id DESC');
        $st->execute([$userId]);
        return array_map([self::class, 'format'], $st->fetchAll());
    }

    public function findRecent(int $limit = 10): array {
        $st = $this->pdo->query('SELECT * FROM found_items ORDER BY id DESC LIMIT ' . (int)$limit);
        return array_map([self::class, 'format'], $st->fetchAll());
    }

    public function search(array $filters): array {
        $conditions = [];
        $params = [];

        $category = $filters['category'] ?? '';
        $status = $filters['status'] ?? '';
        $search = $filters['search'] ?? '';

        if ($category && $category !== 'all') {
            $conditions[] = 'category = ?';
            $params[] = $category;
        }

        if ($status && $status !== 'all') {
            $conditions[] = 'status = ?';
            $params[] = $status;
        }

        if ($search) {
            $like = '%' . $search . '%';
            $conditions[] = '(item_name ILIKE ? OR description ILIKE ? OR location ILIKE ? OR keywords ILIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT * FROM found_items';
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return array_map([self::class, 'format'], $st->fetchAll());
    }

    public function create(array $data): int {
        return $this->insert('found_items', [
            'user_id' => $data['user_id'],
            'user_name' => $data['user_name'],
            'item_name' => $data['item_name'],
            'category' => $data['category'],
            'description' => $data['description'] ?? '',
            'keywords' => $data['keywords'] ?? '',
            'image_path' => $data['image_path'] ?? '',
            'date_found' => $data['date_found'],
            'location' => $data['location'],
            'status' => 'Available'
        ]);
    }

    public function adminDelete(int $id): bool {
        return $this->delete('found_items', 'id = ?', [$id]);
    }

    public function markClaimed(int $id): bool {
        return $this->update('found_items', ['status' => 'Claimed'], 'id = ?', [$id]);
    }

    public function count(): int {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM found_items')->fetchColumn();
    }

    public function countByUser(int $userId): int {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM found_items WHERE user_id = ?');
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
            'status' => $row['status'] ?? 'Available',
            'timestamp' => $row['created_at'] ?? '',
            'imagePath' => $row['image_path'] ?? ''
        ];
    }
}
