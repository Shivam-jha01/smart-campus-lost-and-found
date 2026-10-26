<?php
/**
 * NotificationService
 * Dispatches notifications and queues email updates for users.
 */
class NotificationService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function notifyEmail(?int $userId, string $recipient, string $subject, string $body): void {
        try {
            $st = $this->pdo->prepare('INSERT INTO email_notifications (user_id, recipient, subject, body, status) VALUES (?, ?, ?, ?, ?)');
            $st->execute([$userId, $recipient, $subject, $body, 'queued']);
        } catch (Throwable $e) {
            // Non-breaking notification queue failure
        }
    }

    public function notifyUser(int $userId, string $subject, string $body): void {
        try {
            $st = $this->pdo->prepare('SELECT email FROM users WHERE id = ?');
            $st->execute([$userId]);
            $email = $st->fetchColumn();
            if ($email) {
                $this->notifyEmail($userId, $email, $subject, $body);
            }
        } catch (Throwable $e) {
            // Non-breaking user notification failure
        }
    }
}
