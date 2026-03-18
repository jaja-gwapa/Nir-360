<?php
declare(strict_types=1);

class ProfileService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(int $userId, array $data): array
    {
        $required = ['full_name', 'address', 'barangay', 'emergency_contact_name', 'emergency_contact_mobile'];
        foreach ($required as $k) {
            if (empty(trim((string)($data[$k] ?? '')))) {
                return ['success' => false, 'error' => "Missing required field: {$k}."];
            }
        }

        $fullName = trim((string)$data['full_name']);
        $birthdate = !empty($data['birthdate']) ? trim((string)$data['birthdate']) : null;
        $address = trim((string)$data['address']);
        $barangay = trim((string)$data['barangay']);
        $city = isset($data['city']) ? trim((string)$data['city']) : 'Bago City';
        $province = isset($data['province']) ? trim((string)$data['province']) : 'Negros Occidental';
        $streetAddress = isset($data['street_address']) ? trim((string)$data['street_address']) : null;
        $emergencyName = trim((string)$data['emergency_contact_name']);
        $emergencyMobile = trim((string)$data['emergency_contact_mobile']);

        if (preg_match('/\D/', $emergencyMobile)) {
            return ['success' => false, 'error' => 'Emergency contact number must contain only numbers.'];
        }
        if (strlen($emergencyMobile) !== 11) {
            return ['success' => false, 'error' => 'Emergency contact number must be exactly 11 digits.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO profiles (user_id, full_name, birthdate, address, province, barangay, city, street_address, emergency_contact_name, emergency_contact_mobile)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), birthdate=VALUES(birthdate), address=VALUES(address),
             province=VALUES(province), barangay=VALUES(barangay), city=VALUES(city), street_address=VALUES(street_address),
             emergency_contact_name=VALUES(emergency_contact_name), emergency_contact_mobile=VALUES(emergency_contact_mobile)'
        );
        $stmt->execute([$userId, $fullName, $birthdate ?: null, $address, $province, $barangay, $city, $streetAddress, $emergencyName, $emergencyMobile]);

        return ['success' => true];
    }

    public function getByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get merged user + profile for profile dashboard (by session user id).
     * Returns full_name, email, address, barangay, role, profile_photo, latitude, longitude, location_address.
     */
    public function getProfileForDashboard(int $userId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT u.id, u.email, u.role, u.profile_photo, u.latitude, u.longitude, u.location_address,
                        p.full_name, p.address, p.province, p.barangay, p.city, p.street_address
                 FROM users u
                 LEFT JOIN profiles p ON p.user_id = u.id
                 WHERE u.id = ?'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            if (!str_contains($msg, 'Unknown column') && $e->getCode() !== '42S22') {
                throw $e;
            }
        }
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.email, u.role,
                    p.full_name, p.address, p.province, p.barangay, p.city, p.street_address
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.id
             WHERE u.id = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['profile_photo'] = null;
        $row['latitude'] = null;
        $row['longitude'] = null;
        $row['location_address'] = null;
        return $row;
    }

    public function updateProfilePhoto(int $userId, string $path): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
        return $stmt->execute([$path, $userId]);
    }

    public function updateLocation(int $userId, ?float $latitude, ?float $longitude, ?string $locationAddress): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET latitude = ?, longitude = ?, location_address = ? WHERE id = ?');
        return $stmt->execute([$latitude, $longitude, $locationAddress ?? '', $userId]);
    }
}
