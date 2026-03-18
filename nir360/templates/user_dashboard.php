<?php
$pageTitle = 'Public Reporter Dashboard';
$hideDashTitle = true; // we output title inside our flex so map aligns to top
$myReports = $reportService->listByReporter($userId);
$webBase = $webBase ?? '';
$savedProfile = isset($profileService) ? $profileService->getProfileForDashboard((int)$userId) : null;
$savedLatitude = null;
$savedLongitude = null;
$savedLocationAddress = '';
$csrfToken = Helpers::csrfToken();
if (is_array($savedProfile)) {
  $latRaw = $savedProfile['latitude'] ?? null;
  $lngRaw = $savedProfile['longitude'] ?? null;
  if (is_numeric($latRaw) && is_numeric($lngRaw)) {
    $lat = (float)$latRaw;
    $lng = (float)$lngRaw;
    if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
      $savedLatitude = $lat;
      $savedLongitude = $lng;
      $savedLocationAddress = trim((string)($savedProfile['location_address'] ?? ''));
    }
  }
}
foreach ($myReports as &$r) {
    $mediaList = $reportService->getReportMedia((int)$r['id']);
    $r['media'] = [];
    foreach ($mediaList as $m) {
        $r['media'][] = [
            'id' => (int)$m['id'],
            'media_type' => $m['media_type'],
            'mime_type' => $m['mime_type'],
            'url' => $webBase . '/api/report-media/file?report_id=' . (int)$r['id'] . '&media_id=' . (int)$m['id'],
        ];
    }
}
unset($r);
$dashboardExtraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/2.3.7/css/dataTables.dataTables.min.css"><style>
  .my-reports-table-wrap { width: 100%; overflow-x: auto; }
  .my-reports-datatable-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-top: 0.5rem; }
  #my-reports-table { width: 100% !important; }
  #my-reports-table thead th { white-space: nowrap; text-align: left; vertical-align: middle; padding: 0.6rem 0.5rem; border-bottom: 2px solid #e2e8f0; background: #f8fafc; font-weight: 600; font-size: 0.85rem; color: #374151; }
  #my-reports-table tbody td { padding: 0.55rem 0.5rem; vertical-align: middle; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; }
  #my-reports-table tbody tr:hover { background: #f8fafc; }
  #my-reports-table .my-reports-actions-cell { white-space: nowrap; }
  #my-reports-table .status-badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 500; }
  #my-reports-table .status-pending { background: #fef3c7; color: #92400e; }
  #my-reports-table .status-dispatched { background: #dbeafe; color: #1e40af; }
  #my-reports-table .status-awaiting_closure { background: #fce7f3; color: #9d174d; }
  #my-reports-table .status-resolved { background: #d1fae5; color: #065f46; }
  #my-reports-table .status-draft { background: #f3f4f6; color: #6b7280; }
  #my-reports-table .severity-badge { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.75rem; font-weight: 500; }
  #my-reports-table .severity-low { background: #ecfdf5; color: #047857; }
  #my-reports-table .severity-medium { background: #fffbeb; color: #b45309; }
  #my-reports-table .severity-high { background: #fef2f2; color: #b91c1c; }
  .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { margin-bottom: 0.75rem; }
  .dataTables_wrapper .dataTables_filter input { padding: 0.4rem 0.6rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem; }
  .dataTables_wrapper .dataTables_length select { padding: 0.3rem 0.5rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem; }
  .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { margin-top: 0.75rem; padding-top: 0.5rem; }
  .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 0.3rem 0.6rem; margin: 0 2px; border-radius: 4px; }
  .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #2563eb; color: #fff !important; border: 1px solid #2563eb; }
  .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current) { background: #f3f4f6; }
  @media (max-width: 768px) { .my-reports-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; } #my-reports-table thead th, #my-reports-table tbody td { font-size: 0.8rem; padding: 0.4rem 0.35rem; } }
</style>';
ob_start();
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div id="dashboard-form-view" style="display:flex; gap:1.5rem; align-items:stretch; min-height:calc(100vh - 140px); flex-wrap:wrap;">
  <div style="flex:0 0 300px; min-width:260px;">
    <h1 style="margin-bottom:0.5rem; color:#1f2937; white-space:nowrap; font-size:1.5rem;"><?= htmlspecialchars($pageTitle) ?></h1>
    <p class="role" style="margin-bottom:1rem;">Submit an incident report and view your reports.</p>
    <section style="margin-bottom:2rem;">
      <h2 style="font-size:1.1rem; margin-bottom:0.75rem;">Submit incident report</h2>
      <form id="form-report" class="form-group">
        <div class="form-group">
          <label for="report-title">Title *</label>
          <input type="text" id="report-title" name="title" required placeholder="Brief title" maxlength="255">
        </div>
        <div class="form-group">
          <label for="incident-type">Incident type *</label>
          <select id="incident-type" name="incident_type" required>
            <option value="">Select type...</option>
            <option value="fire">Fire</option>
            <option value="flood">Flood</option>
            <option value="motorcycle accident">Motorcycle accident</option>
            <option value="earthquake">Earthquake</option>
            <option value="storm">Storm</option>
            <option value="tsunami">Tsunami</option>
            <option value="volcano">Volcano</option>
             <option value="crime">Crime</option>
             
          </select>
        </div>
        <div class="form-group">
          <label for="incident-severity">Severity level *</label>
          <select id="incident-severity" name="severity" required>
            <option value="">Select severity...</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
          <small style="color:var(--muted);">Choose how serious the incident is.</small>
        </div>
        <div class="form-group">
          <label for="report-description">Description *</label>
          <textarea id="report-description" name="description" required rows="4" placeholder="What happened? Location, time, details."></textarea>
        </div>
        <div class="form-group">
          <label for="report-media">Photos / Videos (optional)</label>
          <input type="file" id="report-media" name="report_media[]" accept="image/*,video/*" multiple>
          <small style="color:var(--muted);">Up to 4 images and 1 video. Allowed: JPG, PNG, WEBP, MP4, WEBM, MOV (max 30MB each).</small>
          <div id="report-media-preview" style="margin-top:0.75rem; display:flex; flex-wrap:wrap; gap:0.5rem;"></div>
        </div>
        <div class="form-group">
          <label for="incident-address">Incident address</label>
          <input type="text" id="incident-address" name="address" readonly placeholder="Filled automatically from your current location.">
          <small style="color:var(--muted);">Filled automatically from your current location (Nominatim).</small>
        </div>
        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">
        <input type="hidden" id="editing-report-id" value="">
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
          <button type="submit" class="btn btn-primary" id="btn-submit-report">Submit report</button>
          <button type="button" class="btn btn-outline" id="btn-save-draft">Save draft</button>
        </div>
      </form>
      <div id="report-message" class="success-box" style="display:none; margin-top:0.5rem;"></div>
      <div id="report-error" class="error-box" style="display:none; margin-top:0.5rem;"></div>
    </section>

    <section style="margin-bottom:2rem;">
      <h2 style="font-size:1rem; margin-bottom:0.5rem;">Emergency Call</h2>
      <p style="font-size:0.9rem; color:var(--muted); margin-bottom:0.5rem;">Call emergency services directly from the app.</p>
      <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
        <a href="tel:09336936444" class="btn btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; gap:0.35rem;">&#9742; Bago DRRM 24/7 (0933-693-6444)</a>
        <a href="tel:09270224884" class="btn btn-outline" style="text-decoration:none;">&#9742; 0927-022-4884</a>
      </div>
      <details style="font-size:0.9rem;">
        <summary style="cursor:pointer; color:var(--muted);">All emergency hotlines</summary>
        <div style="background:#1d4ed8; color:#f9fafb; border-radius:10px; padding:0.75rem 1rem; margin-top:0.5rem;">
          <div style="margin-bottom:0.35rem;"><strong>Bago DRRM</strong></div>
          <div style="margin-bottom:0.15rem;">
            <span style="font-weight:600;">Landline:</span>
            <span>(034) 701-8250 <span style="opacity:0.85;">(Office Hours)</span></span><br>
            <span style="margin-left:4.1rem;">(034) 473-0043 <span style="opacity:0.85;">(24/7)</span></span>
          </div>
          <div>
            <span style="font-weight:600;">Mobile (24/7):</span><br>
            <a href="tel:09336936444" style="color:#bfdbfe; text-decoration:none;">0933-693-6444</a><br>
            <a href="tel:09270224884" style="color:#bfdbfe; text-decoration:none;">0927-022-4884</a>
          </div>
        </div>
      </details>
    </section>

  </div>
  <div class="user-map-wrapper" style="flex:1; min-width:320px; display:flex; flex-direction:column; min-height:calc(100vh - 140px); margin-left:4rem;">
    <div id="user-map-tracking-bar" style="display:none; margin-bottom:0.5rem; padding:0.6rem 0.85rem; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px;">
      <div style="font-weight:700; color:#1e40af;">Responder tracking</div>
      <div id="user-map-tracking-meta" style="font-size:0.9rem; color:#1e3a8a; margin-top:0.2rem;">Report #— · Updates every 10s until resolved.</div>
      <div id="user-map-tracking-eta" style="font-size:0.9rem; margin-top:0.25rem; font-weight:500;"></div>
    </div>
    <label id="user-map-location-label" style="font-size:0.9rem; font-weight:500; margin:0 0 0.25rem 0;">Incident Location *</label>
    <div class="user-map-container" style="position:relative; width:100%; flex:1; min-height:400px; border:1px solid #e5e7eb; border-radius:6px; margin-top:0; background:#e5e7eb;">
      <div id="map" style="position:absolute; top:0; left:0; right:0; bottom:0; width:100%; height:100%;"></div>
    </div>
    <div id="location-notice" class="error-box" style="display:none; margin-top:0.5rem;"></div>
    <div id="suggestion-notice" style="display:none; margin-top:0.5rem; padding:0.6rem 0.85rem; border-radius:6px; border:1px solid #6ee7b7; background:#ecfdf5; color:#065f46; font-size:0.875rem;"></div>
    <p id="map-coords" style="margin-top:0.5rem; font-size:0.9rem; color:var(--muted); flex-shrink:0;">
      We automatically detect your location. Latitude and longitude will appear here.
    </p>
  </div>
</div>

<div id="my-reports-content" style="display:none;">
  <p class="role" style="margin-bottom:1rem;">Your submitted incident reports.</p>
  <p style="margin-bottom:0.5rem;"><a href="#" id="back-to-form" style="font-size:0.9rem;">← Submit new report</a></p>
  <h2 style="font-size:1.1rem; margin-bottom:0.25rem;">My reports</h2>
  <p style="font-size:0.85rem; color:var(--muted); margin-bottom:0.25rem;">Report tracking: <strong>Pending</strong> → <strong>Dispatched</strong> (responder assigned) → <strong>Resolved</strong>.</p>
  <p style="font-size:0.85rem; color:var(--muted); margin-bottom:0.75rem;">Click a row or <strong>View</strong> to open details. When a responder is dispatched, the <strong>main map</strong> (Submit incident report view) shows <strong>Responder tracking</strong> with live location, route, and ETA until resolved.</p>
  <?php if (empty($myReports)): ?>
    <p class="role">No reports yet.</p>
  <?php else: ?>
    <div class="my-reports-datatable-panel">
      <div class="my-reports-table-wrap">
        <table id="my-reports-table" class="display" style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th>#</th>
              <th>Title</th>
              <th>Incident Type</th>
              <th>Status</th>
              <th>Severity</th>
              <th>Date Submitted</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($myReports as $r): ?>
              <?php
              $statusClass = 'status-' . ($r['status'] ?? 'pending');
              $statusLabel = ucwords(str_replace('_', ' ', (string)($r['status'] ?? '')));
              $severityClass = 'severity-' . ($r['severity'] ?? '');
              $severityLabel = !empty($r['severity']) ? ucfirst((string)$r['severity']) : '—';
              $incidentTypeLabel = !empty($r['incident_type']) ? ucwords(str_replace('_', ' ', (string)$r['incident_type'])) : '—';
              ?>
              <tr class="report-row" data-report-item="1"
                  data-report-id="<?= (int)$r['id'] ?>"
                  data-status="<?= htmlspecialchars($r['status'], ENT_QUOTES) ?>"
                  data-title="<?= htmlspecialchars($r['title'], ENT_QUOTES) ?>"
                  data-severity="<?= htmlspecialchars((string)($r['severity'] ?? ''), ENT_QUOTES) ?>"
                  data-incident-type="<?= htmlspecialchars((string)($r['incident_type'] ?? ''), ENT_QUOTES) ?>"
                  data-created-at="<?= htmlspecialchars($r['created_at'], ENT_QUOTES) ?>"
                  data-description="<?= htmlspecialchars($r['description'], ENT_QUOTES) ?>"
                  data-media="<?= htmlspecialchars(json_encode($r['media'] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                  style="cursor:pointer;">
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= htmlspecialchars($incidentTypeLabel) ?></td>
                <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                <td><?php if (!empty($r['severity'])): ?><span class="severity-badge <?= $severityClass ?>"><?= htmlspecialchars($severityLabel) ?></span><?php else: ?>—<?php endif; ?></td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
                <td class="my-reports-actions-cell">
                  <button type="button" class="btn-view-report btn btn-outline" style="font-size:0.8rem; padding:0.25rem 0.5rem;">View</button>
                  <?php if (($r['status'] ?? '') === 'draft'): ?>
                    <button type="button" class="btn-edit-draft btn btn-outline" style="font-size:0.8rem; padding:0.25rem 0.5rem;" data-report-id="<?= (int)$r['id'] ?>">Edit</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
  <section id="report-detail" style="margin-top:1.5rem; display:none; border:1px solid #e5e7eb; border-radius:8px; padding:1rem; background:#fff;">
    <!-- Filled when a report row is clicked -->
  </section>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.min.js"></script>
<script>
(function() {
  var savedLatitude = <?= $savedLatitude !== null ? json_encode($savedLatitude) : 'null' ?>;
  var savedLongitude = <?= $savedLongitude !== null ? json_encode($savedLongitude) : 'null' ?>;
  var savedLocationAddress = <?= json_encode($savedLocationAddress, JSON_UNESCAPED_SLASHES) ?>;
  var csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
  var base = '<?= htmlspecialchars($webBase ?? '') ?>';
  var reportResetStorageKey = 'nir360-clear-report-form';
  var resetReportDraftOnLoad = false;
  var isSubmittingReport = false;
  var lastUserLatLng = null;

  try {
    resetReportDraftOnLoad = window.sessionStorage.getItem(reportResetStorageKey) === '1';
    if (resetReportDraftOnLoad) {
      window.sessionStorage.removeItem(reportResetStorageKey);
    }
  } catch (e) {
    resetReportDraftOnLoad = false;
  }

  function markReportFormForReset() {
    try {
      window.sessionStorage.setItem(reportResetStorageKey, '1');
    } catch (e) {
    }
  }

  var form = document.getElementById('form-report');

  // Preview selected images/videos before submit
  var reportMediaPreviewUrls = [];
  var mediaInputEl = document.getElementById('report-media');
  var previewContainer = document.getElementById('report-media-preview');
  if (mediaInputEl && previewContainer) {
    mediaInputEl.addEventListener('change', function() {
      reportMediaPreviewUrls.forEach(function(url) { URL.revokeObjectURL(url); });
      reportMediaPreviewUrls = [];
      previewContainer.innerHTML = '';
      var files = this.files;
      if (!files || files.length === 0) return;
      for (var i = 0; i < files.length; i++) {
        var file = files[i];
        var url = URL.createObjectURL(file);
        reportMediaPreviewUrls.push(url);
        if (file.type.indexOf('image/') === 0) {
          var img = document.createElement('img');
          img.src = url;
          img.alt = 'Preview';
          img.style.cssText = 'max-width:120px; max-height:120px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb;';
          previewContainer.appendChild(img);
        } else if (file.type.indexOf('video/') === 0) {
          var vid = document.createElement('video');
          vid.src = url;
          vid.controls = true;
          vid.style.cssText = 'max-width:200px; max-height:140px; border-radius:6px; border:1px solid #e5e7eb;';
          previewContainer.appendChild(vid);
        }
      }
    });
  }

  function setReportSubmittingState(isSubmitting) {
    var submitButton = form ? form.querySelector('button[type="submit"]') : null;
    isSubmittingReport = isSubmitting;
    if (!submitButton) {
      return;
    }
    submitButton.disabled = isSubmitting;
    submitButton.textContent = isSubmitting ? 'Submitting…' : 'Submit report';
    submitButton.style.opacity = isSubmitting ? '0.7' : '';
    submitButton.style.cursor = isSubmitting ? 'not-allowed' : '';
  }

  function buildReportFormData(isDraft, editingId) {
    var formData = new FormData();
    formData.append('title', document.getElementById('report-title').value.trim());
    formData.append('incident_type', document.getElementById('incident-type').value);
    formData.append('severity', document.getElementById('incident-severity').value);
    formData.append('description', document.getElementById('report-description').value.trim());
    formData.append('latitude', document.getElementById('latitude').value);
    formData.append('longitude', document.getElementById('longitude').value);
    var addressInput = document.getElementById('incident-address');
    if (addressInput && addressInput.value) formData.append('address', addressInput.value);
    var mediaInput = document.getElementById('report-media');
    if (mediaInput && mediaInput.files && mediaInput.files.length > 0) {
      for (var i = 0; i < mediaInput.files.length; i++) {
        formData.append('report_media[]', mediaInput.files[i]);
      }
    }
    if (editingId) {
      formData.append('report_id', editingId);
      formData.append('submit', isDraft ? '0' : '1');
    } else {
      formData.append('save_as_draft', isDraft ? '1' : '0');
    }
    return formData;
  }

  function submitReport(isDraft) {
    var editingId = (document.getElementById('editing-report-id') || {}).value || '';
    var lat = document.getElementById('latitude').value;
    var lng = document.getElementById('longitude').value;
    document.getElementById('report-message').style.display = 'none';
    document.getElementById('report-error').style.display = 'none';
    if (!lat || !lng) {
      document.getElementById('report-error').textContent = 'Unable to detect your location. Please enable location services and permission, then try again.';
      document.getElementById('report-error').style.display = 'block';
      return;
    }
    var mediaInput = document.getElementById('report-media');
    if (mediaInput && mediaInput.files && mediaInput.files.length > 0) {
      var imageCount = 0, videoCount = 0;
      for (var i = 0; i < mediaInput.files.length; i++) {
        var t = (mediaInput.files[i].type || '').toLowerCase();
        if (t.indexOf('image/') === 0) imageCount++;
        else if (t.indexOf('video/') === 0) videoCount++;
      }
      if (imageCount > 4) {
        document.getElementById('report-error').textContent = 'You can upload up to 4 images only.';
        document.getElementById('report-error').style.display = 'block';
        return;
      }
      if (videoCount > 1) {
        document.getElementById('report-error').textContent = 'You can upload up to 1 video only.';
        document.getElementById('report-error').style.display = 'block';
        return;
      }
    }
    setReportSubmittingState(true);
    var formData = buildReportFormData(isDraft, editingId || null);
    var url = editingId ? (base + '/api/reports/update-draft') : (base + '/api/reports');
    fetch(url, { method: 'POST', body: formData }).then(function(r) { return r.json(); }).then(function(d) {
      if (d.success) {
        reportMediaPreviewUrls.forEach(function(url) { URL.revokeObjectURL(url); });
        reportMediaPreviewUrls = [];
        if (previewContainer) previewContainer.innerHTML = '';
        markReportFormForReset();
        resetReportForm(true);
        document.getElementById('editing-report-id').value = '';
        document.getElementById('report-message').textContent = isDraft ? 'Draft saved.' : 'Report submitted. You can track its status in My reports.';
        document.getElementById('report-message').style.display = 'block';
        setTimeout(function() { window.location.reload(); }, 1200);
      } else {
        document.getElementById('report-error').textContent = d.error || 'Failed.';
        document.getElementById('report-error').style.display = 'block';
        setReportSubmittingState(false);
      }
    }).catch(function() {
      document.getElementById('report-error').textContent = 'Network error.';
      document.getElementById('report-error').style.display = 'block';
      setReportSubmittingState(false);
    });
  }

  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (isSubmittingReport) return;
      submitReport(false);
    });
  }
  var btnSaveDraft = document.getElementById('btn-save-draft');
  if (btnSaveDraft) {
    btnSaveDraft.addEventListener('click', function() {
      if (isSubmittingReport) return;
      submitReport(true);
    });
  }

  // Sidebar "My reports" click: show reports table as main content (hide form)
  function initSidebarMyReports() {
    var sidebarMyReports = document.getElementById('sidebar-my-reports');
    var myReportsContent = document.getElementById('my-reports-content');
    var formView = document.getElementById('dashboard-form-view');
    if (sidebarMyReports && myReportsContent && formView) {
      sidebarMyReports.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        formView.style.display = 'none';
        myReportsContent.style.display = 'block';
        window.scrollTo(0, 0);
      });
    }
    // "Submit new report" link: show form again, hide reports table
    var backToForm = document.getElementById('back-to-form');
    if (backToForm && formView && myReportsContent) {
      backToForm.addEventListener('click', function(e) {
        e.preventDefault();
        myReportsContent.style.display = 'none';
        formView.style.display = 'flex';
        document.getElementById('editing-report-id').value = '';
      });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarMyReports);
  } else {
    initSidebarMyReports();
  }

  // Edit draft: load report into form and switch to form view
  document.querySelectorAll('.btn-edit-draft').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var reportId = this.getAttribute('data-report-id');
      if (!reportId) return;
      fetch(base + '/api/reports/' + reportId).then(function(r) { return r.json(); }).then(function(d) {
        if (!d.success || !d.report) return;
        var r = d.report;
        document.getElementById('report-title').value = r.title || '';
        document.getElementById('incident-type').value = r.incident_type || '';
        document.getElementById('incident-severity').value = r.severity || '';
        document.getElementById('report-description').value = r.description || '';
        document.getElementById('latitude').value = r.latitude != null ? r.latitude : '';
        document.getElementById('longitude').value = r.longitude != null ? r.longitude : '';
        document.getElementById('incident-address').value = r.address || '';
        document.getElementById('editing-report-id').value = reportId;
        var mapCoords = document.getElementById('map-coords');
        if (mapCoords) mapCoords.textContent = (r.latitude != null && r.longitude != null) ? 'Lat: ' + r.latitude + ', Long: ' + r.longitude : '';
        if (typeof window.__nir360SetDraftLocation === 'function' && r.latitude != null && r.longitude != null) {
          window.__nir360SetDraftLocation(parseFloat(r.latitude), parseFloat(r.longitude), r.address || '');
        }
        myReportsContent.style.display = 'none';
        formView.style.display = 'flex';
      });
    });
  });

  // Load notifications (submission confirmations, etc.)
  var notifEl = document.getElementById('sidebar-notifications');
  if (notifEl) {
    fetch(base + '/api/notifications').then(function(r) { return r.json(); }).then(function(d) {
      if (d.success && Array.isArray(d.notifications)) {
        var unread = d.notifications.filter(function(n) { return !n.read_at; });
        if (unread.length === 0 && d.notifications.length === 0) {
          notifEl.innerHTML = 'No notifications yet.';
        } else if (unread.length === 0) {
          notifEl.innerHTML = 'No new notifications.';
        } else {
          notifEl.innerHTML = '<strong>' + unread.length + ' new</strong><br>' + unread.slice(0, 3).map(function(n) {
            return (n.title || '').replace(/</g, '&lt;') + (n.created_at ? ' <span style="color:var(--muted); font-size:0.8rem;">' + n.created_at + '</span>' : '');
          }).join('<br>');
        }
      } else {
        notifEl.textContent = 'Unable to load.';
      }
    }).catch(function() { notifEl.textContent = 'Unable to load.'; });
  }

  // Load announcements from admin into sidebar (users can view)
  var annEl = document.getElementById('sidebar-announcements');
  if (annEl) {
    annEl.textContent = 'Loading…';
    fetch(base + '/api/announcements')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.success && Array.isArray(d.announcements)) {
          if (d.announcements.length === 0) {
            annEl.textContent = 'No announcements yet.';
          } else {
            var html = '';
            d.announcements.forEach(function(a) {
              html += '<div style="border:1px solid #e5e7eb; border-radius:6px; padding:0.5rem 0.6rem; margin-bottom:0.4rem; background:#fafafa;">';
              html += '<strong style="font-size:0.85rem;">' + (a.title || '').replace(/</g, '&lt;') + '</strong>';
              html += '<span style="color:var(--muted); font-size:0.75rem;"> ' + (a.created_at || '').replace(/</g, '&lt;') + '</span>';
              html += '<p style="margin:0.25rem 0 0; font-size:0.8rem; white-space:pre-wrap; line-height:1.3;">' + (a.body || '').replace(/</g, '&lt;').replace(/\n/g, '<br>') + '</p>';
              html += '</div>';
            });
            annEl.innerHTML = html;
          }
        } else {
          annEl.textContent = 'Unable to load announcements.';
        }
      }).catch(function() {
        annEl.textContent = 'Unable to load announcements.';
      });
  }

  var logoutLink = document.querySelector('.header-logout');
  if (logoutLink) {
    logoutLink.addEventListener('click', function() {
      markReportFormForReset();
      resetReportForm(false);
    });
  }

  function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  var homeTrackIntervalId = null;
  var mapMode = 'location';
  var trackingIncidentMarker = null;
  var trackingResponderMarker = null;
  var trackingRouteLayer = null;
  var trackingReportId = null;
  function getResponderIcon() {
    return typeof L !== 'undefined' && L.divIcon ? L.divIcon({ className: 'responder-marker', html: '<div style="background:#2563eb; width:14px; height:14px; border-radius:50%; border:2px solid #fff; box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>', iconSize: [14, 14], iconAnchor: [7, 7] }) : null;
  }

  function switchToLocationMode() {
    if (homeTrackIntervalId) { clearInterval(homeTrackIntervalId); homeTrackIntervalId = null; }
    mapMode = 'location';
    if (map) {
      if (trackingRouteLayer) { try { map.removeLayer(trackingRouteLayer); } catch (e) {} trackingRouteLayer = null; }
      if (trackingResponderMarker) { try { map.removeLayer(trackingResponderMarker); } catch (e) {} trackingResponderMarker = null; }
      if (trackingIncidentMarker) { try { map.removeLayer(trackingIncidentMarker); } catch (e) {} trackingIncidentMarker = null; }
    }
    trackingReportId = null;
    var bar = document.getElementById('user-map-tracking-bar');
    var label = document.getElementById('user-map-location-label');
    if (bar) bar.style.display = 'none';
    if (label) label.style.display = '';
    if (map && (typeof savedLatitude === 'number' && savedLatitude !== null) && (typeof savedLongitude === 'number' && savedLongitude !== null)) {
      map.setView([savedLatitude, savedLongitude], 15);
      updateSelectedLocation(savedLatitude, savedLongitude, savedLocationAddress || '');
    } else if (map && lastUserLatLng && lastUserLatLng.length === 2) {
      map.setView(lastUserLatLng, 15);
      updateSelectedLocation(lastUserLatLng[0], lastUserLatLng[1], '');
    }
  }

  function updateSingleMapTracking(tracking) {
    if (!map || typeof L === 'undefined') return;
    mapMode = 'tracking';
    trackingReportId = tracking.report_id || null;
    var bar = document.getElementById('user-map-tracking-bar');
    var metaEl = document.getElementById('user-map-tracking-meta');
    var etaEl = document.getElementById('user-map-tracking-eta');
    var label = document.getElementById('user-map-location-label');
    if (bar) bar.style.display = 'block';
    if (label) label.style.display = 'none';
    if (metaEl) metaEl.textContent = trackingReportId ? ('Report #' + trackingReportId + ' · Updates every 10s until resolved.') : 'Updates every 10s until resolved.';
    if (etaEl) { etaEl.textContent = ''; etaEl.style.display = 'none'; }

    var incLat = typeof tracking.incident_lat === 'number' ? tracking.incident_lat : null;
    var incLng = typeof tracking.incident_lng === 'number' ? tracking.incident_lng : null;
    var respLat = tracking.responder_lat;
    var respLng = tracking.responder_lng;

    if (marker) { try { map.removeLayer(marker); } catch (e) {} marker = null; }
    clearSuggestion();
    clearRoute();

    if (typeof incLat === 'number' && typeof incLng === 'number') {
      if (trackingIncidentMarker) map.removeLayer(trackingIncidentMarker);
      trackingIncidentMarker = L.marker([incLat, incLng]).addTo(map).bindPopup('Incident location');
    }
    if (typeof respLat === 'number' && typeof respLng === 'number') {
      if (trackingResponderMarker) map.removeLayer(trackingResponderMarker);
      var respIcon = getResponderIcon();
      trackingResponderMarker = L.marker([respLat, respLng], respIcon ? { icon: respIcon } : {}).addTo(map).bindPopup('Responder');
      var bounds = [];
      if (trackingIncidentMarker) bounds.push([incLat, incLng]);
      bounds.push([respLat, respLng]);
      if (bounds.length >= 1) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
      var routeUrl = 'https://router.project-osrm.org/route/v1/driving/' + respLng + ',' + respLat + ';' + incLng + ',' + incLat + '?overview=full&geometries=geojson';
      var currentReportId = tracking.report_id;
      fetch(routeUrl).then(function(r) { return r.json(); }).then(function(d) {
        if (trackingReportId !== currentReportId || !map) return;
        if (d.code === 'Ok' && d.routes && d.routes[0]) {
          var route = d.routes[0];
          if (route.duration != null && etaEl) {
            etaEl.textContent = 'Estimated time to incident: ~' + Math.round(route.duration / 60) + ' min';
            etaEl.style.display = 'block';
          }
          if (route.geometry && route.geometry.coordinates) {
            if (trackingRouteLayer) map.removeLayer(trackingRouteLayer);
            var latLngs = route.geometry.coordinates.map(function(c) { return [c[1], c[0]]; });
            trackingRouteLayer = L.polyline(latLngs, { color: '#2563eb', weight: 4, opacity: 0.8 }).addTo(map);
          }
        }
      }).catch(function() {});
    } else {
      if (typeof incLat === 'number' && typeof incLng === 'number') map.setView([incLat, incLng], 14);
    }
  }

  function startHomeTracking() {
    var apiUrl = base ? (base + '/api/reports/active-track') : '/api/reports/active-track';
    function poll() {
      fetch(apiUrl, { credentials: 'same-origin' }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success && d.tracking && (d.tracking.status === 'dispatched' || d.tracking.status === 'awaiting_closure')) {
          updateSingleMapTracking(d.tracking);
        } else {
          switchToLocationMode();
        }
      }).catch(function() {});
    }
    poll();
    homeTrackIntervalId = setInterval(poll, 10000);
  }

  // My Reports DataTable init (same style as Admin Manage Users)
  var myReportsDataTable = null;
  function initMyReportsDataTable() {
    var tableEl = document.getElementById('my-reports-table');
    if (!tableEl) return;
    var DT = window.DataTable || window.dataTable;
    if (typeof DT === 'undefined') {
      setTimeout(initMyReportsDataTable, 100);
      return;
    }
    try {
      myReportsDataTable = new DT(tableEl, {
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        order: [[5, 'desc']],
        columnDefs: [
          { orderable: false, targets: 6 },
          { width: '40px', targets: 0 },
          { width: '80px', targets: 6 }
        ],
        responsive: true,
        language: {
          lengthMenu: 'Show _MENU_ entries',
          search: 'Search:',
          info: 'Showing _START_ to _END_ of _TOTAL_ reports',
          infoEmpty: 'No reports found',
          infoFiltered: '(filtered from _MAX_ total)',
          zeroRecords: 'No matching reports found',
          paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        }
      });
    } catch (err) {
      console.error('My Reports DataTable init error:', err);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMyReportsDataTable);
  } else {
    initMyReportsDataTable();
  }

  // Report list: open detail (delegated so it works with DataTable pagination)
  function openReportDetailFromRow(row) {
    if (!row || !row.getAttribute('data-report-id')) return;
    var detailBox = document.getElementById('report-detail');
    if (!detailBox) return;
    var reportId = parseInt(row.getAttribute('data-report-id'), 10);
    var title = row.getAttribute('data-title') || '';
    var status = row.getAttribute('data-status') || '';
    var severity = row.getAttribute('data-severity') || '';
    var incidentType = row.getAttribute('data-incident-type') || '';
    var createdAt = row.getAttribute('data-created-at') || '';
    var description = row.getAttribute('data-description') || '';
    var mediaJson = row.getAttribute('data-media') || '[]';
    var media = [];
    try { media = JSON.parse(mediaJson); } catch (e) {}
    var isTrackable = (status === 'dispatched' || status === 'awaiting_closure');
    var html = '<h2 style="font-size:1.05rem; margin-bottom:0.5rem;">Report #' + reportId + '</h2>' +
      '<p style="margin:0.25rem 0;"><strong>Title:</strong> ' + escapeHtml(title) + '</p>' +
      '<p style="margin:0.25rem 0;"><strong>Incident Type:</strong> ' + escapeHtml(incidentType ? incidentType.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); }) : '—') + '</p>' +
      '<p style="margin:0.25rem 0;"><strong>Status:</strong> ' + escapeHtml(status ? status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ') : '—') + '</p>' +
      '<p style="margin:0.25rem 0;"><strong>Severity:</strong> ' + escapeHtml(severity ? severity.charAt(0).toUpperCase() + severity.slice(1) : '—') + '</p>' +
      '<p style="margin:0.25rem 0;"><strong>Submitted:</strong> ' + escapeHtml(createdAt) + '</p>' +
      '<p style="margin:0.75rem 0 0; white-space:pre-wrap;"><strong>Description:</strong><br>' + escapeHtml(description) + '</p>';
    if (media.length > 0) {
      html += '<div style="margin-top:1rem;"><strong>Photos / Videos</strong><div style="display:flex; flex-wrap:wrap; gap:0.75rem; margin-top:0.5rem;">';
      media.forEach(function(m) {
        var url = m.url || '';
        if (m.media_type === 'image') {
          html += '<img src="' + escapeHtml(url) + '" alt="Report photo" style="max-width:200px; max-height:200px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb;">';
        } else if (m.media_type === 'video') {
          html += '<video src="' + escapeHtml(url) + '" controls style="max-width:280px; max-height:200px; border-radius:6px; border:1px solid #e5e7eb;"></video>';
        }
      });
      html += '</div></div>';
    }
    if (isTrackable) {
      html += '<div style="margin-top:1.25rem; padding-top:1rem; border-top:1px solid #e5e7eb;">' +
        '<p style="font-size:0.9rem; color:#1e40af; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:0.6rem 0.75rem;">' +
        'Live tracking is shown on the <strong>main map</strong> (Submit incident report view). Return to the form view to see the responder’s location, route, and ETA updating every 10s until the report is resolved.</p>' +
        '</div>';
    }
    detailBox.style.display = 'block';
    detailBox.innerHTML = html;
  }

  var myReportsContent = document.getElementById('my-reports-content');
  if (myReportsContent) {
    myReportsContent.addEventListener('click', function(e) {
      var row = e.target.closest('tr[data-report-item]');
      var btnEdit = e.target.closest('.btn-edit-draft');
      var btnView = e.target.closest('.btn-view-report');
      if (btnEdit) {
        e.preventDefault();
        e.stopPropagation();
        var reportId = btnEdit.getAttribute('data-report-id');
        if (!reportId) return;
        fetch(base + '/api/reports/' + reportId).then(function(r) { return r.json(); }).then(function(d) {
          if (!d.success || !d.report) return;
          var r = d.report;
          document.getElementById('report-title').value = r.title || '';
          document.getElementById('incident-type').value = r.incident_type || '';
          document.getElementById('incident-severity').value = r.severity || '';
          document.getElementById('report-description').value = r.description || '';
          document.getElementById('latitude').value = r.latitude != null ? r.latitude : '';
          document.getElementById('longitude').value = r.longitude != null ? r.longitude : '';
          document.getElementById('incident-address').value = r.address || '';
          document.getElementById('editing-report-id').value = reportId;
          var mapCoords = document.getElementById('map-coords');
          if (mapCoords) mapCoords.textContent = (r.latitude != null && r.longitude != null) ? 'Lat: ' + r.latitude + ', Long: ' + r.longitude : '';
          if (typeof window.__nir360SetDraftLocation === 'function' && r.latitude != null && r.longitude != null) {
            window.__nir360SetDraftLocation(parseFloat(r.latitude), parseFloat(r.longitude), r.address || '');
          }
          myReportsContent.style.display = 'none';
          formView.style.display = 'flex';
        });
        return;
      }
      if (btnView && row) {
        e.preventDefault();
        e.stopPropagation();
        openReportDetailFromRow(row);
        return;
      }
      if (row && !e.target.closest('.my-reports-actions-cell')) {
        openReportDetailFromRow(row);
      }
    });
  }

    // Initialize map after container has non-zero dimensions (fixes blank/grey map)
  var map;
  var mapEl = document.getElementById('map');
  var initialLat = (typeof savedLatitude === 'number') ? savedLatitude : 10.5333;
  var initialLng = (typeof savedLongitude === 'number') ? savedLongitude : 122.8333;
  var initialZoom = (typeof savedLatitude === 'number' && typeof savedLongitude === 'number') ? 15 : 13;

  function initMap() {
    if (!mapEl || typeof L === 'undefined') return;
    if (map) return;
    var w = mapEl.offsetWidth;
    var h = mapEl.offsetHeight;
    if (w < 100 || h < 100) {
      return;
    }
    map = L.map(mapEl, { preferCanvas: false }).setView([initialLat, initialLng], initialZoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    onMapReady();
    setTimeout(function() {
      if (map && map.invalidateSize) map.invalidateSize();
    }, 0);
    setTimeout(function() {
      if (map && map.invalidateSize) map.invalidateSize();
    }, 250);
  }

  function tryInitMap() {
    if (!mapEl) return;
    initMap();
    if (!map) {
      var attempts = 0;
      var t = setInterval(function() {
        attempts++;
        initMap();
        if (map || attempts >= 40) clearInterval(t);
      }, 100);
    }
  }

  function onMapReady() {
    if (!map) return;
    if (resetReportDraftOnLoad) {
      if (typeof savedLatitude === 'number' && typeof savedLongitude === 'number') {
        map.setView([savedLatitude, savedLongitude], 15);
      }
      clearSelectedLocation();
    } else if (typeof savedLatitude === 'number' && typeof savedLongitude === 'number') {
      lastUserLatLng = [savedLatitude, savedLongitude];
      updateSelectedLocation(savedLatitude, savedLongitude, savedLocationAddress || '');
      if (!savedLocationAddress) {
        reverseGeocodeAndFill(savedLatitude, savedLongitude, false);
      }
    }
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(function(position) {
        var lat = position.coords.latitude;
        var lng = position.coords.longitude;
        lastUserLatLng = [lat, lng];
        var accuracyMeters = typeof position.coords.accuracy === 'number' ? position.coords.accuracy : null;
        map.setView([lat, lng], 15);
        updateSelectedLocation(lat, lng, '');
        reverseGeocodeAndFill(lat, lng, true);
        if (accuracyMeters !== null && accuracyMeters > 500) {
          showLocationNotice('Location may be inaccurate (±' + Math.round(accuracyMeters) + ' m). Please turn on location services, enable high accuracy mode, and allow location permission.');
        }
      }, function() {
        showLocationNotice('Please turn on your location services and allow location permission so we can auto-detect your current location.');
        if (!resetReportDraftOnLoad && typeof savedLatitude === 'number' && typeof savedLongitude === 'number') {
          lastUserLatLng = [savedLatitude, savedLongitude];
          map.setView([savedLatitude, savedLongitude], 15);
          updateSelectedLocation(savedLatitude, savedLongitude, savedLocationAddress || '');
          if (!savedLocationAddress) {
            reverseGeocodeAndFill(savedLatitude, savedLongitude, false);
          }
        }
      }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
    } else if (!(typeof savedLatitude === 'number' && typeof savedLongitude === 'number')) {
      showLocationNotice('Geolocation is not supported by this browser. Please use a supported browser and enable location services.');
    }
    startHomeTracking();
    if (resetReportDraftOnLoad) {
      resetReportForm(false);
    }
  }

  if (document.readyState === 'complete') {
    setTimeout(tryInitMap, 150);
  } else {
    window.addEventListener('load', function() { setTimeout(tryInitMap, 150); });
  }
  window.addEventListener('resize', function() {
    if (map && map.invalidateSize) map.invalidateSize();
  });

var marker;
function showLocationNotice(message) {
  var noticeEl = document.getElementById('location-notice');
  if (!noticeEl) return;
  noticeEl.textContent = message;
  noticeEl.style.display = 'block';
}

function hideLocationNotice() {
  var noticeEl = document.getElementById('location-notice');
  if (!noticeEl) return;
  noticeEl.textContent = '';
  noticeEl.style.display = 'none';
}

function clearSelectedLocation() {
  if (marker) {
    map.removeLayer(marker);
    marker = null;
  }

  var latitudeInput = document.getElementById('latitude');
  var longitudeInput = document.getElementById('longitude');
  var addressInput = document.getElementById('incident-address');
  var coordsEl = document.getElementById('map-coords');

  if (latitudeInput) latitudeInput.value = '';
  if (longitudeInput) longitudeInput.value = '';
  if (addressInput) addressInput.value = '';
  if (coordsEl) {
    coordsEl.textContent = 'We automatically detect your location. Latitude and longitude will appear here.';
  }
}

  window.__nir360SetDraftLocation = function(lat, lng, addressText) {
    if (typeof map === 'undefined' || typeof lat !== 'number' || typeof lng !== 'number') return;
    map.setView([lat, lng], 15);
    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lng]).addTo(map);
    var latitudeInput = document.getElementById('latitude');
    var longitudeInput = document.getElementById('longitude');
    var addressInput = document.getElementById('incident-address');
    var coordsEl = document.getElementById('map-coords');
    if (latitudeInput) latitudeInput.value = lat;
    if (longitudeInput) longitudeInput.value = lng;
    if (addressInput) addressInput.value = addressText || '';
    if (coordsEl) coordsEl.textContent = 'Lat: ' + lat + ', Long: ' + lng;
  };

  function updateSelectedLocation(lat, lng, addressText) {
    if (marker) {
      map.removeLayer(marker);
    }
    marker = L.marker([lat, lng]).addTo(map);

    document.getElementById("latitude").value = lat;
    document.getElementById("longitude").value = lng;

    var coordsEl = document.getElementById("map-coords");
    if (coordsEl) {
      coordsEl.textContent = "Latitude: " + lat.toFixed(6) + ", Longitude: " + lng.toFixed(6);
    }

    var addressInput = document.getElementById("incident-address");
    if (addressInput) {
      addressInput.value = addressText || '';
    }
    hideLocationNotice();
  }

  function persistUserLocation(lat, lng, addressText) {
    if (!csrfToken || !base) {
      return;
    }
    fetch(base + '/api/profile/update-location', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        latitude: lat,
        longitude: lng,
        location_address: addressText || '',
        csrf_token: csrfToken
      })
    }).catch(function() {});
  }

  function reverseGeocodeAndFill(lat, lng, shouldPersist) {
    var addressInput = document.getElementById("incident-address");
    if (!addressInput) return;
    addressInput.value = "Loading address…";

    var url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat="
      + encodeURIComponent(lat) + "&lon=" + encodeURIComponent(lng);

    fetch(url, {
      headers: {
        "Accept": "application/json"
      }
    }).then(function(response) {
      return response.json();
    }).then(function(data) {
      if (data && data.display_name) {
        addressInput.value = data.display_name;
        if (shouldPersist) {
          persistUserLocation(lat, lng, data.display_name);
        }
      } else {
        addressInput.value = "Address not found for this location.";
        if (shouldPersist) {
          persistUserLocation(lat, lng, '');
        }
      }
    }).catch(function() {
      addressInput.value = "Unable to fetch address.";
      if (shouldPersist) {
        persistUserLocation(lat, lng, '');
      }
    });
  }

