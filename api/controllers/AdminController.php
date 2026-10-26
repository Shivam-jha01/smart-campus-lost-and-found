<?php
/**
 * AdminController
 * Manages administrative dashboard, moderation, claim approvals/rejections, user accounts, and audit logs.
 */
class AdminController extends Controller {
    private User $userModel;
    private LostItem $lostModel;
    private FoundItem $foundModel;
    private Claim $claimModel;
    private AuditLog $auditLog;
    private NotificationService $notificationService;

    public function __construct(Request $request, PDO $pdo) {
        parent::__construct($request, $pdo);
        $this->userModel = new User($pdo);
        $this->lostModel = new LostItem($pdo);
        $this->foundModel = new FoundItem($pdo);
        $this->claimModel = new Claim($pdo);
        $this->auditLog = new AuditLog($pdo);
        $this->notificationService = new NotificationService($pdo);
    }

    public function dashboard(): void {
        $this->requireAdmin();

        $stats = [
            'users' => $this->userModel->count(),
            'lost' => $this->lostModel->count(),
            'found' => $this->foundModel->count(),
            'claims' => $this->claimModel->countPending()
        ];

        $this->ok([
            'stats' => $stats,
            'lost' => $this->lostModel->findRecent(10),
            'found' => $this->foundModel->findRecent(10)
        ]);
    }

    public function deleteItem(): void {
        $u = $this->requireAdmin();
        $type = (string)$this->input('type', '');
        $id = (int)$this->input('id', 0);

        if ($type === 'lost') {
            $this->lostModel->adminDelete($id);
        } else {
            $this->foundModel->adminDelete($id);
        }

        $this->auditLog->log('ADMIN_DELETE', "Admin {$u['name']} removed a {$type} item");
        $this->ok();
    }

    public function claims(): void {
        $this->requireAdmin();
        $claims = $this->claimModel->getAll();
        $this->ok(['claims' => $claims]);
    }

    public function approveClaim(): void {
        $u = $this->requireAdmin();
        $id = (int)$this->input('id', 0);

        $claim = $this->claimModel->findById($id);
        if (!$claim) {
            $this->fail('Claim not found');
        }

        $this->claimModel->updateStatus($id, 'Approved');
        $this->foundModel->markClaimed((int)$claim['item_id']);
        $this->lostModel->resolve((int)$claim['user_id'], $claim['item_name'], $claim['lost_item_id'] ? (int)$claim['lost_item_id'] : null);

        $this->auditLog->log('CLAIM_APPROVED', "Claim #{$id} for \"{$claim['item_name']}\" approved by admin {$u['name']}");
        $this->notificationService->notifyUser(
            (int)$claim['user_id'],
            'Item found: ' . $claim['item_name'],
            'Your item has been found. You can take it from the security room.'
        );

        $this->ok();
    }

    public function rejectClaim(): void {
        $u = $this->requireAdmin();
        $id = (int)$this->input('id', 0);

        $claim = $this->claimModel->findById($id);
        if (!$claim) {
            $this->fail('Claim not found');
        }

        $this->claimModel->updateStatus($id, 'Rejected');
        $this->auditLog->log('CLAIM_REJECTED', "Claim #{$id} for \"{$claim['item_name']}\" rejected by admin {$u['name']}");
        $this->notificationService->notifyUser(
            (int)$claim['user_id'],
            'Claim rejected: ' . $claim['item_name'],
            'Your claim for ' . $claim['item_name'] . ' was reviewed and rejected by the admin.'
        );

        $this->ok();
    }

    public function users(): void {
        $this->requireAdmin();
        $users = $this->userModel->getAllNonAdmin();
        $this->ok(['users' => $users]);
    }

    public function toggleUserStatus(): void {
        $u = $this->requireAdmin();
        $id = (int)$this->input('id', 0);

        $user = $this->userModel->findById($id);
        if (!$user || !empty($user['is_admin'])) {
            $this->fail('User not found');
        }

        $newStatus = ($user['status'] ?? 'active') === 'active' ? 'inactive' : 'active';
        $this->userModel->updateStatus($id, $newStatus);

        $this->auditLog->log('USER_STATUS', "User {$user['name']} status changed to {$newStatus} by admin {$u['name']}");
        $this->ok();
    }

    public function logs(): void {
        $this->requireAdmin();
        $logs = $this->auditLog->getAll(200);
        $this->ok(['logs' => $logs]);
    }
}
