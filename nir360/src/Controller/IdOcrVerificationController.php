<?php
declare(strict_types=1);

/**
 * Government ID OCR verification endpoint.
 * Accepts ID image upload + user fields (first/middle/last name or full_name, birthdate, address).
 * Validates file (JPEG/PNG, size), saves to id_images, runs OCR via OCR.Space API,
 * compares with user input, returns JSON result.
 */
class IdOcrVerificationController
{
    private GovernmentIdOcrVerifier $verifier;
    private string $uploadPath;
    private int $maxSizeBytes;
    private const ALLOWED_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function __construct(GovernmentIdOcrVerifier $verifier, string $uploadPath, int $maxSizeBytes)
    {
        $this->verifier = $verifier;
        $this->uploadPath = $uploadPath;
        $this->maxSizeBytes = $maxSizeBytes;
    }

    /**
    * Handle POST: multipart form with 'id_image' file and name fields, birthdate, address.
     * Returns JSON: name_match, birthdate_match, address_match, match_score, ocr_confidence, verification_status, scan_quality.
     */
    public function verify(): void
    {
        $file = $_FILES['id_image'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'error' => 'No file uploaded or upload error.'], 400);
            return;
        }

        $mime = $this->getMimeType($file['tmp_name']);
        if ($mime === null || !isset(self::ALLOWED_MIMES[$mime])) {
            $this->json(['success' => false, 'error' => 'Invalid file type. Only JPEG and PNG are allowed.'], 400);
            return;
        }

        if ($file['size'] > $this->maxSizeBytes) {
            $maxMb = round($this->maxSizeBytes / (1024 * 1024), 1);
            $this->json(['success' => false, 'error' => 'File too large. Maximum size: ' . $maxMb . 'MB.'], 400);
            return;
        }

        $firstName = $this->sanitizeString($_POST['first_name'] ?? '', 100);
        $middleName = $this->sanitizeString($_POST['middle_name'] ?? '', 100);
        $lastName = $this->sanitizeString($_POST['last_name'] ?? '', 100);
        $fullName = $this->composeFullName($firstName, $middleName, $lastName, $this->sanitizeString($_POST['full_name'] ?? '', 255));
        $birthdate = $this->sanitizeString($_POST['birthdate'] ?? '', 32);
        $barangay = $this->sanitizeString($_POST['barangay'] ?? '', 255);
        $streetAddress = $this->sanitizeString($_POST['street_address'] ?? '', 512);

        if ($fullName === '') {
            $this->json(['success' => false, 'error' => 'Full name is required.'], 400);
            return;
        }

        if (!is_dir($this->uploadPath)) {
            @mkdir($this->uploadPath, 0755, true);
        }
        if (!is_writable($this->uploadPath)) {
            $this->json(['success' => false, 'error' => 'Upload directory not writable.'], 500);
            return;
        }

        $ext = self::ALLOWED_MIMES[$mime];
        $safeName = 'id_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $destination = rtrim($this->uploadPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->json(['success' => false, 'error' => 'Failed to save uploaded file.'], 500);
            return;
        }

        try {
            $result = $this->verifier->verifyStrict($destination, [
                'full_name' => $fullName,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'birthdate' => $birthdate,
                'barangay' => $barangay,
                'street_address' => $streetAddress,
            ]);
        } catch (Throwable $e) {
            if (is_file($destination)) {
                @unlink($destination);
            }
            $this->json(['success' => false, 'error' => 'OCR processing failed.'], 500);
            return;
        }

        $response = [
            'success' => true,
            'name_match' => $result['name_match'],
            'birthdate_match' => $result['birthdate_match'],
            'ocr_birthdate' => $result['ocr_birthdate'] ?? '',
            'input_birthdate' => $result['input_birthdate'] ?? '',
            'address_match' => $result['address_match'],
            'match_score' => $result['match_score'],
            'ocr_confidence' => $result['ocr_confidence'],
            'verification_status' => $result['verification_status'],
            'scan_quality' => $result['scan_quality'],
        ];
        if (isset($result['error'])) {
            $response['error'] = $result['error'];
        }
        if (isset($result['rejection_message'])) {
            $response['rejection_message'] = $result['rejection_message'];
        }
        if (isset($result['debug_ocr_preview'])) {
            $response['debug_ocr_preview'] = $result['debug_ocr_preview'];
        }
        $this->json($response);
    }

    private function getMimeType(string $path): ?string
    {
        if (!is_readable($path)) {
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path) ?: null;
        finfo_close($finfo);
        return $mime;
    }

    private function sanitizeString(string $s, int $maxLen): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        $s = mb_substr($s, 0, $maxLen);
        return $s;
    }

    private function composeFullName(string $firstName, string $middleName, string $lastName, string $fallbackFullName): string
    {
        $parts = array_values(array_filter([$firstName, $middleName, $lastName], fn($part) => $part !== ''));
        $combined = trim(implode(' ', $parts));
        if ($combined !== '') {
            return $combined;
        }
        return trim($fallbackFullName);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }
}
