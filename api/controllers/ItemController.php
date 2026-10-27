<?php
/**
 * ItemController
 * Manages item browsing, reporting lost and found items, retrieving user reports, and deletions.
 * Enforces input sanitization, category validation, and text bounds.
 */
class ItemController extends Controller {
    private LostItem $lostModel;
    private FoundItem $foundModel;
    private Claim $claimModel;
    private MatchNotification $matchNotificationModel;
    private AuditLog $auditLog;
    private MatchService $matchService;

    private const VALID_CATEGORIES = [
        'Phone', 'Wallet', 'Bag', 'Laptop', 'Books', 'USB Drive', 'Keys', 'ID Card', 'Others'
    ];

    public function __construct(Request $request, PDO $pdo) {
        parent::__construct($request, $pdo);
        $this->lostModel = new LostItem($pdo);
        $this->foundModel = new FoundItem($pdo);
        $this->claimModel = new Claim($pdo);
        $this->matchNotificationModel = new MatchNotification($pdo);
        $this->auditLog = new AuditLog($pdo);
        $this->matchService = new MatchService($pdo);
    }

    public function browse(): void {
        $category = strip_tags(trim((string)$this->input('category', '')));
        $status = strip_tags(trim((string)$this->input('status', '')));
        $search = strip_tags(trim((string)$this->input('search', '')));

        if (strlen($search) > 100) {
            $search = substr($search, 0, 100);
        }

        $items = $this->foundModel->search([
            'category' => $category,
            'status' => $status,
            'search' => $search
        ]);

        $this->ok(['items' => $items]);
    }

