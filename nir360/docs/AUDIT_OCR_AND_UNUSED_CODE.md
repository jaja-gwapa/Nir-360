# NIR360 – OCR / recognition and unused code audit

This document traces **active pages**, lists **OCR/recognition-related and unused code**, and gives **DELETE / KEEP / REVIEW** lists plus **exact lines to remove** from active form pages when removing OCR from registration.

**Assumption:** Your “current system” is the **NIR360 app** served by `nir360/public/index.php` (landing, login, register, dashboards, profile). The root-level HTML/JS (`index.html`, `register.html`, etc.) are a **separate legacy flow** and are treated as unused unless you still use them.

---

## 1. Active pages and what they use

| Page / flow | Entry | Template / handler | Routes / APIs used | Key files |
|-------------|--------|--------------------|--------------------|-----------|
| **Landing (login + register UI)** | GET `/` or `/register` | `layout.php` → `landing.php` | — | `templates/layout.php`, `templates/landing.php`, `public/assets/app.js`, `public/assets/styles.css` |
| **Login** | (modal on landing) | (same) | POST `/api/login` | `AuthController::login()` |
| **Register** | (modal on landing) | (same) | POST `/api/register`, POST `/api/id-ocr/verify` (test button) | `AuthController::register()`, `UploadService`, `GovernmentIdOcrVerifier`, `IdOcrVerificationController` |
| **Forgot password** | (modal on landing) | (same) | POST `/api/forgot-password` | `AuthController::forgotPassword()`, `ForgotPasswordService` |
| **OTP verification** | (modal after register) | (same) | POST `/api/otp/verify`, POST `/api/otp/resend` | `OTPController::verify()`, `OTPController::resend()` |
| **User dashboard** | GET `/user/dashboard` | `user_dashboard.php` (via `dashboard_layout.php`) | GET (page load) | `templates/user_dashboard.php`, `templates/dashboard_layout.php` |
| **Responder dashboard** | GET `/responder/dashboard` | `responder_dashboard.php` (via `dashboard_layout.php`) | GET (page load) | `templates/responder_dashboard.php` |
| **Admin dashboard** | GET `/admin/dashboard` | `admin_dashboard.php` (via `dashboard_layout.php`) | GET (page load), GET `/api/admin/users`, GET `/api/admin/view-id`, POST `/api/admin/*` (approve/reject, etc.) | `templates/admin_dashboard.php`, `AdminService` (getUsers, getUserGovernmentIdPath, getPendingReviews, approve, reject) |
| **Profile** | GET `/profile` | `profile_dashboard.php` (via `dashboard_layout.php`) | GET (page load), POST `/api/profile/*` | `templates/profile_dashboard.php`, `ProfileController`, `ProfileService` |
| **Logout** | GET `/logout` | (handled in `index.php`) | — | `public/index.php` (path `/logout`) |
| **Password reset (email link)** | GET `forgot_password.php` (form), GET `reset_password.php` (set new password) | Standalone PHP pages | POST `/api/forgot-password`; reset uses token in URL + form submit | `public/forgot_password.php`, `public/reset_password.php` |

**Router:** All NIR360 routes go through `nir360/public/index.php`. No other PHP entry is used for the main app except the password reset pages (`forgot_password.php`, `reset_password.php`), which are linked from the app.

---

## 2. OCR / recognition-related code (current usage)

- **Registration form (landing.php):** ID upload field `id_front`, “Verify ID (test OCR)” button, `#id-ocr-result` div, ID preview block.
- **Frontend (app.js):** `runIdVerification()`, listeners for `#btn-verify-id` and `#reg-id-front`, `#id-ocr-result` and `#id-preview-wrap` / `#id-preview-img`.
- **Backend:**  
  - `AuthController::register()` requires `id_front`, calls `GovernmentIdOcrVerifier::verifyStrict()`, then `UploadService::saveIdUpload()`, `AuthService::updateGovernmentIdPath()`.  
  - `IdOcrVerificationController` + POST `/api/id-ocr/verify` used by the “Verify ID” button.  
  - `GovernmentIdOcrVerifier` (OCR.Space API + optional external OCR URL).  
  - `UploadService` used only for saving ID uploads in registration.  
  - Admin: `AdminService::getUserGovernmentIdPath()`, GET `/api/admin/view-id` to display stored ID image; `verification_reviews` and approve/reject for ID mismatches.