// ── Nearest facility suggestion ─────────────────────────────────────────────

var suggestionMarker = null;
var suggestionNotice = document.getElementById('suggestion-notice');

// Custom blue-ish icon to distinguish suggestion marker from incident marker
var suggestionIcon = L.divIcon({
  className: '',
  html: '<div style="width:28px;height:28px;border-radius:50%;background:#2563eb;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;">' +
        '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="#fff" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>' +
        '</div>',
  iconSize: [28, 28],
  iconAnchor: [14, 14],
  popupAnchor: [0, -16]
});

// Maps incident type → Overpass tag filters (tries them in order, first non-empty wins)
var facilityQueries = {
  'fire':               [['amenity', 'fire_station']],
  'flood':              [['emergency', 'water_rescue'], ['amenity', 'police'], ['amenity', 'hospital']],
  'motorcycle accident':[['amenity', 'hospital'], ['amenity', 'clinic']],
  'earthquake':         [['amenity', 'hospital'], ['amenity', 'fire_station']],
  'storm':              [['emergency', 'water_rescue'], ['amenity', 'fire_station'], ['amenity', 'hospital']],
  'tsunami':            [['emergency', 'water_rescue'], ['amenity', 'fire_station'], ['amenity', 'hospital']],
  'volcano':            [['amenity', 'fire_station'], ['amenity', 'hospital']],
  'crime':              [['amenity', 'police']]
};

