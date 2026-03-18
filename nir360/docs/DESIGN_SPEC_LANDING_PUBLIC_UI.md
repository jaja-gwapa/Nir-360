# NIR360 – Landing Page & Public Access UI  
## Figma-ready layout specs & copy

---

## 1. Frame & layout

### Desktop
- **Recommended frame width:** 1440px  
- **Content max-width:** 900px (centered)  
- **Background:** `#0f1419` (dark)  
- **Padding (content):** 0 24px horizontal  

---

## 2. Header / Navbar

### Layout
- **Height:** 64px (padding 16px 0)  
- **Border bottom:** 1px solid `#2d3a4d`  
- **Inner:** flex, space-between, align center; max-width 900px, centered  

### Left
- **System name / logo:** `NIR360`  
  - Font: system-ui or equivalent, 24px, weight 700  
  - Color: `#e6edf3`  
  - Hover: `#3b82f6`  
  - Link: points to landing (GET /)  

### Right (in order)
1. **Login** – text/ghost button (opens Login modal)  
2. **Create Account** – outline button (opens Create Account / Register modal)  

**Note:** Forgot Password is not in the header; it is a link inside the Login modal that switches the modal view to Forgot Password (same overlay, different form).  

**Spacing between nav items:** 12px  

---

## 3. Buttons (specs)

### Primary
- **Background:** `#3b82f6`  
- **Hover:** `#2563eb`  
- **Color:** `#ffffff`  
- **Padding:** 12px 20px (default), 14px 24px (large)  
- **Font:** 15px, weight 600  
- **Border radius:** 8px  
- **Border:** none  

### Outline (e.g. Create Account in header)
- **Background:** transparent  
- **Border:** 1px solid `#3b82f6`  
- **Color:** `#3b82f6`  
- **Hover:** background `rgba(59, 130, 246, 0.15)`  
- **Padding / font / radius:** same as primary  

### Ghost (e.g. Login in header)
- **Background:** transparent  
- **Border:** none  
- **Color:** `#e6edf3`  
- **Hover:** `#3b82f6`  
- **Padding / font:** same as primary  

### Inline link (e.g. “Already have an account? Login”, “Forgot Password” in modals)
- **Background:** none  
- **Color:** `#3b82f6`  
- **Hover:** underline  
- **Font:** 14px  

---

## 4. Hero (main section)

### Layout
- **Padding:** 64px 0  
- **Text align:** center  

### Heading
- **Text:** `NIR360`  
- **Font:** clamp(28px, 4vw, 40px), weight 700  
- **Color:** `#e6edf3`  
- **Margin bottom:** 16px  

### Description (short)
- **Copy:**  
  `Safety and incident response with identity verification. Register, verify your phone and ID, and get a verified badge. Join as a civilian or responder.`  
- **Font:** 16–18px  
- **Color:** `#8b9cb3`  
- **Max-width:** 560px, centered  
- **Margin bottom:** 24px  
- **Line height:** 1.5–1.6  

### CTA
- **Label:** `Get Started`  
- **Action:** Opens Registration (Create Account) modal  
- **Style:** Primary, large (btn-lg)  

---

## 5. Modals – layout & behavior

### Container (overlay)
- **Position:** fixed, full viewport  
- **Background:** `rgba(0, 0, 0, 0.6)`  
- **Display:** flex, center align & justify  
- **Padding:** 16px  
- **z-index:** 1000  
- **Transition:** opacity 0.2s, visibility 0.2s  
- **States:** hidden (opacity 0, visibility hidden) / open (opacity 1, visibility visible)  

### Modal box (card)
- **Background:** `#ffffff`  
- **Color (text):** `#1a1a1a`  
- **Max-width:** 420px  
- **Width:** 100%  
- **Max-height:** 90vh  
- **Overflow-y:** auto  
- **Border radius:** 12px  
- **Box shadow:** 0 20px 60px rgba(0, 0, 0, 0.4)  

### Modal header
- **Padding:** 20px 24px  
- **Border bottom:** 1px solid `#e5e7eb`  
- **Layout:** flex, space-between, align center  
- **Title:** 20px, weight 700, color `#1a1a1a`  
- **Close (×):** 24px, color `#6b7280`, hover `#1a1a1a`, top-right; cursor pointer  

