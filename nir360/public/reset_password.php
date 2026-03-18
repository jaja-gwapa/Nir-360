<?php
/**
 * Reset Password – set new password using token from email link.
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__);
$boot = require $baseDir . '/bootstrap.php';
$pdo = $boot['pdo'];
$config = $boot['config']['app'];

if ($pdo === null) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>NIR360</title></head><body><p>Database unavailable. Please try again later.</p></body></html>';
    exit;
}

require_once $baseDir . '/src/Service/AuthService.php';
require_once $baseDir . '/src/Service/ForgotPasswordService.php';

$forgotService = new ForgotPasswordService($pdo, $config);
$webBase = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$assetsUrl = $webBase ? $webBase . '/assets' : 'assets';
$loginUrl = $webBase ? $webBase . '/' : '/';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$validToken = false;
$email = '';

if ($token !== '') {
    $validation = $forgotService->validateToken($token);
    if ($validation['valid']) {
        $validToken = true;
        $email = $validation['email'];
    } else {
        $error = $validation['error'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $error = 'Invalid or expired reset link. Please request a new password reset.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
        $validToken = true;
        $validation = $forgotService->validateToken($token);
        if ($validation['valid']) {
            $email = $validation['email'];
        }
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
        $validToken = true;
        $email = $forgotService->validateToken($token)['email'] ?? '';
    } else {
        $result = $forgotService->resetPassword($token, $password);
        if ($result['success']) {
            header('Location: ' . $loginUrl . '?password_reset=1');
            exit;
        }
        $error = $result['error'] ?? 'Could not reset password.';
        $validToken = true;
        $validation = $forgotService->validateToken($token);
        if ($validation['valid']) {
            $email = $validation['email'];
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password – NIR360</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($assetsUrl) ?>/styles.css">
</head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a href="<?= htmlspecialchars($loginUrl) ?>" class="logo">NIR360</a>
      <nav class="nav">
        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-ghost">Login</a>
      </nav>
    </div>
  </header>
  <main class="main" style="max-width: 420px; margin: 2rem auto; padding: 0 1rem;">
    <h1 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Reset Password</h1>

    <?php if ($error !== '' && !$validToken): ?>
      <div class="error-box" style="margin-bottom: 1rem;" role="alert">
        <?= htmlspecialchars($error) ?>
      </div>
      <p><a href="<?= htmlspecialchars($webBase) ?>/forgot_password.php" style="color: #2563eb;">Request a new reset link</a></p>
      <p style="margin-top: 1rem;"><a href="<?= htmlspecialchars($loginUrl) ?>" style="color: #2563eb;">Back to login</a></p>
    <?php elseif ($validToken): ?>
      <p style="color: #64748b; margin-bottom: 1.5rem;">Enter a new password for your account.</p>

      <?php if ($error !== ''): ?>
        <div class="error-box" style="margin-bottom: 1rem;" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="form-group">
          <label for="password">New password</label>
          <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
        </div>
        <div class="form-group">
          <label for="password_confirm">Confirm new password</label>
          <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password" placeholder="Repeat password">
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Reset password</button>
      </form>

      <p style="margin-top: 1.5rem;"><a href="<?= htmlspecialchars($loginUrl) ?>" style="color: #2563eb;">Back to login</a></p>
    <?php else: ?>
      <p style="color: #64748b;">Use the link from your email to reset your password.</p>
      <p style="margin-top: 1rem;"><a href="<?= htmlspecialchars($loginUrl) ?>" style="color: #2563eb;">Back to login</a></p>
    <?php endif; ?>
  </main>
</body>
</html>
