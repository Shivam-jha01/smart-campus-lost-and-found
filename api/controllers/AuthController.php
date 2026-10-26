<?php
/**
 * AuthController
 * Handles user and admin authentication, session checks, registration, and logout.
 */
class AuthController extends Controller {
    private User $userModel;
    private Admin $adminModel;
    private AuditLog $auditLog;
    private NotificationService $notificationService;

    public function __construct(Request $request, PDO $pdo) {
        parent::__construct($request, $pdo);
        $this->userModel = new User($pdo);
        $this->adminModel = new Admin($pdo);
        $this->auditLog = new AuditLog($pdo);
        $this->notificationService = new NotificationService($pdo);
    }

    public function session(): void {
        $user = $this->getUser();
        $this->ok(['user' => $user]);
    }

    public function login(): void {
        $email = strtolower((string)$this->input('email', ''));
        $pass = (string)$this->input('password', '');
        $isAdmin = (bool)$this->input('isAdmin', false);

        if (!$email || !$pass) {
            $this->fail('Please enter email and password');
        }

        if ($isAdmin) {
            $admin = $this->adminModel->findByEmail($email);
            if (!$admin) {
                $this->fail('Admin credentials not found.');
            }

            $valid = password_verify($pass, $admin['password']) || $pass === $admin['password'];
            if (!$valid) {
                $this->fail('Invalid admin password.');
            }

            if (($admin['role'] ?? '') !== 'admin') {
                $this->fail('This account does not have admin privileges.');
            }

            $userRow = Admin::format($admin);
            Auth::setSession($userRow, true);
            $token = Auth::createToken($userRow);

            $this->auditLog->log('LOGIN', 'Admin ' . $admin['name'] . ' logged in');
            $this->ok(['user' => $userRow, 'token' => $token]);
            return;
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            $this->fail('No account found with this email.');
        }

        $valid = password_verify($pass, $user['password']) || $pass === $user['password'];
        if (!$valid) {
            $this->fail('Invalid password.');
        }

        if (($user['status'] ?? 'active') !== 'active') {
            $this->fail('Your account has been deactivated. Contact admin.');
        }

        $userRow = User::format($user);
        Auth::setSession($userRow, false);
        $token = Auth::createToken($userRow);

        $this->auditLog->log('LOGIN', 'User ' . $user['name'] . ' logged in', (int)$user['id']);
        $this->ok(['user' => $userRow, 'token' => $token]);
    }

    public function register(): void {
        $name = (string)$this->input('name', '');
        $email = strtolower((string)$this->input('email', ''));
        $pass = (string)$this->input('password', '');
        $uid = (string)$this->input('uid', '');
        $dept = (string)$this->input('dept', '');
        $role = (string)$this->input('role', 'student');

        if (!$name || !$email || !$pass || !$uid || !$dept) {
            $this->fail('Please fill all fields');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Invalid email format');
        }

        if ($this->userModel->findByEmail($email) || $this->userModel->findByUid($uid)) {
            $this->fail('Email or University ID already registered');
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $newId = $this->userModel->create([
            'name' => $name,
            'email' => $email,
            'password' => $hash,
            'uid' => $uid,
            'dept' => $dept,
            'role' => $role,
            'status' => 'active'
        ]);

        $userRow = [
            'id' => $newId,
            'name' => $name,
            'email' => $email,
            'uid' => $uid,
            'dept' => $dept,
            'role' => $role,
            'status' => 'active',
            'isAdmin' => false
        ];

        Auth::setSession($userRow, false);
        $token = Auth::createToken($userRow);

        $this->auditLog->log('REGISTER', 'New user registered: ' . $name, $newId);
        $this->notificationService->notifyEmail($newId, $email, 'Welcome to Smart Campus Lost & Found', 'Hello ' . $name . ', your account has been registered successfully.');

        $this->ok(['user' => $userRow, 'token' => $token]);
    }

    public function logout(): void {
        $user = $this->getUser();
        if ($user) {
            $userId = empty($user['isAdmin']) ? (int)$user['id'] : null;
            $this->auditLog->log('LOGOUT', $user['name'] . ' logged out', $userId);
        }
        Auth::destroySession();
        $this->ok();
    }
}
