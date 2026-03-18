<?php
$pageTitle = 'History / Records';
$historyRecords = $reportService->listResolvedByResponder($userId);
$incidentTypes = [];
foreach ($historyRecords as $r) {
    $t = trim((string)($r['incident_type'] ?? ''));
    if ($t !== '' && !in_array($t, $incidentTypes, true)) {
        $incidentTypes[] = $t;
    }
}
sort($incidentTypes);
ob_start();
?>
<style>.responder-history-record:hover { background:#f8fafc !important; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }</style>
<p class="role" style="margin-bottom:1rem;">Incidents you have handled and marked as Resolved. Tap a record to view full details, notes, and attached media.</p>

<div class="responder-history-filters" style="margin-bottom:1.25rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
  <div>
    <label for="filter-date-from" style="display:block; font-size:0.85rem; color:#6b7280; margin-bottom:0.25rem;">From date</label>
    <input type="date" id="filter-date-from" style="padding:0.4rem 0.6rem; border:1px solid #e5e7eb; border-radius:6px; font-size:0.9rem;">
  </div>
  <div>
    <label for="filter-date-to" style="display:block; font-size:0.85rem; color:#6b7280; margin-bottom:0.25rem;">To date</label>
    <input type="date" id="filter-date-to" style="padding:0.4rem 0.6rem; border:1px solid #e5e7eb; border-radius:6px; font-size:0.9rem;">
  </div>
  <div>
    <label for="filter-incident-type" style="display:block; font-size:0.85rem; color:#6b7280; margin-bottom:0.25rem;">Incident type</label>
    <select id="filter-incident-type" style="padding:0.4rem 0.6rem; border:1px solid #e5e7eb; border-radius:6px; font-size:0.9rem; min-width:140px;">
      <option value="">All types</option>
      <?php foreach ($incidentTypes as $t): ?>
        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $t))) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="button" id="responder-history-clear-filters" class="btn btn-outline" style="font-size:0.9rem;">Clear filters</button>
</div>

<section class="responder-history-list" style="margin-bottom:2rem;">
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">Resolved incidents</h2>
  <?php if (empty($historyRecords)): ?>
    <p class="role">No resolved incidents yet. Reports you mark as Resolved will appear here.</p>
  <?php else: ?>
    <ul id="responder-history-ul" style="list-style:none; padding:0;">
      <?php foreach ($historyRecords as $r): ?>
        <?php
        $resolvedAt = $r['updated_at'] ?? '';
        $reportedAt = $r['created_at'] ?? '';
        $incidentTypeLabel = isset($r['incident_type']) && $r['incident_type'] !== '' ? ucwords(str_replace('_', ' ', (string)$r['incident_type'])) : '—';
        ?>
        <li class="responder-history-record"
            style="border:1px solid #e2e8f0; border-radius:8px; padding:1rem; margin-bottom:0.75rem; background:#fff; cursor:pointer; transition:background 0.15s, box-shadow 0.15s;"
            data-report-id="<?= (int)$r['id'] ?>"
            data-incident-type="<?= htmlspecialchars($r['incident_type'] ?? '') ?>"
            data-resolved-at="<?= htmlspecialchars($resolvedAt) ?>"
            data-created-at="<?= htmlspecialchars($reportedAt) ?>">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.5rem;">
            <strong>#<?= (int)$r['id'] ?> <?= htmlspecialchars($r['title']) ?></strong>
            <span style="font-size:0.85rem; color:#16a34a; font-weight:600;">Resolved</span>
          </div>
          <p style="margin:0.35rem 0 0; font-size:0.9rem; color:#374151;"><strong>Incident type:</strong> <?= htmlspecialchars($incidentTypeLabel) ?></p>
          <?php if (!empty($r['address'])): ?>
            <p style="margin:0.25rem 0 0; font-size:0.9rem; color:#6b7280;"><strong>Location:</strong> <?= htmlspecialchars($r['address']) ?></p>
          <?php endif; ?>
          <p style="margin:0.5rem 0 0; font-size:0.85rem; color:#6b7280;">
            <strong>Reported:</strong> <?= htmlspecialchars($reportedAt) ?> · <strong>Resolved:</strong> <?= htmlspecialchars($resolvedAt) ?>
          </p>
          <p style="margin:0.5rem 0 0; font-size:0.85rem; color:var(--muted);">Tap to view full details, notes &amp; media</p>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section id="responder-history-detail" style="margin-bottom:2rem; display:none; border:1px solid #e2e8f0; border-radius:8px; padding:1rem; background:#fff;">
  <!-- Filled via JS when responder taps a record -->
