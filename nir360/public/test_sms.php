<?php
/**
 * One-time script: Test SMS sending (httpSMS / config/sms.local.php).
 * Usage: Open in browser, enter recipient number, then submit.
 * IMPORTANT: Delete or restrict this file after testing for security.
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/sms.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim((string)($_POST['to'] ?? ''));
    $body = trim((string)($_POST['body'] ?? 'NIR360 test: SMS is working.'));

    if ($to === '') {
        $error = 'Please enter a recipient phone number (e.g. 09XXXXXXXXX or +639XXXXXXXXX).';
    } elseif ($body === '') {
        $error = 'Please enter a message.';
    } else {
        $sent = sendSMS($to, $body);
        if ($sent) {
            $message = 'SMS sent successfully to ' . htmlspecialchars($to) . '.';
        } else {
            $error = 'SMS failed. Check config/sms.local.php (api_key, from), network, and that the number is valid (09XXXXXXXXX).';
        }
    }
}

$cfg = getSmsConfig();
$configured = !empty($cfg['api_url']) && (!empty($cfg['api_key']) || ($cfg['username'] !== '' && $cfg['password'] !== ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test SMS – NIR360</title>
  <style>
    body { font-family: sans-serif; max-width: 420px; margin: 2rem auto; padding: 0 1rem; }
    h1 { font-size: 1.25rem; margin-bottom: 0.5rem; }
    .muted { color: #64748b; font-size: 0.9rem; margin-bottom: 1rem; }
    label { display: block; margin-bottom: 0.25rem; font-weight: 500; }
    input, textarea { width: 100%; padding: 0.5rem; margin-bottom: 1rem; box-sizing: border-box; }
    textarea { min-height: 80px; resize: vertical; }
    button { padding: 0.5rem 1rem; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
    button:hover { background: #1d4ed8; }
    .success { background: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
    .error { background: #fee2e2; color: #991b1b; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
    .warn { background: #fef3c7; color: #92400e; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
  </style>
</head>
<body>
  <h1>Test SMS</h1>
  <p class="muted">Send a test SMS using your httpSMS config (config/sms.local.php).</p>

  <?php if (!$configured): ?>
    <div class="warn">SMS not configured. Set api_url and api_key (or username/password) in config/sms.local.php.</div>
  <?php endif; ?>

  <?php if ($message): ?>
    <div class="success"><?= $message ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" action="">
    <label for="to">Recipient number (Philippines)</label>
    <input type="text" id="to" name="to" required placeholder="09XXXXXXXXX or +639XXXXXXXXX" value="<?= htmlspecialchars($_POST['to'] ?? '') ?>">

    <label for="body">Message</label>
    <textarea id="body" name="body" placeholder="NIR360 test: SMS is working."><?= htmlspecialchars($_POST['body'] ?? 'NIR360 test: SMS is working.') ?></textarea>

    <button type="submit"<?= $configured ? '' : ' disabled' ?>>Send test SMS</button>
  </form>

  <p class="muted" style="margin-top: 1.5rem;">From number in config: <?= htmlspecialchars($cfg['from'] ?? '(not set)') ?>. Delete test_sms.php after testing.</p>
</body>
</html>
