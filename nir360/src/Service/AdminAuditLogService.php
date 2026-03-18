<?php
declare(strict_types=1);

/**
 * Log administrative actions (assignments, status changes, user management) with timestamp and admin identity.
 */
class AdminAuditLogService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param string $actionType e.g. report_assign, report_confirm_resolved, user_deactivate, user_activate, user_reset_password, user_create, announcement_create
     * @param string|null $targetType e.g. report, user, announcement
     * @param int|null $targetId
     * @param array|null $details Optional key-value context (stored as JSON)
     * @param string|null $adminEmail Admin email at time of action (for readability)
     */
    public function log(
        int $adminId,
        string $actionType,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $details = null,
        ?string $adminEmail = null
    ): void {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $detailsJson = $details !== null ? json_encode($details, JSON_UNESCAPED_SLASHES) : null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, admin_email, action_type, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $adminId,
            $adminEmail !== null && $adminEmail !== '' ? $adminEmail : null,
            $actionType,
            $targetType,
            $targetId,
            $detailsJson,
            $ip !== null && $ip !== '' ? $ip : null,
        ]);
    }

    /** List recent log entries for admin review (e.g. last 500). */
    public function listRecent(int $limit = 500, ?string $actionType = null): array
    {
        $sql = 'SELECT id, admin_id, admin_email, action_type, target_type, target_id, details, ip_address, created_at FROM admin_audit_log';
        $params = [];
        if ($actionType !== null && $actionType !== '') {
            $sql .= ' WHERE action_type = ?';
            $params[] = $actionType;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            if (isset($r['details']) && $r['details'] !== null) {
                $r['details'] = json_decode($r['details'], true);
            }
        }
        return $rows;
    }
}
