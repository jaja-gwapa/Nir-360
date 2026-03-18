<?php
declare(strict_types=1);

class ProfileController
{
    private AuthService $authService;
    private ProfileService $profileService;
    private array $config;

    public function __construct(AuthService $authService, ProfileService $profileService, array $config)
    {
        $this->authService = $authService;
        $this->profileService = $profileService;
        $this->config = $config;
    }

    private function requireAuth(): ?int
    {
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        if (!$userId) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Unauthorized.'], 401);
            return null;
        }
        return $userId;
    }

    public function updateEmail(): void
    {
        $userId = $this->requireAuth();
        if ($userId === null) {
            return;
        }

        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $email = trim((string)($input['email'] ?? ''));

        if (!Helpers::validateCsrf($input['csrf_token'] ?? '')) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid security token.'], 403);
            return;
        }

        if ($email === '') {
            Helpers::jsonResponse(['success' => false, 'error' => 'Email is required.'], 400);
            return;
        }

        if (!$this->authService->validateEmail($email)) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid email format.'], 400);
            return;
        }

        if ($this->authService->isEmailTakenExcept($userId, $email)) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Email is already taken.'], 400);
            return;
        }

        if (!$this->authService->updateEmail($userId, $email)) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Update failed.'], 500);
            return;
        }

        Helpers::jsonResponse(['success' => true, 'email' => $email]);
    }

    public function updatePassword(): void
    {
        $userId = $this->requireAuth();
        if ($userId === null) {
            return;
        }

        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $current = (string)($input['current_password'] ?? '');
        $newPassword = (string)($input['new_password'] ?? '');

        if (!Helpers::validateCsrf($input['csrf_token'] ?? '')) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid security token.'], 403);
            return;
        }

        $hash = $this->authService->getPasswordHashForUser($userId);
        if (!$hash || !password_verify($current, $hash)) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Current password is incorrect.'], 400);
            return;
        }

        $check = $this->authService->validatePasswordStrength($newPassword);
        if (!$check['valid']) {
            Helpers::jsonResponse(['success' => false, 'error' => 'New password: ' . $check['message']], 400);
            return;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!$this->authService->updatePassword($userId, $newHash)) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Update failed.'], 500);
            return;
        }

        Helpers::jsonResponse(['success' => true]);
    }

    public function uploadPhoto(): void
    {
        try {
            $userId = $this->requireAuth();
            if ($userId === null) {
                return;
            }

            $csrfToken = (string)($_POST['csrf_token'] ?? '');
            if (!Helpers::validateCsrf($csrfToken)) {
                Helpers::jsonResponse(['success' => false, 'error' => 'Invalid security token.'], 403);
                return;
            }

            $file = $_FILES['photo'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                Helpers::jsonResponse(['success' => false, 'error' => 'No file uploaded or upload error.'], 400);
                return;
            }

            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo === false) {
                Helpers::jsonResponse(['success' => false, 'error' => 'Could not validate file type.'], 500);
                return;
            }
            $mime = is_readable($file['tmp_name']) ? (finfo_file($finfo, $file['tmp_name']) ?: '') : '';
            finfo_close($finfo);
            if (!isset($allowedTypes[$mime])) {
                Helpers::jsonResponse(['success' => false, 'error' => 'Only JPEG and PNG are allowed.'], 400);
                return;
            }

            if ($file['size'] > 2 * 1024 * 1024) {
                Helpers::jsonResponse(['success' => false, 'error' => 'File size must not exceed 2MB.'], 400);
                return;
            }

            $uploadPath = $this->config['profile_photo_upload_path'] ?? '';
            $webPath = $this->config['profile_photo_web_path'] ?? '/uploads/profile';
            if ($uploadPath === '') {
                Helpers::jsonResponse(['success' => false, 'error' => 'Upload directory not configured.'], 500);
                return;
            }
            if (!is_dir($uploadPath)) {
                if (!@mkdir($uploadPath, 0755, true)) {
                    Helpers::jsonResponse(['success' => false, 'error' => 'Upload directory could not be created.'], 500);
                    return;
                }
            }

            $ext = $allowedTypes[$mime];
            $filename = $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destination = rtrim($uploadPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                Helpers::jsonResponse(['success' => false, 'error' => 'Failed to save file.'], 500);
                return;
            }

            $relativePath = $filename;
            if (!$this->profileService->updateProfilePhoto($userId, $relativePath)) {
                @unlink($destination);
                Helpers::jsonResponse(['success' => false, 'error' => 'Failed to update profile. If the error persists, run nir360/sql/add_users_profile_photo_location.sql in phpMyAdmin.'], 500);
                return;
            }

            Helpers::jsonResponse([
                'success' => true,
                'profile_photo' => $relativePath,
                'profile_photo_url' => rtrim($webPath, '/') . '/' . $relativePath,
            ]);
        } catch (\Throwable $e) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Upload failed. If the error persists, run nir360/sql/add_users_profile_photo_location.sql in phpMyAdmin to add the profile_photo column.',
            ], JSON_UNESCAPED_SLASHES);
        }
    }

    public function updateLocation(): void
    {
        $userId = $this->requireAuth();
        if ($userId === null) {
            return;
        }

        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $lat = isset($input['latitude']) ? (float)$input['latitude'] : null;
        $lng = isset($input['longitude']) ? (float)$input['longitude'] : null;
        $address = isset($input['location_address']) ? trim((string)$input['location_address']) : null;

        if (!Helpers::validateCsrf($input['csrf_token'] ?? '')) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Invalid security token.'], 403);
            return;
        }

        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Valid latitude and longitude are required.'], 400);
            return;
        }

        if (!$this->profileService->updateLocation($userId, $lat, $lng, $address ?? '')) {
            Helpers::jsonResponse(['success' => false, 'error' => 'Update failed.'], 500);
            return;
        }

        Helpers::jsonResponse(['success' => true, 'latitude' => $lat, 'longitude' => $lng, 'location_address' => $address]);
    }
}
