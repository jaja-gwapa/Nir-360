<?php
$pageTitle = 'Manage Users';
$allUsers = $adminService->getUsers();
$currentAdminId = isset($userId) ? (int)$userId : 0;
$dashboardExtraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/2.3.7/css/dataTables.dataTables.min.css">
<style>
  .dash-main .dash { max-width: none; }
  .admin-users-table-wrap { width: 100%; overflow-x: auto; }
  .admin-users-datatable-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-top: 0.5rem; }
  .users-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; table-layout: auto; }
  .users-table th, .users-table td { vertical-align: top; white-space: normal; word-break: break-word; }
  .users-table .user-actions-cell { white-space: normal !important; }
  #admin-users-table thead th,
  .dataTables_wrapper #admin-users-table thead th {
    white-space: nowrap;
    text-align: left;
    vertical-align: middle;
    padding: 0.75rem 0.5rem;
    min-width: 4rem;
  }
  #admin-users-table thead th:nth-child(1) { min-width: 6rem; }
  #admin-users-table thead th:nth-child(2) { min-width: 5.5rem; }
  #admin-users-table thead th:nth-child(3) { min-width: 10rem; }
  #admin-users-table thead th:nth-child(4) { min-width: 6rem; }
  #admin-users-table thead th:nth-child(7) { min-width: 6rem; }
  #admin-users-table thead th:nth-child(8) { min-width: 8rem; }
  #admin-users-table thead th:nth-child(9) { min-width: 5rem; }
  .dataTables_wrapper .dataTables_length { margin-bottom: 1rem; }
  .dataTables_wrapper .dataTables_filter { margin-bottom: 1rem; }
  .dataTables_wrapper .dataTables_info { margin-top: 1rem; padding-top: 0.75rem; }
  .dataTables_wrapper .dataTables_paginate { margin-top: 1rem; padding-top: 0.5rem; }
  /* Make action buttons compact (table only) */
  #admin-users-table .btn,
  .dataTables_wrapper #admin-users-table .btn {
    font-size: 0.78rem;
    padding: 0.25rem 0.5rem;
    line-height: 1.1;
    border-radius: 6px;
  }
  #admin-users-table .user-actions-cell a {
    font-size: 0.82rem;
  }
</style>';
ob_start();
?>
<p class="role" style="margin-bottom:1rem;">Create users and manage all existing accounts.</p>

<section style="margin-bottom:2rem;">
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">Create user account</h2>
  <form id="form-create-user" style="max-width:400px;">
    <div class="form-group">
      <label for="new-user-username">Username *</label>
      <input type="text" id="new-user-username" name="username" required minlength="3" maxlength="50">
    </div>
    <div class="form-group">
      <label for="new-user-email">Email *</label>
      <input type="email" id="new-user-email" name="email" required>
    </div>
    <div class="form-group">
      <label for="new-user-mobile">Mobile *</label>
      <input type="tel" id="new-user-mobile" name="mobile" required placeholder="09171234567" pattern="[0-9]*">
    </div>
    <div class="form-group">
      <label for="new-user-role">Role *</label>
      <select id="new-user-role" name="role" required>
        <option value="user">User</option>
        <option value="responder">Responder</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div class="form-group">
      <label for="new-user-password">Password *</label>
      <input type="password" id="new-user-password" name="password" required minlength="8">
    </div>
    <button type="submit" class="btn btn-primary">Create user</button>
  </form>
  <div id="create-user-msg" class="success-box" style="display:none; margin-top:0.5rem;"></div>
  <div id="create-user-err" class="error-box" style="display:none; margin-top:0.5rem;"></div>
</section>

<section>
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">All users</h2>
  <?php if (empty($allUsers)): ?>
    <p class="role">No users.</p>
  <?php else: ?>
    <div style="margin-bottom:0.5rem;">
      <select id="users-status-filter" style="padding:0.4rem 0.6rem; border:1px solid var(--border); border-radius:6px;">
        <option value="">All statuses</option>
        <option value="Active">Active</option>
        <option value="Inactive">Inactive</option>
      </select>
    </div>
    <div class="admin-users-datatable-panel">
    <div class="admin-users-table-wrap">
      <table id="admin-users-table" class="users-table display" style="width:100%;">
        <thead>
          <tr>
            <th>Full Name</th>
            <th>Username</th>
            <th>Email</th>
            <th>ID/Mobile</th>
            <th>Role</th>
            <th>Status</th>
            <th>Verification</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allUsers as $u):
            $uid = (int)$u['id'];
            $active = (int)($u['is_active'] ?? 1);
            $status = $active ? 'active' : 'inactive';
            $searchText = strtolower(implode(' ', [
              (string)($u['full_name'] ?? ''),
              (string)($u['username'] ?? ''),
              (string)($u['email'] ?? ''),
              (string)($u['mobile'] ?? '')
            ]));
            $isCurrentAdmin = ($currentAdminId > 0 && $uid === $currentAdminId);
            $viewIdUrl = !empty($u['government_id_path']) ? $webBase . '/api/admin/view-id?user_id=' . $uid : null;
          ?>
          <tr class="user-row" data-user-id="<?= $uid ?>"
              data-status="<?= $status ?>"
              data-search="<?= htmlspecialchars($searchText) ?>">
            <td style="padding:0.5rem;"><?= htmlspecialchars($u['full_name'] ?? '—') ?></td>
            <td style="padding:0.5rem;"><?= htmlspecialchars($u['username'] ?? '') ?></td>
            <td style="padding:0.5rem;"><?= htmlspecialchars($u['email'] ?? '') ?></td>
            <td style="padding:0.5rem;"><?= htmlspecialchars($u['mobile'] ?? '—') ?></td>
            <td style="padding:0.5rem;"><?= htmlspecialchars($u['role'] ?? '') ?></td>
            <td class="user-status-cell" style="padding:0.5rem;"><?= $active ? 'Active' : 'Inactive' ?></td>
            <td style="padding:0.5rem;"><?= htmlspecialchars($u['verification_status'] ?? '—') ?></td>
            <td style="padding:0.5rem;"><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
            <td class="user-actions-cell" style="padding:0.5rem;">
              <?php if ($viewIdUrl): ?>
                <a href="<?= htmlspecialchars($viewIdUrl) ?>" target="_blank" rel="noopener">View ID</a>
                <span style="color:var(--muted);">|</span>
              <?php endif; ?>
              <?php if (!$isCurrentAdmin): ?>
                <button type="button" class="btn-deactivate btn btn-outline" style="display:<?= $active ? 'inline-block' : 'none' ?>; margin:0 2px;">Deactivate</button>
                <button type="button" class="btn-activate btn btn-outline" style="display:<?= $active ? 'none' : 'inline-block' ?>; margin:0 2px;">Activate</button>
                <span style="color:var(--muted);">|</span>
              <?php endif; ?>
              <button type="button" class="btn-reset-pw btn btn-outline" style="margin:0 2px;">Reset password</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </div>
  <?php endif; ?>