### Modal body
- **Padding:** 24px  

### Prototype / interactions
- **Open:** From header or hero CTA → show overlay + modal box.  
- **Close:**  
  - Click on **backdrop** (outside modal box) → close.  
  - Press **ESC** → close.  
  - Click **×** in header → close.  
- **Cross-modal:** “Already have an account? Login” in Create Account → close Create Account, open Login modal.  
- **Login modal view switch:** “Forgot Password” link inside Login → same modal switches to Forgot Password form (no new overlay). “Back to Login” → switches back to Login form. Modal title updates to “Forgot Password” / “Login” accordingly.  

---

## 6. Form components (inside modals)

### Inputs
- **Width:** 100%  
- **Padding:** 12px 16px  
- **Font:** 16px  
- **Border:** 1px solid `#d1d5db`  
- **Border radius:** 8px  
- **Focus:** border `#3b82f6`; optional 2px outline/ring  
- **Placeholder:** `#9ca3af`  

### Labels
- **Font:** 14px, weight 500  
- **Color:** `#374151`  
- **Margin bottom:** 6px  
- **Display:** block  

### Hint text (below input)
- **Font:** 13px  
- **Color:** `#6b7280`  
- **Margin top:** 4px  

---

## 7. Copy & labels by screen

### Login modal
- **Title:** `Login`  
- **Fields:**  
  - `Email or Mobile *`  
  - `Password *`  
- **Placeholders:** `Email or mobile number`, `••••••••`  
- **Primary button:** `Login`  
- **Link:** `Forgot Password` (switches same modal to Forgot Password view)  

### Create Account (Registration) modal
- **Title:** `Create Account`  
- **Fields:**  
  - `Email *`  
  - `Mobile number (E.164) *`  
  - `Password *`  
- **Placeholders:** `you@example.com`, `+639171234567`, `••••••••`  
- **Hints:**  
  - `10–15 digits, with country code`  
  - `Min 8 chars, 1 upper, 1 lower, 1 number, 1 special`  
- **Primary button:** `Create Account`  
- **Footer link:** `Already have an account? Login`  

### Forgot Password (view inside Login modal)
- **Title when shown:** `Forgot Password`  
- **Field:** `Email or Mobile *`  
- **Placeholder:** `Email or mobile number`  
- **Primary button:** `Send reset instructions`  
- **Success message (after submit, DEV):**  
  `If the account exists, we sent instructions to reset your password.`  

---

## 8. Error & success states

### Error (inline, above form or below header)
- **Background:** `#fef2f2`  
- **Border:** 1px solid `#ef4444`  
- **Color:** `#b91c1c`  
- **Padding:** 12px 16px  
- **Border radius:** 8px  
- **Font:** 14px  
- **Margin bottom:** 16px  

### Success (e.g. Forgot Password confirmation)
- **Background:** `#f0fdf4`  
- **Border:** 1px solid `#22c55e`  
- **Color:** `#166534`  
- **Padding / radius / font / margin:** same as error  

---

## 9. Component checklist (Figma)

- [ ] **Header:** logo “NIR360”, nav (Login, Create Account only)  
- [ ] **Buttons:** Primary, Outline, Ghost, Inline link  
- [ ] **Inputs:** default state, focus, placeholder; with label + optional hint  
- [ ] **Modal:** overlay, box, header (title + close), body  
- [ ] **Error box**  
- [ ] **Success box**  
- [ ] **Hero:** heading, description, Get Started CTA  

---

## 10. Prototype flows (Figma)

- **Landing → Create Account:** Click “Create Account” or “Get Started” → Create Account modal opens (centered overlay).  
- **Landing → Login:** Click “Login” → Login modal opens (centered overlay).  
- **Login modal → Forgot Password view:** In Login modal, click “Forgot Password” → same modal view switches to Forgot Password form; title becomes “Forgot Password”.  
- **Forgot Password view → Login view:** Click “Back to Login” → same modal view switches back to Login form; title becomes “Login”.  
- **Create Account → Login:** In Create Account modal, click “Already have an account? Login” → Create Account closes, Login modal opens.  
- **Any modal:** Click backdrop or × or press ESC → modal closes.  

---

*End of design spec. Use this for a 1440px desktop frame, component library, and prototype in Figma.*
