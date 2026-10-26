<?php
/**
 * ClaimController
 * Manages claiming found items, verifying eligibility, and processing user submissions.
 */
class ClaimController extends Controller {
    private FoundItem $foundModel;
    private Claim $claimModel;
    private AuditLog $auditLog;
    private MatchService $matchService;
    private NotificationService $notificationService;

    public function __construct(Request $request, PDO $pdo) {
        parent::__construct($request, $pdo);
        $this->foundModel = new FoundItem($pdo);
        $this->claimModel = new Claim($pdo);
        $this->auditLog = new AuditLog($pdo);
        $this->matchService = new MatchService($pdo);
        $this->notificationService = new NotificationService($pdo);
    }

    public function claimInfo(): void {
        $u = $this->requireUser();
        $id = (int)$this->input('itemId', 0);
        $detailsOnly = (bool)$this->input('detailsOnly', false);

        $item = $this->foundModel->findById($id);
        if (!$item) {
            $this->fail('Item not found', 404);
        }

        if (!$detailsOnly && $item['status'] !== 'Available') {
            $this->fail('This item is no longer available for claiming');
        }

        if (!$detailsOnly) {
            if ($this->claimModel->hasPending($id, (int)$u['id'])) {
                $this->fail('You already have a pending claim for this item');
            }
        }

        $this->ok(['item' => FoundItem::format($item)]);
    }

    public function submitClaim(): void {
        $u = $this->requireUser();
        $id = (int)$this->input('itemId', 0);
        $details = (string)$this->input('details', '');

        if (!$details) {
            $this->fail('Please provide claim details');
        }

        if (!empty($u['isAdmin'])) {
            $this->fail('Admins cannot submit item claims. Please login as a user account.', 403);
        }

        $item = $this->foundModel->findById($id);
        if (!$item || $item['status'] !== 'Available') {
            $this->fail('This item is no longer available for claiming');
        }

        $linkedLostId = null;
        $matches = $this->matchService->findMatchesForFound($item, null, 20);
        foreach ($matches as $lostMatch) {
            if ((int)$lostMatch['userId'] === (int)$u['id']) {
                $linkedLostId = (int)$lostMatch['id'];
                break;
            }
        }

        $imagePath = ImageService::save($this->input('imageData'), 'claim');

        $this->claimModel->create([
            'item_id' => $id,
            'lost_item_id' => $linkedLostId,
            'item_name' => $item['item_name'],
            'user_id' => (int)$u['id'],
            'user_name' => $u['name'],
            'details' => $details,
            'image_path' => $imagePath
        ]);

        $this->auditLog->log('CLAIM_SUBMITTED', "Claim submitted by {$u['name']} for \"{$item['item_name']}\"", (int)$u['id']);
        $this->notificationService->notifyEmail(
            (int)$u['id'],
            $u['email'],
            'Claim submitted: ' . $item['item_name'],
            'Your claim for ' . $item['item_name'] . ' has been submitted and is waiting for admin review.'
        );

        $this->ok();
    }
}