**Face/selfie/camera:** Already removed from the app. Only leftovers are in old SQL migrations (`selfie_path`, etc.) and possibly CSS (e.g. `.id-preview-wrap`); no active camera/selfie flow.

---

## 3. DELETE list (safe to remove)

**Assumption:** You are **removing OCR/ID verification from registration** and do not need the legacy root flow or standalone scripts.

### Files to delete

| Item | Reason |
|------|--------|
| `nir360/public/logout.php` | Logout is handled by `index.php` at path `/logout`. Dashboards link to `$webBase . '/logout'` (same app). This file is redundant. |
| `nir360/public/user/map.php` | Not routed from `index.php`; standalone page, not used by any active NIR360 page. |
| `nir360/templates/authority_dashboard.php` | Not referenced in router; only `user`, `responder`, `admin` dashboards are used. |
| `nir360/templates/civilian_dashboard.php` | Same as above; router uses `user_dashboard.php` for role `user`. |
| `nir360/scripts/extract_id_text.py` | OCR helper; not used by PHP app. |
| `nir360/scripts/id_ocr_verify.py` | OCR helper; not used by PHP app. |
| `nir360/scripts/run_real_id_test.bat` | OCR testing; not used by app. |
| `nir360/scripts/requirements-ocr.txt` | OCR deps; not needed if OCR removed. |
| `nir360/scripts/test_ocr.py` | OCR testing; not used by app. |
| `nir360/scripts/see_extracted_text.bat` | OCR testing; not used by app. |
| `nir360/scripts/README_OCR.md` | Docs for OCR scripts; optional to keep for reference. |
| `nir360/scripts/test_my_id.bat` | OCR testing; not used by app. |

**If you remove OCR from registration**, also remove:

| Item | Reason |
|------|--------|
| `nir360/src/Service/GovernmentIdOcrVerifier.php` | Only used for ID verification in registration and by test endpoint. |
| `nir360/src/Controller/IdOcrVerificationController.php` | Only used for POST `/api/id-ocr/verify` (Verify ID button). |
| `nir360/src/Service/UploadService.php` | Only used to save ID uploads in `AuthController::register()`. |

**Root project (legacy flow)** – safe to remove if you do **not** use the root flow (localStorage, upload-id, etc.):

| Item | Reason |
|------|--------|
| `index.html` | Legacy landing; NIR360 uses `nir360/public/index.php` + `landing.php`. |
| `register.html` | Legacy register; NIR360 uses registration modal on landing. |
| `upload-id.html` | Legacy ID upload step; not part of NIR360 flow. |
| `dashboard.html` | Legacy dashboard; NIR360 uses role-based dashboards via index.php. |
| `admin.html` | Legacy admin; NIR360 uses admin dashboard via index.php. |
| `verify-otp.html` | Legacy OTP; NIR360 uses OTP modal on landing. |
| `js/app.js` | Legacy app flow (different from `nir360/public/assets/app.js`). |
| `js/register.js` | Legacy registration. |
| `js/dashboard.js` | Legacy dashboard. |
| `js/profile.js` | Legacy profile. |
| `js/verify-otp.js` | Legacy OTP. |
| `js/upload-id.js` | Legacy ID upload. |

### Routes / backend to remove (when removing OCR)

- In `nir360/public/index.php`: remove `require_once` for `GovernmentIdOcrVerifier.php`, `IdOcrVerificationController.php`, `UploadService.php`; remove instantiation of `$idOcrVerifier`, `$idOcrVerificationController`, `$uploadService`; remove `$path === '/api/id-ocr/verify'` block; stop passing `$uploadService` and `$idOcrVerifier` into `AuthController`.
- In `AuthController`: remove ID upload + OCR logic from `register()` (see “Lines to remove” below); remove `UploadService` and `GovernmentIdOcrVerifier` from constructor and properties; simplify `getRegisterInput()` so it no longer includes `_files['id_front']` / `id_back`.

**Note:** If you keep **optional** ID upload (no OCR), you could keep `UploadService` and the ID file field and only remove the OCR verifier and test-verify endpoint. The DELETE list above assumes **full** removal of ID verification from registration.

---

## 4. KEEP list (used by active pages)

