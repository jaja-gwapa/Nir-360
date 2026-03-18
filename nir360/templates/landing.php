<?php if (!empty($passwordResetSuccess)): ?>
  <div class="container" style="margin-top: 1rem;">
    <div class="success-box" role="alert">Your password has been reset. You can now log in with your new password.</div>
  </div>
<?php endif; ?>
<section class="hero">
  <div class="container">
    <h1 class="hero-title">NIR360</h1>
    <p class="hero-desc">Safety and incident response with identity verification. Register, verify your phone and ID, and get a verified badge. Join as a civilian or responder.</p>
    <button type="button" class="btn btn-primary btn-lg" data-modal-open="create-account">Get Started</button>
  </div>
</section>

<!-- Register Modal (full registration form: basic info + role + ID upload) -->
<div id="modal-create-account" class="modal" aria-hidden="true" role="dialog" aria-labelledby="modal-create-account-title">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-box modal-box--scroll">
    <div class="modal-header">
      <h2 id="modal-create-account-title">Register</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <div id="create-account-error" class="error-box" style="display:none;"></div>
      <div id="create-account-success" class="success-box" style="display:none;"></div>
      <form id="form-create-account" enctype="multipart/form-data" method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <h3 class="form-section">Basic Information</h3>
        <div class="form-group">
          <label for="reg-first-name">First Name *</label>
          <input type="text" id="reg-first-name" name="first_name" required placeholder="Janine Marie">
        </div>
        <div class="form-group">
          <label for="reg-middle-name">Middle Name</label>
          <input type="text" id="reg-middle-name" name="middle_name" placeholder="Aguirre">
        </div>
        <div class="form-group">
          <label for="reg-last-name">Last Name *</label>
          <input type="text" id="reg-last-name" name="last_name" required placeholder="Balboa">
        </div>
        <div class="form-group">
          <label for="reg-username">Username *</label>
          <input type="text" id="reg-username" name="username" required placeholder="johndoe" minlength="3" maxlength="50" autocomplete="username">
          <span class="hint">Unique. 3–50 characters.</span>
        </div>
        <div class="form-group">
          <label for="reg-email">Email *</label>
          <input type="email" id="reg-email" name="email" required placeholder="you@example.com">
          <span class="hint">Unique per account</span>
        </div>
        <div class="form-group">
          <label for="reg-mobile">Mobile Number *</label>
          <input type="tel" id="reg-mobile" name="mobile" required placeholder="09171234567" pattern="[0-9]{11}" inputmode="numeric" minlength="11" maxlength="11">
          <span class="hint">Numbers only, exactly 11 digits. Unique per account.</span>
        </div>
        <div class="form-group">
          <label for="reg-password">Password *</label>
          <input type="password" id="reg-password" name="password" required minlength="8" placeholder="••••••••">
          <span class="hint">Min 8 chars, 1 upper, 1 lower, 1 number, 1 special</span>
        </div>
        <div class="form-group">
          <label for="reg-confirm-password">Confirm Password *</label>
          <input type="password" id="reg-confirm-password" name="confirm_password" required placeholder="••••••••">
        </div>
        <div class="form-group">
          <label for="reg-birthdate">Birthdate *</label>
          <input type="date" id="reg-birthdate" name="birthdate" required max="<?= date('Y-m-d') ?>">
          <span class="hint">Select your date of birth. Used for ID verification. No future dates.</span>
        </div>
        <h3 class="form-section">Location (Bago City, Negros Occidental)</h3>
        <div class="form-group">
          <label>Province</label>
          <input type="text" id="reg-province" value="Negros Occidental" readonly class="input-readonly" aria-readonly="true">
          <input type="hidden" name="province" value="Negros Occidental">
        </div>
        <div class="form-group">
          <label>City</label>
          <input type="text" id="reg-city" value="Bago City" readonly class="input-readonly" aria-readonly="true">
          <input type="hidden" name="city" value="Bago City">
        </div>
        <?php $bagoBarangays = require dirname(__DIR__) . '/config/bago_city_barangays.php'; ?>
        <div class="form-group">
          <label for="reg-barangay">Barangay *</label>
          <select id="reg-barangay" name="barangay" required>
            <option value="">— Select Barangay —</option>
            <?php foreach ($bagoBarangays as $brgy): ?>
            <option value="<?= htmlspecialchars($brgy) ?>"><?= htmlspecialchars($brgy) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="reg-street-address">Street / Sitio *</label>
          <input type="text" id="reg-street-address" name="street_address" required placeholder="Street name or sitio">
        </div>
        <input type="hidden" name="address" id="reg-address" value="">
        <h3 class="form-section">Identity Verification (Government ID)</h3>
        <p class="id-instruction">Enter your first, middle, and last name plus birthdate and address exactly as shown on your ID card.</p>
        <div class="form-group id-upload-group">
          <label for="reg-id-front" class="id-upload-label">
            <span class="id-upload-icon" aria-hidden="true"></span>
            Upload Government ID (Front) *
          </label>
          <input type="file" id="reg-id-front" name="id_front" accept=".jpg,.jpeg,.png,.pdf" required class="id-file-input">
          <p class="id-upload-desc">Upload a clear image of your government ID (front). Fill name, birthdate, and Barangay above, then click "Verify Government ID" to check. Name and birthdate must match the ID; the selected barangay must appear in the address on the ID. If the ID does not match, upload a clearer ID picture and try again.</p>
          <div id="id-preview-wrap" class="id-preview-wrap" style="display:none;">
            <img id="id-preview-img" src="" alt="ID preview" class="id-preview-img">
          </div>
        </div>
        <div class="form-group">
          <button type="button" id="btn-verify-id" class="btn btn-outline" aria-busy="false">
            <span class="btn-verify-id-text">Verify Government ID</span>
          </button>
          <span class="hint">Fill name, birthdate, and Barangay above, then upload your ID (JPEG/PNG) and click the button to verify.</span>
        </div>
        <div id="id-ocr-result" class="id-ocr-result" role="status" aria-live="polite" style="display:none;"></div>
        <button type="submit" id="reg-submit-btn" class="btn btn-primary btn-block">Register</button>
        <p class="modal-footer-link">Already have an account? <button type="button" class="btn-link" data-modal-open="login" data-modal-close-current="create-account">Login</button></p>
      </form>
    </div>
  </div>
