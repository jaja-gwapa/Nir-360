<?php
declare(strict_types=1);

class AuthController
{
    private const ID_MISMATCH_MESSAGE = 'The information on your ID does not match the details you entered. Please upload a clearer ID picture and try again.';

    private AuthService $authService;
    private OTPService $otpService;
    private UploadService $uploadService;
    private GovernmentIdOcrVerifier $idOcrVerifier;
    private ForgotPasswordService $forgotPasswordService;
    private array $config;

    public function __construct(
        AuthService $authService,
        OTPService $otpService,
        UploadService $uploadService,
        array $config,
        GovernmentIdOcrVerifier $idOcrVerifier,
        ForgotPasswordService $forgotPasswordService
    ) {
        $this->authService = $authService;
        $this->otpService = $otpService;
        $this->uploadService = $uploadService;
        $this->config = $config;
        $this->idOcrVerifier = $idOcrVerifier;
        $this->forgotPasswordService = $forgotPasswordService;
    }

    public function register(): void
    {
        $input = $this->getRegisterInput();
        $fullName = $this->composeFullName($input);
        if (!Helpers::validateCsrf($input['csrf_token'] ?? '')) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid security token.'], 403);
            return;
        }

        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';
        if ($password !== $confirmPassword) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Password and Confirm Password do not match.'], 400);
            return;
        }

        $idFront = $input['_files']['id_front'] ?? null;
        $idBack = $input['_files']['id_back'] ?? null;

        if (!is_array($idFront) || ($idFront['error'] ?? 0) !== UPLOAD_ERR_OK) {
            Helpers::jsonResponse([
                'success' => false,
                'error' => 'Please upload your Government ID (front). Verification is required to register.',
            ], 400);
            return;
        }

        $frontOriginalName = strtolower(trim((string)($idFront['name'] ?? '')));
        if ($frontOriginalName !== '' && str_ends_with($frontOriginalName, '.pdf')) {
            Helpers::jsonResponse([
                'success' => false,
                'error' => 'ID verification requires a JPEG or PNG image of your ID. Please upload a photo, not a PDF.',
            ], 400);
            return;
        }

        $frontTmpPath = (string)($idFront['tmp_name'] ?? '');
        if ($frontTmpPath === '' || !is_readable($frontTmpPath)) {
            Helpers::jsonResponse([
                'success' => false,
                'error' => 'Uploaded ID image could not be read. Please upload again.',
            ], 400);
            return;
        }

        try {
            $ocrResult = $this->idOcrVerifier->verifyStrict($frontTmpPath, [
                'full_name' => $fullName,
                'first_name' => trim((string)($input['first_name'] ?? '')),
                'middle_name' => trim((string)($input['middle_name'] ?? '')),
                'last_name' => trim((string)($input['last_name'] ?? '')),
                'birthdate' => trim((string)($input['birthdate'] ?? '')),
                'barangay' => trim((string)($input['barangay'] ?? '')),
                'street_address' => trim((string)($input['street_address'] ?? '')),
            ]);
        } catch (Throwable $e) {
            Helpers::jsonResponse([
                'success' => false,
                'error' => 'ID verification failed: ' . $e->getMessage() . '. Ensure the image is clear and JPEG/PNG, or try again later.',
            ], 400);
            return;
        }

        if (($ocrResult['verification_status'] ?? '') !== 'verified') {
            $errorMessage = isset($ocrResult['rejection_message']) && $ocrResult['rejection_message'] !== ''
                ? trim((string)$ocrResult['rejection_message'])
                : self::ID_MISMATCH_MESSAGE;
            Helpers::jsonResponse([
                'success' => false,
                'error' => $errorMessage,
            ], 400);
            return;
        }

        $result = $this->authService->register(
            $fullName,
            (string)($input['username'] ?? ''),
            (string)($input['email'] ?? ''),
            (string)($input['mobile'] ?? ''),
            $password,
            (string)($input['address'] ?? ''),
            (string)($input['barangay'] ?? ''),
            (string)($input['emergency_contact_name'] ?? ''),
            (string)($input['emergency_contact_mobile'] ?? ''),
            'user',
            isset($input['birthdate']) && $input['birthdate'] !== '' ? (string)$input['birthdate'] : null,
            isset($input['city']) && $input['city'] !== '' ? (string)$input['city'] : null,
            isset($input['province']) && $input['province'] !== '' ? (string)$input['province'] : null,
            isset($input['street_address']) && $input['street_address'] !== '' ? (string)$input['street_address'] : null
        );
        if (!$result['success']) {
            Helpers::jsonResponse($result, 400);
            return;
        }

        $userId = $result['user_id'];
        $registrationSmsSent = false;
        $governmentIdPath = null;

        $uploadResult = $this->uploadService->saveIdUpload($userId, 'front', $idFront);
        if (empty($uploadResult['success']) || empty($uploadResult['path'])) {
            Helpers::jsonResponse([
                'success' => false,
                'error' => $uploadResult['error'] ?? 'ID upload failed. Use a clear JPEG or PNG image.',
            ], 400);
            return;
        }

        $governmentIdPath = $uploadResult['path'];
        $this->authService->updateGovernmentIdPath($userId, $governmentIdPath);

        // SMS: registration successful – use saved mobile from DB; send if app enables SMS or config is present
        $mobile = isset($result['mobile']) ? trim((string)$result['mobile']) : trim((string)($input['mobile'] ?? ''));
        if ($mobile !== '') {
            $smsPath = dirname(__DIR__, 2) . '/config/sms.php';
            if (file_exists($smsPath)) {
                require_once $smsPath;
                $shouldSend = !empty($this->config['sms_enabled']);
                if (!$shouldSend && function_exists('getSmsConfig')) {
                    $smsCfg = getSmsConfig();
                    $shouldSend = !empty($smsCfg['api_url']) && (!empty($smsCfg['api_key']) || !empty($smsCfg['username']));
                }
                if ($shouldSend && function_exists('sendSMS')) {
                    $registrationSmsSent = sendSMS($mobile, 'NIR360: Registration successful. Please verify your OTP to complete your account.');
                }
            }
        }

        if (is_array($idBack) && ($idBack['error'] ?? 0) === UPLOAD_ERR_OK) {
            $this->uploadService->saveIdUpload($userId, 'back', $idBack);
        }

        $code = $this->otpService->generateAndStore($userId);
        if ($this->config['env'] === 'local') {
            error_log("[NIR360 DEV] OTP for user {$userId}: {$code}");
        }

        $otpSmsSent = false;
        $userMobile = isset($result['mobile']) ? trim((string)$result['mobile']) : trim((string)($input['mobile'] ?? ''));
        $smsConfigPath = dirname(__DIR__, 2) . '/config/sms.php';
        if (file_exists($smsConfigPath)) {
            require_once $smsConfigPath;
        }
        $smsOk = !empty($this->config['sms_enabled']);
        if (!$smsOk && function_exists('getSmsConfig')) {
            $sc = getSmsConfig();
            $smsOk = !empty($sc['api_url']) && (!empty($sc['api_key']) || !empty($sc['username']));
        }
        if ($smsOk && $userMobile !== '' && function_exists('sendSMS')) {
            $expiryMins = (int)($this->config['otp_expiry_minutes'] ?? 5);
            $otpSmsSent = sendSMS($userMobile, "NIR360: Your verification code is {$code}. Valid for {$expiryMins} minutes. Do not share this code.");
        }

        $payload = [
            'success' => true,
            'message' => 'Account created. Verify your OTP.',
            'user_id' => $userId,
            'open_otp_modal' => true,
            'wait_seconds' => (int)($this->config['otp_resend_cooldown_seconds'] ?? 60),
            'registration_sms_sent' => $registrationSmsSent,
            'otp_sms_sent' => $otpSmsSent,
        ];
        if ($this->config['env'] === 'local') {
            $payload['dev_otp'] = $code;
        }
        Helpers::jsonResponse($payload);
    }

    /** Get registration input from JSON (legacy) or multipart form. */
    private function getRegisterInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'multipart/form-data') !== false) {
            $out = [
                'csrf_token' => $_POST['csrf_token'] ?? '',
                'full_name' => $_POST['full_name'] ?? '',
                'first_name' => $_POST['first_name'] ?? '',
                'middle_name' => $_POST['middle_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'username' => $_POST['username'] ?? '',
                'email' => $_POST['email'] ?? '',
                'mobile' => $_POST['mobile'] ?? '',
                'password' => $_POST['password'] ?? '',
                'confirm_password' => $_POST['confirm_password'] ?? '',
                'birthdate' => $_POST['birthdate'] ?? '',
                'address' => $_POST['address'] ?? '',
                'city' => $_POST['city'] ?? 'Bago City',
                'province' => $_POST['province'] ?? 'Negros Occidental',
                'barangay' => $_POST['barangay'] ?? '',
                'street_address' => $_POST['street_address'] ?? '',
                'emergency_contact_name' => $_POST['emergency_contact_name'] ?? '',
                'emergency_contact_mobile' => $_POST['emergency_contact_mobile'] ?? '',
                'role' => $_POST['role'] ?? 'user',
            ];
            $out['_files'] = [
                'id_front' => $_FILES['id_front'] ?? null,
                'id_back' => $_FILES['id_back'] ?? null,
            ];
            return $out;
        }
        $json = $this->getJsonInput();
        $json['_files'] = [];
        return $json;
    }

    private function composeFullName(array $input): string
    {
        $firstName = trim((string)($input['first_name'] ?? ''));
        $middleName = trim((string)($input['middle_name'] ?? ''));
        $lastName = trim((string)($input['last_name'] ?? ''));
        $parts = array_values(array_filter([$firstName, $middleName, $lastName], fn($part) => $part !== ''));
        $combined = trim(implode(' ', $parts));
        if ($combined !== '') {
            return $combined;
        }
        return trim((string)($input['full_name'] ?? ''));
    }

    private function normalizeNameForComparison(string $name): string
    {
        return strtolower(trim((string)preg_replace('/\s+/', ' ', $name)));
    }

    public function login(): void
    {
        $input = $this->getJsonInput();
        if (!Helpers::validateCsrf($input['csrf_token'] ?? '')) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid security token.'], 403);
            return;
        }

        $emailOrMobile = $input['email_or_mobile'] ?? '';
        $password = $input['password'] ?? '';

        $result = $this->authService->login($emailOrMobile, $password);
        if (!$result['success']) {
            Helpers::jsonResponse($result, 401);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $result['user_id'];
        $_SESSION['role'] = $result['role'];

        $redirect = match ($result['role']) {
            'admin' => '/admin/dashboard',
            'responder' => '/responder/dashboard',
            'user' => '/user/dashboard',
            'civilian' => '/user/dashboard',
            'authority' => '/responder/dashboard',
            default => '/',
        };

        Helpers::jsonResponse([
            'success' => true,
            'redirect' => $redirect,
        ]);
    }

    /** Forgot Password – send reset link by email via ForgotPasswordService (PHPMailer). */
    public function forgotPassword(): void
    {
        $input = $this->getJsonInput();
        if (!Helpers::validateCsrf($input['csrf_token'] ?? '')) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid security token.'], 403);
            return;
        }

        $emailOrMobile = trim((string)($input['email_or_mobile'] ?? ''));
        if ($emailOrMobile === '') {
            Helpers::jsonResponse(['success' => false, 'error' => 'Email or mobile is required.'], 400);
            return;
        }

        $email = $this->forgotPasswordService->resolveEmailFromInput($emailOrMobile);
        if ($email === null) {
            Helpers::jsonResponse([
                'success' => true,
                'message' => 'If the account exists, we sent instructions to reset your password. Check your email and spam folder.',
            ]);
            return;
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $resetLinkBase = $protocol . '://' . $host . $scriptDir . '/reset_password.php';

        $result = $this->forgotPasswordService->requestReset($email, $resetLinkBase);
        if ($result['success']) {
            Helpers::jsonResponse([
                'success' => true,
                'message' => 'If the account exists, we sent instructions to reset your password. Check your email and spam folder.',
            ]);
        } else {
            Helpers::jsonResponse([
                'success' => false,
                'error' => $result['error'] ?? 'Could not send reset email. Please check mail configuration or try again later.',
            ]);
        }
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