</section>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.min.js"></script>
<script>
(function() {
  var base = '<?= htmlspecialchars($webBase ?? '') ?>';
  var form = document.getElementById('form-create-user');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      document.getElementById('create-user-msg').style.display = 'none';
      document.getElementById('create-user-err').style.display = 'none';
      var payload = {
        username: document.getElementById('new-user-username').value.trim(),
        email: document.getElementById('new-user-email').value.trim(),
        mobile: document.getElementById('new-user-mobile').value.replace(/\D/g, ''),
        role: document.getElementById('new-user-role').value,
        password: document.getElementById('new-user-password').value
      };
      fetch(base + '/api/admin/users', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
          document.getElementById('create-user-msg').textContent = 'User created.';
          document.getElementById('create-user-msg').style.display = 'block';
          form.reset();
          setTimeout(function() { window.location.reload(); }, 800);
        } else {
          document.getElementById('create-user-err').textContent = d.error || 'Failed.';
          document.getElementById('create-user-err').style.display = 'block';
        }
      });
    });
  }

  var dataTable = null;
  function initUsersDataTable() {
    var tableEl = document.getElementById('admin-users-table');
    if (!tableEl) return;
    var DT = window.DataTable || window.dataTable;
    if (typeof DT === 'undefined') {
      setTimeout(initUsersDataTable, 100);
      return;
    }
    try {
      dataTable = new DT(tableEl, {
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        order: [[7, 'desc']],
        columnDefs: [{ orderable: false, targets: 8 }],
        language: {
          lengthMenu: 'Show _MENU_ entries',
          search: 'Search:',
          info: 'Showing _START_ to _END_ of _TOTAL_ entries',
          infoEmpty: 'Showing 0 to 0 of 0 entries',
          infoFiltered: '(filtered from _MAX_ total entries)',
          zeroRecords: 'No matching records found',
          paginate: {
            first: '«',
            previous: '‹',
            next: '›',
            last: '»'
          }
        }
      });
      var statusEl = document.getElementById('users-status-filter');
      if (statusEl) {
        statusEl.addEventListener('change', function() {
          var v = this.value;
          dataTable.column(5).search(v ? '^' + v + '$' : '', true, false).draw();
        });
      }
    } catch (err) {
      console.error('DataTable init error:', err);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initUsersDataTable);
  } else {
    initUsersDataTable();
  }

  document.getElementById('admin-users-table') && document.getElementById('admin-users-table').addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-deactivate, .btn-activate, .btn-reset-pw');
    if (!btn) return;
    var tr = e.target.closest('tr');
    var userId = parseInt(tr && tr.getAttribute('data-user-id'), 10);
    if (!userId) return;
    var statusCell = tr.querySelector('.user-status-cell');
    var btnDeactivate = tr.querySelector('.btn-deactivate');
    var btnActivate = tr.querySelector('.btn-activate');
    function setRowActive(active) {
      tr.setAttribute('data-status', active ? 'active' : 'inactive');
      if (statusCell) statusCell.textContent = active ? 'Active' : 'Inactive';
      if (btnDeactivate) btnDeactivate.style.display = active ? 'inline-block' : 'none';
      if (btnActivate) btnActivate.style.display = active ? 'none' : 'inline-block';
    }
    if (btn.classList.contains('btn-deactivate')) {
      if (!confirm('Deactivate this user?')) return;
      fetch(base + '/api/admin/users/deactivate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) setRowActive(false);
        else alert(d.error || 'Failed.');
      });
    } else if (btn.classList.contains('btn-activate')) {
      fetch(base + '/api/admin/users/activate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) setRowActive(true);
        else alert(d.error || 'Failed.');
      });
    } else if (btn.classList.contains('btn-reset-pw')) {
      var newPw = prompt('Enter new password (min 8 characters):');
      if (newPw === null) return;
      if (newPw.length < 8) {
        alert('Password must be at least 8 characters.');
        return;
      }
      fetch(base + '/api/admin/users/reset-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, new_password: newPw })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) alert('Password updated.');
        else alert(d.error || 'Failed.');
      });
    }
  });
})();
</script>
<?php
$dashboardContent = ob_get_clean();
require $baseDir . '/templates/dashboard_layout.php';