</div>

<!-- Login Modal (includes Forgot Password view – switch via link inside modal) -->
<div id="modal-login" class="modal" aria-hidden="true" role="dialog" aria-labelledby="modal-login-title">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="modal-login-title">Login</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <!-- View 1: Login form -->
      <div id="login-view" class="modal-view">
        <div id="login-error" class="error-box" style="display:none;"></div>
        <form id="form-login">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <div class="form-group">
            <label for="login-email">Email or Mobile *</label>
            <input type="text" id="login-email" name="email_or_mobile" required placeholder="Email or mobile number">
          </div>
          <div class="form-group">
            <div class="label-row">
              <label for="login-password">Password *</label>
              <a href="<?= htmlspecialchars($forgotPasswordUrl ?? '') ?>" class="btn-link label-link">Forgot Password</a>
            </div>
            <input type="password" id="login-password" name="password" required placeholder="••••••••">
          </div>
          <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
      </div>
      <!-- View 2: Forgot Password (inside same modal) -->
      <div id="forgot-view" class="modal-view" style="display:none;">
        <div id="forgot-password-error" class="error-box" style="display:none;"></div>
        <div id="forgot-password-success" class="success-box" style="display:none;"></div>
        <form id="form-forgot-password">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <div class="form-group">
            <label for="forgot-email">Email or Mobile *</label>
            <input type="text" id="forgot-email" name="email_or_mobile" required placeholder="Email or mobile number">
          </div>
          <button type="submit" class="btn btn-primary btn-block">Send reset instructions</button>
        </form>
        <p class="modal-footer-link"><button type="button" class="btn-link" data-auth-view="login">Back to Login</button></p>
      </div>
    </div>
  </div>
</div>

<!-- OTP Verification Modal -->
<div id="modal-otp" class="modal" aria-hidden="true" role="dialog" aria-labelledby="modal-otp-title">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="modal-otp-title">OTP Verification</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <p id="otp-message" class="success-box">Account created. Verify your OTP.</p>
      <p id="otp-sms-note" class="hint" style="display:none; margin-top:0.5rem;"></p>
      <div id="otp-error" class="error-box" style="display:none;"></div>
      <form id="form-otp">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" id="otp-user-id" name="user_id" value="">
        <div class="form-group">
          <label for="otp-code">6-digit code *</label>
          <input type="text" id="otp-code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" class="otp-input">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Verify</button>
        <p class="resend-row">
          <span id="otp-cooldown" class="hint"></span>
          <button type="button" id="otp-resend" class="btn-link" disabled>Resend OTP</button>
        </p>
        <p id="otp-dev-code" class="hint" style="display:none;"></p>
    </form>
    </div>
  </div>
</div>
