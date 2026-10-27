<?php
/**
 * AuthController
 * Handles user and admin authentication, session checks, registration, and logout.
 * Implements rate limiting, secure password hashing, and input validation.
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
        $email = strtolower(trim((string)$this->input('email', '')));
        $pass = (string)$this->input('password', '');
        $isAdmin = (bool)$this->input('isAdmin', false);

        if (!$email || !$pass) {
            $this->fail('Please enter email and password');
        }

        if (strlen($pass) > 128) {
            $this->fail('Password exceeds maximum allowed length');
        }

        $this->checkRateLimit($email);

        if ($isAdmin) {
            $admin = $this->adminModel->findByEmail($email);
            if (!$admin) {
                $this->auditLog->log('LOGIN_FAILED', 'Failed admin login attempt for: ' . $email);
                $this->fail('Invalid admin credentials.');
            }

            $valid = password_verify($pass, $admin['password']);
            if (!$valid && $pass === $admin['password']) {
                $valid = true;
                $newHash = password_hash($pass, PASSWORD_DEFAULT);
                $this->pdo->prepare('UPDATE admins SET password = ? WHERE id = ?')->execute([$newHash, $admin['id']]);
            }

            if (!$valid) {
                $this->auditLog->log('LOGIN_FAILED', 'Failed admin login attempt for: ' . $email);
                $this->fail('Invalid admin credentials.');
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
            $this->auditLog->log('LOGIN_FAILED', 'Failed user login attempt for: ' . $email);
            $this->fail('Invalid email or password.');
        }

        $valid = password_verify($pass, $user['password']);
        if (!$valid && $pass === $user['password']) {
            $valid = true;
            $newHash = password_hash($pass, PASSWORD_DEFAULT);
            $this->pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$newHash, $user['id']]);
        }

        if (!$valid) {
            $this->auditLog->log('LOGIN_FAILED', 'Failed user login attempt for: ' . $email);
            $this->fail('Invalid email or password.');
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
        $name = strip_tags(trim((string)$this->input('name', '')));
        $email = strtolower(trim((string)$this->input('email', '')));
        $pass = (string)$this->input('password', '');
        $uid = trim((string)$this->input('uid', ''));
        $dept = strip_tags(trim((string)$this->input('dept', '')));
        $role = strtolower(trim((string)$this->input('role', 'student')));

        if (!$name || !$email || !$pass || !$uid || !$dept) {
            $this->fail('Please fill all required fields');
        }

        if (strlen($name) > 100) {
            $this->fail('Name is too long (maximum 100 characters)');
        }

        if (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Please provide a valid email address');
        }

        if (strlen($pass) < 6) {
            $this->fail('Password must be at least 6 characters');
        }

        if (strlen($pass) > 128) {
            $this->fail('Password is too long (maximum 128 characters)');
        }

        if (strlen($uid) > 50 || !preg_match('/^[a-z0-9\-]+$/i', $uid)) {
            $this->fail('University ID must contain only alphanumeric characters and hyphens');
        }

        if (strlen($dept) > 100) {
            $this->fail('Department name is too long (maximum 100 characters)');
        }

        if (!in_array($role, ['student', 'faculty', 'staff'], true)) {
            $role = 'student';
        }

        if ($this->userModel->findByEmail($email) || $this->userModel->findByUid($uid)) {
            $this->fail('Email or University ID is already registered');
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

    private function checkRateLimit(string $email): void {
        try {
            $st = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'LOGIN_FAILED' AND description LIKE ? AND created_at > NOW() - INTERVAL '15 minutes'");
            $st->execute(['%' . $email . '%']);
            $failures = (int)$st->fetchColumn();
            if ($failures >= 5) {
                Response::fail('Too many failed login attempts. Please wait 15 minutes before trying again.', 429);
            }
        } catch (Throwable $e) {
            // Ignore rate limit check exceptions so logins aren't blocked by log issues
        }
    }
}
