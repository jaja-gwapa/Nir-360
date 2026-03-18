<?php
declare(strict_types=1);

/**
 * In-app notifications (e.g. submission confirmation). Optional email when configured.
 */
class NotificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createSubmissionConfirmation(int $userId, int $reportId, string $reportTitle): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_notifications (user_id, report_id, type, title, body) VALUES (?, ?, \'submission_confirmation\', ?, ?)'
        );
        $title = 'Report received';
        $body = 'Your incident report "' . $reportTitle . '" has been submitted successfully. You can track its status in My reports.';
        $stmt->execute([$userId, $reportId, $title, mb_substr($body, 0, 2000)]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Notify reporter that a responder is on the way. Includes report ID, status "On the way / Ongoing", and optional responder name.
     */
    public function createResponderDispatched(int $reporterId, int $reportId, string $reportTitle, ?string $responderName = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_notifications (user_id, report_id, type, title, body) VALUES (?, ?, \'responder_dispatched\', ?, ?)'
        );
        $title = 'Responder on the way';
        $body = 'A responder is on the way to your report. Report #' . $reportId . '. Status: On the way / Ongoing.';
        if ($responderName !== null && trim($responderName) !== '') {
            $body .= ' Responder: ' . trim($responderName) . '.';
        }
        $body .= ' Open My reports to see live location and ETA.';
        $stmt->execute([$reporterId, $reportId, $title, mb_substr($body, 0, 2000)]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createReportResolved(int $reporterId, int $reportId, string $reportTitle): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_notifications (user_id, report_id, type, title, body) VALUES (?, ?, \'report_resolved\', ?, ?)'
        );
        $title = 'Report resolved';
        $body = 'Your incident report "' . $reportTitle . '" has been marked as resolved.';
        $stmt->execute([$reporterId, $reportId, $title, mb_substr($body, 0, 2000)]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array{id, report_id, type, title, body, created_at, read_at}> */
    public function listForUser(int $userId, bool $unreadOnly = false): array
    {
        $sql = 'SELECT id, report_id, type, title, body, created_at, read_at FROM user_notifications WHERE user_id = ?';
        if ($unreadOnly) {
            $sql .= ' AND read_at IS NULL';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 50';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE user_notifications SET read_at = NOW() WHERE id = ? AND user_id = ?');
        return $stmt->execute([$notificationId, $userId]);
    }

    /** Mark all notifications as read for the user. */
    public function markAllRead(int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE user_notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');
        $stmt->execute([$userId]);
        return true;
    }

    /** Return count of unread notifications for the user. */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND read_at IS NULL');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
