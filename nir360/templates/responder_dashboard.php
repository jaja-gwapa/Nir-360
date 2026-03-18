<?php
$pageTitle = 'Responder Dashboard';
$assignedReports = array_values(array_filter(
    $reportService->listAssignedRecentTo($userId, 7),
    static fn($r) => (string)($r['status'] ?? '') !== 'resolved'
));
$mapReports = array_values(array_filter($assignedReports, static function ($r) {
    return isset($r['latitude'], $r['longitude'])
        && $r['latitude'] !== null
        && $r['longitude'] !== null
        && $r['latitude'] !== ''
        && $r['longitude'] !== '';
}));
$dashboardExtraHead = '<style>.dash-main .dash { max-width: none; } #responder-reports-map { width: 100%; min-height: 320px; border: 1px solid #e2e8f0; border-radius: 8px; }</style>';
ob_start();
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<p class="role" style="margin-bottom:1rem;">Showing reports assigned to you from the last <strong>7 days</strong>. Resolved reports are kept in <a href="<?= htmlspecialchars($webBase ?? '') ?>/responder/history">History / Records</a>.</p>

<section style="margin-bottom:2rem;">
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">Assigned incident locations</h2>
  <div id="responder-reports-map"></div>
  <p style="margin-top:0.5rem; color:var(--muted); font-size:0.9rem;"><?= empty($mapReports) ? 'No reports with location yet.' : 'Markers show incidents assigned to you. Select \"Show live route\" on a report below to see a live route from your current location.' ?></p>
  <div id="responder-route-info" style="margin-top:0.5rem; font-size:0.85rem; color:#111827; display:none; border:1px solid #e5e7eb; border-radius:8px; padding:0.75rem; background:#f9fafb;">
    <!-- Filled by JS with live route summary and external map links -->
  </div>
</section>

<section id="responder-report-detail" style="margin-bottom:2rem; display:none; border:1px solid #e2e8f0; border-radius:8px; padding:1rem; background:#fff;">
  <!-- Filled via JS when responder clicks "View photos & videos" -->
</section>

<?php if (empty($assignedReports)): ?>
  <p class="role">No reports dispatched to you yet.</p>
