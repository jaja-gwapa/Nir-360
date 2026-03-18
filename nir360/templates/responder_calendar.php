<?php
$pageTitle = 'Calendar';
$dashboardExtraHead = '<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">';
ob_start();
?>
<p class="role" style="margin-bottom:1rem;">Your assigned incidents. When you mark a report as Resolved, it appears here as a completed event. Click an event to view report details.</p>

<div id="responder-calendar" style="max-width:100%; min-height:500px;"></div>

<section id="responder-calendar-report-detail" style="margin-top:2rem; display:none; border:1px solid #e2e8f0; border-radius:8px; padding:1rem; background:#fff;">
  <!-- Filled via JS when responder clicks an event -->
</section>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
(function() {
  var base = '<?= htmlspecialchars($webBase ?? '') ?>';
  var detailEl = document.getElementById('responder-calendar-report-detail');
  var calendarEl = document.getElementById('responder-calendar');
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
        html += '<a href="' + fullUrl + '" target="_blank" rel="noopener"><img src="' + fullUrl + '" alt="Photo" style="width:110px; height:80px; object-fit:cover; border-radius:6px;"></a>';
      });
      html += '</div></div>';
    }
    if (vids.length) {
      var v = vids[0];
      var vidUrl = (v.url.indexOf('/') === 0 && base) ? (base + v.url) : (base ? base + '/' + v.url.replace(/^\//, '') : v.url);
      html += '<div style="margin-top:0.75rem;"><strong>Video</strong><div style="margin-top:0.35rem;"><video src="' + vidUrl + '" controls style="max-width:100%; max-height:260px; border-radius:8px;"></video></div></div>';
    }
    return html;
  }
  function openReportDetail(reportId) {
    if (!detailEl || !reportId) return;
    detailEl.style.display = 'block';
    detailEl.innerHTML = '<p style="font-size:0.9rem; color:var(--muted);">Loading…</p>';
    detailEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    var apiUrl = base ? (base + '/api/reports/' + reportId) : ('/api/reports/' + reportId);
    fetch(apiUrl).then(function(r) { return r.json(); }).then(function(d) {
      if (!d.success || !d.report) {
        detailEl.innerHTML = '<p style="color:#b91c1c; font-size:0.9rem;">Unable to load report.</p>';
        return;
      }
      var r = d.report;
      var status = (r.status || '').replace(/_/g, ' ');
      status = status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
      var html = '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">' +
        '<h3 style="margin:0; font-size:1rem;">Incident #' + reportId + ' – ' + escapeHtml(r.title || '') + '</h3>' +
        '<button type="button" class="btn btn-outline" id="responder-calendar-detail-close" style="font-size:0.8rem;">Close</button></div>';
      html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Status:</strong> ' + escapeHtml(status) + (r.severity ? ' · <strong>Severity:</strong> ' + escapeHtml(r.severity) : '') + '</p>';
      if (r.address) html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Location:</strong> ' + escapeHtml(r.address) + '</p>';
      html += '<p style="margin:0.5rem 0; font-size:0.9rem; white-space:pre-wrap;">' + escapeHtml(r.description || '') + '</p>';
      html += buildMediaGallery(r.media || []);
      detailEl.innerHTML = html;
      var btn = document.getElementById('responder-calendar-detail-close');
      if (btn) btn.addEventListener('click', function() { detailEl.style.display = 'none'; });
    }).catch(function() { detailEl.innerHTML = '<p style="color:#b91c1c;">Network error.</p>'; });
  }

  function buildEventSource(arg, successCallback, failureCallback) {
    var start = arg.start;
    var end = arg.end;
    var from = start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0');
    var to = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0');
    var url = (base ? (base + '/api/calendar/events') : '/api/calendar/events') + '?from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
    fetch(url).then(function(r) { return r.json(); }).then(function(d) {
      if (d.success && Array.isArray(d.events)) successCallback(d.events);
      else failureCallback();
    }).catch(function() { failureCallback(); });
  }

  if (typeof FullCalendar !== 'undefined' && calendarEl) {
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
      events: buildEventSource,
      eventClick: function(info) {
        info.jsEvent.preventDefault();
        var reportId = info.event.extendedProps && info.event.extendedProps.reportId;
        if (reportId) openReportDetail(reportId);
      },
      eventDidMount: function(info) {
        var p = info.event.extendedProps || {};
        if (p.status === 'resolved') {
          info.el.style.backgroundColor = '#d1fae5';
          info.el.style.borderColor = '#10b981';
        }
      },
    });
    calendar.render();
  }
})();
</script>

<?php
$dashboardContent = ob_get_clean();
require $baseDir . '/templates/dashboard_layout.php';
