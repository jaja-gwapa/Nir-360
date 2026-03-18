<?php
$pageTitle = 'My Profile';
$photoRel = !empty($profile['profile_photo'])
    ? (rtrim($profilePhotoWebPath ?? '/uploads/profile', '/') . '/' . $profile['profile_photo'])
    : null;
$photoUrl = $photoRel ? (($webBase ?? '') . $photoRel) : null;
$webBase = $webBase ?? '';

$dashboardExtraHead = '
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>.profile-avatar { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; }</style>';

ob_start();
?>
    <div class="container py-2">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card shadow-sm">
            <div class="card-body p-4">
              <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-3 mb-4">
                <div class="position-relative">
                  <img id="profile-photo-img" class="profile-avatar" src="<?= $photoUrl ? htmlspecialchars($photoUrl) : ('https://ui-avatars.com/api/?size=120&name=' . urlencode($profile['full_name'] ?? 'User')) ?>" alt="Profile">
                </div>
                <div class="flex-grow-1 text-center text-md-start">
                  <h2 class="h4 mb-1"><?= htmlspecialchars($profile['full_name'] ?? '') ?></h2>
                  <p class="text-muted mb-1"><?= htmlspecialchars($profile['email'] ?? '') ?></p>
                </div>
              </div>

              <?php
              $locationDisplay = trim((string)($profile['address'] ?? ''));
              if ($locationDisplay === '' && !empty($profile['barangay'])) {
                $parts = array_filter([
                  $profile['street_address'] ?? '',
                  $profile['barangay'] ?? '',
                  $profile['city'] ?? 'Bago City',
                  $profile['province'] ?? 'Negros Occidental',
                ]);
                $locationDisplay = implode(', ', $parts);
              }
              if ($locationDisplay === '') {
                $locationDisplay = '—';
              }
              ?>
              <dl class="row mb-0">
                <dt class="col-sm-3">Address</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($profile['address'] ?? '—') ?></dd>
                <dt class="col-sm-3">Barangay</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($profile['barangay'] ?? '—') ?></dd>
                <dt class="col-sm-3">Location</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($locationDisplay) ?></dd>
              </dl>

              <div class="mt-3 d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-email">Update Email</button>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-password">Update Password</button>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-photo">Upload Photo</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Update Email -->
    <div class="modal fade" id="modal-email" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Update Email</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div id="email-error" class="alert alert-danger d-none" role="alert"></div>
            <label class="form-label">New email</label>
            <input type="email" class="form-control" id="email-input" required>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="email-save">Save</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Update Password -->
    <div class="modal fade" id="modal-password" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Update Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div id="password-error" class="alert alert-danger d-none" role="alert"></div>
            <div class="mb-2">
              <label class="form-label">Current password</label>
              <input type="password" class="form-control" id="current-password" required>
            </div>
            <div class="mb-2">
              <label class="form-label">New password (min 8 chars, uppercase, number)</label>
              <input type="password" class="form-control" id="new-password" required>
            </div>
            <div>
              <label class="form-label">Confirm new password</label>
              <input type="password" class="form-control" id="confirm-password" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="password-save">Save</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Upload Photo -->
    <div class="modal fade" id="modal-photo" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Upload Profile Photo</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div id="photo-error" class="alert alert-danger d-none" role="alert"></div>
            <p class="text-muted small">JPEG or PNG, max 2MB.</p>
            <input type="file" class="form-control" id="photo-input" accept="image/jpeg,image/png">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="photo-save">Upload</button>
          </div>
        </div>
      </div>
    </div>

<?php
$dashboardContent = ob_get_clean();

$dashboardExtraScripts = '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const webBase = ' . json_encode($webBase) . ';
  const csrfToken = ' . json_encode($csrfToken ?? '') . ';

  function showError(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.classList.remove("d-none");
  }
  function hideError(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add("d-none");
  }

  document.getElementById("email-save").addEventListener("click", function () {
    const email = document.getElementById("email-input").value.trim();
    hideError("email-error");
    if (!email) { showError("email-error", "Email is required."); return; }
    fetch(webBase + "/api/profile/update-email", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, csrf_token: csrfToken })
    }).then(r => r.json()).then(data => {
      if (data.success) {
        document.querySelector(".text-muted.mb-1").textContent = data.email;
        bootstrap.Modal.getInstance(document.getElementById("modal-email")).hide();
      } else { showError("email-error", data.error || "Update failed."); }
    }).catch(() => showError("email-error", "Request failed."));
  });

  document.getElementById("password-save").addEventListener("click", function () {
    const current = document.getElementById("current-password").value;
    const newPw = document.getElementById("new-password").value;
    const confirm = document.getElementById("confirm-password").value;
    hideError("password-error");
    if (newPw !== confirm) { showError("password-error", "New password and confirmation do not match."); return; }
    fetch(webBase + "/api/profile/update-password", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ current_password: current, new_password: newPw, csrf_token: csrfToken })
    }).then(r => r.json()).then(data => {
      if (data.success) {
        document.getElementById("current-password").value = "";
        document.getElementById("new-password").value = "";
        document.getElementById("confirm-password").value = "";
        bootstrap.Modal.getInstance(document.getElementById("modal-password")).hide();
      } else { showError("password-error", data.error || "Update failed."); }
    }).catch(() => showError("password-error", "Request failed."));
  });

  document.getElementById("photo-save").addEventListener("click", function () {
    const input = document.getElementById("photo-input");
    hideError("photo-error");
    if (!input.files || !input.files[0]) { showError("photo-error", "Please select a file."); return; }
    const fd = new FormData();
    fd.append("csrf_token", csrfToken);
    fd.append("photo", input.files[0]);
    const uploadUrl = (webBase ? webBase : "") + "/api/profile/upload-photo";
    fetch(uploadUrl, { method: "POST", body: fd })
      .then(function (r) {
        const ct = (r.headers.get("Content-Type") || "").toLowerCase();
        if (ct.indexOf("application/json") !== -1) {
          return r.json().then(function (data) { return { ok: r.ok, data: data }; });
        }
        return r.text().then(function (text) {
          return { ok: false, data: { success: false, error: "Server error. Please try again." } };
        });
      })
      .then(function (res) {
        var data = res.data;
        if (res.ok && data.success) {
          document.getElementById("profile-photo-img").src = (webBase ? webBase : "") + (data.profile_photo_url || ("/uploads/profile/" + (data.profile_photo || "")));
          input.value = "";
          bootstrap.Modal.getInstance(document.getElementById("modal-photo")).hide();
        } else {
          showError("photo-error", data.error || "Upload failed.");
        }
      })
      .catch(function (err) {
        showError("photo-error", "Network error. Check your connection and try again.");
      });
  });
})();
</script>';

$baseDir = dirname(__DIR__);
require $baseDir . '/templates/dashboard_layout.php';
