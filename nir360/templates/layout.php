<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NIR360</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a href="<?= htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/') ?>" class="logo">NIR360</a>
      <nav class="nav">
        <button type="button" class="btn btn-ghost" data-modal-open="login">Login</button>
        <button type="button" class="btn btn-outline" data-modal-open="create-account">Create Account</button>
      </nav>
    </div>
  </header>
  <main class="main">
    <?php include $baseDir . '/templates/landing.php'; ?>
  </main>

  <div class="emergency-alert-banner" role="alert" aria-live="polite">
    <div class="emergency-alert-arrows emergency-alert-arrows--top" aria-hidden="true">
      <?php for ($i = 0; $i < 4; $i++): ?>
      <span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span><span class="emergency-alert-arrow">&#10095;</span>
      <?php endfor; ?>
    </div>
    <div class="emergency-alert-marquee-wrap">
      <div class="emergency-alert-marquee" aria-hidden="true">
        <span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span>
        <span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span><span class="emergency-alert-item">&#9888; EMERGENCY ALERT</span>
      </div>
    </div>
    <div class="emergency-alert-arrows emergency-alert-arrows--bottom" aria-hidden="true">
      <?php for ($i = 0; $i < 4; $i++): ?>
      <span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span><span class="emergency-alert-arrow">&#10094;</span>
      <?php endfor; ?>
    </div>
  </div>

  <script>
    window.NIR360_CSRF = <?= json_encode($csrfToken) ?>;
  </script>
  <?php $appJsVer = @filemtime(__DIR__ . '/../public/assets/app.js') ?: time(); ?>
  <script src="assets/app.js?v=<?= (int)$appJsVer ?>"></script>
</body>
</html>
