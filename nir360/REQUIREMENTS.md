# NIR360 – Requirement-to-Code Checklist

Each requirement from the project board mapped to file(s) and function(s).

---

## UI / PAGES

| Requirement | Implementation |
|-------------|----------------|
| **Landing Page (GET /)** | `public/index.php` (route `$path === '/'`); `templates/layout.php`, `templates/landing.php` |
| Header: left "NIR360" logo/title | `templates/layout.php` – `.logo` link |
| Header: right "Create Account" and "Log In" buttons | `templates/layout.php` – `.nav` buttons with `data-modal-open` |
| Hero: heading "Welcome to NIR360" | `templates/landing.php` – `.hero-title` |
| Hero: short description (safety/incident/verification) | `templates/landing.php` – `.hero-desc` |
| Hero: primary CTA "Get Started" opens Create Account modal | `templates/landing.php` – button `data-modal-open="create-account"` |
| No separate register/login pages; modals only | All auth UI in `templates/landing.php` (modals); no `register.php` / `login.php` |

---

## Modals

| Requirement | Implementation |
|-------------|----------------|
| **Create Account Modal** | `templates/landing.php` – `#modal-create-account`; submit → `public/assets/app.js` → `POST /api/register` |
| Fields: email, mobile, password | `templates/landing.php` – `#reg-email`, `#reg-mobile`, `#reg-password` |
| Validate email format | `src/Service/AuthService.php` – `validateEmail()`; used in `register()` |
| Validate contact number (digits only, 11–12) | `src/Service/AuthService.php` – `validateContactNumber()`; used in `register()` |
| Unique email enforced (DB + server) | `src/Service/AuthService.php` – `isEmailTaken()`; `register()` returns error if taken |
| Unique mobile enforced (DB + server) | `src/Service/AuthService.php` – `isMobileTaken()`; `register()` returns error if taken |
| Password strength: min 8, 1 upper, 1 lower, 1 number, 1 special | `src/Service/AuthService.php` – `validatePasswordStrength()`; used in `register()` |
| On success: close register modal, open OTP modal, message "Account created. Verify your OTP." | `public/assets/app.js` – on `res.json.success` and `open_otp_modal`: `closeModal('create-account')`, `openModal('otp')`, set message |
| **Log In Modal** | `templates/landing.php` – `#modal-login`; submit → `app.js` → `POST /api/login` |
| Fields: email OR mobile + password | `templates/landing.php` – `#login-email`, `#login-password`; backend accepts either | `src/Service/AuthService.php` – `login()` |
| On success: redirect by role (civilian → /civilian/dashboard, authority → /authority/dashboard, admin → /admin/dashboard) | `src/Controller/AuthController.php` – `login()` sets `$_SESSION`, returns `redirect`; `app.js` does `window.location.href = res.json.redirect` |
| **OTP Verification Modal** | `templates/landing.php` – `#modal-otp`; verify → `POST /api/otp/verify`, resend → `POST /api/otp/resend` |
| OTP generation (6 digits) | `src/Service/OTPService.php` – `generateAndStore()` – `random_int(100000, 999999)` |
| OTP expiration 5 minutes | `config/app.php` – `otp_expiry_minutes` = 5; `OTPService::generateAndStore()` |
| OTP retry limit: max 5 attempts; lock 15 minutes | `config/app.php` – `otp_max_attempts`, `otp_lock_minutes`; `src/Service/OTPService.php` – `verify()` |
| Resend OTP with cooldown timer | `config/app.php` – `otp_resend_cooldown_seconds`; `OTPService::canResend()`, `resend()`; `app.js` – `startOtpCooldown()`, Resend button |
| DEV MODE: no SMS/email provider; store OTP server-side; generic success; log OTP (or return when APP_ENV=local) | `src/Controller/AuthController.php` – after register, `error_log()` when env=local; response includes `dev_otp` when env=local; `src/Controller/OTPController.php` – resend same |
| On success: set is_phone_verified=1 | `src/Service/OTPService.php` – `verify()` – `UPDATE users SET is_phone_verified = 1` |

