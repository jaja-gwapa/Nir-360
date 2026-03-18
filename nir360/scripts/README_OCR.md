# Government ID OCR Verification (Python)

Python script for OCR verification of government ID images using **pytesseract** and **OpenCV**. Extracts text, normalizes it, and compares with user input (full name, birthdate, address). Returns JSON with match results and verification status.

## Requirements

- Python 3.10+
- **Tesseract OCR** installed at: `C:/Program Files/Tesseract-OCR/tesseract.exe`  
  Override with environment variable: `TESSERACT_PATH`

## Install dependencies

```bash
pip install -r requirements-ocr.txt
```

Or:

```bash
pip install pytesseract opencv-python
```

## See extracted text in CMD (raw OCR from ID image)

To **only view the text** that OCR reads from an ID image in the command line:

### Option 1: Script that prints raw text only

```cmd
cd c:\xampp2\htdocs\capstone_project\nir360\scripts
python extract_id_text.py "c:\path\to\your\id.png"
```

This prints the **extracted text** directly in the CMD window. Use `--json` to get JSON with `raw_text`:

```cmd
python extract_id_text.py "c:\path\to\your\id.png" --json
```

### Option 2: Full verification JSON (includes `extracted_text`)

```cmd
cd c:\xampp2\htdocs\capstone_project\nir360\scripts
python id_ocr_verify.py "c:\path\to\id.png" "Full Name" "2002-06-17" "Address"
```

The output JSON includes an **`extracted_text`** field (normalized full OCR text). Copy the path and replace the name/birthdate/address as on your ID.

### Option 3: Tesseract directly (no Python preprocessing)

If Tesseract is installed and on your PATH:

```cmd
"C:\Program Files\Tesseract-OCR\tesseract.exe" "c:\path\to\id.png" stdout
```

This prints raw OCR text to the console. No cropping or preprocessing.

---

## Test the OCR

From the `scripts` folder:

```bash
# Test with a generated sample image (no real ID needed)
python test_ocr.py
```

This creates `test_sample_id.jpg` with sample text, runs verification with matching name/birthdate/address, and prints JSON. You should see `match_score: 3` and `verification_status: "verified"`.

### Test with a real ID

1. **Put your ID image** in the `scripts` folder. Supported formats: JPEG (`.jpg`, `.jpeg`) or PNG (`.png`). You can use any filename, e.g. `my_id.jpg`.

2. **Run the test** with the image path and the name, birthdate, and address **exactly as they appear on the ID** (or very close) so the OCR can match them:

   ```bash
   cd c:\xampp2\htdocs\capstone_project\nir360\scripts
   python test_ocr.py "my_id.jpg" "FULL NAME ON ID" "1990-01-15" "ADDRESS ON ID"
   ```

   Replace:
   - `my_id.jpg` with your image filename (or full path).
   - `FULL NAME ON ID` with the name as shown on the ID.
   - `1990-01-15` with the birthdate (YYYY-MM-DD or same format as on the ID).
   - `ADDRESS ON ID` with the address as shown on the ID.

3. **Example** (if your file is `my_id.jpg` in the scripts folder):

   ```bash
   python test_ocr.py my_id.jpg "Juan Dela Cruz" "January 15, 1990" "123 Main St, Manila"
   ```

   The script prints JSON with `extracted_text`, `name_match`, `birthdate_match`, `address_match`, `match_score` (0–3), `ocr_confidence`, and `verification_status` (`verified` / `manual_review` / `rejected`).

**Tip:** For best OCR results, use a clear photo or scan of the ID (good lighting, minimal glare). Crop to the ID area if possible.

## Usage

### Command-line (arguments)

```bash
python id_ocr_verify.py <image_path> "<full_name>" "<birthdate>" "<address>"
```

Example:

```bash
python id_ocr_verify.py "C:/uploads/id_123.jpg" "Juan Dela Cruz" "1990-01-15" "123 Main St, Manila"
```

### Command-line (JSON stdin)

```bash
echo {"image_path": "C:/path/to/id.png", "full_name": "Jane Doe", "birthdate": "1985-06-20", "address": "456 Oak Ave"} | python id_ocr_verify.py
```

### From PHP

```php
$image_path = escapeshellarg($savedPath);
$full_name = escapeshellarg($userInput['full_name']);
$birthdate = escapeshellarg($userInput['birthdate']);
$address = escapeshellarg($userInput['address']);
$script = 'C:/path/to/nir360/scripts/id_ocr_verify.py';
$cmd = "python " . $script . " $image_path $full_name $birthdate $address 2>&1";
$json = shell_exec($cmd);
$result = json_decode($json, true);
```

Or with JSON input:

```php
$input = json_encode([
    'image_path' => $savedPath,
    'full_name' => $userInput['full_name'],
    'birthdate' => $userInput['birthdate'],
    'address' => $userInput['address'],
]);
$proc = proc_open(
    'python C:/path/to/nir360/scripts/id_ocr_verify.py',
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
fwrite($pipes[0], $input);
fclose($pipes[0]);
$json = stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);
$result = json_decode($json, true);
```

## Output (JSON)

| Field | Type | Description |
|-------|------|-------------|
| `extracted_text` | string | Normalized OCR text (uppercase, extra spaces removed). |
| `name_match` | boolean | True if full name matches OCR. |
| `birthdate_match` | boolean | True if birthdate matches OCR. |
| `address_match` | boolean | True if address matches OCR. |
| `match_score` | int (0–3) | Name + birthdate + address match count. |
| `ocr_confidence` | float | Average OCR confidence (0–100). |
| `verification_status` | string | `verified` \| `manual_review` \| `rejected`. |

**Verification rules:** 3/3 → verified, 2/3 → manual_review, 0–1 → rejected.

## Flow

1. Load image with OpenCV.
2. Convert to grayscale and apply Otsu threshold for better OCR.
3. Run `pytesseract.image_to_string` for extracted text and `image_to_data` for confidence.
4. Normalize text: uppercase, collapse spaces, strip problematic special characters.
5. Compare user full name, birthdate, and address with normalized OCR text.
6. Compute match score (0–3) and verification status.
7. Return JSON result.
