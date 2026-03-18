<?php
$pageTitle = 'Admin Dashboard';
$activeReports = $reportService->listActive();
$historyReports = $reportService->listHistory();
$recentAccidents = $reportService->listRecentAccidents(7);
$responders = $adminService->getResponders();
$mapReports = array_values(array_filter($activeReports, static function ($r) {
    return isset($r['latitude'], $r['longitude'])
        && $r['latitude'] !== null
        && $r['longitude'] !== null
        && $r['latitude'] !== ''
        && $r['longitude'] !== '';
}));
$resolvedMapReports = array_values(array_filter($historyReports, static function ($r) {
    return isset($r['latitude'], $r['longitude'])
        && $r['latitude'] !== null
        && $r['longitude'] !== null
        && $r['latitude'] !== ''
        && $r['longitude'] !== '';
}));
$dashboardExtraHead = '<style>
  .dash-main .dash { max-width: none; }
  #admin-reports-map { width: 100%; min-height: 360px; border: 1px solid #e2e8f0; border-radius: 8px; }
</style>';
ob_start();
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<p class="role" style="margin-bottom:1rem;">View all reports and assign responders. Use `Manage Users` for account management.</p>

<section style="margin-bottom:2rem;">
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">Incident locations map</h2>
  <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.5rem; flex-wrap:wrap;">
    <label style="display:inline-flex; align-items:center; gap:0.35rem; font-size:0.9rem; cursor:pointer;">
      <input type="checkbox" id="admin-map-show-resolved" style="width:1rem; height:1rem;">
      <span>Show Resolved</span>
    </label>
    <span style="font-size:0.85rem; color:var(--muted);">Only active reports (Pending/Dispatched/Ongoing) appear by default. Enable to show resolved reports on the map.</span>
  </div>
  <div id="admin-reports-map" style="width:100%; min-height:360px; border:1px solid #e2e8f0; border-radius:8px;"></div>
  <p style="margin-top:0.5rem; color:var(--muted); font-size:0.9rem;"><?= empty($mapReports) && empty($resolvedMapReports) ? 'No report coordinates yet. Map shows default area.' : 'Active reports with location are shown as pins. Resolved reports stay in History; use "Show Resolved" to display them on the map. Click a pin to view full details.' ?></p>
</section>

<section style="margin-bottom:2rem;">
  <h2 style="font-size:1.05rem; margin-bottom:0.5rem;">Recent accident reports (last 7 days)</h2>
  <?php if (empty($recentAccidents)): ?>
    <p class="role">No recent accident reports.</p>
  <?php else: ?>
    <ul style="list-style:none; padding:0;">
      <?php foreach ($recentAccidents as $r): ?>
        <li style="border:1px solid #e2e8f0; border-radius:8px; padding:0.75rem; margin-bottom:0.5rem; background:#fff;" data-report-id="<?= (int)$r['id'] ?>">
          <strong>#<?= (int)$r['id'] ?> <?= htmlspecialchars($r['title']) ?></strong>
          <span style="color:var(--muted); font-size:0.85rem;"> — <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$r['status']))) ?><?= !empty($r['severity']) ? ' · ' . htmlspecialchars(ucfirst((string)$r['severity'])) : '' ?></span>
          <br><small>Reporter: <?= htmlspecialchars($r['reporter_email'] ?? '') ?> · Assigned: <?= htmlspecialchars($r['assigned_email'] ?? '—') ?> · <?= htmlspecialchars($r['created_at']) ?></small>
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

