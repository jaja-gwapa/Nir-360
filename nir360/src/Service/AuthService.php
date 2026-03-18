<?php
declare(strict_types=1);

class AuthService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 254;
    }

    /** Contact number: digits only, exactly 11 digits. */
    public function validateContactNumber(string $mobile): array
    {
        $trimmed = trim($mobile);
        if ($trimmed === '') {
            return ['valid' => false, 'error' => 'Contact number is required.'];
        }
        if (preg_match('/\D/', $trimmed)) {
            return ['valid' => false, 'error' => 'Contact number must contain only numbers.'];
        }
        if (strlen($trimmed) !== 11) {
            return ['valid' => false, 'error' => 'Contact number must be exactly 11 digits.'];
        }
        return ['valid' => true, 'error' => null];
    }

    /** Emergency contact number: digits only, exactly 11 digits. */
    public function validateEmergencyContactNumber(string $mobile): array
    {
        $trimmed = trim($mobile);
        if ($trimmed === '') {
            return ['valid' => false, 'error' => 'Emergency contact number is required.'];
        }
        if (preg_match('/\D/', $trimmed)) {
            return ['valid' => false, 'error' => 'Emergency contact number must contain only numbers.'];
        }
        if (strlen($trimmed) !== 11) {
            return ['valid' => false, 'error' => 'Emergency contact number must be exactly 11 digits.'];
        }
        return ['valid' => true, 'error' => null];
    }

    public function isUsernameTaken(string $username): bool
    {
        $username = trim($username);
        if ($username === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return (bool)$stmt->fetch();
    }

    public function isEmailTaken(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([trim($email)]);
        return (bool)$stmt->fetch();
    }

    /** Check if email is taken by another user (excluding given user id). */
    public function isEmailTakenExcept(int $excludeUserId, string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE id != ? AND LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([$excludeUserId, trim($email)]);
        return (bool)$stmt->fetch();
    }

    public function isMobileTaken(string $mobile): bool
    {
        $digits = preg_replace('/\D/', '', $mobile);
        if ($digits === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE mobile = ? LIMIT 1');
        $stmt->execute([$digits]);
        return (bool)$stmt->fetch();
    }

    public function validatePasswordStrength(string $password): array
    {
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'At least 8 characters.'];
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'At least one uppercase letter.'];
        }
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'At least one lowercase letter.'];
        }
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'At least one number.'];
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return ['valid' => false, 'message' => 'At least one special character.'];
        }
        return ['valid' => true, 'message' => ''];
    }

    /**
     * Register with full profile. Public reporters only (role = 'user'); admin/responder created by admin.
     * Location: city (fixed Bago City), barangay (from allowed list), street_address.
     * Username, email, and mobile must be unique. Password hashed with password_hash(); inserts use prepared statements.
     */
    public function register(
        string $fullName,
        string $username,
        string $email,
        string $mobile,
        string $password,
        string $address,
        string $barangay,
        string $emergencyContactName,
        string $emergencyContactMobile,
        string $role = 'user',
        ?string $birthdate = null,
        ?string $city = null,
        ?string $province = null,
        ?string $streetAddress = null
    ): array {
        $email = trim($email);
        $username = trim($username);
        $normalizedMobile = Helpers::normalizeMobile($mobile);
        $fullName = trim($fullName);
        $address = trim($address);
        $barangay = trim($barangay);
        $emergencyContactName = trim($emergencyContactName);
        $emergencyContactMobile = trim($emergencyContactMobile);
        $birthdate = $birthdate !== null && $birthdate !== '' ? trim($birthdate) : null;
        $city = $city !== null && $city !== '' ? trim($city) : 'Bago City';
        $province = $province !== null && $province !== '' ? trim($province) : 'Negros Occidental';
        $streetAddress = $streetAddress !== null ? trim($streetAddress) : null;

        if ($fullName === '') {
            return ['success' => false, 'error' => 'Full name is required.'];
        }
        if ($username === '') {
            return ['success' => false, 'error' => 'Username is required.'];
        }
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'error' => 'Username must be 3 to 50 characters.'];
        }
        if ($this->isUsernameTaken($username)) {
            return ['success' => false, 'error' => 'This username is already taken. Please choose another one.'];
        }
        if (!$this->validateEmail($email)) {
            return ['success' => false, 'error' => 'Invalid email format.'];
        }
        $contactCheck = $this->validateContactNumber($mobile);
        if (!$contactCheck['valid']) {
            return ['success' => false, 'error' => $contactCheck['error']];
        }
        if ($this->isEmailTaken($email)) {
            return ['success' => false, 'error' => 'This email address is already registered.'];
        }
        if ($this->isMobileTaken($mobile)) {
            return ['success' => false, 'error' => 'This contact number is already registered.'];
        }

        $check = $this->validatePasswordStrength($password);
        if (!$check['valid']) {
            return ['success' => false, 'error' => 'Password: ' . $check['message']];
        }

        if ($address === '' || $barangay === '') {
            return ['success' => false, 'error' => 'Address and barangay are required.'];
        }

        if ($emergencyContactMobile !== '' && $emergencyContactMobile !== null) {
            $emergencyCheck = $this->validateEmergencyContactNumber($emergencyContactMobile);
            if (!$emergencyCheck['valid']) {
                return ['success' => false, 'error' => $emergencyCheck['error']];
            }
        }

        $allowedBarangays = $this->getBagoCityBarangays();
        if (!in_array($barangay, $allowedBarangays, true)) {
            return ['success' => false, 'error' => 'Please select a valid barangay from the list.'];
        }

        if ($streetAddress === '' || $streetAddress === null) {
            $streetAddress = $address;
        }

        $role = 'user';

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email, mobile, password_hash, role, verification_status, province, city, barangay, street_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $username,
            $email,
            $normalizedMobile,
            $hash,
            $role,
            'pending',
            $province,
            $city,
            $barangay,
            $streetAddress !== null && $streetAddress !== '' ? $streetAddress : null,
        ]);

        $userId = (int)$this->pdo->lastInsertId();

        $birthdateDb = null;
        if ($birthdate !== null && $birthdate !== '') {
            $ts = strtotime($birthdate);
            if ($ts !== false) {
                $birthdateDb = date('Y-m-d', $ts);
            }
        }
        $stmtProfile = $this->pdo->prepare(
            'INSERT INTO profiles (user_id, full_name, birthdate, address, province, barangay, city, street_address, emergency_contact_name, emergency_contact_mobile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmtProfile->execute([
            $userId,
            $fullName,
            $birthdateDb,
            $address,
            $province,
            $barangay,
            $city,
            $streetAddress,
            $emergencyContactName !== '' ? $emergencyContactName : '',
            $emergencyContactMobile !== '' ? $emergencyContactMobile : '',
        ]);

        return [
            'success' => true,
            'user_id' => $userId,
            'email' => $email,
            'mobile' => $normalizedMobile,
        ];
    }

    /** Allowed barangays for Bago City (registration validation). */
    private function getBagoCityBarangays(): array
    {
        $path = dirname(__DIR__, 2) . '/config/bago_city_barangays.php';
        return is_file($path) ? require $path : [];
    }

    public function updateGovernmentIdPath(int $userId, string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE users SET government_id_path = ? WHERE id = ?');
        return $stmt->execute([$path, $userId]);
    }

    public function login(string $emailOrMobile, string $password): array
    {
        $input = trim($emailOrMobile);
        $digits = preg_replace('/\D/', '', $input);
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, role FROM users WHERE email = ? OR mobile = ? LIMIT 1'
        );
        $stmt->execute([$input, $digits !== '' ? $digits : $input]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid email/mobile or password.'];
        }

        return [
            'success' => true,
            'user_id' => (int)$user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }

    public function getUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, email, mobile, role, is_phone_verified, is_verified FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getPasswordHashForUser(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? $row['password_hash'] : null;
    }

    public function updatePassword(int $userId, string $newPasswordHash): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$newPasswordHash, $userId]);
    }

    public function updateEmail(int $userId, string $email): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
        return $stmt->execute([trim($email), $userId]);
    }

    public function setVerificationStatus(int $userId, string $status): bool
    {
        if (!in_array($status, ['pending', 'verified', 'rejected'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE users SET verification_status = ? WHERE id = ?');
        return $stmt->execute([$status, $userId]);
    }
}
