<?php
declare(strict_types=1);

/**
 * Incident reports: submit (user), list by role, assign (admin), update status (responder).
 */
class ReportService
{
    private PDO $pdo;
    private string $mediaStoragePath;
    private int $maxMediaSizeBytes = 30 * 1024 * 1024;
    private array $allowedMediaMimes = [
        'image/jpeg' => ['ext' => 'jpg', 'type' => 'image'],
        'image/png' => ['ext' => 'png', 'type' => 'image'],
        'image/webp' => ['ext' => 'webp', 'type' => 'image'],
        'video/mp4' => ['ext' => 'mp4', 'type' => 'video'],
        'video/webm' => ['ext' => 'webm', 'type' => 'video'],
        'video/quicktime' => ['ext' => 'mov', 'type' => 'video'],
    ];

    public function __construct(PDO $pdo, ?string $mediaStoragePath = null)
    {
        $this->pdo = $pdo;
        $defaultStorage = dirname(__DIR__, 2) . '/storage/uploads/reports';
        $this->mediaStoragePath = rtrim($mediaStoragePath ?: $defaultStorage, '/\\');
    }

    public function create(int $reporterId, string $title, string $description): array
    {
        return $this->createWithLocation($reporterId, $title, $description, null, null);
    }

    public function createWithLocation(
        int $reporterId,
        string $title,
        string $description,
        ?float $latitude = null,
        ?float $longitude = null,
        array $mediaFiles = [],
        ?string $address = null,
        ?string $incidentType = null,
        ?string $severity = null,
        bool $draft = false
    ): array
    {
        $title = trim($title);
        $description = trim($description);
        if ($title === '') {
            return ['success' => false, 'error' => 'Title is required.'];
        }
        if ($description === '') {
            return ['success' => false, 'error' => 'Description is required.'];
        }
        if ($latitude === null || $longitude === null) {
            return ['success' => false, 'error' => 'Incident location is required.'];
        }
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return ['success' => false, 'error' => 'Invalid incident location coordinates.'];
        }
        $address = $address !== null ? trim($address) : null;
        if ($address === '') {
            $address = null;
        }
        $incidentType = $incidentType !== null ? trim($incidentType) : null;
        if ($incidentType === '') {
            $incidentType = null;
        }
        if ($severity !== null && !in_array($severity, ['low', 'medium', 'high'], true)) {
            $severity = null;
        }

        $normalizedFiles = $this->normalizeUploadedFiles($mediaFiles);
        $status = $draft ? 'draft' : 'pending';

        // Ensure media table exists before starting transaction (DDL would otherwise commit it)
        if (!empty($normalizedFiles)) {
            $this->ensureIncidentReportMediaTable();
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO incident_reports (user_id, title, description, incident_type, latitude, longitude, address, severity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$reporterId, $title, $description, $incidentType, $latitude, $longitude, $address, $severity, $status]);
            $reportId = (int) $this->pdo->lastInsertId();