var facilityLabels = {
  'amenity=fire_station':    'Fire Station',
  'emergency=water_rescue':  'Water Rescue Station',
  'amenity=hospital':        'Hospital',
  'amenity=clinic':          'Clinic',
  'amenity=police':          'Police Station'
};

function haversineKm(lat1, lng1, lat2, lng2) {
  var R = 6371;
  var dLat = (lat2 - lat1) * Math.PI / 180;
  var dLng = (lng2 - lng1) * Math.PI / 180;
  var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
          Math.cos(lat1 * Math.PI/180) * Math.cos(lat2 * Math.PI/180) *
          Math.sin(dLng/2) * Math.sin(dLng/2);
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function clearSuggestion() {
  if (suggestionMarker) {
    map.removeLayer(suggestionMarker);
    suggestionMarker = null;
  }
  clearRoute();
  if (suggestionNotice) {
    suggestionNotice.style.display = 'none';
    suggestionNotice.innerHTML = '';
  }
}

function resetReportForm(keepSuccessMessage) {
  var reportTitle = document.getElementById('report-title');
  var incidentType = document.getElementById('incident-type');
  var incidentSeverity = document.getElementById('incident-severity');
  var description = document.getElementById('report-description');
  var mediaInput = document.getElementById('report-media');
  var reportMessage = document.getElementById('report-message');
  var reportError = document.getElementById('report-error');
  var editingIdEl = document.getElementById('editing-report-id');

  if (reportTitle) reportTitle.value = '';
  if (incidentType) incidentType.value = '';
  if (incidentSeverity) incidentSeverity.value = '';
  if (description) description.value = '';
  if (mediaInput) mediaInput.value = '';
  if (editingIdEl) editingIdEl.value = '';
  if (reportMessage && !keepSuccessMessage) {
    reportMessage.style.display = 'none';
    reportMessage.textContent = '';
  }
  if (reportError) {
    reportError.style.display = 'none';
    reportError.textContent = '';
  }
  clearSelectedLocation();
  clearSuggestion();
  hideLocationNotice();
  setReportSubmittingState(false);
}

function showSuggestionNotice(msg, isLoading) {
  if (!suggestionNotice) return;
  suggestionNotice.textContent = msg;
  suggestionNotice.style.display = 'block';
  suggestionNotice.style.background = isLoading ? '#eff6ff' : '#ecfdf5';
  suggestionNotice.style.borderColor = isLoading ? '#bfdbfe' : '#6ee7b7';
  suggestionNotice.style.color = isLoading ? '#1e40af' : '#065f46';
}

function placeSuggestionMarker(lat, lng, name, typeLabel, distKm) {
  if (suggestionMarker) {
    map.removeLayer(suggestionMarker);
    suggestionMarker = null;
  }
  var distText = distKm < 1
    ? Math.round(distKm * 1000) + ' m away'
    : distKm.toFixed(1) + ' km away';

  var facilityLat = lat, facilityLng = lng;

  suggestionMarker = L.marker([lat, lng], { icon: suggestionIcon })
    .addTo(map)
    .bindPopup(
      '<strong>Nearest ' + typeLabel + '</strong><br>' +
      (name ? name + '<br>' : '') +
      '<em>' + distText + '</em>',
      { maxWidth: 220 }
    ).openPopup();

  // Draw driving route from incident pin to facility via OSRM
  var incLat = parseFloat(document.getElementById('latitude').value);
  var incLng = parseFloat(document.getElementById('longitude').value);
  if (!isNaN(incLat) && !isNaN(incLng)) {
    drawRoute(incLat, incLng, facilityLat, facilityLng);
  }

  // Build Google Maps directions URL
  var gmUrl = 'https://www.google.com/maps/dir/?api=1'
    + '&origin=' + encodeURIComponent(incLat + ',' + incLng)
    + '&destination=' + encodeURIComponent(facilityLat + ',' + facilityLng)
    + '&travelmode=driving';

  if (suggestionNotice) {
    suggestionNotice.innerHTML =
      '📍 Nearest ' + typeLabel + ': <strong>' + (name || 'Unnamed') + '</strong> (' + distText + ')' +
      '&nbsp;&nbsp;<a href="' + gmUrl + '" target="_blank" rel="noopener" ' +
      'style="display:inline-block;margin-left:0.5rem;padding:0.25rem 0.75rem;background:#2563eb;color:#fff;border-radius:4px;font-size:0.8rem;text-decoration:none;font-weight:600;">🗺 Get Directions</a>';
    suggestionNotice.style.display = 'block';
    suggestionNotice.style.background = '#ecfdf5';
    suggestionNotice.style.borderColor = '#6ee7b7';
    suggestionNotice.style.color = '#065f46';
  }
}

var routePolyline = null;

// Decode Google-encoded polyline (format used by OSRM)
function decodePolyline(encoded) {
  var points = [], index = 0, len = encoded.length, lat = 0, lng = 0;
  while (index < len) {
    var b, shift = 0, result = 0;
    do { b = encoded.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5; } while (b >= 0x20);
    lat += (result & 1) ? ~(result >> 1) : (result >> 1);
    shift = 0; result = 0;
    do { b = encoded.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5; } while (b >= 0x20);
    lng += (result & 1) ? ~(result >> 1) : (result >> 1);
    points.push([lat / 1e5, lng / 1e5]);
  }
  return points;
}

function clearRoute() {
  if (routePolyline) { map.removeLayer(routePolyline); routePolyline = null; }
}

function drawRoute(fromLat, fromLng, toLat, toLng) {
  clearRoute();
  var url = 'https://router.project-osrm.org/route/v1/driving/'
    + fromLng + ',' + fromLat + ';'
    + toLng   + ',' + toLat
    + '?overview=full&geometries=polyline';
  fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.routes || !data.routes[0]) return;
      var coords = decodePolyline(data.routes[0].geometry);
      routePolyline = L.polyline(coords, {
        color: '#2563eb',
        weight: 5,
        opacity: 0.8,
        dashArray: '10 6'
      }).addTo(map);
      map.fitBounds(routePolyline.getBounds(), { padding: [50, 50] });
    }).catch(function() {});
}

