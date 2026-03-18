<?php
/**
 * NIR360 Bootstrap - load config, DB, helpers
 */
declare(strict_types=1);

$configPath = __DIR__ . '/config';
$config = [
    'app' => require $configPath . '/app.php',
    'db' => require $configPath . '/database.php',
];

$pdo = null;
$dbError = null;
try {
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['username'],
        $config['db']['password'],
        $config['db']['options']
    );
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

// Ensure upload storage exists (only if DB is ok)
if ($pdo && isset($config['app']['upload_storage_path'])) {
    $uploadPath = $config['app']['upload_storage_path'];
    if (!is_dir($uploadPath)) {
        @mkdir($uploadPath, 0755, true);
    }
}
if ($pdo && isset($config['app']['profile_photo_upload_path'])) {
    $profilePath = $config['app']['profile_photo_upload_path'];
    if (!is_dir($profilePath)) {
        @mkdir($profilePath, 0755, true);
    }
}
if ($pdo && isset($config['app']['id_images_upload_path'])) {
    $idImagesPath = $config['app']['id_images_upload_path'];
    if (!is_dir($idImagesPath)) {
        @mkdir($idImagesPath, 0755, true);
    }
}
return [
    'pdo' => $pdo,
    'config' => $config,
    'db_error' => $dbError,
];
