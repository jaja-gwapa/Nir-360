<?php
/**
 * One-time migration: add profile_photo and location columns to users table if missing.
 * Open this file in your browser once (e.g. http://localhost/.../nir360/public/migrate_profile_photo.php),
 * then delete it for security.
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/src/Helpers.php';
$boot = require $baseDir . '/bootstrap.php';
$pdo = $boot['pdo'];

header('Content-Type: text/html; charset=utf-8');

if (!$pdo) {
    echo '<h1>Migration failed</h1><p>Database not connected. Check nir360/config/database.php.</p>';
    exit;
}

$dbConfig = $boot['config']['db'] ?? [];
$dbName = $dbConfig['database'] ?? null;
if (!$dbName && preg_match('/dbname=([^;]+)/', $dbConfig['dsn'] ?? '', $m)) {
    $dbName = trim($m[1]);
}
if (!$dbName) {
    echo '<h1>Migration failed</h1><p>Could not detect database name.</p>';
    exit;
}

function columnExists(PDO $pdo, string $db, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$db, $table, $column]);
    return (bool) $stmt->fetch();
}

$done = [];
$errors = [];

if (!columnExists($pdo, $dbName, 'users', 'profile_photo')) {
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL DEFAULT NULL');
        $done[] = 'profile_photo';
    } catch (Throwable $e) {
        $errors[] = 'profile_photo: ' . $e->getMessage();
    }
}
if (!columnExists($pdo, $dbName, 'users', 'latitude')) {
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN latitude DECIMAL(10, 8) NULL DEFAULT NULL');
        $done[] = 'latitude';
    } catch (Throwable $e) {
        $errors[] = 'latitude: ' . $e->getMessage();
    }
}
if (!columnExists($pdo, $dbName, 'users', 'longitude')) {
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN longitude DECIMAL(11, 8) NULL DEFAULT NULL');
        $done[] = 'longitude';
    } catch (Throwable $e) {
        $errors[] = 'longitude: ' . $e->getMessage();
    }
}
if (!columnExists($pdo, $dbName, 'users', 'location_address')) {
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN location_address TEXT NULL DEFAULT NULL');
        $done[] = 'location_address';
    } catch (Throwable $e) {
        $errors[] = 'location_address: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile photo migration – NIR360</title>
  <style>body{font-family:system-ui,sans-serif;max-width:520px;margin:2rem auto;padding:0 1rem;} .ok{color:#166534;} .err{color:#b91c1c;} code{background:#f1f5f9;padding:2px 6px;}</style>
</head>
<body>
  <h1>Profile photo migration</h1>
  <?php if (!empty($done)): ?>
    <p class="ok"><strong>Added columns:</strong> <?= htmlspecialchars(implode(', ', $done)) ?></p>
    <p>Profile photo upload should work now. Try uploading again from your profile page.</p>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <p class="err"><strong>Errors:</strong></p>
    <ul class="err"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if (empty($done) && empty($errors)): ?>
    <p>All columns already exist. No changes made.</p>
  <?php endif; ?>
  <p style="margin-top:1.5rem;"><strong>Security:</strong> Delete <code>migrate_profile_photo.php</code> from your server after running this.</p>
  <p><a href="javascript:history.back()">Go back</a> or <a href="<?= htmlspecialchars(dirname($_SERVER['SCRIPT_NAME'] ?? '')) ?>/">Go to NIR360</a></p>
</body>
</html>
