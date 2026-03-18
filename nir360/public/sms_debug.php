<?php
/**
 * Debug: Why didn't registration SMS arrive?
 * Shows sms_enabled, recent users' mobiles, and lets you send a test SMS.
 * DELETE this file after debugging.
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__);
$boot = require $baseDir . '/bootstrap.php';
$pdo = $boot['pdo'];
$config = $boot['config']['app'] ?? [];

$smsEnabled = !empty($config['sms_enabled']);
$smsPath = $baseDir . '/config/sms.php';
$smsLoaded = file_exists($smsPath);
if ($smsLoaded) {
    require_once $smsPath;
}
$cfg = $smsLoaded && function_exists('getSmsConfig') ? getSmsConfig() : [];
$apiUrl = $cfg['api_url'] ?? '';
$hasKey = !empty($cfg['api_key']) || (!empty($cfg['username']) && $cfg['password'] !== null);

$message = '';
$error = '';
$users = [];

if ($pdo) {
    $stmt = $pdo->query('SELECT id, username, email, mobile, created_at FROM users ORDER BY id DESC LIMIT 10');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $smsLoaded && function_exists('sendSMS')) {
    $to = trim((string)($_POST['to'] ?? ''));
    $body = trim((string)($_POST['body'] ?? 'NIR360 test: SMS is working.'));
    if ($to === '') {
        $error = 'Enter a recipient number or pick one from the list.';
    } else {
        $sent = sendSMS($to, $body);
        if ($sent) {
            $message = 'SMS sent to ' . htmlspecialchars($to) . '.';
        } else {
            $error = 'SMS failed. Check: (1) config/sms.local.php api_key and from, (2) httpSMS app is open on the sender phone, (3) number is 09XXXXXXXXX.';
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
  <title>SMS debug – NIR360</title>
  <style>
    body { font-family: sans-serif; max-width: 560px; margin: 2rem auto; padding: 0 1rem; }
    h1 { font-size: 1.25rem; }
    h2 { font-size: 1rem; margin-top: 1.5rem; }
    .ok { color: #166534; }
    .bad { color: #b91c1c; }
    .muted { color: #64748b; font-size: 0.9rem; }
    table { width: 100%; border-collapse: collapse; margin: 0.5rem 0; font-size: 0.9rem; }
    th, td { text-align: left; padding: 0.35rem 0.5rem; border-bottom: 1px solid #e2e8f0; }
    input[type="text"], textarea { width: 100%; padding: 0.5rem; box-sizing: border-box; margin: 0.25rem 0; }
    textarea { min-height: 60px; resize: vertical; }
    button { padding: 0.5rem 1rem; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; margin-top: 0.5rem; }
    button:hover { background: #1d4ed8; }
    .success { background: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 6px; margin: 0.5rem 0; }
    .err { background: #fee2e2; color: #991b1b; padding: 0.75rem; border-radius: 6px; margin: 0.5rem 0; }
    .box { background: #f8fafc; padding: 0.75rem; border-radius: 6px; margin: 0.5rem 0; }
  </style>
</head>
<body>
  <h1>SMS debug (registration didn’t get SMS)</h1>
  <p class="muted">This page helps find why the registration SMS wasn’t received. Delete sms_debug.php when done.</p>

  <h2>1. Is SMS enabled in the app?</h2>
  <div class="box">
    <strong>sms_enabled:</strong>
    <?php if ($smsEnabled): ?><span class="ok">Yes</span><?php else: ?><span class="bad">No</span> – set api_url and api_key in config/sms.local.php<?php endif; ?>
  </div>

  <h2>2. SMS config</h2>
  <div class="box">
    api_url: <?= htmlspecialchars($apiUrl ?: '(empty)') ?><br>
    api_key / username: <?= $hasKey ? '<span class="ok">Set</span>' : '<span class="bad">Missing</span>' ?><br>
    from (sender): <?= htmlspecialchars($cfg['from'] ?? '(not set)') ?>
  </div>

  <h2>3. Recent users (mobile in DB)</h2>
  <?php if (empty($users)): ?>
    <p class="muted">No users in database yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Mobile</th><th>Created</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars($u['username'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['mobile'] ?? '—') ?></td>
            <td><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2>4. Send a test SMS</h2>
  <?php if ($message): ?><div class="success"><?= $message ?></div><?php endif; ?>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if ($smsLoaded && function_exists('sendSMS')): ?>
  <form method="post" action="">
    <label>Recipient (e.g. copy a mobile from the table above)</label>
    <input type="text" name="to" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($_POST['to'] ?? '') ?>">
    <label>Message</label>
    <textarea name="body">NIR360 test: SMS is working.</textarea>
    <button type="submit">Send test SMS</button>
  </form>
  <?php else: ?>
  <p class="bad">Cannot send: config/sms.php not loaded or sendSMS not available.</p>
  <?php endif; ?>

  <p class="muted" style="margin-top: 1.5rem;">If “Send test SMS” works but registration still doesn’t send, the registration flow may be failing before the SMS line (e.g. ID upload error). Check the browser Network tab for the registration response.</p>
</body>
</html>