            if (!empty($normalizedFiles)) {
                $this->storeReportMedia($reportId, $reporterId, $normalizedFiles);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['success' => true, 'id' => $reportId];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage() ?: 'Failed to submit report.'];
        }
    }

    private function normalizeUploadedFiles(array $files): array
    {
        if (empty($files) || !isset($files['name'], $files['tmp_name'], $files['error'], $files['size'])) {
            return [];
        }

        if (is_array($files['name'])) {
            $normalized = [];
            $count = count($files['name']);
            for ($index = 0; $index < $count; $index++) {
                $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $normalized[] = [
                    'name' => (string)($files['name'][$index] ?? ''),
                    'tmp_name' => (string)($files['tmp_name'][$index] ?? ''),
                    'error' => $error,
                    'size' => (int)($files['size'][$index] ?? 0),
                ];
            }
            return $normalized;
        }

        $error = (int)$files['error'];
        if ($error === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return [[
            'name' => (string)($files['name'] ?? ''),
            'tmp_name' => (string)($files['tmp_name'] ?? ''),
            'error' => $error,
            'size' => (int)($files['size'] ?? 0),
        ]];
    }

    private function storeReportMedia(int $reportId, int $reporterId, array $files): void
    {
        if (!is_dir($this->mediaStoragePath) && !mkdir($this->mediaStoragePath, 0775, true) && !is_dir($this->mediaStoragePath)) {
            throw new RuntimeException('Could not create report media directory.');
        }

        // Table existence is ensured by caller before beginTransaction (DDL would commit the transaction)

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $insert = $this->pdo->prepare(
            'INSERT INTO incident_report_media (report_id, media_type, file_path, mime_type, original_name, file_size)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($files as $file) {
            $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('One of the uploaded files failed to upload.');
            }

            $tmpName = (string)($file['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('Invalid uploaded file detected.');
            }

            $size = (int)($file['size'] ?? 0);
            if ($size <= 0 || $size > $this->maxMediaSizeBytes) {
                throw new RuntimeException('Each image/video must be up to 30MB only.');
            }

            $mime = (string)$finfo->file($tmpName);
            if (!isset($this->allowedMediaMimes[$mime])) {
                throw new RuntimeException('Allowed files: JPG, PNG, WEBP, MP4, WEBM, MOV.');
            }

            $meta = $this->allowedMediaMimes[$mime];
            $ext = $meta['ext'];
            $mediaType = $meta['type'];
            $safeName = sprintf(
                'report_%d_user_%d_%s.%s',
                $reportId,
                $reporterId,
                bin2hex(random_bytes(10)),
                $ext
            );
            $destination = $this->mediaStoragePath . DIRECTORY_SEPARATOR . $safeName;

            if (!move_uploaded_file($tmpName, $destination)) {
                throw new RuntimeException('Could not save uploaded media file.');
            }

            $insert->execute([
                $reportId,
                $mediaType,
                $destination,
                $mime,
                (string)($file['name'] ?? ''),
                $size,
            ]);
        }
    }

    private function ensureIncidentReportMediaTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS incident_report_media (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id INT UNSIGNED NOT NULL,
                media_type ENUM('image', 'video') NOT NULL,
                file_path VARCHAR(512) NOT NULL,
                mime_type VARCHAR(64) NOT NULL,
                original_name VARCHAR(255) NULL,
                file_size INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (report_id) REFERENCES incident_reports(id) ON DELETE CASCADE,
                INDEX idx_report_media_report (report_id),
                INDEX idx_report_media_type (media_type),
                INDEX idx_report_media_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** All reports for admin (excludes drafts) */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT r.id, r.user_id AS reporter_id, r.assigned_to, r.title, r.description, r.incident_type, r.severity, r.status, r.created_at, r.updated_at,
                    r.latitude, r.longitude, r.address,
                    u.email AS reporter_email, u.username AS reporter_username,
                    a.email AS assigned_email, a.username AS assigned_username,
                    a.latitude AS assigned_latitude, a.longitude AS assigned_longitude
             FROM incident_reports r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN users a ON a.id = r.assigned_to
             WHERE r.status != \'draft\'
             ORDER BY r.created_at DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Active reports for admin: pending, dispatched, awaiting_closure (excludes resolved). Includes tracking_enabled when column exists. */
    public function listActive(): array
    {
        $baseSql = 'SELECT r.id, r.user_id AS reporter_id, r.assigned_to, r.title, r.description, r.incident_type, r.severity, r.status, r.created_at, r.updated_at,
                    r.latitude, r.longitude, r.address,
                    u.email AS reporter_email, u.username AS reporter_username,
                    a.email AS assigned_email, a.username AS assigned_username,
                    a.latitude AS assigned_latitude, a.longitude AS assigned_longitude
             FROM incident_reports r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN users a ON a.id = r.assigned_to
             WHERE r.status != \'draft\' AND r.status != \'resolved\'
             ORDER BY r.updated_at DESC, r.created_at DESC';
        try {
            $stmt = $this->pdo->query('SELECT r.id, r.user_id AS reporter_id, r.assigned_to, r.title, r.description, r.incident_type, r.severity, r.status, r.created_at, r.updated_at,
                    r.latitude, r.longitude, r.address,
                    r.tracking_enabled,
                    u.email AS reporter_email, u.username AS reporter_username,
                    a.email AS assigned_email, a.username AS assigned_username,
                    a.latitude AS assigned_latitude, a.longitude AS assigned_longitude
             FROM incident_reports r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN users a ON a.id = r.assigned_to
             WHERE r.status != \'draft\' AND r.status != \'resolved\'
             ORDER BY r.updated_at DESC, r.created_at DESC');
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'tracking_enabled') !== false) {
                $stmt = $this->pdo->query($baseSql);
            } else {
                throw $e;
            }
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Resolved/completed reports for admin history */
    public function listHistory(): array
    {
        $stmt = $this->pdo->query(
            'SELECT r.id, r.user_id AS reporter_id, r.assigned_to, r.title, r.description, r.incident_type, r.severity, r.status, r.created_at, r.updated_at,
                    r.latitude, r.longitude, r.address,
                    u.email AS reporter_email, u.username AS reporter_username,
                    a.email AS assigned_email, a.username AS assigned_username,
                    a.latitude AS assigned_latitude, a.longitude AS assigned_longitude
             FROM incident_reports r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN users a ON a.id = r.assigned_to
             WHERE r.status = \'resolved\'
             ORDER BY r.updated_at DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recent accident reports (last N days).
     * "Accident" is matched by incident_type containing "accident" (case-insensitive).
     * Excludes drafts.
     */
    public function listRecentAccidents(int $days = 7): array
    {
        $days = max(1, min(90, $days));
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.user_id AS reporter_id, r.assigned_to, r.title, r.description, r.incident_type, r.severity, r.status, r.created_at, r.updated_at,
                    r.latitude, r.longitude, r.address,
                    u.email AS reporter_email, u.username AS reporter_username,
                    a.email AS assigned_email, a.username AS assigned_username
             FROM incident_reports r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN users a ON a.id = r.assigned_to
             WHERE r.status != \'draft\'
               AND r.created_at >= (NOW() - INTERVAL ' . $days . ' DAY)
               AND LOWER(COALESCE(r.incident_type, \'\')) LIKE \'%accident%\'
             ORDER BY r.created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Reports assigned to this responder */
    public function listAssignedTo(int $responderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.user_id AS reporter_id, r.title, r.description, r.incident_type, r.severity, r.status, r.created_at, r.updated_at,
                    r.latitude, r.longitude, r.address,
                    u.email AS reporter_email, u.username AS reporter_username
             FROM incident_reports r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.assigned_to = ?
             ORDER BY r.updated_at DESC'
        );
        $stmt->execute([$responderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Reports assigned to this responder within the last N days (for Responder Dashboard). */
    public function listAssignedRecentTo(int $responderId, int $days): array
    {
        $days = max(1, min(90, $days));
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.user_id AS reporter_id, r.title, r.description, r.incident_type, r.severity, r.status, r.created_at, r.updated_at,
                    r.latitude, r.longitude, r.address,
                    u.email AS reporter_email, u.username AS reporter_username
             FROM incident_reports r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.assigned_to = ? AND r.updated_at >= (NOW() - INTERVAL ' . $days . ' DAY)
             ORDER BY r.updated_at DESC'
        );
        $stmt->execute([$responderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Resolved reports that this responder was assigned to (for History/Records page) */
    public function listResolvedByResponder(int $responderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.user_id AS reporter_id, r.title, r.description, r.incident_type, r.severity, r.status, r.created_at, r.updated_at,
                    r.latitude, r.longitude, r.address,
                    u.email AS reporter_email, u.username AS reporter_username
             FROM incident_reports r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.assigned_to = ? AND r.status = \'resolved\'
             ORDER BY r.updated_at DESC'
        );
        $stmt->execute([$responderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calendar events for Admin or Responder.
     * Admin: all non-draft reports in date range, optional filters incident_type, responder_id, status.
     * Responder: only reports assigned_to = userId in date range.
     * Returns array of report rows with assigned_email (for admin). Event start = created_at, end = updated_at when resolved else created_at.
     */
    public function getEventsForCalendar(int $userId, string $role, array $params): array
    {
        $from = isset($params['from']) ? trim((string)$params['from']) : '';
        $to = isset($params['to']) ? trim((string)$params['to']) : '';
        if ($from === '' || $to === '') {
            return [];
        }
        $from .= ' 00:00:00';
        $to .= ' 23:59:59';

        $conditions = ["r.status != 'draft'"];
        $bind = [];

        if ($role === 'responder') {
            $conditions[] = 'r.assigned_to = ?';
            $bind[] = $userId;
        } else {
            if (isset($params['incident_type']) && trim((string)$params['incident_type']) !== '') {
                $conditions[] = 'r.incident_type = ?';
                $bind[] = trim($params['incident_type']);
            }
            if (isset($params['responder_id']) && (int)$params['responder_id'] > 0) {
                $conditions[] = 'r.assigned_to = ?';
                $bind[] = (int)$params['responder_id'];
            }
            if (isset($params['status']) && trim((string)$params['status']) !== '') {
                $conditions[] = 'r.status = ?';
                $bind[] = trim($params['status']);
            }
        }

        $conditions[] = '(r.created_at BETWEEN ? AND ? OR r.updated_at BETWEEN ? AND ?)';
        $bind[] = $from;
        $bind[] = $to;
        $bind[] = $from;
        $bind[] = $to;

        $sql = 'SELECT r.id, r.user_id AS reporter_id, r.assigned_to, r.title, r.description, r.incident_type, r.severity, r.status, r.created_at, r.updated_at,
                    r.latitude, r.longitude, r.address,
                    u.email AS reporter_email, u.username AS reporter_username,
                    a.email AS assigned_email, a.username AS assigned_username
             FROM incident_reports r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN users a ON a.id = r.assigned_to
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY r.created_at ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Distinct incident_type values (non-empty) for filter dropdowns */
    public function getDistinctIncidentTypes(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT incident_type FROM incident_reports WHERE incident_type IS NOT NULL AND incident_type != '' AND status != 'draft' ORDER BY incident_type");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'incident_type');
    }

    /** Reports submitted by this user (for tracking: pending → dispatched → resolved) */
    public function listByReporter(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, description, incident_type, severity, status, created_at, updated_at FROM incident_reports WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Full history/records for a reporter (includes assigned responder + location). Excludes drafts. */
    public function listHistoryByReporter(int $reporterId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.title, r.incident_type, r.severity, r.status, r.created_at, r.updated_at,
                    r.latitude, r.longitude, r.address,
                    a.email AS assigned_email, a.username AS assigned_username
             FROM incident_reports r
             LEFT JOIN users a ON a.id = r.assigned_to
             WHERE r.user_id = ? AND r.status != \'draft\'
             ORDER BY r.updated_at DESC, r.created_at DESC'
        );
        $stmt->execute([$reporterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Media for a report (id, media_type, mime_type) for building URLs */
    public function getReportMedia(int $reportId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, media_type, mime_type FROM incident_report_media WHERE report_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Single media file path and report owner for permission check and streaming */
    public function getReportMediaFile(int $reportId, int $mediaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.file_path, m.mime_type, r.user_id AS reporter_id
             FROM incident_report_media m
             INNER JOIN incident_reports r ON r.id = m.report_id
             WHERE m.report_id = ? AND m.id = ?'
        );
        $stmt->execute([$reportId, $mediaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getOne(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.user_id AS reporter_id, r.assigned_to, r.title, r.description, r.incident_type, r.severity, r.status,
                    r.created_at, r.updated_at,
                    u.email AS reporter_email, u.username AS reporter_username,
                    a.email AS assigned_email, a.username AS assigned_username
             FROM incident_reports r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN users a ON a.id = r.assigned_to
             WHERE r.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Get report for the reporter (e.g. to edit draft). Only returns if reporter_id matches. */
    public function getOneByReporter(int $reportId, int $reporterId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id AS reporter_id, title, description, incident_type, severity, status, latitude, longitude, address, created_at, updated_at
             FROM incident_reports WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$reportId, $reporterId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get tracking data for the reporter: incident location and responder's live location.
     * Only for reports that are dispatched or awaiting_closure and reporter is the owner.
     * When tracking_enabled column exists (migration_tracking_enabled.sql), respects tracking_enabled = 1.
     */
    public function getReportTrackingForReporter(int $reportId, int $reporterId): ?array
    {
        $sql = 'SELECT r.id AS report_id, r.status, r.latitude AS incident_lat, r.longitude AS incident_lng, r.address,
                    a.latitude AS responder_lat, a.longitude AS responder_lng
             FROM incident_reports r
             LEFT JOIN users a ON a.id = r.assigned_to
             WHERE r.id = ? AND r.user_id = ? AND r.status IN (\'dispatched\', \'awaiting_closure\')';
        $params = [$reportId, $reporterId];
        try {
            $stmt = $this->pdo->prepare($sql . ' AND (r.tracking_enabled = 1 OR r.tracking_enabled IS NULL)');
            $stmt->execute($params);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'tracking_enabled') !== false) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                throw $e;
            }
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $out = [
            'report_id' => (int)$row['report_id'],
            'status' => (string)$row['status'],
            'incident_lat' => isset($row['incident_lat']) && $row['incident_lat'] !== null && $row['incident_lat'] !== '' ? (float)$row['incident_lat'] : null,
            'incident_lng' => isset($row['incident_lng']) && $row['incident_lng'] !== null && $row['incident_lng'] !== '' ? (float)$row['incident_lng'] : null,
            'address' => trim((string)($row['address'] ?? '')),
            'responder_lat' => null,
            'responder_lng' => null,
        ];
        if (isset($row['responder_lat'], $row['responder_lng']) && $row['responder_lat'] !== null && $row['responder_lng'] !== null && $row['responder_lat'] !== '' && $row['responder_lng'] !== '') {
            $out['responder_lat'] = (float)$row['responder_lat'];
            $out['responder_lng'] = (float)$row['responder_lng'];
        }
        return $out;
    }

    /**
     * Get the reporter's most recent active tracking report (dispatched/awaiting_closure).
     * Returns null when no active dispatched report exists or when tracking is disabled.
     */
    public function getActiveTrackingForReporter(int $reporterId): ?array
    {
        $sql = 'SELECT r.id
                FROM incident_reports r
                WHERE r.user_id = ? AND r.status IN (\'dispatched\', \'awaiting_closure\')
                ORDER BY r.updated_at DESC, r.id DESC
                LIMIT 1';
        $params = [$reporterId];
        try {
            $stmt = $this->pdo->prepare($sql . ' AND (r.tracking_enabled = 1 OR r.tracking_enabled IS NULL)');
            $stmt->execute($params);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'tracking_enabled') !== false) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                throw $e;
            }
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['id'])) {
            return null;
        }
        return $this->getReportTrackingForReporter((int)$row['id'], $reporterId);
    }

    /** Set tracking_enabled for a report (admin only). */
    public function setTrackingEnabled(int $reportId, bool $enabled): bool
    {
        $stmt = $this->pdo->prepare('UPDATE incident_reports SET tracking_enabled = ? WHERE id = ?');
        return $stmt->execute([$enabled ? 1 : 0, $reportId]);
    }

    /**
     * Update a draft report by its reporter. Server sets updated_at via DB.
     * If $submit is true, sets status to 'pending' (submit for dispatch).
     * $data must include title, description, latitude, longitude; optional incident_type, severity, address, report_media.
     */
    public function updateReportByReporter(int $reportId, int $reporterId, array $data, bool $submit = false): array
    {
        $report = $this->getOneByReporter($reportId, $reporterId);
        if (!$report || ($report['status'] ?? '') !== 'draft') {
            return ['success' => false, 'error' => 'Report not found or not a draft.'];
        }

        $title = trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $incidentType = isset($data['incident_type']) ? trim((string)$data['incident_type']) : null;
        $severity = isset($data['severity']) && in_array($data['severity'], ['low', 'medium', 'high'], true) ? $data['severity'] : null;
        $latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
        $longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
        $address = isset($data['address']) ? trim((string)$data['address']) : null;
        if ($address === '') {
            $address = null;
        }
        if ($incidentType === '') {
            $incidentType = null;
        }

        if ($title === '') {
            return ['success' => false, 'error' => 'Title is required.'];
        }
        if ($description === '') {
            return ['success' => false, 'error' => 'Description is required.'];
        }
        if ($latitude === null || $longitude === null) {
            return ['success' => false, 'error' => 'Incident location is required.'];
        }
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return ['success' => false, 'error' => 'Invalid incident location.'];
        }

        $mediaFiles = $data['report_media'] ?? [];
        $normalizedFiles = $this->normalizeUploadedFiles($mediaFiles);

        if (!empty($normalizedFiles)) {
            $this->ensureIncidentReportMediaTable();
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'UPDATE incident_reports SET title = ?, description = ?, incident_type = ?, severity = ?, latitude = ?, longitude = ?, address = ?, status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([
                $title, $description, $incidentType, $severity, $latitude, $longitude, $address,
                $submit ? 'pending' : 'draft',
                $reportId, $reporterId,
            ]);

            if (!empty($normalizedFiles)) {
                $this->storeReportMedia($reportId, $reporterId, $normalizedFiles);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['success' => true, 'id' => $reportId];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage() ?: 'Failed to update report.'];
        }
    }

    public function updateStatus(int $reportId, string $status, ?int $responderId = null): bool
    {
        $allowed = ['pending', 'dispatched', 'awaiting_closure', 'resolved'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $report = $this->getOne($reportId);
        if ($report && ($report['status'] ?? '') === 'resolved') {
            return false; // Resolved reports cannot be edited
        }
        $stmt = $this->pdo->prepare('UPDATE incident_reports SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $reportId]);
    }

    /** Set all dispatched reports assigned to this responder to awaiting_closure. Returns report IDs updated. */
    public function setAwaitingClosureForResponder(int $responderId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM incident_reports WHERE assigned_to = ? AND status = \'dispatched\'');
        $stmt->execute([$responderId]);
        $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        if (empty($ids)) {
            return [];
        }
        $stmt = $this->pdo->prepare('UPDATE incident_reports SET status = \'awaiting_closure\' WHERE assigned_to = ? AND status = \'dispatched\'');
        $stmt->execute([$responderId]);
        return $ids;
    }

    /** Confirm resolved: only for reports in awaiting_closure or dispatched. Responder: must be assigned. Admin: any. */
    public function confirmResolved(int $reportId, int $userId, string $role): bool
    {
        $report = $this->getOne($reportId);
        if (!$report) {
            return false;
        }
        $status = $report['status'] ?? '';
        if ($status !== 'awaiting_closure' && $status !== 'dispatched') {
            return false;
        }
        if ($role === 'admin') {
            return $this->updateStatus($reportId, 'resolved');
        }
        if ($role === 'responder' && (int)($report['assigned_to'] ?? 0) === $userId) {
            return $this->updateStatus($reportId, 'resolved');
        }
        return false;
    }

    public function assignResponder(int $reportId, int $responderId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE incident_reports SET assigned_to = ?, status = ? WHERE id = ?');
        return $stmt->execute([$responderId, 'dispatched', $reportId]);
    }

    /** Number of media items (photos/videos) attached to the report */
    public function getReportMediaCount(int $reportId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM incident_report_media WHERE report_id = ?');
        $stmt->execute([$reportId]);
        return (int) $stmt->fetchColumn();
    }

    /** Build SMS message for responder when admin assigns (incident type, severity, address, coords, description, report ID, link). */
    public function buildResponderAssignSmsMessage(array $report, string $dashboardUrl): string
    {
        $base = 'NIR360: You have been assigned to incident #' . (int)($report['id'] ?? 0);
        $type = trim((string)($report['incident_type'] ?? ''));
        if ($type !== '') {
            $base .= '. Type: ' . $type;
        }
        $sev = trim((string)($report['severity'] ?? ''));
        if ($sev !== '') {
            $base .= '. Severity: ' . ucfirst($sev);
        }
        $addr = trim((string)($report['address'] ?? ''));
        if ($addr !== '') {
            $base .= '. Address: ' . mb_substr($addr, 0, 80) . (mb_strlen($addr) > 80 ? '…' : '');
        }
        $lat = isset($report['latitude']) ? (float)$report['latitude'] : null;
        $lng = isset($report['longitude']) ? (float)$report['longitude'] : null;
        if ($lat !== null && $lng !== null) {
            $base .= '. Coords: ' . round($lat, 5) . ',' . round($lng, 5);
        }
        $desc = trim((string)($report['description'] ?? ''));
        if ($desc !== '') {
            $base .= '. ' . mb_substr($desc, 0, 100) . (mb_strlen($desc) > 100 ? '…' : '');
        }
        $dashboardUrl = rtrim($dashboardUrl, '/');
        $base .= '. Open: ' . $dashboardUrl;
        return $base;
    }

    /** Ensure report is assigned to this responder (for status update) */
    public function canResponderUpdate(int $reportId, int $responderId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM incident_reports WHERE id = ? AND assigned_to = ?');
        $stmt->execute([$reportId, $responderId]);
        return (bool) $stmt->fetch();
    }

    /** Delete a report (admin only). Related media removed by DB cascade; notifications keep with report_id null. */
    public function deleteReport(int $reportId): bool
    {
        $report = $this->getOne($reportId);
        if (!$report) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM incident_reports WHERE id = ?');
        return $stmt->execute([$reportId]);
    }
}
