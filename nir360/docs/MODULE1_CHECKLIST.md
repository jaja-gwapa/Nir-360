# MODULE 1: User Registration & Identity Verification – Checklist

## 1. Landing Page
| Requirement | Status | Implementation |
|-------------|--------|----------------|
| System name | ✓ | "NIR360" in header and hero |
| Login button | ✓ | Header → opens Login modal |
| Register button | ✓ | Header "Register" → opens Register modal |
| Forgot Password link | ✓ | Header + inside Login form (next to Password) |
| Professional modern UI | ✓ | Dark theme, modals, buttons in `public/assets/styles.css` |
| Responsive layout | ✓ | Container max-width, flexible nav; add breakpoints as needed |

## 2. User Registration Form
| Requirement | Status | Implementation |
|-------------|--------|----------------|
| Full Name | ✓ | `reg-full-name` in Register modal |
| Email (unique) | ✓ | `AuthService::isEmailTaken()`, DB UNIQUE |
| Mobile (unique) | ✓ | `AuthService::isMobileTaken()` |
| Password (strength policy) | ✓ | `AuthService::validatePasswordStrength()` – 8+ chars, 1 upper, 1 lower, 1 number, 1 special |
| Confirm Password | ✓ | Field + server-side match in `AuthController::register()` |
| Address | ✓ | `reg-address` textarea |
| Barangay | ✓ | `reg-barangay` |
| Emergency Contact | ✓ | `emergency_contact_name`, `emergency_contact_mobile` |
| Role: Civilian / Responder/Authority | ✓ | `<select name="role">` |
| Upload Government ID (Front) | ✓ | `id_front` file input, accept JPEG/PNG/PDF |
| Upload Government ID (Back) | ✓ | `id_back` file input |
| File upload UI + backend storage | ✓ | `UploadService::saveIdUpload()`, storage outside web root |
| No OCR logic yet | ✓ | `OCRService::placeholderProcessUpload()` only; no extraction |
| Placeholder for OCR | ✓ | `OCRService::extractFromFile()`, `placeholderProcessUpload()` |

## 3. OTP Verification
| Requirement | Status | Implementation |
|-------------|--------|----------------|
| OTP generation | ✓ | `OTPService::generateAndStore()` – 6 digits |
| OTP expiration | ✓ | Config `otp_expiry_minutes` (5), checked in `verify()` |
| OTP retry limit | ✓ | Config `otp_max_attempts` (5), lock 15 min |
| Placeholder SMS/Email | ✓ | DEV MODE: no provider; OTP in response when `APP_ENV=local` |
| Verification fallback (Admin) | ✓ | `verification_reviews` table, AdminService approve/reject |
| OTP table | ✓ | `otp_codes` |
| Verification status in users | ✓ | `users.is_phone_verified`, `users.verification_status` |

## 4. ID Verification Structure (DB only, no OCR logic)
| Requirement | Status | Implementation |
|-------------|--------|----------------|
| ID upload paths | ✓ | `id_uploads.file_path` |
| OCR extracted name (nullable) | ✓ | `ocr_results.extracted_name` |
| OCR extracted birthdate (nullable) | ✓ | `ocr_results.extracted_birthdate` |
| OCR extracted address (nullable) | ✓ | `ocr_results.extracted_address` |
| Name match flag | ✓ | `ocr_results.name_match` |
| Birthdate match flag | ✓ | `ocr_results.birthdate_match` |
| Address match flag | ✓ | `ocr_results.address_match` |
| Verification status (Pending/Verified/Rejected) | ✓ | `users.verification_status` + `verification_reviews.status` |

## 5. Roles & Permissions (RBAC)
| Requirement | Status | Implementation |
|-------------|--------|----------------|
| Civilians cannot see authority dashboard | ✓ | `RBACMiddleware`, route `/authority/*` → role `authority` only |
| Authorities can view incidents | ✓ | Placeholder endpoint `/authority/incidents`; RBAC allows authority |
| Admin can manage users and verification | ✓ | `/admin/dashboard`, AdminService approve/reject |

## 6. Security
| Requirement | Status | Implementation |
|-------------|--------|----------------|
| Password hashing | ✓ | `password_hash(PASSWORD_DEFAULT)` in AuthService |
| Input validation | ✓ | Email, mobile, password strength, required fields in AuthService + controller |
| Unique email and mobile | ✓ | DB UNIQUE + `isEmailTaken()` / `isMobileTaken()` |
| Prepared statements | ✓ | All queries use `$pdo->prepare()` + `execute()` |
| CSRF protection | ✓ | Helpers::validateCsrf() on POST APIs |

---

**Schema update (existing DB):** If you already created the DB before `verification_status` was added, run:
`sql/migration_verification_status.sql`
