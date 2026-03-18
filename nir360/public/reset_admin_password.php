<?php
/**
 * One-time script: Reset password for an admin account (e.g. when you forget it).
 * Usage: Open in browser, enter the admin email and new password, then submit.
 * IMPORTANT: Delete or rename this file after use for security.
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/src/Helpers.php';
$boot = require $baseDir . '/bootstrap.php';
$pdo = $boot['pdo'];

$message = '';
$error = '';

if ($pdo === null) {
    $error = 'Database not available. Check config and MySQL.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if ($email === '') {
        $error = 'Please enter the admin account email.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'No admin account found with that email.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $up = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $up->execute([$hash, (int)$user['id']]);
            $message = 'Password updated for admin "' . htmlspecialchars($user['username']) . '". You can now log in with this email and the new password. Delete this file (reset_admin_password.php) for security.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Admin Password – NIR360</title>
  <style>
    body { font-family: sans-serif; max-width: 420px; margin: 2rem auto; padding: 0 1rem; }
    h1 { font-size: 1.25rem; margin-bottom: 0.5rem; }
    .muted { color: #64748b; font-size: 0.9rem; margin-bottom: 1rem; }
    label { display: block; margin-bottom: 0.25rem; font-weight: 500; }
    input[type="email"], input[type="password"] { width: 100%; padding: 0.5rem; margin-bottom: 1rem; box-sizing: border-box; }
    button { padding: 0.5rem 1rem; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
    button:hover { background: #1d4ed8; }
    .success { background: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
    .error { background: #fee2e2; color: #991b1b; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
  </style>
</head>
<body>
  <h1>Reset Admin Password</h1>
  <p class="muted">Use this only if you forgot your admin login. Enter the admin account email and choose a new password.</p>

  <?php if ($message): ?>
    <div class="success"><?= $message ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$message): ?>
  <form method="post" action="">
    <label for="email">Admin account email</label>
    <input type="email" id="email" name="email" required placeholder="admin@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

    <label for="password">New password (min 8 characters)</label>
    <input type="password" id="password" name="password" required minlength="8">

    <label for="password_confirm">Confirm new password</label>
    <input type="password" id="password_confirm" name="password_confirm" required minlength="8">

    <button type="submit">Reset password</button>
  </form>
  <?php endif; ?>

  <p class="muted" style="margin-top: 1.5rem;">After resetting, <a href="index.php">go to login</a>. Then delete <code>reset_admin_password.php</code> from the server.</p>
</body>
</html>
