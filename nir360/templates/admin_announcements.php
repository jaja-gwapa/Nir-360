<?php
$pageTitle = 'Announcements';
$allAnnouncements = $announcementService->listForUsers(100);
$dashboardExtraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/2.3.7/css/dataTables.dataTables.min.css"><style>
  .announcements-table-wrap { width: 100%; overflow-x: auto; }
  .announcements-datatable-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-top: 0.5rem; }
  #announcements-table { width: 100% !important; }
  #announcements-table thead th { white-space: nowrap; text-align: left; vertical-align: middle; padding: 0.6rem 0.5rem; border-bottom: 2px solid #e2e8f0; background: #f8fafc; font-weight: 600; font-size: 0.85rem; color: #374151; }
  #announcements-table tbody td { padding: 0.55rem 0.5rem; vertical-align: middle; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; }
  #announcements-table tbody tr:hover { background: #f8fafc; }
  #announcements-table .ann-body-cell { max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { margin-bottom: 0.75rem; }
  .dataTables_wrapper .dataTables_filter input { padding: 0.4rem 0.6rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem; }
  .dataTables_wrapper .dataTables_length select { padding: 0.3rem 0.5rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem; }
  .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { margin-top: 0.75rem; padding-top: 0.5rem; }
  .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 0.3rem 0.6rem; margin: 0 2px; border-radius: 4px; }
  .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #2563eb; color: #fff !important; border: 1px solid #2563eb; }
  .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current) { background: #f3f4f6; }
  @media (max-width: 768px) { .announcements-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; } #announcements-table thead th, #announcements-table tbody td { font-size: 0.8rem; padding: 0.4rem 0.35rem; } }
</style>';
ob_start();
?>
<p class="role" style="margin-bottom:1rem;">Post announcements that all users can view on their dashboard.</p>

<section style="margin-bottom:2rem;">
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">Post new announcement</h2>
  <form id="form-announcement" style="max-width:500px;">
    <div class="form-group">
      <label for="ann-title">Title *</label>
      <input type="text" id="ann-title" name="title" required maxlength="255" placeholder="Announcement title">
    </div>
    <div class="form-group">
      <label for="ann-body">Message *</label>
      <textarea id="ann-body" name="body" required rows="4" placeholder="Announcement content..."></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Post announcement</button>
  </form>
  <div id="ann-msg" class="success-box" style="display:none; margin-top:0.5rem;"></div>
  <div id="ann-err" class="error-box" style="display:none; margin-top:0.5rem;"></div>
</section>

<section>
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">All announcements</h2>
  <?php if (empty($allAnnouncements)): ?>
    <p class="role">No announcements yet.</p>
  <?php else: ?>
    <div class="announcements-datatable-panel">
      <div class="announcements-table-wrap">
        <table id="announcements-table" class="display" style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th>#</th>
              <th>Title</th>
              <th>Message</th>
              <th>Date Posted</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allAnnouncements as $a): ?>
              <tr data-ann-id="<?= (int)$a['id'] ?>"
                  data-ann-title="<?= htmlspecialchars($a['title'], ENT_QUOTES) ?>"
                  data-ann-body="<?= htmlspecialchars($a['body'], ENT_QUOTES) ?>"
                  data-ann-created="<?= htmlspecialchars($a['created_at'], ENT_QUOTES) ?>">
                <td><?= (int)$a['id'] ?></td>
                <td><?= htmlspecialchars($a['title']) ?></td>
                <td class="ann-body-cell" title="<?= htmlspecialchars($a['body'], ENT_QUOTES) ?>"><?= htmlspecialchars(mb_strlen($a['body']) > 80 ? mb_substr($a['body'], 0, 77) . '...' : $a['body']) ?></td>
                <td><?= htmlspecialchars($a['created_at']) ?></td>
                <td>
                  <button type="button" class="btn btn-outline btn-view-ann" style="font-size:0.8rem; padding:0.25rem 0.5rem;">View</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</section>

<div id="ann-detail-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:10px; max-width:600px; width:90%; max-height:80vh; overflow-y:auto; padding:1.5rem; position:relative;">
    <button type="button" id="ann-detail-close" style="position:absolute; top:0.75rem; right:0.75rem; background:none; border:none; font-size:1.5rem; cursor:pointer; color:#6b7280;">&times;</button>
    <h3 id="ann-detail-title" style="margin:0 0 0.5rem; font-size:1.1rem;"></h3>
    <p id="ann-detail-date" style="margin:0 0 1rem; font-size:0.85rem; color:#6b7280;"></p>
    <div id="ann-detail-body" style="white-space:pre-wrap; font-size:0.95rem; line-height:1.6;"></div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.min.js"></script>
<script>
(function() {
  var base = '<?= htmlspecialchars($webBase ?? '') ?>';
  var form = document.getElementById('form-announcement');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      document.getElementById('ann-msg').style.display = 'none';
      document.getElementById('ann-err').style.display = 'none';
      var payload = {
        title: document.getElementById('ann-title').value.trim(),
        body: document.getElementById('ann-body').value.trim()
      };
      fetch(base + '/api/admin/announcements', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
          document.getElementById('ann-msg').textContent = 'Announcement posted.';
          document.getElementById('ann-msg').style.display = 'block';
          form.reset();
          setTimeout(function() { window.location.reload(); }, 600);
        } else {
          document.getElementById('ann-err').textContent = d.error || 'Failed.';
          document.getElementById('ann-err').style.display = 'block';
        }
      }).catch(function() {
        document.getElementById('ann-err').textContent = 'Network error.';
        document.getElementById('ann-err').style.display = 'block';
      });
    });
  }

  // DataTable init
  var annDataTable = null;
  function initAnnDataTable() {
    var tableEl = document.getElementById('announcements-table');
    if (!tableEl) return;
    var DT = window.DataTable || window.dataTable;
    if (typeof DT === 'undefined') {
      setTimeout(initAnnDataTable, 100);
      return;
    }
    try {
      annDataTable = new DT(tableEl, {
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        order: [[3, 'desc']],
        columnDefs: [
          { orderable: false, targets: 4 },
          { width: '40px', targets: 0 },
          { width: '80px', targets: 4 }
        ],
        responsive: true,
        language: {
          lengthMenu: 'Show _MENU_ entries',
          search: 'Search:',
          info: 'Showing _START_ to _END_ of _TOTAL_ announcements',
          infoEmpty: 'No announcements found',
          infoFiltered: '(filtered from _MAX_ total)',
          zeroRecords: 'No matching announcements found',
          paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        }
      });
    } catch (err) {
      console.error('Announcements DataTable init error:', err);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAnnDataTable);
  } else {
    initAnnDataTable();
  }

  // View modal
  var modal = document.getElementById('ann-detail-modal');
  var closeBtn = document.getElementById('ann-detail-close');
  function openModal(title, body, date) {
    document.getElementById('ann-detail-title').textContent = title;
    document.getElementById('ann-detail-body').textContent = body;
    document.getElementById('ann-detail-date').textContent = 'Posted: ' + date;
    modal.style.display = 'flex';
  }
  function closeModal() {
    modal.style.display = 'none';
  }
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

  // View button click (delegated for DataTable pagination)
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-view-ann');
    if (!btn) return;
    var row = btn.closest('tr');
    if (!row) return;
    var title = row.getAttribute('data-ann-title') || '';
    var body = row.getAttribute('data-ann-body') || '';
    var created = row.getAttribute('data-ann-created') || '';
    openModal(title, body, created);
  });
})();
</script>
<?php
$dashboardContent = ob_get_clean();
require $baseDir . '/templates/dashboard_layout.php';
