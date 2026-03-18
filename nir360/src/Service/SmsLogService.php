<?php
declare(strict_types=1);

/**
 * Log SMS sent to responders (queued/sent/failed) with timestamp.
 */
class SmsLogService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function log(int $reportId, int $recipientUserId, string $recipientPhone, string $messageBody, string $status, ?string $errorMessage = null): void
    {
        $sentAt = ($status === 'sent' || $status === 'failed') ? date('Y-m-d H:i:s') : null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO sms_log (report_id, recipient_user_id, recipient_phone, message_body, status, sent_at, error_message) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$reportId, $recipientUserId, $recipientPhone, $messageBody, $status, $sentAt, $errorMessage]);
    }
}
