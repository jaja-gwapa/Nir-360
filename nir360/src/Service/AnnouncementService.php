<?php
declare(strict_types=1);

class AnnouncementService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS announcements (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                created_by INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** List announcements for users (newest first). */
    public function listForUsers(int $limit = 50): array
    {
        $this->ensureTable();
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->query(
            'SELECT id, title, body, created_at FROM announcements ORDER BY created_at DESC LIMIT ' . $limit
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Admin: create announcement. Returns ['success' => true, 'id' => x] or ['success' => false, 'error' => '...'] */
    public function create(int $adminId, string $title, string $body): array
    {
        $this->ensureTable();
        $title = trim($title);
        $body = trim($body);
        if ($title === '') {
            return ['success' => false, 'error' => 'Title is required.'];
        }
        $stmt = $this->pdo->prepare('INSERT INTO announcements (created_by, title, body) VALUES (?, ?, ?)');
        $stmt->execute([$adminId, $title, $body]);
        return ['success' => true, 'id' => (int) $this->pdo->lastInsertId()];
    }
}
