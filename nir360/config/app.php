<?php
/**
 * NIR360 App config
 * Optional: create config/app.local.php that returns an array to override values (e.g. ocr_space_api_key).
 */
$config = [
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => (bool)(getenv('APP_DEBUG') ?: false),
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'csrf_token_name' => 'nir360_csrf',
    'upload_storage_path' => dirname(__DIR__) . '/storage/uploads/ids',
    'report_media_upload_path' => dirname(__DIR__) . '/storage/uploads/reports',
    'profile_photo_upload_path' => dirname(__DIR__) . '/public/uploads/profile',
    'profile_photo_web_path' => '/uploads/profile',
    'otp_expiry_minutes' => 5,
    'otp_max_attempts' => 5,
    'otp_lock_minutes' => 15,
    'otp_resend_cooldown_seconds' => 60,

    // Government ID OCR verification (OCR.Space API + optional PhilSys template service)
    'ocr_space_api_key' => getenv('OCR_SPACE_API_KEY') ?: '',
    'id_images_upload_path' => dirname(__DIR__) . '/storage/uploads/id_images',
    'id_images_max_size_bytes' => 5 * 1024 * 1024, // 5MB
    'ocr_confidence_poor_threshold' => 70.0, // below this % = scan_quality "poor"
    // Optional: PhilSys template OCR service (Flask). If set, verification uses /scan-philid for better extraction.
    'ocr_service_url' => getenv('OCR_SERVICE_URL') ?: 'http://127.0.0.1:5000',

    // Mail (PHPMailer) - for forgot password. Override with config/mail.local.php (your app password).
    'mail' => array_merge([
        'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'smtp_port' => (int)(getenv('SMTP_PORT') ?: 587),
        'smtp_username' => getenv('SMTP_USERNAME') ?: '',
        'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
        'from_email' => getenv('MAIL_FROM_EMAIL') ?: getenv('SMTP_USERNAME') ?: 'noreply@localhost',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'NIR360',
    ], (function () {
        $local = __DIR__ . '/mail.local.php';
        return file_exists($local) ? (require $local) : [];
    })()),
    'password_reset_expiry_minutes' => 15,
    'phpmailer_src' => dirname(__DIR__) . '/../PHPMailer/src',

    // SMS (HTTPSMS API) – env or config/sms.local.php
    // Base/office geofence: when responder returns inside this circle, dispatched reports move to "Awaiting Closure"
    'base_geofence_lat' => (float)(getenv('BASE_GEOFENCE_LAT') ?: '10.5333'),
    'base_geofence_lng' => (float)(getenv('BASE_GEOFENCE_LNG') ?: '122.8333'),
    'base_geofence_radius_meters' => (float)(getenv('BASE_GEOFENCE_RADIUS_METERS') ?: '500'),

    'sms_enabled' => (function () {
        $url = getenv('SMS_API_URL') ?: '';
        $user = getenv('SMS_USERNAME') ?: '';
        $key = getenv('SMS_API_KEY') ?: '';
        if ($url !== '' && ($user !== '' || $key !== '')) {
            return true;
        }
        $local = __DIR__ . '/sms.local.php';
        if (file_exists($local)) {
            $c = (require $local);
            return is_array($c) && !empty($c['api_url']) && (!empty($c['username']) || !empty($c['api_key']));
        }
        return false;
    })(),
];

$local = __DIR__ . '/app.local.php';
if (file_exists($local)) {
    $overrides = require $local;
    if (is_array($overrides)) {
        $config = array_merge($config, $overrides);
    }
}

return $config;
