# OCR Service (Flask)

Python Flask API for government ID OCR. Receives an uploaded ID image, preprocesses it with OpenCV, extracts text with Tesseract, and returns structured JSON. Designed to be called from PHP via cURL.

## Requirements

- Python 3.10+
- **Tesseract OCR** installed (e.g. `C:\Program Files\Tesseract-OCR\tesseract.exe` on Windows)
- Install Python deps: `pip install -r requirements.txt`

## Run the server

```bash
cd ocr_service
python app.py
```

Server runs at **http://127.0.0.1:5000**.

## API

### POST /scan-id

- **Content-Type:** `multipart/form-data`
- **Body:** One file field named `image`, `id_image`, or `file` (ID image, PNG or JPEG).
- **Query (optional):** `template=philid` (default) or `template=generic` to choose ID layout for cropping.
- **OpenCV template:** The service crops **Name**, **Birthdate**, and **Address** regions from the ID using the selected template, runs OCR on each region, then merges with full-image OCR for `id_number` and `raw_text`.

**Success response (200):**

```json
{
  "success": true,
  "name": "JULIO MARTINEZ VARGAS",
  "birthdate": "JUNE 17, 2002",
  "address": "BUCROZ GAMAY, BARANGAY II, CITY OF LA CARLOTA, NEGROS OCCIDENTAL",
  "id_number": "4750-3265-7605-4817",
  "raw_text": "..."
}
```

**Error (400/500):**

```json
{
  "success": false,
  "error": "No image uploaded. Send the file as 'image', 'id_image', or 'file' in multipart/form-data."
}
```

### POST /scan-philid (PhilSys template-based)

- **Content-Type:** `multipart/form-data`
- **Body:** One file field named `image`, `id_image`, or `file` (PhilSys ID image, PNG or JPEG).
- **Behavior:** Loads image, resizes to a standard resolution, crops only **Full Name**, **Birthdate**, and **Address** regions (PhilID layout), runs OCR on each region, cleans text and normalizes birthdate to `YYYY-MM-DD`. Returns minimal JSON for PHP integration.

**Success response (200):**

```json
{
  "success": true,
  "name": "JUAN DELA CRUZ",
  "birthdate": "1999-05-12",
  "address": "CEBU CITY"
}
```

Use the same cURL pattern as `/scan-id` but with URL `http://127.0.0.1:5000/scan-philid` and read `name`, `birthdate`, `address` from the response.

### GET /health

Returns `{"status": "ok", "service": "ocr_service"}`. Use from PHP to check if the OCR service is running.

---

## How PHP can send the ID image to the OCR API

### Option 1: cURL with a saved temp file (recommended)

After the user uploads an ID image in your PHP registration form, you have a tmp path (e.g. `$_FILES['id_front']['tmp_name']`). Send it to the Flask API with cURL:

```php
<?php
/**
 * Send uploaded ID image to OCR service and get JSON result.
 * OCR service must be running: python app.py (http://127.0.0.1:5000)
 */
function callOcrScanId(string $uploadedFilePath): array {
    $apiUrl = 'http://127.0.0.1:5000/scan-id';

    if (!is_file($uploadedFilePath) || !is_readable($uploadedFilePath)) {
        return ['success' => false, 'error' => 'Invalid or unreadable image file.'];
    }

    $cfile = new CURLFile($uploadedFilePath, mime_content_type($uploadedFilePath), basename($uploadedFilePath));
    $post = ['image' => $cfile];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, []);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'OCR service request failed.'];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid JSON from OCR service.'];
    }

    return is_array($data) ? $data : ['success' => false, 'error' => 'Unexpected response.'];
}

// Example: from registration form after upload
// $tmpPath = $_FILES['id_front']['tmp_name'];
// $ocrResult = callOcrScanId($tmpPath);
// if (!empty($ocrResult['success']) && $ocrResult['success']) {
//     $name = $ocrResult['name'] ?? '';
//     $birthdate = $ocrResult['birthdate'] ?? '';
//         $address = $ocrResult['address'] ?? '';
//     $id_number = $ocrResult['id_number'] ?? '';
//     $raw_text = $ocrResult['raw_text'] ?? '';
// }
```

### Option 2: cURL with file field name `id_image`

If your form uses `id_image`:

```php
$cfile = new CURLFile($uploadedFilePath, mime_content_type($uploadedFilePath), 'id_front.jpg');
$post = ['id_image' => $cfile];
// ... same curl options, POST to http://127.0.0.1:5000/scan-id
```

### Option 3: Check if OCR service is running before calling

```php
$ch = curl_init('http://127.0.0.1:5000/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($code !== 200 || empty($body)) {
    // OCR service not available; skip OCR or show message
}
```

### Integration in NIR360 registration flow

1. User submits registration with ID front image.
2. PHP saves the upload (or keeps tmp file).
3. PHP calls `callOcrScanId($_FILES['id_front']['tmp_name'])`.
4. Use returned `name`, `birthdate`, `address`, `id_number` to prefill or compare with form data, and store `raw_text` or parsed fields in DB if needed.

---

## ID template detection (OpenCV)

The service crops **Name**, **Birthdate**, and **Address** regions from the ID using a template:

1. Templates live in `id_templates.py` (relative 0-1 regions). Default: PhilID. Use `?template=generic` for generic layout.
2. Define regions for “name”, “birthdate”, “address”, “id_number” per ID type (e.g. PhilID, driver’s license).
3. Template results are merged with full-image OCR for id_number and raw_text. To add a new ID type, add a template in id_templates.py and use ?template=your_type.
