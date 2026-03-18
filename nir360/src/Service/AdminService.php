<?php
declare(strict_types=1);

class AdminService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPendingReviews(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT vr.id, vr.user_id, vr.status, vr.reason, vr.created_at, u.email, u.mobile
             FROM verification_reviews vr
             JOIN users u ON u.id = vr.user_id
             WHERE vr.status = ? ORDER BY vr.created_at DESC'
        );
        $stmt->execute(['pending']);
        return $stmt->fetchAll();
    }

    public function approve(int $reviewId, int $adminId): void
    {
        $this->pdo->prepare('UPDATE verification_reviews SET status = ?, admin_id = ?, reviewed_at = NOW() WHERE id = ?')
            ->execute(['approved', $adminId, $reviewId]);
        $stmt = $this->pdo->prepare('SELECT user_id FROM verification_reviews WHERE id = ?');
        $stmt->execute([$reviewId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $userId = (int) $u['user_id'];
            $this->pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$userId]);
            $smsPath = dirname(__DIR__) . '/../config/sms.php';
            if (file_exists($smsPath)) {
                require_once $smsPath;
                $m = $this->pdo->prepare('SELECT mobile FROM users WHERE id = ?');
                $m->execute([$userId]);
                $row = $m->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['mobile']) && function_exists('sendSMS')) {
                    sendSMS($row['mobile'], 'NIR360: Your account verification has been approved. You can now access all features.');
                }
            }
        }
    }

    public function reject(int $reviewId, int $adminId, string $reason): void
    {
        $this->pdo->prepare('UPDATE verification_reviews SET status = ?, admin_id = ?, reason = ?, reviewed_at = NOW() WHERE id = ?')
            ->execute(['rejected', $adminId, $reason, $reviewId]);
    }

    /** List all users with full_name, is_active, verification_status, government_id_path for admin table */
    public function getUsers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT u.id, u.username, u.email, u.mobile, u.role, u.is_active, u.verification_status, u.government_id_path, u.created_at,
                    p.full_name
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.id
             ORDER BY u.created_at DESC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['is_active'] = (int)($r['is_active'] ?? 1);
        }
        return $rows;
    }

    /** Set user active (1) or inactive (0). */
    public function setUserActive(int $userId, int $active): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Admin reset password for a user. */
    public function adminResetPassword(int $userId, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) {
            return false;
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Get path to user's government ID image (for admin view). Returns null if none. */
    public function getUserGovernmentIdPath(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT government_id_path FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $path = $row['government_id_path'] ?? null;
        if ($path === null || $path === '') {
            return null;
        }
        $base = dirname(__DIR__, 2);
        $full = $base . '/' . ltrim($path, '/\\');
        return is_file($full) ? $full : null;
    }

    /** Get mobile numbers of all admin users (for SMS notifications). */
    public function getAdminMobiles(): array
    {
        $stmt = $this->pdo->query("SELECT mobile FROM users WHERE role = 'admin' AND mobile IS NOT NULL AND mobile != ''");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'mobile');
    }

    /** List users with role = responder */
    public function getResponders(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, username, email, mobile, created_at FROM users WHERE role = 'responder' ORDER BY username"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Create a responder account (admin-only). Returns ['success' => true, 'id' => x] or ['success' => false, 'error' => '...'] */
    public function createResponder(string $username, string $email, string $mobile, string $password): array
    {
        $username = trim($username);
        $email = trim($email);
        $mobile = preg_replace('/\D/', '', $mobile);
        if ($username === '') {
            return ['success' => false, 'error' => 'Username is required.'];
        }
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'error' => 'Username must be 3–50 characters.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email.'];
        }
        if (strlen($mobile) !== 11) {
            return ['success' => false, 'error' => 'Mobile must be exactly 11 digits.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = ? OR email = ? OR mobile = ?');
        $stmt->execute([$username, $email, $mobile]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Username, email, or mobile already in use.'];
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (username, email, mobile, password_hash, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, $email, $mobile, $hash, 'responder']);
        return ['success' => true, 'id' => (int) $this->pdo->lastInsertId()];
    }

    /** Create a user/admin/responder account (admin-only). */
    public function createUser(string $username, string $email, string $mobile, string $password, string $role = 'user'): array
    {
        $username = trim($username);
        $email = trim($email);
        $mobile = preg_replace('/\D/', '', $mobile);
        $role = trim(strtolower($role));

        if ($username === '') {
            return ['success' => false, 'error' => 'Username is required.'];
        }
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'error' => 'Username must be 3–50 characters.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address.'];
        }
        if (strlen($mobile) !== 11) {
            return ['success' => false, 'error' => 'Mobile must be exactly 11 digits.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        if (!in_array($role, ['user', 'responder', 'admin'], true)) {
            return ['success' => false, 'error' => 'Invalid role selected.'];
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = ? OR email = ? OR mobile = ?');
        $stmt->execute([$username, $email, $mobile]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Username, email, or mobile already in use.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (username, email, mobile, password_hash, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, $email, $mobile, $hash, $role]);

        return ['success' => true, 'id' => (int) $this->pdo->lastInsertId()];
    }
}
