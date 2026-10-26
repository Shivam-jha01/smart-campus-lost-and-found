<?php
/**
 * MatchService
 * Intelligent item matching and recommendation engine using keyword tokenization and proximity scoring.
 */
class MatchService {
    private PDO $pdo;
    private const STOP_WORDS = [
        'the'=>1,'and'=>1,'for'=>1,'with'=>1,'from'=>1,'this'=>1,'that'=>1,
        'item'=>1,'lost'=>1,'found'=>1,'near'=>1,'area'=>1,'block'=>1,
        'floor'=>1,'room'=>1,'has'=>1,'have'=>1,'inside'=>1
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function matchTokens(string $text): array {
        $text = strtolower(trim($text));
        if ($text === '') return [];
        $parts = preg_split('/[^a-z0-9]+/', $text);
        $out = [];
        foreach ($parts as $p) {
            if (strlen($p) >= 3 && empty(self::STOP_WORDS[$p])) {
                $out[$p] = 1;
            }
        }
        return array_keys($out);
    }

    public function tokenOverlap(string $a, string $b): int {
        return count(array_intersect($this->matchTokens($a), $this->matchTokens($b)));
    }

    public function score(array $lost, array $found): array {
        $score = 0;
        $reasons = [];

        if (strcasecmp(trim($lost['category'] ?? ''), trim($found['category'] ?? '')) === 0) {
            $score += 35;
            $reasons[] = 'same category';
        }

        $lostText = implode(' ', [$lost['item_name'] ?? '', $lost['description'] ?? '', $lost['keywords'] ?? '']);
        $foundText = implode(' ', [$found['item_name'] ?? '', $found['description'] ?? '', $found['keywords'] ?? '']);
        $wordHits = $this->tokenOverlap($lostText, $foundText);
        if ($wordHits > 0) {
            $score += min(35, $wordHits * 10);
            $reasons[] = $wordHits . ' keyword match' . ($wordHits > 1 ? 'es' : '');
        }

        $lostName = strtolower(trim($lost['item_name'] ?? ''));
        $foundName = strtolower(trim($found['item_name'] ?? ''));
        if ($lostName && $foundName && (strpos($lostName, $foundName) !== false || strpos($foundName, $lostName) !== false)) {
            $score += 15;
            $reasons[] = 'similar item name';
        }

        $lostLoc = strtolower(trim($lost['location'] ?? ''));
        $foundLoc = strtolower(trim($found['location'] ?? ''));
        if ($lostLoc && $foundLoc) {
            if ($lostLoc === $foundLoc || strpos($lostLoc, $foundLoc) !== false || strpos($foundLoc, $lostLoc) !== false) {
                $score += 25;
                $reasons[] = 'same location';
            } else {
                $locHits = $this->tokenOverlap($lostLoc, $foundLoc);
                if ($locHits > 0) {
                    $score += min(20, $locHits * 10);
                    $reasons[] = 'nearby location';
                }
            }
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    public function findMatchesForLost(array $lost, ?int $excludeUserId = null, int $limit = 5): array {
        if ($excludeUserId !== null) {
            $st = $this->pdo->prepare('SELECT * FROM found_items WHERE status = \'Available\' AND user_id <> ? ORDER BY id DESC LIMIT 100');
            $st->execute([$excludeUserId]);
        } else {
            $st = $this->pdo->query('SELECT * FROM found_items WHERE status = \'Available\' ORDER BY id DESC LIMIT 100');
        }

        $matches = [];
        foreach ($st->fetchAll() as $found) {
            $m = $this->score($lost, $found);
            if ($m['score'] >= 35) {
                $row = FoundItem::format($found);
                $row['matchScore'] = $m['score'];
                $row['matchReason'] = implode(', ', $m['reasons']);
                $row['lostItemId'] = (int)($lost['id'] ?? 0);
                $row['lostItemName'] = $lost['item_name'] ?? '';
                $matches[] = $row;
            }
        }
        usort($matches, fn($a, $b) => $b['matchScore'] <=> $a['matchScore']);
        return array_slice($matches, 0, $limit);
    }

    public function findMatchesForFound(array $found, ?int $excludeUserId = null, int $limit = 20): array {
        if ($excludeUserId !== null) {
            $st = $this->pdo->prepare('SELECT * FROM lost_items WHERE status = \'Active\' AND user_id <> ? ORDER BY id DESC LIMIT 100');
            $st->execute([$excludeUserId]);
        } else {
            $st = $this->pdo->query('SELECT * FROM lost_items WHERE status = \'Active\' ORDER BY id DESC LIMIT 100');
        }

        $matches = [];
        foreach ($st->fetchAll() as $lost) {
            $m = $this->score($lost, $found);
            if ($m['score'] >= 35) {
                $row = LostItem::format($lost);
                $row['matchScore'] = $m['score'];
                $row['matchReason'] = implode(', ', $m['reasons']);
                $matches[] = $row;
            }
        }
        usort($matches, fn($a, $b) => $b['matchScore'] <=> $a['matchScore']);
        return array_slice($matches, 0, $limit);
    }
}
