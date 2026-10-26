<?php
/**
 * MatchNotification Model
 * Manages item match recommendation records and alerts for users.
 */
class MatchNotification extends BaseModel {
    public function save(int $userId, int $lostItemId, int $foundItemId, int $score, string $reason): void {
        $sql = 'INSERT INTO item_match_notifications (user_id, lost_item_id, found_item_id, match_score, match_reason) 
                VALUES (?, ?, ?, ?, ?) 
                ON CONFLICT (user_id, lost_item_id, found_item_id) 
                DO UPDATE SET match_score = EXCLUDED.match_score, match_reason = EXCLUDED.match_reason, created_at = CURRENT_TIMESTAMP';
        $st = $this->pdo->prepare($sql);
        $st->execute([$userId, $lostItemId, $foundItemId, $score, $reason]);
    }

    public function getByUser(int $userId, int $limit = 10): array {
        $sql = 'SELECT n.*, l.item_name AS lost_item_name, f.item_name AS found_item_name, f.location AS found_location, 
                       f.image_path AS item_image_path, f.date_found, f.status AS found_status, l.status AS lost_status 
                FROM item_match_notifications n 
                JOIN lost_items l ON l.id = n.lost_item_id 
                JOIN found_items f ON f.id = n.found_item_id 
                WHERE n.user_id = ? AND l.status = \'Active\' AND f.status = \'Available\' 
                ORDER BY n.id DESC LIMIT ' . (int)$limit;
        $st = $this->pdo->prepare($sql);
        $st->execute([$userId]);

        $messages = [];
        foreach ($st->fetchAll() as $r) {
            $messages[] = [
                'itemName' => $r['found_item_name'],
                'message' => 'Your item is available in claim section. You can claim it.',
                'location' => $r['found_location'] ?? '',
                'timestamp' => $r['created_at'],
                'imagePath' => $r['item_image_path'] ?? '',
                'itemId' => (int)$r['found_item_id'],
                'matchScore' => (int)$r['match_score'],
                'matchReason' => $r['match_reason'] ?? '',
                'lostItemId' => (int)$r['lost_item_id'],
                'lostItemName' => $r['lost_item_name'],
                'dateFound' => $r['date_found'] ?? ''
            ];
        }
        return $messages;
    }
}
