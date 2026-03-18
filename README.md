# User Registration & Identity Verification Module

**Verify** – Capstone UI for the User Registration & Identity Verification Module.

User flows: **Register → OTP → Profile → Upload ID → Dashboard** (mock data, no backend yet).

## How to run

- **XAMPP:** Put this folder under `htdocs` and open:  
  `http://localhost/capstone_project/`
- **Or** open `index.html` directly in your browser (file://).

## Flow

1. **index.html** – Landing; “Get started” goes to Register.
2. **register.html** – Email, mobile, password → saves to localStorage, then **Verify OTP**.
3. **verify-otp.html** – 6-digit OTP (any 6 digits for mock), 60s resend countdown → **Profile**.
4. **profile.html** – Full name, address, barangay, emergency contact → **Upload ID**.
5. **upload-id.html** – Front & back image upload (drag/drop or click) → **Dashboard**.
6. **dashboard.html** – Shows email, mobile, verification status, role badge, and “Verified” if completed.

State is stored in `localStorage` so you can click through the whole flow. To reset, clear site data for this origin or run in console: `localStorage.removeItem('verify_capstone_user')`.

## Next steps (when you add backend)

- Replace mock `setAppState` with API calls (register, send OTP, verify OTP, save profile, upload ID).
- Add real OTP generation/expiration/retry and SMS/email provider.
- Add OCR and admin fallback for verification; set `verified` from server.
- Add role (Civilian / Responder / Admin) from server and show correct dashboard.
