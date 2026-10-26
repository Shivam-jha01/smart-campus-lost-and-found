<?php
/**
 * DashboardController
 * Aggregates statistics, claim notifications, intelligent match alerts, and audit logs for users.
 */
class DashboardController extends Controller {
    private LostItem $lostModel;
    private FoundItem $foundModel;
    private Claim $claimModel;
    private MatchNotification $matchNotificationModel;
    private AuditLog $auditLog;
    private MatchService $matchService;

    public function __construct(Request $request, PDO $pdo) {
        parent::__construct($request, $pdo);
        $this->lostModel = new LostItem($pdo);
        $this->foundModel = new FoundItem($pdo);
        $this->claimModel = new Claim($pdo);
        $this->matchNotificationModel = new MatchNotification($pdo);
        $this->auditLog = new AuditLog($pdo);
        $this->matchService = new MatchService($pdo);
    }

    public function dashboard(): void {
        $u = $this->requireUser();
        $userId = (int)$u['id'];

        $lost = $this->lostModel->countByUser($userId);
        $found = $this->foundModel->countByUser($userId);
        $claims = $this->claimModel->countByUser($userId);
        $total = $this->lostModel->count() + $this->foundModel->count();

        // 1. Fetch approved claims messages (item in security room)
        $st = $this->pdo->prepare('SELECT c.*, f.location AS found_location, f.image_path AS item_image_path 
                                   FROM claims c 
                                   LEFT JOIN found_items f ON f.id = c.item_id 
                                   WHERE c.user_id = ? AND c.status = \'Approved\' 
                                   ORDER BY c.id DESC LIMIT 5');
        $st->execute([$userId]);
        $foundMessages = [];
        foreach ($st->fetchAll() as $r) {
            $foundMessages[] = [
                'itemName' => $r['item_name'],
                'message' => 'Your item has been found. You can take it from the security room.',
                'location' => $r['found_location'] ?? '',
                'timestamp' => $r['created_at'],
                'imagePath' => $r['item_image_path'] ?? ''
            ];
        }

        // 2. Load stored match notifications
        $seenFoundMatches = [];
        foreach ($this->matchNotificationModel->getByUser($userId, 10) as $m) {
            $seenFoundMatches[$m['lostItemId'] . '-' . $m['itemId']] = 1;
            $foundMessages[] = $m;
        }

        // 3. Compute active lost item matches in real-time
        $activeLost = $this->lostModel->findActiveByUser($userId, 20);
        foreach ($activeLost as $lostRow) {
            $matches = $this->matchService->findMatchesForLost($lostRow, $userId, 3);
            foreach ($matches as $match) {
                $key = $lostRow['id'] . '-' . $match['id'];
                if (isset($seenFoundMatches[$key])) {
                    continue;
                }
                $seenFoundMatches[$key] = 1;
                $this->matchNotificationModel->save(
                    $userId,
                    (int)$lostRow['id'],
                    (int)$match['id'],
                    (int)$match['matchScore'],
                    $match['matchReason']
                );
                $foundMessages[] = [
                    'itemName' => $match['itemName'],
                    'message' => 'Your item is available in claim section. You can claim it.',
                    'location' => $match['location'] ?? '',
                    'timestamp' => $match['timestamp'] ?? '',
                    'imagePath' => $match['imagePath'] ?? '',
                    'itemId' => $match['id'],
                    'matchScore' => $match['matchScore'],
                    'matchReason' => $match['matchReason'],
                    'lostItemName' => $match['lostItemName']
                ];
            }
        }

        // 4. Recent audit logs
        $logs = $this->auditLog->getRecent(5);

        $this->ok([
            'stats' => ['lost' => $lost, 'found' => $found, 'claims' => $claims, 'total' => $total],
            'foundMessages' => $foundMessages,
            'logs' => $logs
        ]);
    }
}
