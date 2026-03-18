<?php
$pageTitle = 'History / Records';
$records = $reportService->listHistoryByReporter($userId);
$dashboardExtraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/2.3.7/css/dataTables.dataTables.min.css">
<style>
  .user-history-panel { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1rem; margin-top:0.5rem; }
  .user-history-table-wrap { width:100%; overflow-x:auto; }
  #user-history-table thead th { white-space:nowrap; text-align:left; vertical-align:middle; padding:0.75rem 0.5rem; border-bottom:1px solid #e2e8f0; }
  #user-history-table tbody td { padding:0.5rem; vertical-align:top; }
  .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { margin-bottom:0.75rem; }
  .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { margin-top:0.75rem; padding-top:0.5rem; }
  #user-history-detail-map { width:100%; height:260px; border:1px solid #e5e7eb; border-radius:8px; background:#f3f4f6; }
</style>';
ob_start();
?>

<p class="role" style="margin-bottom:1rem;">All of your past reports (submitted). Use search/sort to find a report. Click a row or <strong>View</strong> to open full details.</p>

<section style="margin-bottom:1.5rem;">
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">History / Records</h2>
  <?php if (empty($records)): ?>
    <p class="role">No submitted reports yet.</p>
  <?php else: ?>
    <div class="user-history-panel">
      <div class="user-history-table-wrap">
        <table id="user-history-table" class="display" style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="background:#f9fafb;">
              <th>Report ID</th>
              <th>Incident type</th>
              <th>Location</th>
              <th>Date submitted</th>
              <th>Date resolved</th>
              <th>Assigned responder</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r): ?>
              <?php
                $rid = (int)$r['id'];
                $incidentType = (string)($r['incident_type'] ?? '');
                $incidentTypeLabel = $incidentType !== '' ? ucwords(str_replace('_', ' ', $incidentType)) : '—';
                $status = (string)($r['status'] ?? '');
                $statusLabel = $status ? ucwords(str_replace('_', ' ', $status)) : '—';
                $createdAt = (string)($r['created_at'] ?? '');
                $updatedAt = (string)($r['updated_at'] ?? '');
                $resolvedAt = ($status === 'resolved') ? $updatedAt : '';
                $assigned = (string)($r['assigned_email'] ?? '');
                $addr = trim((string)($r['address'] ?? ''));
                $lat = isset($r['latitude']) && $r['latitude'] !== '' && $r['latitude'] !== null ? (float)$r['latitude'] : null;
                $lng = isset($r['longitude']) && $r['longitude'] !== '' && $r['longitude'] !== null ? (float)$r['longitude'] : null;
              ?>
              <tr class="user-history-row"
                  data-report-id="<?= $rid ?>"
                  data-lat="<?= $lat !== null ? htmlspecialchars((string)$lat, ENT_QUOTES) : '' ?>"
                  data-lng="<?= $lng !== null ? htmlspecialchars((string)$lng, ENT_QUOTES) : '' ?>"
                  data-address="<?= htmlspecialchars($addr, ENT_QUOTES) ?>">
                <td>#<?= $rid ?></td>
                <td><?= htmlspecialchars($incidentTypeLabel) ?></td>
                <td><?= $addr !== '' ? htmlspecialchars($addr) : '—' ?></td>
                <td><?= htmlspecialchars($createdAt) ?></td>
                <td><?= $resolvedAt !== '' ? htmlspecialchars($resolvedAt) : '—' ?></td>
                <td><?= $assigned !== '' ? htmlspecialchars($assigned) : '—' ?></td>
                <td><?= htmlspecialchars($statusLabel) ?></td>
                <td style="white-space:nowrap;">
                  <button type="button" class="btn btn-outline btn-user-history-view" style="font-size:0.85rem;">View</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</section>