</section>

<script>
(function() {
  var base = '<?= htmlspecialchars($webBase ?? '') ?>';
  var detailEl = document.getElementById('responder-history-detail');
  var listUl = document.getElementById('responder-history-ul');
  var filterDateFrom = document.getElementById('filter-date-from');
  var filterDateTo = document.getElementById('filter-date-to');
  var filterIncidentType = document.getElementById('filter-incident-type');
  var btnClearFilters = document.getElementById('responder-history-clear-filters');

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
      html += '<div style="margin-top:0.75rem;"><strong>Photos</strong> <span style="font-size:0.85rem; color:var(--muted);">(click to enlarge)</span><div style="margin-top:0.35rem; display:flex; flex-wrap:wrap; gap:0.5rem;">';
      imgs.forEach(function(m) {
        var fullUrl = (m.url.indexOf('/') === 0 && base) ? (base + m.url) : (base ? base + '/' + m.url.replace(/^\//, '') : m.url);
        html += '<a href="' + fullUrl + '" target="_blank" rel="noopener" style="display:inline-block; border-radius:6px; overflow:hidden; border:1px solid #e5e7eb;">' +
          '<img src="' + fullUrl + '" alt="Photo" style="width:110px; height:80px; object-fit:cover; display:block; cursor:pointer;"></a>';
      });
      html += '</div></div>';
    }
    if (vids.length) {
      html += '<div style="margin-top:0.75rem;"><strong>Video</strong><div style="margin-top:0.35rem;">';
      var v = vids[0];
      var vidUrl = (v.url.indexOf('/') === 0 && base) ? (base + v.url) : (base ? base + '/' + v.url.replace(/^\//, '') : v.url);
      html += '<video src="' + vidUrl + '" controls style="max-width:100%; max-height:260px; border-radius:8px; border:1px solid #e5e7eb; background:#000;"></video>';
      html += '</div></div>';
    }
    return html;
  }

  function parseDateOnly(str) {
    if (!str) return null;
    var m = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
    var d = new Date(str);
    return isNaN(d.getTime()) ? null : d;
  }

  function applyFilters() {
    if (!listUl) return;
    var fromVal = filterDateFrom ? filterDateFrom.value : '';
    var toVal = filterDateTo ? filterDateTo.value : '';
    var typeVal = filterIncidentType ? (filterIncidentType.value || '').trim() : '';
    var fromDate = fromVal ? parseDateOnly(fromVal) : null;
    var toDate = toVal ? parseDateOnly(toVal) : null;

    var items = listUl.querySelectorAll('.responder-history-record');
    items.forEach(function(li) {
      var resolvedAt = li.getAttribute('data-resolved-at') || '';
      var createdAt = li.getAttribute('data-created-at') || '';
      var incidentType = (li.getAttribute('data-incident-type') || '').trim();
      var show = true;

      if (typeVal !== '' && incidentType !== typeVal) show = false;
      if (fromDate) {
        var d = parseDateOnly(resolvedAt) || parseDateOnly(createdAt);
        if (!d || d < fromDate) show = false;
      }
      if (toDate) {
        var d = parseDateOnly(resolvedAt) || parseDateOnly(createdAt);
        if (!d || d > toDate) show = false;
      }

      li.style.display = show ? '' : 'none';
    });
  }

  function openDetail(reportId) {
    if (!detailEl || !reportId) return;
    detailEl.style.display = 'block';
    detailEl.innerHTML = '<p style="font-size:0.9rem; color:var(--muted);">Loading…</p>';
    detailEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    var apiUrl = base ? (base + '/api/reports/' + reportId) : ('/api/reports/' + reportId);
    fetch(apiUrl).then(function(r) { return r.json(); }).then(function(d) {
      if (!d.success || !d.report) {
        detailEl.innerHTML = '<p style="color:#b91c1c; font-size:0.9rem;">Unable to load details.' + (d.error ? ' ' + escapeHtml(d.error) : '') + '</p>';
        return;
      }
      var r = d.report;
      var html = '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">' +
        '<h3 style="margin:0; font-size:1rem;">Incident #' + reportId + ' – ' + escapeHtml(r.title || '') + '</h3>' +
        '<button type="button" class="btn btn-outline" id="responder-history-detail-close" style="font-size:0.8rem;">Close</button></div>';
      html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Status:</strong> Resolved' +
        (r.severity ? ' · <strong>Severity:</strong> ' + escapeHtml(r.severity.charAt(0).toUpperCase() + r.severity.slice(1)) : '') + '</p>';
      if (r.address) {
        html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Location:</strong> ' + escapeHtml(r.address) + '</p>';
        var destParam = r.address.length < 200 ? encodeURIComponent(r.address) : (r.latitude && r.longitude ? r.latitude + ',' + r.longitude : '');
        if (destParam) {
          html += '<p style="margin:0.25rem 0;"><a href="https://www.google.com/maps/dir/?api=1&destination=' + destParam + '&travelmode=driving" target="_blank" rel="noopener">Open location in Google Maps</a></p>';
        }
      }
      html += '<div style="margin-top:0.75rem;"><strong>Notes / description</strong><p style="margin:0.35rem 0 0; font-size:0.9rem; white-space:pre-wrap;">' + escapeHtml(r.description || '—') + '</p></div>';
      html += '<div style="margin-top:0.75rem;"><strong>Route summary</strong><p style="margin:0.35rem 0 0; font-size:0.9rem; color:var(--muted);">Incident was at the location above. You were assigned, responded, and marked this report as Resolved.</p></div>';
      html += '<div style="margin-top:0.75rem;"><strong>Attached media</strong>' + buildMediaGallery(r.media || []) + '</div>';
      detailEl.innerHTML = html;
      var btnClose = document.getElementById('responder-history-detail-close');
      if (btnClose) btnClose.addEventListener('click', function() { detailEl.style.display = 'none'; });
    }).catch(function(err) {
      detailEl.innerHTML = '<p style="color:#b91c1c; font-size:0.9rem;">Network error. Please try again.</p>';
      console.error('Responder history detail fetch failed:', err);
    });
  }

  if (listUl) {
    listUl.addEventListener('click', function(e) {
      var li = e.target.closest('.responder-history-record');
      if (!li) return;
      var reportId = parseInt(li.getAttribute('data-report-id'), 10);
      if (reportId) openDetail(reportId);
    });
  }

  if (filterDateFrom) filterDateFrom.addEventListener('change', applyFilters);
  if (filterDateTo) filterDateTo.addEventListener('change', applyFilters);
  if (filterIncidentType) filterIncidentType.addEventListener('change', applyFilters);
  if (btnClearFilters) {
    btnClearFilters.addEventListener('click', function() {
      if (filterDateFrom) filterDateFrom.value = '';
      if (filterDateTo) filterDateTo.value = '';
      if (filterIncidentType) filterIncidentType.value = '';
      applyFilters();
    });
  }
})();
</script>

<?php
$dashboardContent = ob_get_clean();
require $baseDir . '/templates/dashboard_layout.php';