function queryOverpassForFacility(lat, lng, tagPairs, radius, callback) {
  // Build a union of node/way/relation queries for each tag pair
  var parts = tagPairs.map(function(pair) {
    var filter = '["' + pair[0] + '"="' + pair[1] + '"]';
    return 'node' + filter + '(around:' + radius + ',' + lat + ',' + lng + ');' +
           'way'  + filter + '(around:' + radius + ',' + lat + ',' + lng + ');';
  }).join('\n');

  var overpassQuery = '[out:json][timeout:15];\n(\n' + parts + ');\nout center;';
  var url = 'https://overpass-api.de/api/interpreter?data=' + encodeURIComponent(overpassQuery);

  fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(data) { callback(null, data); })
    .catch(function(err) { callback(err, null); });
}

function suggestNearestFacility(lat, lng, incidentType) {
  var queries = facilityQueries[incidentType.toLowerCase()];
  if (!queries) {
    clearSuggestion();
    return;
  }

  clearSuggestion();
  showSuggestionNotice('🔍 Searching for nearest facility…', true);

  var searchRadius = 10000; // 10 km
  var tried = 0;

  function tryNext(index) {
    if (index >= queries.length) {
      // All tried, nothing found — expand to 30 km fallback
      if (searchRadius < 30000) {
        searchRadius = 30000;
        tried = 0;
        tryNext(0);
      } else {
        showSuggestionNotice('⚠️ No nearby facility found within 30 km.', false);
        if (suggestionNotice) {
          suggestionNotice.style.background = '#fff7ed';
          suggestionNotice.style.borderColor = '#fed7aa';
          suggestionNotice.style.color = '#92400e';
        }
      }
      return;
    }

    var pair = queries[index];
    var tagKey = pair[0] + '=' + pair[1];
    var typeLabel = facilityLabels[tagKey] || 'Facility';

    queryOverpassForFacility(lat, lng, [pair], searchRadius, function(err, data) {
      if (err || !data || !data.elements || data.elements.length === 0) {
        tryNext(index + 1);
        return;
      }

      // Find the closest element
      var best = null;
      var bestDist = Infinity;
      data.elements.forEach(function(el) {
        var elLat = el.lat !== undefined ? el.lat : (el.center ? el.center.lat : null);
        var elLng = el.lon !== undefined ? el.lon : (el.center ? el.center.lon : null);
        if (elLat === null || elLng === null) return;
        var d = haversineKm(lat, lng, elLat, elLng);
        if (d < bestDist) {
          bestDist = d;
          best = { lat: elLat, lng: elLng, name: (el.tags && (el.tags.name || el.tags['name:en'])) || '' };
        }
      });

      if (best) {
        placeSuggestionMarker(best.lat, best.lng, best.name, typeLabel, bestDist);
      } else {
        tryNext(index + 1);
      }
    });
  }

  tryNext(0);
}

// Also trigger suggestion when incident type dropdown changes (uses current pin location)
var incidentTypeSelect = document.getElementById('incident-type');
if (incidentTypeSelect) {
  incidentTypeSelect.addEventListener('change', function() {
    var latVal = parseFloat(document.getElementById('latitude').value);
    var lngVal = parseFloat(document.getElementById('longitude').value);
    if (!isNaN(latVal) && !isNaN(lngVal)) {
      if (this.value) {
        suggestNearestFacility(latVal, lngVal, this.value);
      } else {
        clearSuggestion();
      }
    }
  });
  }
})();
</script>
<?php
$dashboardContent = ob_get_clean();
require $baseDir . '/templates/dashboard_layout.php';
