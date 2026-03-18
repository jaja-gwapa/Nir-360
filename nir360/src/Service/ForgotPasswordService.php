<?php
declare(strict_types=1);

/**
 * Forgot password: token creation, email via PHPMailer, validation, password update.
 */
class ForgotPasswordService
{
    private PDO $pdo;
    private array $config;
    private string $phpmailerSrc;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->phpmailerSrc = $config['phpmailer_src'] ?? dirname(dirname(__DIR__)) . '/../PHPMailer/src';
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([trim($email)]);
        return (bool) $stmt->fetch();
    }

    /** Resolve email from input: if it looks like email return it; else look up by mobile. Returns null if mobile not found. */
    public function resolveEmailFromInput(string $emailOrMobile): ?string
    {
        $emailOrMobile = trim($emailOrMobile);
        if (str_contains($emailOrMobile, '@') && filter_var($emailOrMobile, FILTER_VALIDATE_EMAIL)) {
            return $emailOrMobile;
        }
        $stmt = $this->pdo->prepare('SELECT email FROM users WHERE mobile = ? LIMIT 1');
        $stmt->execute([$emailOrMobile]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string)$row['email'] : null;
    }

    /**
     * Create a reset token and send email. Returns ['success' => true] or ['success' => false, 'error' => '...'].
     * $resetLinkBase: full URL to reset_password.php without query (e.g. http://localhost/your-system/reset_password.php).
     */
    public function requestReset(string $email, string $resetLinkBase): array
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address.'];
        }

        if (!$this->emailExists($email)) {
            return ['success' => true];
        }

        $token = bin2hex(random_bytes(32));
        $expiryMinutes = (int) ($this->config['password_reset_expiry_minutes'] ?? 15);
        $expiresAt = date('Y-m-d H:i:s', time() + $expiryMinutes * 60);

        $this->pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
        $stmt = $this->pdo->prepare('INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())');
        if (!$stmt->execute([$email, $token, $expiresAt])) {
            return ['success' => false, 'error' => 'Could not save reset token.'];
        }

        $resetLink = rtrim($resetLinkBase, '?') . '?token=' . $token;
        $sendResult = $this->sendResetEmail($email, $resetLink, $expiryMinutes);
        if ($sendResult !== true) {
            $this->pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
            return ['success' => false, 'error' => $sendResult];
        }

        return ['success' => true];
    }

    /**
     * Send reset email. Returns true on success, or an error message string on failure.
     */
    private function sendResetEmail(string $toEmail, string $resetLink, int $expiryMinutes): bool|string
    {
        $mailConfig = $this->config['mail'] ?? [];
        $smtpHost = trim((string)($mailConfig['smtp_host'] ?? ''));
        $smtpUser = trim((string)($mailConfig['smtp_username'] ?? ''));
        $smtpPass = trim((string)($mailConfig['smtp_password'] ?? '')); // trim in case App Password was pasted with spaces
        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '') {
            if (function_exists('error_log')) {
                error_log('[NIR360 ForgotPassword] Mail not sent: missing smtp_host, smtp_username, or smtp_password.');
            }
            return 'Mail not configured. Check config/mail.local.php (smtp_host, smtp_username, smtp_password).';
        }

        $phpmailerSrc = realpath($this->phpmailerSrc) ?: $this->phpmailerSrc;
        if (!is_dir($phpmailerSrc) || !is_readable($phpmailerSrc . '/PHPMailer.php')) {
            return 'PHPMailer not found at: ' . $this->phpmailerSrc . '. Check phpmailer_src in config/app.php.';
        }

        require_once $phpmailerSrc . '/Exception.php';
        require_once $phpmailerSrc . '/PHPMailer.php';
        require_once $phpmailerSrc . '/SMTP.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)($mailConfig['smtp_port'] ?? 587);
            // Avoid TLS verify failures on local/XAMPP (optional; remove on production if you use proper certs)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mail->setFrom($mailConfig['from_email'] ?? $smtpUser, $mailConfig['from_name'] ?? 'NIR360');
            $mail->addAddress($toEmail);
            $mail->Subject = 'Password Reset Request – NIR360';
            $mail->Body = "You requested a password reset. Click the link below to reset your password. This link will expire in {$expiryMinutes} minutes.\n\n" . $resetLink . "\n\nIf you did not request this, please ignore this email.";
            $mail->CharSet = 'UTF-8';
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[NIR360 ForgotPassword] PHPMailer: ' . $e->getMessage());
            }
            return 'Could not send email: ' . $e->getMessage() . ' Check Gmail App Password and 2-Step Verification.';
        }
    }

    /**
     * Returns ['valid' => true, 'email' => '...'] or ['valid' => false, 'error' => '...'].
     */
    public function validateToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['valid' => false, 'error' => 'Invalid or expired reset link.'];
        }
        $stmt = $this->pdo->prepare('SELECT email, expires_at FROM password_resets WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['valid' => false, 'error' => 'Invalid or expired reset link.'];
        }
        if (strtotime($row['expires_at']) < time()) {
            $this->pdo->prepare('DELETE FROM password_resets WHERE token = ?')->execute([$token]);
            return ['valid' => false, 'error' => 'This reset link has expired.'];
        }
        return ['valid' => true, 'email' => $row['email']];
    }

    /**
     * Update password and delete token. Returns ['success' => true] or ['success' => false, 'error' => '...'].
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        $validation = $this->validateToken($token);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        $authService = new \AuthService($this->pdo);
        $check = $authService->validatePasswordStrength($newPassword);
        if (!$check['valid']) {
            return ['success' => false, 'error' => $check['message'] ?? 'Password does not meet requirements.'];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $email = $validation['email'];
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE LOWER(email) = LOWER(?)');
        if (!$stmt->execute([$hash, $email])) {
            return ['success' => false, 'error' => 'Could not update password.'];
        }

        $this->pdo->prepare('DELETE FROM password_resets WHERE token = ?')->execute([$token]);
        return ['success' => true];
    }
}
