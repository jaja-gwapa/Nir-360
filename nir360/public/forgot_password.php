<?php
/**
 * Forgot Password – request a password reset link by email (PHPMailer).
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
$message = '';
$isSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $message = 'Please enter your email address.';
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $resetLinkBase = $protocol . '://' . $host . $scriptDir . '/reset_password.php';
        $result = $forgotService->requestReset($email, $resetLinkBase);
        if ($result['success']) {
            $message = 'If an account exists for that email, you will receive a password reset link shortly. Please check your inbox and spam folder.';
            $isSuccess = true;
        } else {
            $message = $result['error'] ?? 'Something went wrong. Please try again.';
        }
    }
}

$webBase = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$assetsUrl = $webBase ? $webBase . '/assets' : 'assets';
$loginUrl = $webBase ? $webBase . '/' : '/';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password – NIR360</title>
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
    <h1 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Forgot Password</h1>
    <p style="color: #64748b; margin-bottom: 1.5rem;">Enter the email address you used to register. We’ll send you a link to reset your password.</p>

    <?php if ($message !== ''): ?>
      <div class="<?= $isSuccess ? 'success-box' : 'error-box' ?>" style="margin-bottom: 1rem;" role="alert">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="form-group">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Send reset link</button>
    </form>

    <p style="margin-top: 1.5rem;">
      <a href="<?= htmlspecialchars($loginUrl) ?>" style="color: #2563eb;">Back to login</a>
    </p>
  </main>
</body>
</html>