---

## Backend features

| Requirement | Implementation |
|-------------|----------------|
| **Profile setup** | `src/Service/ProfileService.php` – `save()`; table `profiles` |
| Full name, address, barangay, emergency contact name + mobile | `sql/schema.sql` – `profiles`; `ProfileService::save()` |
| **Government ID upload** | `src/Service/UploadService.php` – `saveIdUpload()` |
| Front & back | `UploadService` – `side` in ['front','back'] |
| Accept JPEG/PNG/PDF | `UploadService` – `$allowedMimes` |
| Store outside web root; save path in DB | `config/app.php` – `upload_storage_path` = `dirname(__DIR__).'/storage/uploads/ids'`; `id_uploads.file_path` |
| **OCR extraction + comparison** | `src/Service/OCRService.php` |
| Extract name, birthdate, address | `OcrSpaceClient::parseImage()` + `GovernmentIdOcrVerifier` (OCR.Space API) |
| Compare OCR vs user profile: name fuzzy, birthdate exact, address fuzzy | `OCRService::compareWithProfile()` – `fuzzyMatch()`, `exactDateMatch()` |
| Flag mismatch for admin review | `OCRService::flagForAdminReview()`; table `verification_reviews` |
| **Verification fallback (admin approve/reject with reason)** | `src/Service/AdminService.php` – `approve()`, `reject()`; table `verification_reviews` |
| **Verified user badge** | `users.is_verified`; set by `AdminService::approve()` |
| **Multi-role users (RBAC)** | `users.role` ENUM('civilian','authority','admin'); session stores `role` |
| Backend guards/middleware | `src/Middleware/RBACMiddleware.php`; `public/index.php` – check before serving `/civilian/`, `/authority/`, `/admin/` |
| Authorities can access incidents endpoints; civilians cannot | `public/index.php` – `/authority/incidents` only in authority block; RBAC denies civilian |

---

## Implementation constraints

| Requirement | Implementation |
|-------------|----------------|
| Server-rendered HTML templates + minimal JS for modals and AJAX (fetch) | `templates/*.php`; `public/assets/app.js` – modals, fetch for register/login/OTP |
| Auth endpoints return JSON | `AuthController::register()`, `login()`; `OTPController::verify()`, `resend()` – all `Helpers::jsonResponse()` |
| CSRF protection for AJAX | `src/Helpers.php` – `csrfToken()`, `validateCsrf()`; all POST APIs validate token; layout passes `NIR360_CSRF` to `app.js` |
| Password hashing via password_hash() | `src/Service/AuthService.php` – `password_hash($password, PASSWORD_DEFAULT)` |
| Prepared statements (PDO) | All DB access in Services use `$pdo->prepare()` and `execute()` |

---

## Deliverables (files)

| Deliverable | Path |
|-------------|------|
| Front controller / router | `public/index.php` |
| Layout with header "NIR360" | `templates/layout.php` |
| Landing + modal markup (Register, Login, OTP) | `templates/landing.php` |
| Modal open/close + AJAX submit + error handling | `public/assets/app.js` |
| Auth controller | `src/Controller/AuthController.php` |
| OTP controller | `src/Controller/OTPController.php` |
| Auth service | `src/Service/AuthService.php` |
| OTP service | `src/Service/OTPService.php` |
| Profile service | `src/Service/ProfileService.php` |
| Upload service | `src/Service/UploadService.php` |
| OCR service | `src/Service/OCRService.php` |
| Admin service | `src/Service/AdminService.php` |
| RBAC middleware | `src/Middleware/RBACMiddleware.php` |
| Helpers (JSON, CSRF) | `src/Helpers.php` |
| MySQL schema | `sql/schema.sql` |
| Config | `config/app.php`, `config/database.php` |
| Bootstrap | `bootstrap.php` |
| Requirement-to-code checklist | `REQUIREMENTS.md` (this file) |