<?php else: ?>
  <h2 style="font-size:1.1rem; margin-bottom:0.5rem;">Report list</h2>
  <ul id="responder-assigned-reports-list" style="list-style:none; padding:0;">
    <?php foreach ($assignedReports as $r): ?>
      <li data-report-id="<?= (int)$r['id'] ?>" style="border:1px solid #e2e8f0; border-radius:8px; padding:1rem; margin-bottom:0.75rem;">
        <strong><?= htmlspecialchars($r['title']) ?></strong>
        <span style="color:var(--muted); font-size:0.85rem;"> — <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$r['status']))) ?></span>
        <br><small>From: <?= htmlspecialchars($r['reporter_email'] ?? '') ?> · <?= htmlspecialchars($r['created_at']) ?></small>
        <?php if (!empty($r['address'])): ?>
          <p style="margin:0.35rem 0 0; font-size:0.9rem; color:#374151;"><strong>Incident location:</strong> <?= htmlspecialchars($r['address']) ?></p>
        <?php endif; ?>
        <p style="margin:0.5rem 0;"><?= nl2br(htmlspecialchars($r['description'])) ?></p>
        <?php if (isset($r['latitude'], $r['longitude']) && $r['latitude'] !== null && $r['longitude'] !== null): ?>
          <p style="margin:0.5rem 0 0; display:flex; flex-wrap:wrap; gap:0.5rem;">
            <a href="#" class="btn-get-directions btn btn-outline" style="font-size:0.9rem; text-decoration:none; display:inline-block;"
               data-dest-lat="<?= (float)$r['latitude'] ?>"
               data-dest-lng="<?= (float)$r['longitude'] ?>"
               data-dest-addr="<?= htmlspecialchars($r['address'] ?? '', ENT_QUOTES) ?>">Open in Google Maps</a>
            <button type="button"
                    class="btn-live-route btn btn-primary"
                    style="font-size:0.9rem;"
                    data-dest-lat="<?= (float)$r['latitude'] ?>"
                    data-dest-lng="<?= (float)$r['longitude'] ?>"
                    data-dest-addr="<?= htmlspecialchars($r['address'] ?? '', ENT_QUOTES) ?>">
              Show live route
            </button>
          </p>
        <?php endif; ?>
        <button type="button"
                class="btn btn-outline btn-view-report-media"
                data-report-id="<?= (int)$r['id'] ?>"
                style="margin-top:0.5rem; font-size:0.85rem;">
          View photos &amp; videos
        </button>
        <?php if (($r['status'] ?? '') === 'awaiting_closure'): ?>
          <p style="margin-top:0.5rem; font-size:0.9rem; color:#b45309;">You are back at base. Confirm to close this incident as Resolved.</p>
          <button type="button" class="btn-confirm-resolved btn btn-primary" data-report-id="<?= (int)$r['id'] ?>" style="margin-top:0.35rem;">Confirm Resolved</button>
        <?php endif; ?>
        <?php if (($r['status'] ?? '') === 'resolved'): ?>
          <p style="margin-top:0.5rem; font-size:0.9rem; color:var(--muted);">This report is closed. Status cannot be edited.</p>
        <?php else: ?>
        <form class="report-update-status" data-report-id="<?= (int)$r['id'] ?>" style="margin-top:0.5rem;">
          <label for="status-<?= (int)$r['id'] ?>">Update status:</label>
          <select name="status" id="status-<?= (int)$r['id'] ?>">
            <option value="dispatched" <?= ($r['status'] ?? '') === 'dispatched' ? 'selected' : '' ?>>Dispatched</option>
            <option value="awaiting_closure" <?= ($r['status'] ?? '') === 'awaiting_closure' ? 'selected' : '' ?>>Awaiting closure</option>
            <option value="resolved" <?= ($r['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
          </select>
          <button type="submit" class="btn btn-outline" style="margin-left:0.5rem;">Save</button>
        </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<script>
(function() {
  var base = '<?= htmlspecialchars($webBase ?? '') ?>';
  var mapReports = <?= json_encode(array_map(static function ($r) {
      return [
          'id' => (int)$r['id'],
          'title' => (string)($r['title'] ?? ''),
          'status' => (string)($r['status'] ?? ''),
          'address' => (string)($r['address'] ?? ''),
          'reporter_email' => (string)($r['reporter_email'] ?? ''),
          'created_at' => (string)($r['created_at'] ?? ''),
          'latitude' => isset($r['latitude']) ? (float)$r['latitude'] : null,
          'longitude' => isset($r['longitude']) ? (float)$r['longitude'] : null,
      ];
  }, $mapReports), JSON_UNESCAPED_SLASHES) ?>;

  var mapEl = document.getElementById('responder-reports-map');
  if (mapEl && typeof L !== 'undefined') {
    var responderMap = L.map('responder-reports-map').setView([10.5333, 122.8333], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(responderMap);
    var markersLayer = L.featureGroup().addTo(responderMap);
    var reportMarkers = {};
    if (Array.isArray(mapReports)) {
      mapReports.forEach(function(r) {
        if (typeof r.latitude !== 'number' || typeof r.longitude !== 'number') return;
        var destParam = (r.address && r.address.length < 200) ? encodeURIComponent(r.address) : (r.latitude + ',' + r.longitude);
        var dirUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + destParam + '&travelmode=driving';
        var popup = '<strong>#' + r.id + ' ' + (r.title || '') + '</strong><br>Status: ' + (r.status || '') + '<br>From: ' + (r.reporter_email || '—') + (r.address ? '<br>Address: ' + r.address.replace(/</g, '&lt;') : '') + '<br>' + (r.created_at || '') + '<br><a href=\"' + dirUrl + '\" target=\"_blank\" rel=\"noopener\">Open in Google Maps</a>';
        var m = L.marker([r.latitude, r.longitude]).bindPopup(popup);
        m.addTo(markersLayer);
        reportMarkers[String(r.id)] = m;
      });
    }
    if (markersLayer.getLayers().length === 1) responderMap.fitBounds(markersLayer.getBounds(), { padding: [25, 25], maxZoom: 14 });
    else if (markersLayer.getLayers().length > 1) responderMap.fitBounds(markersLayer.getBounds(), { padding: [25, 25] });
  }

  function removeReportFromDashboard(reportId) {
    var idStr = String(reportId);
    // Remove list item
    var ul = document.getElementById('responder-assigned-reports-list');
    if (ul) {
      var li = ul.querySelector('li[data-report-id="' + idStr.replace(/"/g, '') + '"]');
      if (li) li.remove();
      if (!ul.querySelector('li')) {
        ul.style.display = 'none';
        var msg = document.getElementById('responder-no-active-msg');
        if (!msg) {
          msg = document.createElement('p');
          msg.id = 'responder-no-active-msg';
          msg.className = 'role';
          msg.textContent = 'No reports dispatched to you yet.';
          ul.parentNode.insertBefore(msg, ul.nextSibling);
        }
      }
    }
    // Remove map marker
    if (typeof responderMap !== 'undefined' && typeof markersLayer !== 'undefined' && typeof reportMarkers !== 'undefined') {
      var marker = reportMarkers[idStr];
      if (marker) {
        markersLayer.removeLayer(marker);
        delete reportMarkers[idStr];
        if (markersLayer.getLayers().length > 0) {
          responderMap.fitBounds(markersLayer.getBounds(), { padding: [25, 25] });
        }
      }
    }
    // If currently viewing details for this report, hide detail
    var detailEl = document.getElementById('responder-report-detail');
    if (detailEl && detailEl.style.display !== 'none') {
      // Best-effort: hide detail pane after resolution so dashboard stays clean
      detailEl.style.display = 'none';
    }
  }

  document.querySelectorAll('.btn-get-directions').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var destLat = parseFloat(this.getAttribute('data-dest-lat'), 10);
      var destLng = parseFloat(this.getAttribute('data-dest-lng'), 10);
      var destAddr = (this.getAttribute('data-dest-addr') || '').trim();
      var destParam = (destAddr && destAddr.length < 200) ? encodeURIComponent(destAddr) : (destLat + ',' + destLng);
      function openDirections(originLat, originLng) {
        var url = 'https://www.google.com/maps/dir/?api=1&origin=' + originLat + ',' + originLng + '&destination=' + destParam + '&travelmode=driving';
        window.open(url, '_blank', 'noopener');
      }
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function(pos) { openDirections(pos.coords.latitude, pos.coords.longitude); },
          function() {
            var url = 'https://www.google.com/maps/dir/?api=1&destination=' + destParam + '&travelmode=driving';
            window.open(url, '_blank', 'noopener');
          }
        );
      } else {
        var url = 'https://www.google.com/maps/dir/?api=1&destination=' + destParam + '&travelmode=driving';
        window.open(url, '_blank', 'noopener');
      }
    });
  });

  // Live route: watch responder location and show fastest route to incident
  var routeInfoEl = document.getElementById('responder-route-info');
  var activeRouteLine = null;
  var activeUserMarker = null;
  var watchId = null;

  function clearActiveRoute() {
    if (activeRouteLine && typeof responderMap !== 'undefined') {
      responderMap.removeLayer(activeRouteLine);
      activeRouteLine = null;
    }
    if (activeUserMarker && typeof responderMap !== 'undefined') {
      responderMap.removeLayer(activeUserMarker);
      activeUserMarker = null;
    }
  }

  function updateRouteInfo(contentHtml) {
    if (!routeInfoEl) return;
    routeInfoEl.innerHTML = contentHtml;
    routeInfoEl.style.display = 'block';
  }

  function buildExternalLinks(originLat, originLng, destLat, destLng, destAddr) {
    var destParam = destAddr && destAddr.length < 200 ? encodeURIComponent(destAddr) : (destLat + ',' + destLng);
    var gmaps = 'https://www.google.com/maps/dir/?api=1&origin=' + originLat + ',' + originLng + '&destination=' + destParam + '&travelmode=driving';
    var waze = 'https://waze.com/ul?ll=' + destLat + ',' + destLng + '&navigate=yes';
    return '<p style="margin-top:0.5rem;"><a href=\"' + gmaps + '\" target=\"_blank\" rel=\"noopener\">Open in Google Maps</a> · ' +
           '<a href=\"' + waze + '\" target=\"_blank\" rel=\"noopener\">Open in Waze</a></p>';
  }

  function startLiveRoute(destLat, destLng, destAddr) {
    if (!navigator.geolocation || typeof responderMap === 'undefined') {
      alert('Live route requires location permission on this device.');
      return;
    }
    if (watchId !== null) {
      navigator.geolocation.clearWatch(watchId);
      watchId = null;
    }
    clearActiveRoute();

    var lastSentLocationTime = 0;
    function onPosition(pos) {
      var userLat = pos.coords.latitude;
      var userLng = pos.coords.longitude;

      if (activeUserMarker) {
        responderMap.removeLayer(activeUserMarker);
      }
      activeUserMarker = L.circleMarker([userLat, userLng], { radius: 6, color: '#2563eb', fillColor: '#3b82f6', fillOpacity: 0.9 }).addTo(responderMap);

      var now = Date.now();
      if (now - lastSentLocationTime >= 10000) {
        lastSentLocationTime = now;
        var apiUrl = base ? (base + '/api/responder/location') : '/api/responder/location';
        fetch(apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ latitude: userLat, longitude: userLng })
        }).then(function(r) { return r.json(); }).then(function(d) {
          if (d.success && d.awaiting_closure_report_ids && d.awaiting_closure_report_ids.length > 0) {
            var n = d.awaiting_closure_report_ids.length;
            alert('You are at base. ' + n + ' incident(s) marked Awaiting Closure. Confirm Resolved when ready.');
          }
        }).catch(function() {});
      }

      var url = 'https://router.project-osrm.org/route/v1/driving/' + userLng + ',' + userLat + ';' + destLng + ',' + destLat + '?overview=full&geometries=geojson&steps=true';
      fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.routes || !data.routes[0]) return;
        var route = data.routes[0];
        var coords = route.geometry.coordinates.map(function(c) { return [c[1], c[0]]; });
        if (activeRouteLine) {
          responderMap.removeLayer(activeRouteLine);
        }
        activeRouteLine = L.polyline(coords, { color: '#10b981', weight: 5, opacity: 0.8 }).addTo(responderMap);
        responderMap.fitBounds(L.latLngBounds([[userLat, userLng], [destLat, destLng]]), { padding: [30, 30] });

        var mins = Math.round(route.duration / 60);
        var km = (route.distance / 1000).toFixed(1);
        var summary = '<strong>Live route to incident</strong><br>' +
                      'Approx. ' + mins + ' min, ' + km + ' km.';

        var stepsHtml = '';
        if (route.legs && route.legs[0] && Array.isArray(route.legs[0].steps)) {
          stepsHtml = '<ol style="margin-top:0.5rem; padding-left:1.2rem; max-height:180px; overflow:auto; font-size:0.8rem;">';
          route.legs[0].steps.slice(0, 15).forEach(function(step) {
            var instruction = (step.maneuver && step.maneuver.instruction) || step.name || '';
            if (!instruction) return;
            stepsHtml += '<li style="margin-bottom:0.25rem;">' + instruction.replace(/</g, '&lt;') + '</li>';
          });
          stepsHtml += '</ol>';
        }

        updateRouteInfo(summary + stepsHtml + buildExternalLinks(userLat, userLng, destLat, destLng, destAddr));
      }).catch(function() {
        updateRouteInfo('<strong>Live route</strong><br>Unable to fetch route. You can still open external navigation apps.' +
          buildExternalLinks(userLat, userLng, destLat, destLng, destAddr));
      });
    }

    watchId = navigator.geolocation.watchPosition(onPosition, function(err) {
      alert('Unable to get your current location for live routing (' + (err && err.message ? err.message : 'unknown error') + ').');
    }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 });
  }

  document.querySelectorAll('.btn-live-route').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var destLat = parseFloat(this.getAttribute('data-dest-lat'), 10);
      var destLng = parseFloat(this.getAttribute('data-dest-lng'), 10);
      var destAddr = (this.getAttribute('data-dest-addr') || '').trim();
      if (!isFinite(destLat) || !isFinite(destLng)) return;
      startLiveRoute(destLat, destLng, destAddr);
    });
  });

  document.querySelectorAll('.report-update-status').forEach(function(f) {
    f.addEventListener('submit', function(e) {
      e.preventDefault();
      var id = f.getAttribute('data-report-id');
      var status = f.querySelector('[name=status]').value;
      var apiUrl = base ? (base + '/api/reports/update-status') : '/api/reports/update-status';
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: parseInt(id, 10), status: status })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
          if (status === 'resolved') removeReportFromDashboard(parseInt(id, 10));
          else window.location.reload();
        }
        else alert(d.error || 'Failed to update.');
      });
    });
  });

  document.querySelectorAll('.btn-confirm-resolved').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var reportId = parseInt(this.getAttribute('data-report-id'), 10);
      if (!reportId) return;
      if (!confirm('Mark this incident as Resolved?')) return;
      var apiUrl = base ? (base + '/api/reports/confirm-resolved') : '/api/reports/confirm-resolved';
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: reportId })
      }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) removeReportFromDashboard(reportId);
        else alert(d.error || 'Failed.');
      }).catch(function() { alert('Network error.'); });
    });
  });

  // Responder report details + media gallery
  var detailEl = document.getElementById('responder-report-detail');
  function escapeHtml(str) {
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

  document.querySelectorAll('.btn-view-report-media').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = parseInt(this.getAttribute('data-report-id'), 10);
      if (!detailEl || !id) return;
      detailEl.style.display = 'block';
      detailEl.innerHTML = '<p style="font-size:0.9rem; color:var(--muted);">Loading report details…</p>';
      var apiUrl = base ? (base + '/api/reports/' + id) : ('/api/reports/' + id);
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
          '<h3 style="margin:0; font-size:1rem;">Incident #' + id + ' – ' + escapeHtml(r.title || '') + '</h3>' +
          '<button type="button" class="btn btn-outline" id="responder-detail-close" style="font-size:0.8rem;">Close</button></div>';
        html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Status:</strong> ' + escapeHtml(status || '—') +
          (sev ? ' · <strong>Severity:</strong> ' + escapeHtml(sev) : '') + '</p>';
        if (r.address) {
          html += '<p style="margin:0.25rem 0; font-size:0.9rem;"><strong>Location:</strong> ' + escapeHtml(r.address) + '</p>';
        }
        html += '<p style="margin:0.5rem 0; font-size:0.9rem; white-space:pre-wrap;">' + escapeHtml(r.description || '') + '</p>';
        html += buildMediaGallery(r.media || []);
        detailEl.innerHTML = html;
        detailEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        var btnClose = document.getElementById('responder-detail-close');
        if (btnClose) {
          btnClose.addEventListener('click', function() {
            detailEl.style.display = 'none';
          });
        }
      }).catch(function(err) {
        detailEl.innerHTML = '<p style="color:#b91c1c; font-size:0.9rem;">Network error while loading report.</p>';
        console.error('Responder report detail fetch failed:', err);
      });
    });
  });
})();
</script>
<?php
$dashboardContent = ob_get_clean();
require $baseDir . '/templates/dashboard_layout.php';
