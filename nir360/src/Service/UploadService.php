<?php
declare(strict_types=1);

class UploadService
{
    private PDO $pdo;
    private string $storagePath;
    private array $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];

    public function __construct(PDO $pdo, string $storagePath)
    {
        $this->pdo = $pdo;
        $this->storagePath = rtrim($storagePath, '/');
    }

    public function saveIdUpload(int $userId, string $side, array $file): array
    {
        if (!in_array($side, ['front', 'back'], true)) {
            return ['success' => false, 'error' => 'Invalid side.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload failed.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $this->allowedMimes, true)) {
            return ['success' => false, 'error' => 'Only JPEG, PNG, PDF allowed.'];
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
        $safeName = $userId . '_' . $side . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $fullPath = $this->storagePath . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return ['success' => false, 'error' => 'Could not save file.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO id_uploads (user_id, side, file_path, mime_type) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), mime_type=VALUES(mime_type)'
        );
        $stmt->execute([$userId, $side, $fullPath, $mime]);

        return ['success' => true, 'path' => $fullPath];
    }

    public function getUserUploads(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, side, file_path, mime_type FROM id_uploads WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