    public function submitLost(): void {
        $u = $this->requireUser();
        $name = strip_tags(trim((string)$this->input('itemName', '')));
        $cat = trim((string)$this->input('category', ''));
        $date = trim((string)$this->input('dateLost', ''));
        $loc = strip_tags(trim((string)$this->input('location', '')));
        $desc = strip_tags(trim((string)$this->input('description', '')));
        $keywords = strip_tags(trim((string)$this->input('keywords', '')));

        if (!$name || !$cat || !$date || !$loc) {
            $this->fail('Please fill all required fields');
        }

        if (strlen($name) < 2 || strlen($name) > 150) {
            $this->fail('Item name must be between 2 and 150 characters');
        }

        if (!in_array($cat, self::VALID_CATEGORIES, true)) {
            $this->fail('Invalid category selected');
        }

        if (strlen($loc) < 2 || strlen($loc) > 150) {
            $this->fail('Location must be between 2 and 150 characters');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->fail('Invalid date format. Use YYYY-MM-DD');
        }

        if (strlen($desc) > 2000) {
            $this->fail('Description is too long (maximum 2000 characters)');
        }

        if (strlen($keywords) > 500) {
            $this->fail('Keywords string is too long (maximum 500 characters)');
        }

        if ($this->lostModel->exists((int)$u['id'], $name, $cat)) {
            $this->fail('You have already registered an active lost report for this item.');
        }

        $imagePath = ImageService::save($this->input('imageData'), 'lost');

        $lostId = $this->lostModel->create([
            'user_id' => $u['id'],
            'user_name' => $u['name'],
            'item_name' => $name,
            'category' => $cat,
            'description' => $desc,
            'keywords' => $keywords,
            'image_path' => $imagePath,
            'date_lost' => $date,
            'location' => $loc
        ]);

        $lost = [
            'id' => $lostId,
            'user_id' => $u['id'],
            'user_name' => $u['name'],
            'item_name' => $name,
            'category' => $cat,
            'description' => $desc,
            'keywords' => $keywords,
            'image_path' => $imagePath,
            'date_lost' => $date,
            'location' => $loc,
            'status' => 'Active',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $matches = $this->matchService->findMatchesForLost($lost, (int)$u['id'], 5);
        foreach ($matches as $match) {
            $this->matchNotificationModel->save(
                (int)$u['id'],
                $lost['id'],
                $match['id'],
                $match['matchScore'],
                $match['matchReason']
            );
        }

        $matchCount = count($matches);
        $matchSuffix = $matchCount ? " with {$matchCount} possible match(es)" : '';
        $this->auditLog->log('REPORT_LOST', "Lost item reported: \"{$name}\" by {$u['name']}{$matchSuffix}", (int)$u['id']);

        $this->ok(['matches' => $matches]);
    }

    public function submitFound(): void {
        $u = $this->requireUser();
        $name = strip_tags(trim((string)$this->input('itemName', '')));
        $cat = trim((string)$this->input('category', ''));
        $date = trim((string)$this->input('dateFound', ''));
        $loc = strip_tags(trim((string)$this->input('location', '')));
        $desc = strip_tags(trim((string)$this->input('description', '')));
        $keywords = strip_tags(trim((string)$this->input('keywords', '')));

        if (!$name || !$cat || !$date || !$loc) {
            $this->fail('Please fill all required fields');
        }

        if (strlen($name) < 2 || strlen($name) > 150) {
            $this->fail('Item name must be between 2 and 150 characters');
        }

        if (!in_array($cat, self::VALID_CATEGORIES, true)) {
            $this->fail('Invalid category selected');
        }

        if (strlen($loc) < 2 || strlen($loc) > 150) {
            $this->fail('Location must be between 2 and 150 characters');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->fail('Invalid date format. Use YYYY-MM-DD');
        }

        if (strlen($desc) > 2000) {
            $this->fail('Description is too long (maximum 2000 characters)');
        }

        if (strlen($keywords) > 500) {
            $this->fail('Keywords string is too long (maximum 500 characters)');
        }

        $imagePath = ImageService::save($this->input('imageData'), 'found');

        $foundId = $this->foundModel->create([
            'user_id' => $u['id'],
            'user_name' => $u['name'],
            'item_name' => $name,
            'category' => $cat,
            'description' => $desc,
            'keywords' => $keywords,
            'image_path' => $imagePath,
            'date_found' => $date,
            'location' => $loc
        ]);

        $found = [
            'id' => $foundId,
            'user_id' => $u['id'],
            'user_name' => $u['name'],
            'item_name' => $name,
            'category' => $cat,
            'description' => $desc,
            'keywords' => $keywords,
            'image_path' => $imagePath,
            'date_found' => $date,
            'location' => $loc,
            'status' => 'Available',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $matches = [];
        $lostMatches = $this->matchService->findMatchesForFound($found, (int)$u['id'], 20);
        foreach ($lostMatches as $lostRow) {
            $this->matchNotificationModel->save(
                $lostRow['userId'],
                $lostRow['id'],
                $found['id'],
                $lostRow['matchScore'],
                $lostRow['matchReason']
            );
            $matches[] = [
                'lostItemId' => $lostRow['id'],
                'lostItemName' => $lostRow['itemName'],
                'userId' => $lostRow['userId'],
                'matchScore' => $lostRow['matchScore'],
                'matchReason' => $lostRow['matchReason']
            ];
        }

        $matchCount = count($matches);
        $matchSuffix = $matchCount ? " matching {$matchCount} lost report(s)" : '';
        $this->auditLog->log('REPORT_FOUND', "Found item reported: \"{$name}\" by {$u['name']}{$matchSuffix}", (int)$u['id']);

        $this->ok(['matches' => $matches]);
    }

    public function myReports(): void {
        $u = $this->requireUser();
        $userId = (int)$u['id'];

        $lost = $this->lostModel->findByUser($userId);
        $found = $this->foundModel->findByUser($userId);
        $claims = $this->claimModel->findByUser($userId);
        $matches = $this->matchNotificationModel->getByUser($userId, 20);

        $this->ok([
            'lost' => $lost,
            'found' => $found,
            'claims' => $claims,
            'matches' => $matches
        ]);
    }

    public function deleteLost(): void {
        $u = $this->requireUser();
        $id = (int)$this->input('id', 0);
        $this->lostModel->deleteByUser($id, (int)$u['id']);
        $this->auditLog->log('DELETE_REPORT', 'Lost item report deleted', (int)$u['id']);
        $this->ok();
    }
}
