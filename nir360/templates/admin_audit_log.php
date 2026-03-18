<?php
$pageTitle = 'Audit log';
ob_start();
?>
<p class="role" style="margin-bottom:1rem;">Administrative actions (assignments, status changes, user management) with timestamp and admin identity.</p>

<div style="margin-bottom:1rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
  <label style="font-size:0.9rem;">
    Action type
    <select id="audit-action-type" style="margin-left:0.35rem; padding:0.35rem 0.5rem;">
      <option value="">All</option>
      <option value="report_assign">Report assign</option>
      <option value="report_confirm_resolved">Report confirm resolved</option>
      <option value="report_delete">Report delete</option>
      <option value="user_deactivate">User deactivate</option>
      <option value="user_activate">User activate</option>
      <option value="user_reset_password">User reset password</option>
      <option value="user_create">User create</option>
      <option value="responder_create">Responder create</option>
      <option value="announcement_create">Announcement create</option>
    </select>
  </label>
  <label style="font-size:0.9rem;">
    Limit
    <select id="audit-limit" style="margin-left:0.35rem; padding:0.35rem 0.5rem;">
      <option value="50">50</option>
      <option value="100">100</option>
      <option value="250" selected>250</option>
      <option value="500">500</option>
    </select>
  </label>
  <button type="button" id="audit-refresh" class="btn btn-outline" style="font-size:0.9rem;">Refresh</button>
</div>

<div id="audit-loading" style="color:var(--muted); font-size:0.9rem;">Loading…</div>
<div id="audit-error" class="error-box" style="display:none; margin-bottom:1rem;"></div>
<div id="audit-list" style="display:none;"></div>

<script>
(function() {
  var base = '<?= htmlspecialchars($webBase ?? '') ?>';
  var loadingEl = document.getElementById('audit-loading');
  var errorEl = document.getElementById('audit-error');
  var listEl = document.getElementById('audit-list');

  function escapeHtml(str) {
    if (str == null || str === '') return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function formatActionType(action) {
    var labels = {
      report_assign: 'Report assign',
      report_confirm_resolved: 'Confirm resolved',
      user_deactivate: 'User deactivate',
      user_activate: 'User activate',
      user_reset_password: 'User reset password',
      user_create: 'User create',
      responder_create: 'Responder create',
      announcement_create: 'Announcement create'
    };
    return labels[action] || action;
  }

  function render() {
    var actionType = document.getElementById('audit-action-type').value || undefined;
    var limit = document.getElementById('audit-limit').value || '250';
    var qs = '?limit=' + limit;
    if (actionType) qs += '&action_type=' + encodeURIComponent(actionType);

    loadingEl.style.display = 'block';
    errorEl.style.display = 'none';
    listEl.style.display = 'none';

    fetch(base + '/api/admin/audit-log' + qs, { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        loadingEl.style.display = 'none';
        if (!d.success) {
          errorEl.textContent = d.error || 'Failed to load audit log.';
          errorEl.style.display = 'block';
          return;
        }
        var entries = d.entries || [];
        if (entries.length === 0) {
          listEl.innerHTML = '<p class="role">No audit entries found.</p>';
        } else {
          var html = '<ul style="list-style:none; padding:0;">';
          entries.forEach(function(e) {
            var details = e.details && typeof e.details === 'object' ? JSON.stringify(e.details) : (e.details || '');
            if (details.length > 80) details = details.substring(0, 77) + '…';
            html += '<li style="border:1px solid #e2e8f0; border-radius:8px; padding:0.6rem 0.75rem; margin-bottom:0.4rem; font-size:0.9rem;">';
            html += '<strong>' + escapeHtml(formatActionType(e.action_type)) + '</strong>';
            html += ' <span style="color:var(--muted);">' + escapeHtml(e.created_at) + '</span>';
            html += ' — Admin: ' + escapeHtml(e.admin_email || 'id:' + e.admin_id);
            if (e.target_type && e.target_id) {
              html += ' · ' + escapeHtml(e.target_type) + ' #' + escapeHtml(String(e.target_id));
            }
            if (details) html += ' <span style="color:#6b7280;">' + escapeHtml(details) + '</span>';
            if (e.ip_address) html += ' <small style="color:var(--muted);">IP: ' + escapeHtml(e.ip_address) + '</small>';
            html += '</li>';
          });
          html += '</ul>';
          listEl.innerHTML = html;
        }
        listEl.style.display = 'block';
      })
      .catch(function() {
        loadingEl.style.display = 'none';
        errorEl.textContent = 'Network error.';
        errorEl.style.display = 'block';
      });
  }

  document.getElementById('audit-refresh').addEventListener('click', render);
  document.getElementById('audit-action-type').addEventListener('change', render);
  document.getElementById('audit-limit').addEventListener('change', render);
  render();
})();
</script>
<?php
$dashboardContent = ob_get_clean();
require $baseDir . '/templates/dashboard_layout.php';
