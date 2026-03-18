<?php
/**
 * NIR360 Front controller / router
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/src/Helpers.php';
$boot = require $baseDir . '/bootstrap.php';
$pdo = $boot['pdo'];
$config = $boot['config']['app'];
$dbError = $boot['db_error'] ?? null;
// Always prefer OCR key from app.local.php when that file exists (reliable on all run contexts)
$appLocalPath = $baseDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.local.php';
if (is_file($appLocalPath)) {
    $localOverrides = @require $appLocalPath;
    if (is_array($localOverrides) && isset($localOverrides['ocr_space_api_key']) && trim((string)$localOverrides['ocr_space_api_key']) !== '') {
        $config['ocr_space_api_key'] = trim((string)$localOverrides['ocr_space_api_key']);
    }
}

// If database is not connected, show setup page for GET / so user sees what to do
if ($pdo === null) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = $scriptName !== '/' ? substr($requestPath, strlen(rtrim($scriptName, '/'))) : $requestPath;
    $path = rtrim($path, '/') ?: '/';
    if ($method === 'GET' && $path === '/') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>NIR360 – Setup</title></head><body style="font-family:sans-serif;max-width:560px;margin:2rem auto;padding:0 1rem;">';
        echo '<h1>NIR360 – Database setup required</h1>';
        echo '<p>This page cannot load until the database is configured and reachable.</p>';
        if ($dbError) {
            echo '<p><strong>Error:</strong> <code style="background:#f0f0f0;padding:2px 6px;">' . htmlspecialchars($dbError) . '</code></p>';
        }
        echo '<ol style="line-height:1.6;">';
        echo '<li>Start <strong>MySQL</strong> in XAMPP (if not already running).</li>';
        echo '<li>Create the database and tables. In phpMyAdmin, create database <code>nir360</code>, then Import the file: <code>nir360/sql/schema.sql</code></li>';
        echo '<li>If you use a different MySQL user or password, edit <code>nir360/config/database.php</code> (or set DB_USER, DB_PASS, DB_NAME in your environment).</li>';
        echo '</ol>';
        echo '<p>Then <a href="">reload this page</a>.</p></body></html>';
        exit;
    }
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database unavailable', 'detail' => $dbError]);
    exit;
}

require_once $baseDir . '/src/Service/AuthService.php';
require_once $baseDir . '/src/Service/OTPService.php';
require_once $baseDir . '/src/Service/ProfileService.php';
require_once $baseDir . '/src/Service/UploadService.php';
require_once $baseDir . '/src/Service/OcrSpaceClient.php';
require_once $baseDir . '/src/Service/GovernmentIdOcrVerifier.php';
require_once $baseDir . '/src/Service/AdminService.php';
require_once $baseDir . '/src/Service/AnnouncementService.php';
require_once $baseDir . '/src/Service/ReportService.php';
require_once $baseDir . '/src/Service/NotificationService.php';
require_once $baseDir . '/src/Service/SmsLogService.php';
require_once $baseDir . '/src/Service/AdminAuditLogService.php';
require_once $baseDir . '/src/Service/ForgotPasswordService.php';
require_once $baseDir . '/src/Controller/AuthController.php';
require_once $baseDir . '/src/Controller/OTPController.php';
require_once $baseDir . '/src/Controller/ProfileController.php';
require_once $baseDir . '/src/Controller/IdOcrVerificationController.php';
require_once $baseDir . '/src/Middleware/RBACMiddleware.php';

$authService = new AuthService($pdo);
$otpService = new OTPService($pdo, $config);
$profileService = new ProfileService($pdo);
$uploadService = new UploadService($pdo, $config['upload_storage_path']);
$adminService = new AdminService($pdo);
$announcementService = new AnnouncementService($pdo);
$reportService = new ReportService($pdo, $config['report_media_upload_path'] ?? null);
$notificationService = new NotificationService($pdo);
$smsLogService = new SmsLogService($pdo);
$auditLogService = new AdminAuditLogService($pdo);
$otpController = new OTPController($otpService, $authService, $config);
$profileController = new ProfileController($authService, $profileService, $config);
$ocrSpaceClient = new OcrSpaceClient((string)($config['ocr_space_api_key'] ?? ''));
$idOcrVerifier = new GovernmentIdOcrVerifier(
    $ocrSpaceClient,
    (float)($config['ocr_confidence_poor_threshold'] ?? 70.0),
    (string)($config['ocr_service_url'] ?? '')
);
$idOcrVerificationController = new IdOcrVerificationController(
    $idOcrVerifier,
    $config['id_images_upload_path'] ?? $baseDir . '/storage/uploads/id_images',
    (int)($config['id_images_max_size_bytes'] ?? 5242880)
);
$forgotPasswordService = new ForgotPasswordService($pdo, $config);
$authController = new AuthController($authService, $otpService, $uploadService, $config, $idOcrVerifier, $forgotPasswordService);
$rbac = new RBACMiddleware();

$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = $scriptName !== '/' ? substr($requestPath, strlen(rtrim($scriptName, '/'))) : $requestPath;
$path = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Session for auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$role = $_SESSION['role'] ?? null;

// --- API (JSON) routes ---
if ($path === '/api/register' && $method === 'POST') {
    try {
        $authController->register();
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Registration error: ' . $e->getMessage() . '. If you see "Unknown column \'province\'", run BOTH nir360/sql/fix_users_table_location_only.sql AND nir360/sql/fix_profiles_table_location.sql in phpMyAdmin (select database nir360, then SQL tab).',
        ], JSON_UNESCAPED_SLASHES);
    }
    return;
}
if ($path === '/api/login' && $method === 'POST') {
    $authController->login();
    return;
}
if ($path === '/api/forgot-password' && $method === 'POST') {
    $authController->forgotPassword();
    return;
}
if ($path === '/api/otp/verify' && $method === 'POST') {
    $otpController->verify();
    return;
}
if ($path === '/api/otp/resend' && $method === 'POST') {
    $otpController->resend();
    return;
}
if ($path === '/api/profile/update-email' && $method === 'POST') {
    $profileController->updateEmail();
    return;
}
if ($path === '/api/profile/update-password' && $method === 'POST') {
    $profileController->updatePassword();
    return;
}
if ($path === '/api/profile/upload-photo' && $method === 'POST') {
    $profileController->uploadPhoto();
    return;
}
if ($path === '/api/profile/update-location' && $method === 'POST') {
    $profileController->updateLocation();
    return;
}
if ($path === '/api/id-ocr/verify' && $method === 'POST') {
    $idOcrVerificationController->verify();
    return;
}

// --- Reports API (role-based) ---
if ($path === '/api/reports' && $method === 'POST') {
    if (!$userId || $role !== 'user') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
    if ($isMultipart) {
        $in = [
            'title' => (string)($_POST['title'] ?? ''),
            'description' => (string)($_POST['description'] ?? ''),
            'latitude' => $_POST['latitude'] ?? null,
            'longitude' => $_POST['longitude'] ?? null,
            'address' => (string)($_POST['address'] ?? ''),
            'incident_type' => (string)($_POST['incident_type'] ?? ''),
            'severity' => (string)($_POST['severity'] ?? ''),
            'save_as_draft' => isset($_POST['save_as_draft']) && ($_POST['save_as_draft'] === '1' || $_POST['save_as_draft'] === 'true'),
        ];
    } else {
        $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    }
    $draft = !empty($in['save_as_draft']);
    $result = $reportService->createWithLocation(
        $userId,
        (string)($in['title'] ?? ''),
        (string)($in['description'] ?? ''),
        isset($in['latitude']) ? (float)$in['latitude'] : null,
        isset($in['longitude']) ? (float)$in['longitude'] : null,
        $isMultipart ? ($_FILES['report_media'] ?? []) : [],
        isset($in['address']) ? (string)$in['address'] : null,
        isset($in['incident_type']) ? (string)$in['incident_type'] : null,
        isset($in['severity']) ? (string)$in['severity'] : null,
        $draft
    );
    if ($result['success'] && !$draft) {
        if (!empty($config['sms_enabled'])) {
            $smsPath = $baseDir . '/config/sms.php';
            if (file_exists($smsPath)) {
                require_once $smsPath;
                $title = mb_substr(trim((string)($in['title'] ?? '')), 0, 40);
                $msg = 'NIR360: New incident report submitted: ' . $title . '. Please review in the admin dashboard.';
                foreach ($adminService->getAdminMobiles() as $adminMobile) {
                    sendSMS($adminMobile, $msg);
                }
            }
        }
        $reportId = (int)($result['id'] ?? 0);
        $reportTitle = mb_substr(trim((string)($in['title'] ?? '')), 0, 255);
        $notificationService->createSubmissionConfirmation($userId, $reportId, $reportTitle);
        $mailConfig = $config['mail'] ?? [];
        if (!empty($mailConfig['smtp_host']) && !empty($mailConfig['smtp_username']) && !empty($mailConfig['smtp_password'])) {
            $user = $authService->getUserById($userId);
            $toEmail = $user['email'] ?? '';
            if ($toEmail !== '') {
                $appUrl = rtrim($config['url'] ?? 'http://localhost', '/');
                $body = "Your incident report \"{$reportTitle}\" has been submitted successfully. Track status at: {$appUrl}/user/dashboard (My reports).";
                $phpmailerSrc = $config['phpmailer_src'] ?? $baseDir . '/../PHPMailer/src';
                if (is_dir($phpmailerSrc) && is_readable($phpmailerSrc . '/PHPMailer.php')) {
                    try {
                        require_once $phpmailerSrc . '/Exception.php';
                        require_once $phpmailerSrc . '/PHPMailer.php';
                        require_once $phpmailerSrc . '/SMTP.php';
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = $mailConfig['smtp_host'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $mailConfig['smtp_username'];
                        $mail->Password = $mailConfig['smtp_password'];
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = (int)($mailConfig['smtp_port'] ?? 587);
                        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                        $mail->setFrom($mailConfig['from_email'] ?? $mailConfig['smtp_username'], $mailConfig['from_name'] ?? 'NIR360');
                        $mail->addAddress($toEmail);
                        $mail->Subject = 'NIR360: Report submitted';
                        $mail->Body = $body;
                        $mail->CharSet = 'UTF-8';
                        $mail->send();
                    } catch (Throwable $e) {
                        // log and ignore
                    }
                }
            }
        }
    }
    Helpers::jsonResponse($result, $result['success'] ? 201 : 400);
    return;
}
if ($path === '/api/reports/update-draft' && $method === 'POST') {
    if (!$userId || $role !== 'user') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
    if ($isMultipart) {
        $in = [
            'report_id' => isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0,
            'submit' => isset($_POST['submit']) && ($_POST['submit'] === '1' || $_POST['submit'] === 'true'),
            'title' => (string)($_POST['title'] ?? ''),
            'description' => (string)($_POST['description'] ?? ''),
            'incident_type' => (string)($_POST['incident_type'] ?? ''),
            'severity' => (string)($_POST['severity'] ?? ''),
            'latitude' => $_POST['latitude'] ?? null,
            'longitude' => $_POST['longitude'] ?? null,
            'address' => (string)($_POST['address'] ?? ''),
        ];
    } else {
        $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    }
    $reportId = (int)($in['report_id'] ?? 0);
    $submit = !empty($in['submit']);
    $data = [
        'title' => $in['title'] ?? '',
        'description' => $in['description'] ?? '',
        'incident_type' => $in['incident_type'] ?? null,
        'severity' => $in['severity'] ?? null,
        'latitude' => isset($in['latitude']) ? (float)$in['latitude'] : null,
        'longitude' => isset($in['longitude']) ? (float)$in['longitude'] : null,
        'address' => $in['address'] ?? null,
        'report_media' => $isMultipart ? ($_FILES['report_media'] ?? []) : [],
    ];
    $result = $reportService->updateReportByReporter($reportId, $userId, $data, $submit);
    if ($result['success'] && $submit) {
        $report = $reportService->getOne($reportId);
        $reportTitle = $report ? mb_substr((string)($report['title'] ?? ''), 0, 255) : '';
        $notificationService->createSubmissionConfirmation($userId, $reportId, $reportTitle);
        if (!empty($config['sms_enabled'])) {
            $smsPath = $baseDir . '/config/sms.php';
            if (file_exists($smsPath)) {
                require_once $smsPath;
                $msg = 'NIR360: New incident report submitted: ' . $reportTitle . '. Please review in the admin dashboard.';
                foreach ($adminService->getAdminMobiles() as $adminMobile) {
                    sendSMS($adminMobile, $msg);
                }
            }
        }
        $mailConfig = $config['mail'] ?? [];
        if (!empty($mailConfig['smtp_host']) && !empty($mailConfig['smtp_username']) && !empty($mailConfig['smtp_password'])) {
            $user = $authService->getUserById($userId);
            $toEmail = $user['email'] ?? '';
            if ($toEmail !== '') {
                $appUrl = rtrim($config['url'] ?? 'http://localhost', '/');
                $body = "Your incident report \"{$reportTitle}\" has been submitted successfully. Track status at: {$appUrl}/user/dashboard (My reports).";
                $phpmailerSrc = $config['phpmailer_src'] ?? $baseDir . '/../PHPMailer/src';
                if (is_dir($phpmailerSrc) && is_readable($phpmailerSrc . '/PHPMailer.php')) {
                    try {
                        require_once $phpmailerSrc . '/Exception.php';
                        require_once $phpmailerSrc . '/PHPMailer.php';
                        require_once $phpmailerSrc . '/SMTP.php';
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = $mailConfig['smtp_host'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $mailConfig['smtp_username'];
                        $mail->Password = $mailConfig['smtp_password'];
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = (int)($mailConfig['smtp_port'] ?? 587);
                        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                        $mail->setFrom($mailConfig['from_email'] ?? $mailConfig['smtp_username'], $mailConfig['from_name'] ?? 'NIR360');
                        $mail->addAddress($toEmail);
                        $mail->Subject = 'NIR360: Report submitted';
                        $mail->Body = $body;
                        $mail->CharSet = 'UTF-8';
                        $mail->send();
                    } catch (Throwable $e) {
                    }
                }
            }
        }
    }
    Helpers::jsonResponse($result, $result['success'] ? 200 : 400);
    return;
}
if ($path === '/api/reports' && $method === 'GET') {
    if (!$userId) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Unauthorized.'], 401);
        return;
    }
    $list = $role === 'admin' ? $reportService->listAll()
        : ($role === 'responder' ? $reportService->listAssignedTo($userId) : $reportService->listByReporter($userId));
    Helpers::jsonResponse(['success' => true, 'reports' => $list]);
    return;
}
if (preg_match('#^/api/reports/(\d+)/track$#', $path, $m) && $method === 'GET') {
    if (!$userId || $role !== 'user') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $reportId = (int)$m[1];
    $tracking = $reportService->getReportTrackingForReporter($reportId, $userId);
    if (!$tracking) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Report not found or tracking not available.'], 404);
        return;
    }
    Helpers::jsonResponse(['success' => true, 'tracking' => $tracking]);
    return;
}
if ($path === '/api/reports/active-track' && $method === 'GET') {
    if (!$userId || $role !== 'user') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $tracking = $reportService->getActiveTrackingForReporter($userId);
    if (!$tracking) {
        Helpers::jsonResponse(['success' => false, 'error' => 'No active dispatched report.'], 404);
        return;
    }
    Helpers::jsonResponse(['success' => true, 'tracking' => $tracking]);
    return;
}
if ($path === '/api/reports/active-track' && $method === 'GET') {
    if (!$userId || $role !== 'user') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $tracking = $reportService->getActiveTrackingForReporter($userId);
    if (!$tracking) {
        Helpers::jsonResponse(['success' => false, 'error' => 'No active dispatched report.'], 404);
        return;
    }
    Helpers::jsonResponse(['success' => true, 'tracking' => $tracking]);
    return;
}
if (preg_match('#^/api/reports/(\d+)/tracking$#', $path, $m) && $method === 'POST') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $reportId = (int)$m[1];
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $enabled = isset($in['enabled']) ? (bool)$in['enabled'] : true;
    if (!$reportService->getOne($reportId)) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Report not found.'], 404);
        return;
    }
    try {
        $ok = $reportService->setTrackingEnabled($reportId, $enabled);
    } catch (Throwable $e) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Tracking setting not available. Run sql/migration_tracking_enabled.sql.']);
        return;
    }
    Helpers::jsonResponse($ok ? ['success' => true, 'tracking_enabled' => $enabled] : ['success' => false, 'error' => 'Update failed.']);
    return;
}
if (preg_match('#^/api/reports/(\d+)$#', $path, $m) && $method === 'GET') {
    if (!$userId) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Unauthorized.'], 401);
        return;
    }
    $reportId = (int)$m[1];
    if ($role === 'user') {
        $report = $reportService->getOneByReporter($reportId, $userId);
    } else {
        $report = $reportService->getOne($reportId);
        if ($report && $role === 'responder') {
            $report = ($report['assigned_to'] ?? null) == $userId ? $report : null;
        }
    }
    if (!$report) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Report not found.'], 404);
        return;
    }
    // Attach media for authorized viewers (reporter, admin, assigned responder)
    $report['media'] = [];
    foreach ($reportService->getReportMedia($reportId) as $med) {
        $report['media'][] = [
            'id' => (int)$med['id'],
            'media_type' => $med['media_type'],
            'mime_type' => $med['mime_type'],
            'url' => '/api/report-media/file?report_id=' . $reportId . '&media_id=' . (int)$med['id'],
        ];
    }
    Helpers::jsonResponse(['success' => true, 'report' => $report]);
    return;
}
if ($path === '/api/notifications' && $method === 'GET') {
    if (!$userId) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Unauthorized.'], 401);
        return;
    }
    $unreadOnly = isset($_GET['unread_only']) && ($_GET['unread_only'] === '1' || $_GET['unread_only'] === 'true');
    $list = $notificationService->listForUser($userId, $unreadOnly);
    Helpers::jsonResponse(['success' => true, 'notifications' => $list]);
    return;
}
if ($path === '/api/notifications/read' && $method === 'POST') {
    if (!$userId) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Unauthorized.'], 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $notificationId = (int)($in['notification_id'] ?? 0);
    if ($notificationId <= 0) {
        Helpers::jsonResponse(['success' => false, 'error' => 'notification_id required.'], 400);
        return;
    }
    $ok = $notificationService->markRead($notificationId, $userId);
    Helpers::jsonResponse($ok ? ['success' => true] : ['success' => false, 'error' => 'Not found.']);
    return;
}
if ($path === '/api/notifications/read-all' && $method === 'POST') {
    if (!$userId) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Unauthorized.'], 401);
        return;
    }
    $notificationService->markAllRead($userId);
    Helpers::jsonResponse(['success' => true]);
    return;
}
if ($path === '/api/announcements' && $method === 'GET') {
    if (!$userId) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Unauthorized.'], 401);
        return;
    }
    $list = $announcementService->listForUsers();
    Helpers::jsonResponse(['success' => true, 'announcements' => $list]);
    return;
}
if ($path === '/api/report-media/file' && $method === 'GET') {
    if (!$userId) {
        http_response_code(401);
        exit;
    }
    $reportId = (int)($_GET['report_id'] ?? 0);
    $mediaId = (int)($_GET['media_id'] ?? 0);
    if ($reportId <= 0 || $mediaId <= 0) {
        http_response_code(400);
        exit;
    }
    $fileInfo = $reportService->getReportMediaFile($reportId, $mediaId);
    if (!$fileInfo || !is_file($fileInfo['file_path'])) {
        http_response_code(404);
        exit;
    }
    $reporterId = (int)$fileInfo['reporter_id'];
    $canView = ($reporterId === $userId) || $role === 'admin';
    if (!$canView && $role === 'responder') {
        // Only the responder assigned to this report may access its media
        $canView = $reportService->canResponderUpdate($reportId, $userId);
    }
    if (!$canView) {
        http_response_code(403);
        exit;
    }
    $mime = $fileInfo['mime_type'] ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fileInfo['file_path']));
    readfile($fileInfo['file_path']);
    exit;
}
if ($path === '/api/reports/update-status' && $method === 'POST') {
    if (!$userId || $role !== 'responder') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $reportId = (int)($in['report_id'] ?? 0);
    $status = (string)($in['status'] ?? '');
    if (!$reportId || !$reportService->canResponderUpdate($reportId, $userId)) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Report not found or not assigned to you.'], 403);
        return;
    }
    $ok = $reportService->updateStatus($reportId, $status);
    if ($ok && $status === 'resolved' && !empty($config['sms_enabled'])) {
        $report = $reportService->getOne($reportId);
        if ($report && !empty($report['reporter_id'])) {
            $reporter = $authService->getUserById((int)$report['reporter_id']);
            if ($reporter && !empty($reporter['mobile'])) {
                $smsPath = $baseDir . '/config/sms.php';
                if (file_exists($smsPath)) {
                    require_once $smsPath;
                    sendSMS($reporter['mobile'], 'NIR360: Your incident report has been resolved. Thank you for using NIR360.');
                }
            }
        }
    }
    Helpers::jsonResponse($ok ? ['success' => true] : ['success' => false, 'error' => 'Invalid status.']);
    return;
}
if ($path === '/api/reports/confirm-resolved' && $method === 'POST') {
    if (!$userId || ($role !== 'admin' && $role !== 'responder')) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $reportId = (int)($in['report_id'] ?? 0);
    if ($reportId <= 0) {
        Helpers::jsonResponse(['success' => false, 'error' => 'report_id required.'], 400);
        return;
    }
    $ok = $reportService->confirmResolved($reportId, $userId, $role);
    if ($ok && $role === 'admin') {
        $adminUser = $authService->getUserById($userId);
        $auditLogService->log($userId, 'report_confirm_resolved', 'report', $reportId, null, $adminUser['email'] ?? null);
    }
    if ($ok) {
        $report = $reportService->getOne($reportId);
        if ($report && !empty($report['reporter_id'])) {
            $notificationService->createReportResolved((int)$report['reporter_id'], $reportId, $report['title'] ?? 'Report');
            if (!empty($config['sms_enabled'])) {
                $reporter = $authService->getUserById((int)$report['reporter_id']);
                if ($reporter && !empty($reporter['mobile'])) {
                    $smsPath = $baseDir . '/config/sms.php';
                    if (file_exists($smsPath)) {
                        require_once $smsPath;
                        sendSMS($reporter['mobile'], 'NIR360: Your incident report has been resolved. Thank you for using NIR360.');
                    }
                }
            }
        }
    }
    Helpers::jsonResponse($ok ? ['success' => true] : ['success' => false, 'error' => 'Cannot confirm resolved for this report.']);
    return;
}
if ($path === '/api/admin/reports/sms-preview' && $method === 'GET') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $reportId = (int)($_GET['report_id'] ?? 0);
    if ($reportId <= 0) {
        Helpers::jsonResponse(['success' => false, 'error' => 'report_id required.'], 400);
        return;
    }
    $report = $reportService->getOne($reportId);
    if (!$report) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Report not found.'], 404);
        return;
    }
    $appUrl = rtrim($config['url'] ?? 'http://localhost', '/');
    $dashboardUrl = $appUrl . '/responder/dashboard';
    $message = $reportService->buildResponderAssignSmsMessage($report, $dashboardUrl);
    Helpers::jsonResponse(['success' => true, 'message' => $message]);
    return;
}
if ($path === '/api/reports/delete' && $method === 'POST') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $reportId = (int)($in['report_id'] ?? 0);
    if (!$reportId) {
        Helpers::jsonResponse(['success' => false, 'error' => 'report_id required.'], 400);
        return;
    }
    $report = $reportService->getOne($reportId);
    if (!$report) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Report not found.'], 404);
        return;
    }
    $ok = $reportService->deleteReport($reportId);
    if ($ok) {
        $adminUser = $authService->getUserById($userId);
        $auditLogService->log($userId, 'report_delete', 'report', $reportId, ['title' => $report['title'] ?? ''], $adminUser['email'] ?? null);
        Helpers::jsonResponse(['success' => true]);
    } else {
        Helpers::jsonResponse(['success' => false, 'error' => 'Failed to delete report.']);
    }
    return;
}
if ($path === '/api/reports/assign' && $method === 'POST') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $reportId = (int)($in['report_id'] ?? 0);
    $responderId = (int)($in['responder_id'] ?? 0);
    if (!$reportId || !$responderId) {
        Helpers::jsonResponse(['success' => false, 'error' => 'report_id and responder_id required.'], 400);
        return;
    }
    $ok = $reportService->assignResponder($reportId, $responderId);
    if ($ok) {
        $adminUser = $authService->getUserById($userId);
        $auditLogService->log($userId, 'report_assign', 'report', $reportId, ['responder_id' => $responderId], $adminUser['email'] ?? null);
        $report = $reportService->getOne($reportId);
        $responder = $authService->getUserById($responderId);
        $responderMobile = $responder['mobile'] ?? '';
        $appUrl = rtrim($config['url'] ?? 'http://localhost', '/');
        $dashboardUrl = $appUrl . '/responder/dashboard';
        $smsBody = $report ? $reportService->buildResponderAssignSmsMessage($report, $dashboardUrl) : 'NIR360: You have been assigned to incident #' . $reportId . '. Open: ' . $dashboardUrl;
        if (!empty($config['sms_enabled']) && $responderMobile !== '') {
            $smsPath = $baseDir . '/config/sms.php';
            if (file_exists($smsPath)) {
                require_once $smsPath;
                $sent = sendSMS($responderMobile, $smsBody);
                $smsLogService->log($reportId, $responderId, $responderMobile, $smsBody, $sent ? 'sent' : 'failed', $sent ? null : 'Send failed');
            } else {
                $smsLogService->log($reportId, $responderId, $responderMobile, $smsBody, 'queued', null);
            }
        } else {
            $smsLogService->log($reportId, $responderId, $responderMobile ?: '0', $smsBody, 'queued', $responderMobile === '' ? 'No phone' : 'SMS disabled');
        }
        if ($report && !empty($report['reporter_id'])) {
            $responderProfile = $profileService->getByUserId($responderId);
            $responderName = $responderProfile['full_name'] ?? null;
            $notificationService->createResponderDispatched((int)$report['reporter_id'], $reportId, $report['title'] ?? 'Report', $responderName);
            if (!empty($config['sms_enabled'])) {
                $reporter = $authService->getUserById((int)$report['reporter_id']);
                if ($reporter && !empty($reporter['mobile'])) {
                    $smsPath = $baseDir . '/config/sms.php';
                    if (file_exists($smsPath)) {
                        require_once $smsPath;
                        $reporterMsg = 'NIR360: A responder is on the way. Report #' . $reportId . '. Status: On the way / Ongoing.';
                        if ($responderName !== null && trim($responderName) !== '') {
                            $reporterMsg .= ' Responder: ' . trim($responderName) . '.';
                        }
                        $reporterMsg .= ' Check My reports for live location and ETA.';
                        sendSMS($reporter['mobile'], $reporterMsg);
                    }
                }
            }
        }
    }
    Helpers::jsonResponse($ok ? ['success' => true] : ['success' => false, 'error' => 'Assignment failed.']);
    return;
}
if ($path === '/api/responder/location' && $method === 'POST') {
    if (!$userId || $role !== 'responder') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $lat = isset($in['latitude']) ? (float)$in['latitude'] : null;
    $lng = isset($in['longitude']) ? (float)$in['longitude'] : null;
    if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Valid latitude and longitude required.'], 400);
        return;
    }
    $ok = $profileService->updateLocation($userId, $lat, $lng, null);
    $awaitingClosureIds = [];
    $baseLat = isset($config['base_geofence_lat']) ? (float)$config['base_geofence_lat'] : null;
    $baseLng = isset($config['base_geofence_lng']) ? (float)$config['base_geofence_lng'] : null;
    $radiusM = isset($config['base_geofence_radius_meters']) ? (float)$config['base_geofence_radius_meters'] : 0;
    if ($ok && $baseLat !== null && $baseLng !== null && $radiusM > 0) {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat - $baseLat);
        $dLng = deg2rad($lng - $baseLng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($baseLat)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceM = $earthRadius * $c;
        if ($distanceM <= $radiusM) {
            $awaitingClosureIds = $reportService->setAwaitingClosureForResponder($userId);
        }
    }
    Helpers::jsonResponse([
        'success' => $ok,
        'error' => $ok ? null : 'Update failed.',
        'awaiting_closure_report_ids' => $awaitingClosureIds,
    ]);
    return;
}
if ($path === '/api/admin/responder-locations' && $method === 'GET') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $stmt = $pdo->query(
        "SELECT r.id AS report_id, a.latitude, a.longitude
         FROM incident_reports r
         INNER JOIN users a ON a.id = r.assigned_to
         WHERE r.status = 'dispatched' AND r.assigned_to IS NOT NULL
           AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL"
    );
    $locations = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $locations[(string)$row['report_id']] = [
            'lat' => (float)$row['latitude'],
            'lng' => (float)$row['longitude'],
        ];
    }
    Helpers::jsonResponse(['success' => true, 'locations' => $locations]);
    return;
}
if ($path === '/api/calendar/events' && $method === 'GET') {
    if (!$userId || ($role !== 'admin' && $role !== 'responder')) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
    if ($from === '' || $to === '') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Query parameters from and to (YYYY-MM-DD) are required.'], 400);
        return;
    }
    $params = ['from' => $from, 'to' => $to];
    if ($role === 'admin') {
        if (isset($_GET['incident_type']) && trim((string)$_GET['incident_type']) !== '') {
            $params['incident_type'] = trim($_GET['incident_type']);
        }
        if (isset($_GET['responder_id']) && (int)$_GET['responder_id'] > 0) {
            $params['responder_id'] = (int)$_GET['responder_id'];
        }
        if (isset($_GET['status']) && trim((string)$_GET['status']) !== '') {
            $params['status'] = trim($_GET['status']);
        }
    }
    $rows = $reportService->getEventsForCalendar($userId, $role, $params);
    $events = [];
    foreach ($rows as $r) {
        $start = $r['created_at'] ?? '';
        $end = (isset($r['status']) && $r['status'] === 'resolved' && !empty($r['updated_at']))
            ? ($r['updated_at']) : $start;
        $events[] = [
            'id' => 'report-' . (int)$r['id'],
            'reportId' => (int)$r['id'],
            'title' => '#' . (int)$r['id'] . ' ' . (string)($r['title'] ?? ''),
            'start' => $start,
            'end' => $end,
            'extendedProps' => [
                'reportId' => (int)$r['id'],
                'incident_type' => (string)($r['incident_type'] ?? ''),
                'location' => (string)($r['address'] ?? ''),
                'assigned_responder' => (string)($r['assigned_email'] ?? ''),
                'dispatch_time' => null,
                'resolved_time' => (isset($r['status']) && $r['status'] === 'resolved' && !empty($r['updated_at'])) ? $r['updated_at'] : null,
                'status' => (string)($r['status'] ?? ''),
            ],
        ];
    }
    Helpers::jsonResponse(['success' => true, 'events' => $events]);
    return;
}
if ($path === '/api/admin/users' && $method === 'GET') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    Helpers::jsonResponse(['success' => true, 'users' => $adminService->getUsers()]);
    return;
}
if ($path === '/api/admin/audit-log' && $method === 'GET') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 500;
    $actionType = isset($_GET['action_type']) ? trim((string)$_GET['action_type']) : null;
    if ($actionType === '') {
        $actionType = null;
    }
    $entries = $auditLogService->listRecent($limit, $actionType);
    Helpers::jsonResponse(['success' => true, 'entries' => $entries]);
    return;
}
if ($path === '/api/admin/users/deactivate' && $method === 'POST') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $uid = (int)($in['user_id'] ?? 0);
    if ($uid <= 0) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Invalid user_id.']);
        return;
    }
    $ok = $adminService->setUserActive($uid, 0);
    if ($ok) {
        $adminUser = $authService->getUserById($userId);
        $auditLogService->log($userId, 'user_deactivate', 'user', $uid, null, $adminUser['email'] ?? null);
    }
    Helpers::jsonResponse($ok ? ['success' => true] : ['success' => false, 'error' => 'Update failed.']);
    return;
}
if ($path === '/api/admin/users/activate' && $method === 'POST') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $uid = (int)($in['user_id'] ?? 0);
    if ($uid <= 0) {
        Helpers::jsonResponse(['success' => false, 'error' => 'Invalid user_id.']);
        return;
    }
    $ok = $adminService->setUserActive($uid, 1);
    if ($ok) {
        $adminUser = $authService->getUserById($userId);
        $auditLogService->log($userId, 'user_activate', 'user', $uid, null, $adminUser['email'] ?? null);
    }
    Helpers::jsonResponse($ok ? ['success' => true] : ['success' => false, 'error' => 'Update failed.']);
    return;
}
if ($path === '/api/admin/users/reset-password' && $method === 'POST') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $uid = (int)($in['user_id'] ?? 0);
    $newPassword = (string)($in['new_password'] ?? '');
    if ($uid <= 0 || $newPassword === '') {
        Helpers::jsonResponse(['success' => false, 'error' => 'user_id and new_password required.']);
        return;
    }
    $ok = $adminService->adminResetPassword($uid, $newPassword);
    if ($ok) {
        $adminUser = $authService->getUserById($userId);
        $auditLogService->log($userId, 'user_reset_password', 'user', $uid, null, $adminUser['email'] ?? null);
    }
    Helpers::jsonResponse($ok ? ['success' => true] : ['success' => false, 'error' => 'Password must be at least 8 characters or user not found.']);
    return;
}
if ($path === '/api/admin/view-id' && $method === 'GET') {
    if (!$userId || $role !== 'admin') {
        http_response_code($userId ? 403 : 401);
        exit;
    }
    $uid = (int)($_GET['user_id'] ?? 0);
    if ($uid <= 0) {
        http_response_code(400);
        exit;
    }
    $filePath = $adminService->getUserGovernmentIdPath($uid);
    if ($filePath === null) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'ID image not found.';
        exit;
    }
    $mime = mime_content_type($filePath) ?: 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}
if ($path === '/api/admin/responders' && $method === 'GET') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    Helpers::jsonResponse(['success' => true, 'responders' => $adminService->getResponders()]);
    return;
}
if ($path === '/api/admin/responders' && $method === 'POST') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $result = $adminService->createResponder(
        (string)($in['username'] ?? ''),
        (string)($in['email'] ?? ''),
        (string)($in['mobile'] ?? ''),
        (string)($in['password'] ?? '')
    );
    if ($result['success'] ?? false) {
        $adminUser = $authService->getUserById($userId);
        $auditLogService->log($userId, 'responder_create', 'user', (int)($result['id'] ?? 0), ['email' => $in['email'] ?? ''], $adminUser['email'] ?? null);
    }
    Helpers::jsonResponse($result, $result['success'] ? 201 : 400);
    return;
}
if ($path === '/api/admin/announcements' && $method === 'POST') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $result = $announcementService->create(
        $userId,
        (string)($in['title'] ?? ''),
        (string)($in['body'] ?? '')
    );
    if ($result['success'] ?? false) {
        $adminUser = $authService->getUserById($userId);
        $auditLogService->log($userId, 'announcement_create', 'announcement', (int)($result['id'] ?? 0), ['title' => $in['title'] ?? ''], $adminUser['email'] ?? null);
    }
    Helpers::jsonResponse($result, $result['success'] ? 201 : 400);
    return;
}

if ($path === '/api/admin/users' && $method === 'POST') {
    if (!$userId || $role !== 'admin') {
        Helpers::jsonResponse(['success' => false, 'error' => 'Forbidden.'], $userId ? 403 : 401);
        return;
    }
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $result = $adminService->createUser(
        (string)($in['username'] ?? ''),
        (string)($in['email'] ?? ''),
        (string)($in['mobile'] ?? ''),
        (string)($in['password'] ?? ''),
        (string)($in['role'] ?? 'user')
    );
    if ($result['success'] ?? false) {
        $adminUser = $authService->getUserById($userId);
        $auditLogService->log($userId, 'user_create', 'user', (int)($result['id'] ?? 0), ['email' => $in['email'] ?? '', 'role' => $in['role'] ?? 'user'], $adminUser['email'] ?? null);
    }
    Helpers::jsonResponse($result, $result['success'] ? 201 : 400);
    return;
}

$webBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') ?: '';

// --- Protected role-based routes: /user/, /responder/, /admin/ ---
if (str_starts_with($path, '/user/') || str_starts_with($path, '/responder/') || str_starts_with($path, '/admin/')) {
    if (!$rbac($path, $userId, $role)) {
        if ($method === 'GET' && (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html'))) {
            header('Location: ' . ($webBase ? $webBase . '/' : '/'));
            exit;
        }
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    if ($path === '/user/dashboard' && $method === 'GET') {
        $pageTitle = 'Public Reporter Dashboard';
        require $baseDir . '/templates/user_dashboard.php';
        return;
    }
    if ($path === '/user/history' && $method === 'GET') {
        $pageTitle = 'History / Records';
        require $baseDir . '/templates/user_history.php';
        return;
    }
    if ($path === '/responder/dashboard' && $method === 'GET') {
        $pageTitle = 'Responder Dashboard';
        require $baseDir . '/templates/responder_dashboard.php';
        return;
    }
    if ($path === '/responder/history' && $method === 'GET') {
        $pageTitle = 'History / Records';
        require $baseDir . '/templates/responder_history.php';
        return;
    }
    if ($path === '/responder/calendar' && $method === 'GET') {
        $pageTitle = 'Calendar';
        require $baseDir . '/templates/responder_calendar.php';
        return;
    }
    if ($path === '/admin/dashboard' && $method === 'GET') {
        $pageTitle = 'Admin Dashboard';
        require $baseDir . '/templates/admin_dashboard.php';
        return;
    }
    if ($path === '/admin/manage_users' && $method === 'GET') {
        $pageTitle = 'Manage Users';
        require $baseDir . '/templates/admin_manage_users.php';
        return;
    }
    if ($path === '/admin/announcements' && $method === 'GET') {
        $pageTitle = 'Announcements';
        require $baseDir . '/templates/admin_announcements.php';
        return;
    }
    if ($path === '/admin/audit_log' && $method === 'GET') {
        $pageTitle = 'Audit log';
        require $baseDir . '/templates/admin_audit_log.php';
        return;
    }
    if ($path === '/admin/history' && $method === 'GET') {
        $pageTitle = 'History';
        require $baseDir . '/templates/admin_history.php';
        return;
    }
    if ($path === '/admin/calendar' && $method === 'GET') {
        $pageTitle = 'Calendar';
        require $baseDir . '/templates/admin_calendar.php';
        return;
    }
}

// --- Profile (any authenticated user) ---
if ($path === '/profile' && $method === 'GET') {
    if (!$userId) {
        header('Location: ' . ($webBase ?: '/'));
        exit;
    }
    $profile = $profileService->getProfileForDashboard($userId);
    if (!$profile) {
        $profile = array_merge(
            ['full_name' => '', 'email' => '', 'address' => '', 'barangay' => '', 'role' => $role ?? 'user', 'profile_photo' => null, 'latitude' => null, 'longitude' => null, 'location_address' => null],
            $profile ?? []
        );
    }
    $csrfToken = Helpers::csrfToken();
    $profilePhotoWebPath = $config['profile_photo_web_path'] ?? '/uploads/profile';
    require $baseDir . '/templates/profile_dashboard.php';
    return;
}

// --- Logout ---
if ($path === '/logout' && $method === 'GET') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . (rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') ?: '/'));
    exit;
}

// --- Landing (GET / or /index.php or /register) ---
if ($method === 'GET' && ($path === '/' || $path === '' || $path === '/index.php' || $path === '/register')) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    $csrfToken = Helpers::csrfToken();
    $forgotPasswordUrl = (rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') ?: '') . '/forgot_password.php';
    $passwordResetSuccess = isset($_GET['password_reset']);
    require $baseDir . '/templates/layout.php';
    return;
}

// 404
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
$webBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') ?: '';
$homeUrl = $webBase ? $webBase . '/' : '/';
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not Found – NIR360</title></head><body style="font-family:system-ui,sans-serif;max-width:480px;margin:3rem auto;padding:0 1rem;text-align:center;">';
echo '<h1 style="font-size:1.5rem;">Page not found</h1>';
echo '<p>The page you requested does not exist.</p>';
echo '<p><a href="' . htmlspecialchars($homeUrl) . '" style="color:#2563eb;">Go to NIR360 home</a></p>';
echo '</body></html>';