<section style="margin-bottom:2rem;">
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">Incident reports</h2>
  <p style="font-size:0.85rem; color:var(--muted); margin-bottom:0.5rem;">Pending, dispatched, and awaiting closure. Resolved reports are available in <a href="<?= htmlspecialchars($webBase ?? '') ?>/admin/history">History</a>.</p>
  <?php if (empty($activeReports)): ?>
    <p class="role">No active reports.</p>
  <?php else: ?>
    <ul id="admin-active-reports-list" style="list-style:none; padding:0;">
      <?php foreach ($activeReports as $r): ?>
        <li style="border:1px solid #e2e8f0; border-radius:8px; padding:0.75rem; margin-bottom:0.5rem;" data-report-id="<?= (int)$r['id'] ?>">
          <strong>#<?= (int)$r['id'] ?> <?= htmlspecialchars($r['title']) ?></strong>
          <span style="color:var(--muted); font-size:0.85rem;"> — <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$r['status']))) ?><?= !empty($r['severity']) ? ' · ' . htmlspecialchars(ucfirst((string)$r['severity'])) : '' ?></span>
          <br><small>Reporter: <?= htmlspecialchars($r['reporter_email'] ?? '') ?> · Assigned: <?= htmlspecialchars($r['assigned_email'] ?? '—') ?> · <?= htmlspecialchars($r['created_at']) ?></small>
          <?php if (!empty($r['address'])): ?>
            <br><small style="color:var(--muted);">Location: <?= htmlspecialchars($r['address']) ?></small>
          <?php endif; ?>
          <button type="button"
                  class="btn btn-outline btn-view-report-media"
                  data-report-id="<?= (int)$r['id'] ?>"
                  style="margin-top:0.5rem; font-size:0.85rem;">
            View details &amp; media
          </button>
          <?php if (($r['status'] ?? '') === 'pending' && !empty($responders)): ?>
            <form class="assign-report" data-report-id="<?= (int)$r['id'] ?>" style="margin-top:0.5rem; display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; position:relative; z-index:1;">
              <select name="responder_id" class="assign-responder-select">
                <option value="">Assign to...</option>
                <?php foreach ($responders as $resp): ?>
                  <option value="<?= (int)$resp['id'] ?>"><?= htmlspecialchars($resp['username']) ?> (<?= htmlspecialchars($resp['email']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn-confirm-dispatch btn btn-outline" style="cursor:pointer;">Confirm &amp; dispatch</button>
            </form>
          <?php elseif (($r['status'] ?? '') === 'awaiting_closure'): ?>
            <p style="margin-top:0.5rem; font-size:0.9rem; color:#b45309;">Responder returned to base. Confirm to close as Resolved.</p>
            <button type="button" class="btn-confirm-resolved btn btn-outline" data-report-id="<?= (int)$r['id'] ?>" style="margin-top:0.35rem; cursor:pointer;">Confirm Resolved</button>
          <?php endif; ?>
          <?php
          $status = $r['status'] ?? '';
          $showTracking = ($status === 'dispatched' || $status === 'awaiting_closure') && array_key_exists('tracking_enabled', $r);
          if ($showTracking): ?>
            <?php $trackOn = (int)($r['tracking_enabled'] ?? 1) === 1; ?>
            <p style="margin-top:0.5rem; font-size:0.9rem; color:var(--muted);">Reporter tracking: <?= $trackOn ? 'On' : 'Off' ?>
              <button type="button" class="btn-toggle-tracking btn btn-outline" data-report-id="<?= (int)$r['id'] ?>" data-enabled="<?= $trackOn ? '1' : '0' ?>" style="margin-left:0.35rem; font-size:0.8rem; cursor:pointer;"><?= $trackOn ? 'Turn off' : 'Turn on' ?></button>
            </p>
          <?php endif; ?>
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
  var mapReports = <?= json_encode(array_map(static function ($r) {
      return [
          'id' => (int)$r['id'],
          'title' => (string)($r['title'] ?? ''),
          'status' => (string)($r['status'] ?? ''),
          'severity' => (string)($r['severity'] ?? ''),
          'incident_type' => (string)($r['incident_type'] ?? ''),
          'reporter_email' => (string)($r['reporter_email'] ?? ''),
          'created_at' => (string)($r['created_at'] ?? ''),
          'address' => (string)($r['address'] ?? ''),
          'latitude' => isset($r['latitude']) ? (float)$r['latitude'] : null,
          'longitude' => isset($r['longitude']) ? (float)$r['longitude'] : null,
          'assigned_latitude' => isset($r['assigned_latitude']) && $r['assigned_latitude'] !== null && $r['assigned_latitude'] !== '' ? (float)$r['assigned_latitude'] : null,
          'assigned_longitude' => isset($r['assigned_longitude']) && $r['assigned_longitude'] !== null && $r['assigned_longitude'] !== '' ? (float)$r['assigned_longitude'] : null,
      ];
  }, $mapReports), JSON_UNESCAPED_SLASHES) ?>;
  var resolvedMapReports = <?= json_encode(array_map(static function ($r) {
      return [
          'id' => (int)$r['id'],
          'title' => (string)($r['title'] ?? ''),
          'status' => 'resolved',
          'severity' => (string)($r['severity'] ?? ''),
          'incident_type' => (string)($r['incident_type'] ?? ''),
          'reporter_email' => (string)($r['reporter_email'] ?? ''),
          'created_at' => (string)($r['created_at'] ?? ''),
          'address' => (string)($r['address'] ?? ''),
          'latitude' => isset($r['latitude']) ? (float)$r['latitude'] : null,
          'longitude' => isset($r['longitude']) ? (float)$r['longitude'] : null,
      ];
  }, $resolvedMapReports), JSON_UNESCAPED_SLASHES) ?>;

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
      // If the incident type was changed elsewhere, keep the marker icon in sync.
      if (typeof window.updateReportMarkerIcon === 'function') {
        window.updateReportMarkerIcon({
          id: reportId,
          incident_type: r.incident_type || '',
          status: r.status || ''
        });
      }
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
      detailEl.innerHTML = '<p style="color:#b91c1c; font-size:0.9rem;">Network error while loading report. Check console for details.</p>';
      console.error('Admin report detail fetch failed:', err);
    });
  }

  var adminMap = null;
  var mapEl = document.getElementById('admin-reports-map');
  if (mapEl && typeof L !== 'undefined') {
    adminMap = L.map('admin-reports-map').setView([10.5333, 122.8333], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(adminMap);

    function normalizeIncidentType(t) {
      return (t || '').toString().trim().toLowerCase().replace(/\s+/g, '_');
    }

    function incidentIconSpec(incidentType) {
      var t = normalizeIncidentType(incidentType);
      // Expand this map as needed.
      if (t === 'fire') return { label: '🔥', bg: '#ef4444', fg: '#ffffff' };
      if (t === 'hospital' || t === 'medical' || t === 'hospital/medical' || t === 'hospital_medical') return { label: 'H', bg: '#16a34a', fg: '#ffffff' };
      if (t === 'crime' || t === 'police') return { label: '🚓', bg: '#2563eb', fg: '#ffffff' };
      if (t === 'accident' || t === 'vehicular_accident' || t === 'road_accident') return { label: '🚧', bg: '#f59e0b', fg: '#111827' };
      if (t === 'flood') return { label: '🌊', bg: '#0ea5e9', fg: '#ffffff' };
      return { label: '!', bg: '#334155', fg: '#ffffff' };
    }

    function makeIncidentDivIcon(incidentType, isResolved) {
      var spec = incidentIconSpec(incidentType);
      var bg = isResolved ? '#94a3b8' : spec.bg;
      var fg = isResolved ? '#0f172a' : spec.fg;
      var border = isResolved ? '#64748b' : '#0f172a';
      // A compact "pin" that reads well without extra assets.
      var html =
        '<div style="' +
          'width:28px;height:28px;border-radius:999px;' +
          'background:' + bg + ';color:' + fg + ';' +
          'border:2px solid ' + border + ';' +
          'display:flex;align-items:center;justify-content:center;' +
          'font-weight:800;font-size:14px;line-height:1;' +
          'box-shadow:0 6px 14px rgba(15,23,42,0.25);' +
        '">' + (spec.label || '!') + '</div>';
      return L.divIcon({
        className: '',
        html: html,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -12]
      });
    }

    var bounds = [];
    var responderLayers = {};
    var incidentMarkers = {};
    var resolvedMarkers = {};
    if (Array.isArray(mapReports)) {
      mapReports.forEach(function(r) {
        if (typeof r.latitude !== 'number' || typeof r.longitude !== 'number') return;
        var incidentType = (r.incident_type || '').replace(/_/g, ' ');
        incidentType = incidentType ? incidentType.charAt(0).toUpperCase() + incidentType.slice(1) : '—';
        var status = (r.status || '').replace(/_/g, ' ');
        status = status ? status.charAt(0).toUpperCase() + status.slice(1) : '—';
        var severity = r.severity ? (r.severity.charAt(0).toUpperCase() + r.severity.slice(1)) : '—';
        var popup = '<strong>#' + r.id + ' ' + (r.title || '').replace(/</g, '&lt;') + '</strong>' +
          '<br><strong>Incident type:</strong> ' + incidentType +
          '<br><strong>Severity:</strong> ' + severity +
          '<br><strong>Status:</strong> ' + status +
          (r.address ? '<br><strong>Address:</strong> ' + (r.address || '').replace(/</g, '&lt;') : '') +
          '<br><strong>Date:</strong> ' + (r.created_at || '') +
          '<br><small style="color:#6b7280;">Click pin to view full details</small>';
        var marker = L.marker([r.latitude, r.longitude], { icon: makeIncidentDivIcon(r.incident_type, false) }).addTo(adminMap).bindPopup(popup);
        marker.on('click', function() { openReportDetail(r.id); });
        incidentMarkers[r.id] = marker;
        bounds.push([r.latitude, r.longitude]);
        responderLayers[r.id] = { marker: null, line: null };
      });
    }
    if (bounds.length === 1) {
      adminMap.setView(bounds[0], 14);
    } else if (bounds.length > 1) {
      adminMap.fitBounds(bounds, { padding: [25, 25] });
    }

    function updateResponderLayers(locations) {
      if (!adminMap || !locations) return;
      Object.keys(responderLayers).forEach(function(reportIdStr) {
        var reportId = parseInt(reportIdStr, 10);
        var r = mapReports.find(function(x) { return x.id === reportId; });
        if (!r || r.status !== 'dispatched' || typeof r.latitude !== 'number' || typeof r.longitude !== 'number') return;
        var loc = locations[reportIdStr] || locations[reportId];
        if (responderLayers[reportIdStr].marker) {
          adminMap.removeLayer(responderLayers[reportIdStr].marker);
          responderLayers[reportIdStr].marker = null;
        }
        if (responderLayers[reportIdStr].line) {
          adminMap.removeLayer(responderLayers[reportIdStr].line);
          responderLayers[reportIdStr].line = null;
        }
        var lat = loc && typeof loc.lat === 'number' ? loc.lat : (r.assigned_latitude);
        var lng = loc && typeof loc.lng === 'number' ? loc.lng : (r.assigned_longitude);
        if (typeof lat !== 'number' || typeof lng !== 'number') return;
        var respMarker = L.circleMarker([lat, lng], { radius: 8, color: '#2563eb', fillColor: '#3b82f6', fillOpacity: 0.9, weight: 2 }).addTo(adminMap);
        respMarker.bindPopup('<strong>Responder</strong> en route to incident #' + reportId);
        responderLayers[reportIdStr].marker = respMarker;
        var routeUrl = 'https://router.project-osrm.org/route/v1/driving/' + lng + ',' + lat + ';' + r.longitude + ',' + r.latitude + '?overview=full&geometries=geojson';
        fetch(routeUrl).then(function(res) { return res.json(); }).then(function(data) {
          if (!data.routes || !data.routes[0] || !responderLayers[reportIdStr].marker) return;
          var coords = data.routes[0].geometry.coordinates.map(function(c) { return [c[1], c[0]]; });
          var line = L.polyline(coords, { color: '#10b981', weight: 4, opacity: 0.8 }).addTo(adminMap);
          responderLayers[reportIdStr].line = line;
        }).catch(function() {});
      });
    }

    mapReports.forEach(function(r) {
      if ((r.status !== 'dispatched') || typeof r.latitude !== 'number' || typeof r.longitude !== 'number') return;
      var lat = r.assigned_latitude;
      var lng = r.assigned_longitude;
      if (typeof lat !== 'number' || typeof lng !== 'number') return;
      if (!responderLayers[r.id]) return;
      var respMarker = L.circleMarker([lat, lng], { radius: 8, color: '#2563eb', fillColor: '#3b82f6', fillOpacity: 0.9, weight: 2 }).addTo(adminMap);
      respMarker.bindPopup('<strong>Responder</strong> en route to incident #' + r.id);
      responderLayers[r.id].marker = respMarker;
      var routeUrl = 'https://router.project-osrm.org/route/v1/driving/' + lng + ',' + lat + ';' + r.longitude + ',' + r.latitude + '?overview=full&geometries=geojson';
      fetch(routeUrl).then(function(res) { return res.json(); }).then(function(data) {
        if (!data.routes || !data.routes[0] || !responderLayers[r.id]) return;
        var coords = data.routes[0].geometry.coordinates.map(function(c) { return [c[1], c[0]]; });
        var line = L.polyline(coords, { color: '#10b981', weight: 4, opacity: 0.8 }).addTo(adminMap);
        responderLayers[r.id].line = line;
      }).catch(function() {});
    });

    setInterval(function() {
      var apiUrl = base ? (base + '/api/admin/responder-locations') : '/api/admin/responder-locations';
      fetch(apiUrl).then(function(res) { return res.json(); }).then(function(d) {
        if (d.success && d.locations) updateResponderLayers(d.locations);
      }).catch(function() {});
    }, 10000);

    function removeReportFromMap(reportId) {
      var id = parseInt(reportId, 10);
      if (incidentMarkers[id]) {
        adminMap.removeLayer(incidentMarkers[id]);
        delete incidentMarkers[id];
      }
      var rid = '' + id;
      if (responderLayers[rid]) {
        if (responderLayers[rid].marker) { adminMap.removeLayer(responderLayers[rid].marker); responderLayers[rid].marker = null; }
        if (responderLayers[rid].line) { adminMap.removeLayer(responderLayers[rid].line); responderLayers[rid].line = null; }
        delete responderLayers[rid];
      }
    }

    function updateResolvedOnMap(show) {
      var id;
      if (show) {
        if (!Array.isArray(resolvedMapReports)) return;
        resolvedMapReports.forEach(function(r) {
          if (typeof r.latitude !== 'number' || typeof r.longitude !== 'number') return;
          if (resolvedMarkers[r.id]) return;
          var incidentType = (r.incident_type || '').replace(/_/g, ' ');
          incidentType = incidentType ? incidentType.charAt(0).toUpperCase() + incidentType.slice(1) : '—';
          var severity = r.severity ? (r.severity.charAt(0).toUpperCase() + r.severity.slice(1)) : '—';
          var popup = '<strong>#' + r.id + ' (Resolved) ' + (r.title || '').replace(/</g, '&lt;') + '</strong>' +
            '<br><strong>Incident type:</strong> ' + incidentType +
            '<br><strong>Severity:</strong> ' + severity +
            (r.address ? '<br><strong>Address:</strong> ' + (r.address || '').replace(/</g, '&lt;') : '') +
            '<br><strong>Date:</strong> ' + (r.created_at || '') +
            '<br><small style="color:#6b7280;">Click to view full details</small>';
          var marker = L.marker([r.latitude, r.longitude], { icon: makeIncidentDivIcon(r.incident_type, true) }).addTo(adminMap).bindPopup(popup);
          marker.on('click', function() { openReportDetail(r.id); });
          resolvedMarkers[r.id] = marker;
        });
      } else {
        for (id in resolvedMarkers) { if (resolvedMarkers.hasOwnProperty(id)) { adminMap.removeLayer(resolvedMarkers[id]); } }
        resolvedMarkers = {};
      }
    }

    var showResolvedCb = document.getElementById('admin-map-show-resolved');
    if (showResolvedCb) {
      showResolvedCb.addEventListener('change', function() { updateResolvedOnMap(this.checked); });
    }

    window.removeReportFromMap = removeReportFromMap;
    window.updateReportMarkerIcon = function(info) {
      if (!info || !info.id) return;
      var id = parseInt(info.id, 10);
      var incidentType = info.incident_type || '';

      // Update in-memory datasets so responder updates stay consistent.
      if (Array.isArray(mapReports)) {
        var a = mapReports.find(function(x) { return x.id === id; });
        if (a) a.incident_type = incidentType;
      }
      if (Array.isArray(resolvedMapReports)) {
        var h = resolvedMapReports.find(function(x) { return x.id === id; });
        if (h) h.incident_type = incidentType;
      }

      if (incidentMarkers[id]) {
        incidentMarkers[id].setIcon(makeIncidentDivIcon(incidentType, false));
      }
      if (resolvedMarkers[id]) {
        resolvedMarkers[id].setIcon(makeIncidentDivIcon(incidentType, true));
      }
    };
  }

  function doAssignDispatch(btnOrForm) {
    var form = btnOrForm.tagName === 'FORM' ? btnOrForm : btnOrForm.closest('form.assign-report');
    if (!form) return;
    if (form.getAttribute('data-dispatching') === '1') return;
    var reportId = form.getAttribute('data-report-id');
    var responderEl = form.querySelector('[name=responder_id]');
    var responderId = responderEl ? responderEl.value : '';
    if (!responderId) {
      alert('Please select a responder first.');
      return;
    }
    var btn = form.querySelector('.btn-confirm-dispatch');
    form.setAttribute('data-dispatching', '1');
    if (btn) { btn.disabled = true; btn.textContent = 'Dispatching…'; }
    if (responderEl) responderEl.disabled = true;
    var payload = { report_id: parseInt(reportId, 10), responder_id: parseInt(responderId, 10) };
    var apiUrl = base ? (base + '/api/reports/assign') : '/api/reports/assign';
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function(r) { return r.json(); }).then(function(d) {
      if (d.success) window.location.reload();
      else {
        form.removeAttribute('data-dispatching');
        if (btn) { btn.disabled = false; btn.textContent = 'Confirm \u0026 dispatch'; }
        if (responderEl) responderEl.disabled = false;
        alert(d.error || 'Failed.');
      }
    }).catch(function() {
      form.removeAttribute('data-dispatching');
      if (btn) { btn.disabled = false; btn.textContent = 'Confirm \u0026 dispatch'; }
      if (responderEl) responderEl.disabled = false;
      alert('Network error. Please try again.');
    });
  }

  document.querySelectorAll('.assign-report').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      doAssignDispatch(form);
    });
  });

  document.querySelectorAll('.btn-confirm-dispatch').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      doAssignDispatch(btn);
    });
  });

  document.querySelectorAll('.btn-view-report-media').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = parseInt(this.getAttribute('data-report-id'), 10);
      openReportDetail(id);
    });
  });

  document.querySelectorAll('.btn-confirm-resolved').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var reportId = parseInt(this.getAttribute('data-report-id'), 10);
      if (!reportId) return;
      if (this.disabled) return;
      if (!confirm('Mark this incident as Resolved?')) return;
      this.disabled = true;
      var origText = this.textContent;
      this.textContent = 'Confirming…';
      var apiUrl = base ? (base + '/api/reports/confirm-resolved') : '/api/reports/confirm-resolved';
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: reportId })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
          if (typeof window.removeReportFromMap === 'function') window.removeReportFromMap(reportId);
          var activeList = document.getElementById('admin-active-reports-list');
          if (activeList) {
            var li = activeList.querySelector('li[data-report-id="' + reportId + '"]');
            if (li) li.remove();
          }
          window.location.reload();
        } else { btn.disabled = false; btn.textContent = origText; alert(d.error || 'Failed.'); }
      }).catch(function() {
        btn.disabled = false;
        btn.textContent = origText;
        alert('Network error.');
      });
    });
  });

  document.querySelectorAll('.btn-toggle-tracking').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      var reportId = parseInt(this.getAttribute('data-report-id'), 10);
      var currentlyEnabled = this.getAttribute('data-enabled') === '1';
      if (!reportId) return;
      if (this.disabled) return;
      this.disabled = true;
      var apiUrl = base ? (base + '/api/reports/' + reportId + '/tracking') : ('/api/reports/' + reportId + '/tracking');
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ enabled: !currentlyEnabled })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) window.location.reload();
        else { btn.disabled = false; alert(d.error || 'Failed to update tracking.'); }
      }).catch(function() {
        btn.disabled = false;
        alert('Network error.');
      });
    });
  });
})();
</script>
<?php
$dashboardContent = ob_get_clean();
require $baseDir . '/templates/dashboard_layout.php';
