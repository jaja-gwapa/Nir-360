<?php
$dashboardHome = match ($role ?? '') {
    'admin' => $webBase . '/admin/dashboard',
    'responder' => $webBase . '/responder/dashboard',
    'user' => $webBase . '/user/dashboard',
    default => $webBase . '/',
};
$roleLabel = ucfirst($role ?? 'user');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> – NIR360</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($webBase ?? '') ?>/assets/styles.css">
  <?= $dashboardExtraHead ?? '' ?>
  <style>
    :root {
      --dash-sidebar-bg: #fff;
      --dash-sidebar-border: #e5e7eb;
      --dash-logo-color: #1f2937;
      --dash-section-color: #888;
      --dash-link-color: #555;
      --dash-link-hover: #2563eb;
      --dash-header-bg: #f9fafb;
      --dash-header-border: #e5e7eb;
    }
    .dash-wrapper { display: flex; min-height: 100vh; flex-direction: column; }
    .dash-header {
      height: 56px; min-height: 56px; background: var(--dash-header-bg); border-bottom: 1px solid var(--dash-header-border);
      display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; flex-shrink: 0;
    }
    .dash-header .app-title { font-size: 1.25rem; font-weight: 600; color: var(--dash-logo-color); }
    .dash-header .header-logout {
      color: var(--dash-link-color); text-decoration: none; font-size: 0.95rem;
    }
    .dash-header .header-logout:hover { color: var(--dash-link-hover); }
    .dash-header .dash-header-right { display: flex; align-items: center; gap: 0.75rem; }
    .notification-bell-wrap { position: relative; }
    .notification-bell-btn {
      display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px;
      border: none; background: transparent; color: var(--dash-link-color); cursor: pointer; border-radius: 8px;
    }
    .notification-bell-btn:hover { background: rgba(37,99,235,0.08); color: var(--dash-link-hover); }
    .notification-badge {
      position: absolute; top: 4px; right: 4px; min-width: 18px; height: 18px; padding: 0 5px;
      font-size: 0.7rem; font-weight: 700; color: #fff; background: #dc2626; border-radius: 9px;
      display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box;
    }
    .notification-badge.hidden { display: none; }
    .notification-dropdown {
      position: absolute; top: 100%; right: 0; margin-top: 4px; width: 360px; max-width: calc(100vw - 2rem);
      background: #fff; border: 1px solid var(--dash-sidebar-border); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
      z-index: 1000; max-height: 70vh; overflow: hidden; display: flex; flex-direction: column;
    }
    .notification-dropdown.hidden { display: none; }
    .notification-dropdown .n-header { padding: 0.75rem 1rem; border-bottom: 1px solid #e5e7eb; font-weight: 600; font-size: 0.95rem; background: #f9fafb; }
    .notification-dropdown .n-list { overflow-y: auto; flex: 1; }
    .notification-dropdown .n-item { padding: 0.65rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: 0.9rem; }
    .notification-dropdown .n-item.unread { background: #f0f9ff; }
    .notification-dropdown .n-item .n-title { font-weight: 500; }
    .notification-dropdown .n-item .n-meta { font-size: 0.8rem; color: var(--dash-section-color); margin-top: 0.2rem; }
    .notification-dropdown .n-item .n-mark { font-size: 0.8rem; color: var(--dash-link-hover); cursor: pointer; margin-top: 0.25rem; }
    .notification-dropdown .n-footer { padding: 0.5rem 1rem; border-top: 1px solid #e5e7eb; background: #f9fafb; }
    .dash-body { display: flex; flex: 1; overflow: hidden; }
    .dash-sidebar {
      width: 240px; min-width: 240px; background: var(--dash-sidebar-bg); border-right: 1px solid var(--dash-sidebar-border);
      padding: 1.5rem 0; display: flex; flex-direction: column; flex-shrink: 0;
    }
    .dash-sidebar .sidebar-section {
      font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em;
      color: var(--dash-section-color); padding: 1rem 1.25rem 0.5rem 0.25rem;
    }
    .dash-sidebar nav { padding: 0.25rem 0 1rem; }
    .dash-sidebar nav a {
      display: block; padding: 0.5rem 1.25rem; color: var(--dash-link-color); text-decoration: none;
      font-size: 1rem; transition: color .15s, background .15s;
    }
    .dash-sidebar nav a:hover { color: var(--dash-link-hover); background: rgba(37,99,235,0.06); }
    .dash-sidebar .sidebar-user-link {
      display: block; padding: 0.5rem 1.25rem; color: var(--dash-link-color); text-decoration: none;
      font-size: 0.95rem; font-weight: 500; transition: color .15s, background .15s; cursor: pointer;
    }
    .dash-sidebar .sidebar-user-link:hover { color: var(--dash-link-hover); background: rgba(37,99,235,0.06); }
    .dash-main { flex: 1; padding: 2rem 1.5rem; overflow-x: auto; background: #fafafa; }
    .dash-main .dash { max-width: 100%; }
    .dash h1 { margin-bottom: 0.5rem; color: #1f2937; }
    .dash .role { color: #6b7280; font-size: 0.9rem; }
    .dash a { color: var(--dash-link-hover); }
    @media (max-width: 768px) {
      .dash-body { flex-direction: column; }
      .dash-sidebar {
        width: 100%; flex-direction: row; flex-wrap: wrap; align-items: center;
        padding: 0.75rem 1rem; gap: 0.5rem; border-right: none; border-bottom: 1px solid var(--dash-sidebar-border);
      }
      .dash-sidebar .sidebar-section { order: 3; width: 100%; padding: 0.25rem 0 0; margin: 0; }
      .dash-sidebar nav { display: flex; flex-wrap: wrap; gap: 0.25rem; padding: 0; flex: none; }
      .dash-sidebar nav a { padding: 0.4rem 0.75rem; }
      .dash-sidebar .sidebar-user-link { padding: 0.4rem 0.75rem; font-size: 0.9rem; }
    }
  </style>
</head>
<body>
  <div class="dash-wrapper">
    <header class="dash-header">
      <span class="app-title">NIR360</span>
      <div class="dash-header-right">
        <?php if (($role ?? null) === 'user'): ?>
        <div class="notification-bell-wrap">
          <button type="button" class="notification-bell-btn" id="notification-bell-btn" aria-label="Notifications">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <span class="notification-badge hidden" id="notification-badge">0</span>
          </button>
          <div class="notification-dropdown hidden" id="notification-dropdown">
            <div class="n-header">Notifications</div>
            <div class="n-list" id="notification-list">Loading…</div>
            <div class="n-footer">
              <button type="button" class="btn btn-outline" id="notification-mark-all-read" style="font-size:0.85rem;">Mark all as read</button>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($webBase ?? '') ?>/logout" class="header-logout">Log out</a>
      </div>
    </header>
    <div class="dash-body">
      <aside class="dash-sidebar">
        <span class="sidebar-section"><?= htmlspecialchars($roleLabel) ?></span>
        <nav>
          <a href="<?= htmlspecialchars($dashboardHome) ?>"><?= ($role ?? '') === 'user' ? 'Public Reports' : 'Dashboard' ?></a>
          <?php if (($role ?? null) === 'responder'): ?>
            <a href="<?= htmlspecialchars($webBase ?? '') ?>/responder/history">History / Records</a>
            <a href="<?= htmlspecialchars($webBase ?? '') ?>/responder/calendar">Calendar</a>
          <?php endif; ?>
          <?php if (($role ?? null) === 'admin'): ?>
            <a href="<?= htmlspecialchars($webBase ?? '') ?>/admin/manage_users.php">Manage Users</a>
            <a href="<?= htmlspecialchars($webBase ?? '') ?>/admin/announcements">Announcements</a>
            <a href="<?= htmlspecialchars($webBase ?? '') ?>/admin/audit_log">Audit log</a>
            <a href="<?= htmlspecialchars($webBase ?? '') ?>/admin/history">History</a>
            <a href="<?= htmlspecialchars($webBase ?? '') ?>/admin/calendar">Calendar</a>
          <?php endif; ?>
          <a href="<?= htmlspecialchars($webBase ?? '') ?>/profile">Profile</a>
        </nav>
        <?php if (($role ?? null) === 'user'): ?>
          <div style="padding:0.5rem 0; border-top:1px solid var(--dash-sidebar-border); margin-top:0.5rem;">
            <a href="#" id="sidebar-my-reports" class="sidebar-user-link">My Reports</a>
            <a href="#" id="sidebar-announcements-link" class="sidebar-user-link">Announcements</a>
            <a href="<?= htmlspecialchars($webBase ?? '') ?>/user/history" class="sidebar-user-link">History / Records</a>
          </div>
        <?php endif; ?>
      </aside>
      <main class="dash-main">
        <div class="dash">
          <?php if (empty($hideDashTitle)): ?><h1><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1><?php endif; ?>
          <?php if (isset($dashboardContent)) echo $dashboardContent; ?>
        </div>
      </main>
    </div>
  </div>
<?= $dashboardExtraScripts ?? '' ?>
<?php if (($role ?? null) === 'user'): ?>
<script>
(function() {
  var base = '<?= htmlspecialchars($webBase ?? '') ?>';
  var bellBtn = document.getElementById('notification-bell-btn');
  var badge = document.getElementById('notification-badge');
  var dropdown = document.getElementById('notification-dropdown');
  var listEl = document.getElementById('notification-list');
  var markAllBtn = document.getElementById('notification-mark-all-read');
  if (!bellBtn || !dropdown || !listEl) return;

  function escapeHtml(s) { if (s == null) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function updateBadge(count) {
    if (!badge) return;
    if (count > 0) { badge.textContent = count > 99 ? '99+' : String(count); badge.classList.remove('hidden'); }
    else { badge.classList.add('hidden'); }
  }
  function renderList(notifications) {
    if (!notifications || !notifications.length) {
      listEl.innerHTML = '<div class="n-item" style="color:var(--dash-section-color);">No notifications yet.</div>';
      return;
    }
    var html = '';
    notifications.forEach(function(n) {
      var unread = !n.read_at;
      html += '<div class="n-item' + (unread ? ' unread' : '') + '" data-id="' + n.id + '">';
      html += '<div class="n-title">' + escapeHtml(n.title || 'Notification') + '</div>';
      if (n.body) html += '<div style="font-size:0.85rem; margin-top:0.2rem;">' + escapeHtml(n.body.length > 120 ? n.body.slice(0, 117) + '…' : n.body) + '</div>';
      html += '<div class="n-meta">' + escapeHtml(n.created_at || '') + '</div>';
      if (unread) html += '<div class="n-mark" data-id="' + n.id + '">Mark as read</div>';
      html += '</div>';
    });
    listEl.innerHTML = html;
    listEl.querySelectorAll('.n-mark').forEach(function(el) {
      el.addEventListener('click', function() {
        var id = parseInt(this.getAttribute('data-id'), 10);
        if (!id) return;
        fetch(base + '/api/notifications/read', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ notification_id: id }),
          credentials: 'same-origin'
        }).then(function(r) { return r.json(); }).then(function(d) {
          if (d.success) loadNotifications();
        });
      });
    });
  }
  function loadNotifications() {
    fetch(base + '/api/notifications', { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.success && Array.isArray(d.notifications)) {
          var unread = d.notifications.filter(function(n) { return !n.read_at; });
          updateBadge(unread.length);
          if (dropdown && !dropdown.classList.contains('hidden')) renderList(d.notifications);
        }
      }).catch(function() {});
  }
  bellBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    if (dropdown.classList.contains('hidden')) {
      dropdown.classList.remove('hidden');
      listEl.textContent = 'Loading…';
      fetch(base + '/api/notifications', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (d.success && Array.isArray(d.notifications)) renderList(d.notifications);
          else listEl.innerHTML = '<div class="n-item" style="color:var(--dash-section-color);">Unable to load.</div>';
        })
        .catch(function() { listEl.textContent = 'Network error.'; });
    } else {
      dropdown.classList.add('hidden');
    }
  });
  markAllBtn.addEventListener('click', function() {
    fetch(base + '/api/notifications/read-all', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.success) loadNotifications(); });
  });
  document.addEventListener('click', function(e) {
    if (dropdown && !dropdown.classList.contains('hidden') && !dropdown.contains(e.target) && !bellBtn.contains(e.target))
      dropdown.classList.add('hidden');
  });
  loadNotifications();
})();
</script>
<?php endif; ?>
</body>
</html>
