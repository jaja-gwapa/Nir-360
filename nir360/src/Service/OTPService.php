<?php
declare(strict_types=1);

class OTPService
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    public function generateAndStore(int $userId): string
    {
        $code = (string)random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['otp_expiry_minutes'] * 60);

        $this->pdo->prepare('INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)')
            ->execute([$userId, $code, $expiresAt]);

        return $code;
    }

    public function verify(int $userId, string $code): array
    {
        $row = $this->pdo->prepare(
            'SELECT id, code, expires_at, attempts, locked_until FROM otp_codes WHERE user_id = ? ORDER BY id DESC LIMIT 1'
        );
        $row->execute([$userId]);
        $otp = $row->fetch();

        if (!$otp) {
            return ['success' => false, 'error' => 'No OTP found. Request a new one.'];
        }

        if ($otp['locked_until'] && strtotime($otp['locked_until']) > time()) {
            $mins = (int)ceil((strtotime($otp['locked_until']) - time()) / 60);
            return ['success' => false, 'error' => "Too many attempts. Try again in {$mins} minutes."];
        }

        if (strtotime($otp['expires_at']) < time()) {
            return ['success' => false, 'error' => 'OTP expired. Request a new one.'];
        }

        $attempts = (int)$otp['attempts'];
        $maxAttempts = $this->config['otp_max_attempts'];
        if ($attempts >= $maxAttempts) {
            $lockUntil = date('Y-m-d H:i:s', time() + $this->config['otp_lock_minutes'] * 60);
            $this->pdo->prepare('UPDATE otp_codes SET locked_until = ? WHERE id = ?')->execute([$lockUntil, $otp['id']]);
            return ['success' => false, 'error' => 'Too many attempts. Account locked for ' . $this->config['otp_lock_minutes'] . ' minutes.'];
        }

        if (!hash_equals($otp['code'], $code)) {
            $this->pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$otp['id']]);
            $left = $maxAttempts - $attempts - 1;
            return ['success' => false, 'error' => 'Invalid code.' . ($left > 0 ? " {$left} attempts left." : '')];
        }

        $this->pdo->prepare(
            "UPDATE users SET is_phone_verified = 1, is_verified = 1, verification_status = 'verified' WHERE id = ?"
        )->execute([$userId]);
        return ['success' => true];
    }

    public function canResend(int $userId): array
    {
        $row = $this->pdo->prepare(
            'SELECT created_at FROM otp_codes WHERE user_id = ? ORDER BY id DESC LIMIT 1'
        );
        $row->execute([$userId]);
        $last = $row->fetch();
        if (!$last) {
            return ['allowed' => true, 'wait_seconds' => 0];
        }
        $cooldown = $this->config['otp_resend_cooldown_seconds'];
        $elapsed = time() - strtotime($last['created_at']);
        if ($elapsed >= $cooldown) {
            return ['allowed' => true, 'wait_seconds' => 0];
        }
        return ['allowed' => false, 'wait_seconds' => $cooldown - $elapsed];
    }

    /** For DEV MODE: return OTP in response when APP_ENV=local */
    public function getLastCodeForUser(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT code FROM otp_codes WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? $row['code'] : null;
    }
}