<section id="user-history-detail" style="display:none; border:1px solid #e5e7eb; border-radius:8px; padding:1rem; background:#fff; margin-bottom:2rem;">
  <!-- Filled via JS -->
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.min.js"></script>
<script>
(function() {
  var base = '<?= htmlspecialchars($webBase ?? '') ?>';
  var detailEl = document.getElementById('user-history-detail');
  var map = null;
  var mapMarker = null;

  function escapeHtml(str) {
    if (str == null) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function buildMediaGallery(media) {
    if (!media || !media.length) return '<p style="margin-top:0.5rem; font-size:0.9rem; color:var(--muted);">No media attached.</p>';
    var imgs = media.filter(function(m) { return m.media_type === 'image'; });
    var vids = media.filter(function(m) { return m.media_type === 'video'; });
    var html = '';
    if (imgs.length) {
      html += '<div style="margin-top:0.75rem;"><strong>Photos</strong><div style="margin-top:0.35rem; display:flex; flex-wrap:wrap; gap:0.5rem;">';
      imgs.forEach(function(m) {
        var fullUrl = (m.url.indexOf('/') === 0 && base) ? (base + m.url) : (base ? base + '/' + m.url.replace(/^\//, '') : m.url);
        html += '<a href="' + fullUrl + '" target="_blank" rel="noopener" style="display:inline-block; border-radius:6px; overflow:hidden; border:1px solid #e5e7eb;">' +
          '<img src="' + fullUrl + '" alt="Photo" style="width:110px; height:80px; object-fit:cover; display:block;"></a>';
      });
      html += '</div></div>';
    }
    if (vids.length) {
      var v = vids[0];
      var vidUrl = (v.url.indexOf('/') === 0 && base) ? (base + v.url) : (base ? base + '/' + v.url.replace(/^\//, '') : v.url);
      html += '<div style="margin-top:0.75rem;"><strong>Video</strong><div style="margin-top:0.35rem;">' +
        '<video src="' + vidUrl + '" controls style="max-width:100%; max-height:260px; border-radius:8px; border:1px solid #e5e7eb; background:#000;"></video>' +
        '</div></div>';
    }
    return html;
  }

  function initDataTable() {
    var tableEl = document.getElementById('user-history-table');
    if (!tableEl) return;
    var DT = window.DataTable || window.dataTable;
    if (typeof DT === 'undefined') {
      setTimeout(initDataTable, 100);
      return;
    }
    try {
      new DT(tableEl, {
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        order: [[3, 'desc']],
        columnDefs: [{ orderable: false, targets: 7 }],
        language: {
          lengthMenu: 'Show _MENU_ entries',
          search: 'Search:',
          info: 'Showing _START_ to _END_ of _TOTAL_ entries',
          infoEmpty: 'Showing 0 to 0 of 0 entries',
          infoFiltered: '(filtered from _MAX_ total entries)',
          zeroRecords: 'No matching records found',
          paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        }
      });
    } catch (err) {
      console.error('User history DataTable init error:', err);
    }
  }

  function renderMiniMap(lat, lng) {
    var el = document.getElementById('user-history-detail-map');
    if (!el || typeof L === 'undefined') return;
    if (!map) {
      map = L.map('user-history-detail-map', { preferCanvas: true }).setView([lat, lng], 14);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors' }).addTo(map);
    }
    if (mapMarker) { try { map.removeLayer(mapMarker); } catch (e) {} mapMarker = null; }
    mapMarker = L.marker([lat, lng]).addTo(map).bindPopup('Incident location');
    map.setView([lat, lng], 15);
    setTimeout(function() { if (map && map.invalidateSize) map.invalidateSize(); }, 150);
  }

  function openDetail(reportId, rowEl) {
    if (!detailEl || !reportId) return;
    detailEl.style.display = 'block';
    detailEl.innerHTML = '<p style="font-size:0.9rem; color:var(--muted);">Loading…</p>';
    detailEl.scrollIntoView({ behavior: 'smooth', block: 'start' });

    var apiUrl = base ? (base + '/api/reports/' + reportId) : ('/api/reports/' + reportId);
    fetch(apiUrl).then(function(r) { return r.json(); }).then(function(d) {
      if (!d.success || !d.report) {
        detailEl.innerHTML = '<p style="color:#b91c1c; font-size:0.9rem;">Unable to load report details.</p>';
        return;
      }
      var r = d.report;
      var status = (r.status || '').replace(/_/g, ' ');
      status = status ? status.charAt(0).toUpperCase() + status.slice(1) : '—';
      var incType = (r.incident_type || '').replace(/_/g, ' ');
      incType = incType ? incType.charAt(0).toUpperCase() + incType.slice(1) : '—';
      var sev = r.severity ? (r.severity.charAt(0).toUpperCase() + r.severity.slice(1)) : '';

      var html = '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">' +
        '<h3 style="margin:0; font-size:1rem;">Report #' + reportId + ' – ' + escapeHtml(r.title || '') + '</h3>' +
        '<button type="button" class="btn btn-outline" id="user-history-detail-close" style="font-size:0.8rem;">Close</button></div>';
      html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Status:</strong> ' + escapeHtml(status) +
        (sev ? ' · <strong>Severity:</strong> ' + escapeHtml(sev) : '') +
        ' · <strong>Incident type:</strong> ' + escapeHtml(incType) + '</p>';
      if (r.address) html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Location:</strong> ' + escapeHtml(r.address) + '</p>';
      html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Submitted:</strong> ' + escapeHtml(r.created_at || '') + '</p>';
      html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Last updated:</strong> ' + escapeHtml(r.updated_at || '') + '</p>';
      html += '<div style="margin-top:0.5rem; font-size:0.9rem; white-space:pre-wrap;">' + escapeHtml(r.description || '') + '</div>';

      var lat = rowEl ? parseFloat(rowEl.getAttribute('data-lat') || '') : NaN;
      var lng = rowEl ? parseFloat(rowEl.getAttribute('data-lng') || '') : NaN;
      if (!isNaN(lat) && !isNaN(lng)) {
        html += '<div style="margin-top:0.9rem;"><strong>Map summary</strong><div id="user-history-detail-map" style="margin-top:0.35rem;"></div></div>';
      } else {
        html += '<p style="margin-top:0.9rem; font-size:0.9rem; color:var(--muted);">No map location was saved for this report.</p>';
      }

      html += '<div style="margin-top:0.9rem;"><strong>Media</strong>' + buildMediaGallery(r.media || []) + '</div>';
      detailEl.innerHTML = html;

      var btnClose = document.getElementById('user-history-detail-close');
      if (btnClose) btnClose.addEventListener('click', function() { detailEl.style.display = 'none'; });

      if (!isNaN(lat) && !isNaN(lng)) {
        renderMiniMap(lat, lng);
      }
    }).catch(function(err) {
      detailEl.innerHTML = '<p style="color:#b91c1c; font-size:0.9rem;">Network error while loading report.</p>';
      console.error('User history detail fetch failed:', err);
    });
  }

  initDataTable();

  var tableWrap = document.getElementById('user-history-table');
  if (tableWrap) {
    tableWrap.addEventListener('click', function(e) {
      var btn = e.target.closest('.btn-user-history-view');
      var tr = e.target.closest('tr.user-history-row');
      if (!tr) return;
      if (btn) {
        e.preventDefault();
        e.stopPropagation();
        openDetail(parseInt(tr.getAttribute('data-report-id'), 10), tr);
        return;
      }
      if (!e.target.closest('td')) return;
      openDetail(parseInt(tr.getAttribute('data-report-id'), 10), tr);
    });
  }
})();
</script>

<?php
$dashboardContent = ob_get_clean();
require $baseDir . '/templates/dashboard_layout.php';

