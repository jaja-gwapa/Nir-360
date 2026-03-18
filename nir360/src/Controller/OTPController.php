<?php
declare(strict_types=1);

class OTPController
{
    private OTPService $otpService;
    private AuthService $authService;
    private array $config;

    public function __construct(OTPService $otpService, AuthService $authService, array $config)
    {
        $this->otpService = $otpService;
        $this->authService = $authService;
        $this->config = $config;
    }

    public function verify(): void
    {
        $input = $this->getJsonInput();
        if (!Helpers::validateCsrf($input['csrf_token'] ?? '')) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid security token.'], 403);
            return;
        }

        $userId = (int)($input['user_id'] ?? 0);
        $code = trim((string)($input['code'] ?? ''));

        if ($userId < 1 || strlen($code) !== 6) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid request.'], 400);
            return;
        }

        $result = $this->otpService->verify($userId, $code);
        if (!$result['success']) {
            Helpers::jsonResponse($result, 400);
            return;
        }

        Helpers::jsonResponse(['success' => true, 'message' => 'Registration completed. Your account has been automatically verified and updated in the database.']);
    }

    public function resend(): void
    {
        $input = $this->getJsonInput();
        if (!Helpers::validateCsrf($input['csrf_token'] ?? '')) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid security token.'], 403);
            return;
        }

        $userId = (int)($input['user_id'] ?? 0);
        if ($userId < 1) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid request.'], 400);
            return;
        }

        $can = $this->otpService->canResend($userId);
        if (!$can['allowed']) {
            Helpers::jsonResponse([
                'success' => false,
                'error' => 'Please wait before resending.',
                'wait_seconds' => $can['wait_seconds'],
            ], 429);
            return;
        }

        $code = $this->otpService->generateAndStore($userId);
        if ($this->config['env'] === 'local') {
            error_log("[NIR360 DEV] OTP resend for user {$userId}: {$code}");
        }

        if (!empty($this->config['sms_enabled'])) {
            $smsConfigPath = dirname(__DIR__, 2) . '/config/sms.php';
            if (file_exists($smsConfigPath)) {
                require_once $smsConfigPath;
                $user = $this->authService->getUserById($userId);
                $userMobile = $user['mobile'] ?? '';
                if ($userMobile !== '') {
                    $expiryMins = (int)($this->config['otp_expiry_minutes'] ?? 5);
                    sendSMS($userMobile, "NIR360: Your verification code is {$code}. Valid for {$expiryMins} minutes. Do not share this code.");
                }
            }
        }

        $payload = ['success' => true, 'message' => 'OTP sent.', 'wait_seconds' => $this->config['otp_resend_cooldown_seconds']];
        if ($this->config['env'] === 'local') {
            $payload['dev_otp'] = $code;
        }
        Helpers::jsonResponse($payload);
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
