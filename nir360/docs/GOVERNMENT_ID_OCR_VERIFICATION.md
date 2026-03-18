# Government ID OCR Verification Module

This module uses the **OCR.Space API** to extract text from uploaded government ID images, normalizes it, and compares it with user-provided registration data (full name, birthdate, address). It returns a verification result with match score and scan quality.

## Requirements

- **OCR.Space API key** – Get a free key at [ocr.space/ocrapi](https://ocr.space/ocrapi). Set the environment variable:
  - `OCR_SPACE_API_KEY` – Your API key (required for ID verification).

- **PHP** with `curl` and `CURLFile` (standard in PHP 5.5+).

- Optional: **PhilSys template OCR service** – If `OCR_SERVICE_URL` is set (e.g. a Flask service), the verifier can use it for structured name/birthdate/address extraction and merge that with OCR.Space output.

## Configuration (`config/app.php`)

| Key | Default | Description |
|-----|---------|-------------|
| `ocr_space_api_key` | `getenv('OCR_SPACE_API_KEY')` | OCR.Space API key. Set in env or `.env`. |
| `id_images_upload_path` | `nir360/storage/uploads/id_images` | Directory where uploaded ID images are saved. |
| `id_images_max_size_bytes` | 5242880 (5MB) | Max upload size in bytes. |
| `ocr_confidence_poor_threshold` | 70.0 | Below this % confidence, `scan_quality` is `"poor"`. When using OCR.Space, confidence is 85 when text is returned, 0 when OCR fails. |
| `ocr_service_url` | `getenv('OCR_SERVICE_URL')` | Optional PhilSys/template OCR service base URL. |

## Where to put the OCR key

**Option A – Local config file (recommended)**  
1. Copy `config/app.local.example.php` to `config/app.local.php`.  
2. Open `config/app.local.php` and set your key:
   ```php
   return [
       'ocr_space_api_key' => 'your_actual_key_here',
   ];
   ```
   Do not commit `app.local.php` if it contains secrets (add to `.gitignore` if needed).

**Option B – Environment variable**  
Set `OCR_SPACE_API_KEY` in your environment (e.g. Windows system env, or in Apache/PHP config):
```env
OCR_SPACE_API_KEY=your_api_key_here
```

Without a valid `ocr_space_api_key`, OCR will return no text and verification will fail (rejected / poor quality).

## API Endpoint

**POST** `/api/id-ocr/verify`

- **Content-Type:** `multipart/form-data`
- **Fields:**
  - `id_image` (file) – Government ID image (JPEG or PNG only).
  - `full_name` (string) – User’s full name (or `first_name`, `middle_name`, `last_name`).
  - `birthdate` (string) – User’s birthdate (any format parseable by PHP).
  - `address` / `barangay` / `street_address` (strings) – User’s address fields.

### Security

- Allowed file types: **JPEG**, **PNG** (validated by MIME).
- File size limited by `id_images_max_size_bytes`.
- Uploaded files are renamed to a random safe name before storage.
- Inputs are trimmed and length-limited before use.

### Response (JSON)

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Whether the request and OCR run succeeded. |
| `name_match` | boolean | True if full name matches OCR content. |
| `birthdate_match` | boolean | True if birthdate matches OCR content. |
| `address_match` | boolean | True if address matches OCR content. |
| `match_score` | int (0–3) | Sum of the three match flags. |
| `ocr_confidence` | float | OCR confidence (0–100). 85 when OCR.Space returns text, 0 on failure. |
| `verification_status` | string | `verified` \| `manual_review` \| `rejected`. |
| `scan_quality` | string | `ok` or `poor`. |

**Verification status rules:**

- **3/3** → `verified`
- **2/3** → `manual_review`
- **0 or 1** → `rejected`

**Scan quality:**

- OCR confidence **< 70%** or OCR failure → `scan_quality = "poor"`.
- Otherwise → `scan_quality = "ok"`.

## File Layout

- **OCR client:** `src/Service/OcrSpaceClient.php` – Sends image to OCR.Space API, returns `ParsedResults[0].ParsedText`. Error handling and logging for API/HTTP errors.
- **Verifier:** `src/Service/GovernmentIdOcrVerifier.php` – Uses `OcrSpaceClient` for text extraction, normalizes text, compares with user input, scoring.
- **Controller:** `src/Controller/IdOcrVerificationController.php` – Upload validation, save to `id_images`, call verifier, return JSON.
- **Route:** `public/index.php` – `POST /api/id-ocr/verify` → `IdOcrVerificationController::verify()`.

## Error Handling

- **Missing API key:** `OCR_SPACE_API_KEY` empty → `OcrSpaceClient::parseImage()` returns `''`; verifier treats as no text (confidence 0, scan_quality poor).
- **HTTP/API errors:** Logged to PHP error log with `[NIR360 OcrSpace]` prefix; client returns empty string.
- **Invalid image / unsupported type:** Client returns empty string after logging.
- **API error response:** `IsErroredOnProcessing` or `ParsedResults[0].ErrorMessage` logged; client returns empty string.

## Integration with Registration

1. User submits registration form with full name, birthdate, address, and ID image.
2. Frontend or backend sends the same data to `POST /api/id-ocr/verify`.
3. Use the returned `verification_status` and `match_score` to:
   - Auto-approve when `verified`,
   - Send to manual review when `manual_review`,
   - Reject or ask to re-upload when `rejected` or `scan_quality === "poor"`.

Uploaded images are stored under `id_images_upload_path` with unique names; you can later link them to the user if needed.