- **Router & bootstrap:** `nir360/public/index.php`, `nir360/bootstrap.php`, `nir360/config/database.php`, `nir360/config/app.php`.
- **Templates:** `layout.php`, `landing.php`, `user_dashboard.php`, `responder_dashboard.php`, `admin_dashboard.php`, `profile_dashboard.php`, `dashboard_layout.php`.
- **Auth & OTP:** `AuthService`, `AuthController` (login, forgot password, and register after you strip ID/OCR), `OTPController`, `OTPService`, `ForgotPasswordService`.
- **Profile:** `ProfileController`, `ProfileService`.
- **Reports:** `ReportService` (user report submission; report media path in config).
- **Admin:** `AdminService` (getUsers, setUserActive, getPendingReviews, approve, reject, getUserGovernmentIdPath for viewing stored IDs, etc.), admin routes in `index.php`.
- **Middleware:** `RBACMiddleware`, `Helpers`.
- **Config:** `bago_city_barangays.php`, `mail.local.php`, `sms.php` / `sms.local.php` (if you use email/SMS).
- **Password reset:** `public/forgot_password.php`, `public/reset_password.php` (linked from app).
- **Assets:** `public/assets/app.js` (after removing OCR-only blocks), `public/assets/styles.css`.
- **SQL:** `schema.sql` and migrations; `verification_reviews` and `government_id_path` can stay for existing data; you can stop writing new rows/paths if you remove ID upload.

---

## 5. REVIEW list (confirm before removing)

| Item | Why REVIEW | How to confirm |
|------|------------|-----------------|
| Root `index.html`, `register.html`, `upload-id.html`, `dashboard.html`, `admin.html`, `verify-otp.html`, `js/*.js` | Separate flow; may still be used in some environments or bookmarks. | Confirm no one uses the root URL (e.g. opening `index.html` or root `dashboard.html`). If unused, move to DELETE. |
| `nir360/public/check_schema.php`, `nir360/public/sms_debug.php`, `nir360/public/test_sms.php` | Dev/debug utilities; not linked from main app. | If you never run these manually, safe to remove. |
| `nir360/public/reset_admin_password.php` | One-off admin password reset; referenced in its own UI. | Keep if you use it for recovery; delete after use and secure server. |
| `nir360/docs/*.md` (e.g. MODULE1_CHECKLIST.md, GOVERNMENT_ID_OCR_VERIFICATION.md, DESIGN_SPEC_*) | Documentation only. | Keep for reference or delete if you want to reduce docs. |
| Admin “View ID” and `getUserGovernmentIdPath` / GET `/api/admin/view-id` | Only relevant if you still store/show government IDs (e.g. from old registrations). | If you remove ID upload entirely, you can remove view-id route and the “View ID” UI; keep DB columns for existing data or plan a migration to drop them later. |
| `nir360/scripts/` folder (entire folder) | Contains OCR Python scripts and batch files. | If you removed OCR from the app, the whole folder is optional; keep README_OCR.md only if you want to document past OCR setup. |

---

## 6. Lines to remove from active form pages (when removing OCR)

### 6.1 `nir360/templates/landing.php`

Remove the **Identity Verification (Government ID)** section and the ID-related form elements:

- **Lines 104–122** (entire block): from `<h3 class="form-section">Identity Verification (Government ID)</h3>` through `<div id="id-ocr-result" ...></div>` (inclusive), including:
  - The `<h3>`, `<p class="id-instruction">`, the `.id-upload-group` div (label, file input, `.id-preview-wrap`), the “Verify ID (test OCR)” button and hint, and `#id-ocr-result`.

So delete from:

```php
        <h3 class="form-section">Identity Verification (Government ID)</h3>
        <p class="id-instruction">...</p>
        <div class="form-group id-upload-group">
          ...
        </div>
        <div class="form-group">
          <button type="button" id="btn-verify-id" ...>Verify ID (test OCR)</button>
          ...
        </div>
        <div id="id-ocr-result" class="id-ocr-result" style="display:none;"></div>
```

Leave the **Register** submit button and the “Already have an account? Login” line that follow (lines 123–124 in current file).

Optional: you can soften the birthdate hint (line 66) from “Used for ID verification” to something like “Select your date of birth” if you remove ID verification.

### 6.2 `nir360/public/assets/app.js`

Remove OCR/ID verification UI logic and the “Verify ID” flow:

- **Lines 275–413** (inclusive): the entire block that includes:
  - Comment `// Verify ID (OCR): shared logic for button and auto-trigger`
  - `var btnVerifyId`, `idOcrResult`, `composeFullName`, `runIdVerification`, the `fetch(baseUrl + '/api/id-ocr/verify', ...)` block, `if (btnVerifyId && idOcrResult) { ... addEventListener(...) }`, and the ID front image preview + auto-trigger block (including `idFrontInput`, `idPreviewWrap`, `idPreviewImg` and their `change` listener that calls `runIdVerification(true)`).

So delete from the line starting with `// Verify ID (OCR): shared logic` down to and including the closing `});` and `}` for the `idFrontInput.addEventListener('change', ...)` and the closing `  }` of the `if (formCreate)` block that wraps the registration + ID logic. Be careful to leave the closing `  }` that matches `if (formCreate) {` (so the **Register** submit handler and its `});` stay).

Exact range: from **line 275** (`// Verify ID (OCR): shared logic...`) through **line 413** (the `});` after the `idFrontInput.addEventListener('change', ...)` and the following `  }` that closes the `if (idFrontInput && idPreviewWrap && idPreviewImg)` block). That leaves the **Register** form submit handler (lines 122–273) and the closing `  }` for `if (formCreate)`.

After removal, the block `if (formCreate) { ... }` should contain only the form submit listener (no `btnVerifyId`, `runIdVerification`, or ID preview listeners).

### 6.3 Backend: `nir360/src/Controller/AuthController.php`

When removing OCR and ID upload from registration:

- Remove **constructor parameters** and **properties**: `UploadService $uploadService`, `GovernmentIdOcrVerifier $idOcrVerifier` (and their assignments).
- In `register()`:
  - Remove the entire block that: validates `_files['id_front']`, rejects PDF, reads `$frontTmpPath`, calls `$this->idOcrVerifier->verifyStrict(...)`, checks `$ocrResult` (name match, verification_status), and returns errors for ID/OCR (roughly lines 47–115 in the current `register()`).
  - Remove the block that calls `$this->uploadService->saveIdUpload(...)` and `$this->authService->updateGovernmentIdPath(...)` (and any `$idBack` handling if present).
- In `getRegisterInput()`: remove the `_files` key and any handling of `id_front` / `id_back` in the multipart branch.

Result: `register()` only validates basic fields and calls `$this->authService->register(...)` (and OTP sending, etc.), with no ID upload or OCR.

---

## 7. Summary

- **Active system:** Login, register, forgot password, OTP, user/responder/admin dashboards, profile, logout, and password reset pages – all go through `nir360/public/index.php` and the listed templates/controllers.
- **OCR/recognition:** Currently used in registration (ID upload + Verify ID button + backend verification). To remove it, use the DELETE list and line removals above; then remove OCR/ID from `AuthController::register()` and from `index.php` (route and service wiring).
- **DELETE:** Standalone/unused scripts, redundant `logout.php`, unused dashboards (`authority_dashboard`, `civilian_dashboard`), `user/map.php`, and (if removing OCR) OCR services and ID upload service/controller plus root legacy HTML/JS if you don’t use that flow.
- **KEEP:** Everything listed in §4 that your current flows depend on.
- **REVIEW:** Root HTML/JS, dev scripts, admin “View ID”, and docs – confirm usage before deleting.

After you apply the line removals in §6 and the backend changes, remove the files in the DELETE list to finish the cleanup.

---

## Quick reference – lines to remove

| File | Lines to remove | What |
|------|-----------------|------|
| `nir360/templates/landing.php` | **104–122** | Entire "Identity Verification (Government ID)" block: `<h3>`, instruction `<p>`, ID upload group, "Verify ID" button, `#id-ocr-result` div. |
| `nir360/public/assets/app.js` | **275–413** | All ID/OCR UI code: comment, `btnVerifyId`, `idOcrResult`, `composeFullName`, `runIdVerification`, fetch to `/api/id-ocr/verify`, button listener, ID preview + auto-trigger listener. |
| `AuthController.php` | See §6.3 | Constructor: remove `UploadService`, `GovernmentIdOcrVerifier`. `register()`: remove ID validation, `verifyStrict`, upload, `updateGovernmentIdPath`. `getRegisterInput()`: remove `_files`. |
| `public/index.php` | See §3 | Remove requires and wiring for `UploadService`, `GovernmentIdOcrVerifier`, `IdOcrVerificationController`; remove `/api/id-ocr/verify` route; stop passing those deps into `AuthController`. |
