<?php
$pageTitle = 'History';
$historyReports = $reportService->listHistory();
ob_start();
?>
<p class="role" style="margin-bottom:1rem;">Resolved and completed reports.</p>

<section style="margin-bottom:2rem;">
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">History</h2>
  <?php if (empty($historyReports)): ?>
    <p class="role">No resolved reports yet.</p>
  <?php else: ?>
    <ul style="list-style:none; padding:0;">
      <?php foreach ($historyReports as $r): ?>
        <li style="border:1px solid #e2e8f0; border-radius:8px; padding:0.75rem; margin-bottom:0.5rem; background:#f8fafc;" data-report-id="<?= (int)$r['id'] ?>">
          <strong>#<?= (int)$r['id'] ?> <?= htmlspecialchars($r['title']) ?></strong>
          <span style="color:var(--muted); font-size:0.85rem;"> — Resolved<?= !empty($r['severity']) ? ' · ' . htmlspecialchars(ucfirst((string)$r['severity'])) : '' ?></span>
          <br><small>Reporter: <?= htmlspecialchars($r['reporter_email'] ?? '') ?> · Assigned: <?= htmlspecialchars($r['assigned_email'] ?? '—') ?> · <?= htmlspecialchars($r['updated_at']) ?></small>
          <?php if (!empty($r['address'])): ?>
            <br><small style="color:var(--muted);">Location: <?= htmlspecialchars($r['address']) ?></small>
          <?php endif; ?>
          <button type="button"
                  class="btn btn-outline btn-view-report-media"
                  data-report-id="<?= (int)$r['id'] ?>"
                  style="margin-top:0.5rem; font-size:0.85rem;">
            View details &amp; media
          </button>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section id="admin-report-detail" style="margin-bottom:2rem; display:none; border:1px solid #e2e8f0; border-radius:8px; padding:1rem; background:#fff;">
  <!-- Filled via JS when admin clicks "View details & media" -->
</section>

<script>
(function() {
  var base = '<?= htmlspecialchars($webBase ?? '') ?>';
  var detailEl = document.getElementById('admin-report-detail');
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
  function openReportDetail(reportId) {
    if (!detailEl || !reportId) return;
    detailEl.style.display = 'block';
    detailEl.innerHTML = '<p style="font-size:0.9rem; color:var(--muted);">Loading report details…</p>';
    var apiUrl = base ? (base + '/api/reports/' + reportId) : ('/api/reports/' + reportId);
    fetch(apiUrl).then(function(r) { return r.json(); }).then(function(d) {
      if (!d.success || !d.report) {
        detailEl.innerHTML = '<p style="color:#b91c1c; font-size:0.9rem;">Unable to load report details.' + (d.error ? ' ' + escapeHtml(d.error) : '') + '</p>';
        return;
      }
      var r = d.report;
      var status = (r.status || '').replace(/_/g, ' ');
      status = status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
      var sev = r.severity ? r.severity.charAt(0).toUpperCase() + r.severity.slice(1) : '';
      var html = '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">' +
        '<h3 style="margin:0; font-size:1rem;">Incident #' + reportId + ' – ' + escapeHtml(r.title || '') + '</h3>' +
        '<button type="button" class="btn btn-outline" id="admin-detail-close" style="font-size:0.8rem;">Close</button></div>';
      html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Status:</strong> ' + escapeHtml(status || '—') +
        (sev ? ' · <strong>Severity:</strong> ' + escapeHtml(sev) : '') + '</p>';
      if (r.address) {
        html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Location:</strong> ' + escapeHtml(r.address) + '</p>';
      }
      html += '<p style="margin:0.5rem 0; font-size:0.9rem; white-space:pre-wrap;">' + escapeHtml(r.description || '') + '</p>';
      html += buildMediaGallery(r.media || []);
      detailEl.innerHTML = html;
      detailEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
      var btnClose = document.getElementById('admin-detail-close');
      if (btnClose) {
        btnClose.addEventListener('click', function() {
          detailEl.style.display = 'none';
        });
      }
    }).catch(function(err) {
      detailEl.innerHTML = '<p style="color:#b91c1c; font-size:0.9rem;">Network error while loading report.</p>';
      console.error('History report detail fetch failed:', err);
    });
  }

  document.querySelectorAll('.btn-view-report-media').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = parseInt(this.getAttribute('data-report-id'), 10);
      openReportDetail(id);
    });
  });

})();
</script>
<?php
$dashboardContent = ob_get_clean();
require $baseDir . '/templates/dashboard_layout.php';
