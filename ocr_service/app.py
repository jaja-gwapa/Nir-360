"""
Flask OCR Service for Government ID scanning.
Designed to be called from PHP via cURL (POST multipart with ID image).
Endpoint: POST /scan-id
Returns: JSON with name, birthdate, address, id_number, raw_text.
Uses OpenCV ID template detection to crop Name, Birthdate, Address regions.
Runs on http://127.0.0.1:5000
"""

import os
import re

import cv2
import numpy as np
import pytesseract
from flask import Flask, request, jsonify

from id_templates import DEFAULT_TEMPLATE, TEMPLATES
from philsys_scanner import scan_philid_image

# -----------------------------------------------------------------------------
# Configuration
# -----------------------------------------------------------------------------
# Tesseract executable path; set TESSERACT_PATH env var to override
_default_tesseract = r"C:/Program Files/Tesseract-OCR/tesseract.exe"
pytesseract.pytesseract.tesseract_cmd = os.environ.get("TESSERACT_PATH", _default_tesseract)

app = Flask(__name__)
# Max content length for uploads (e.g. 10MB)
app.config["MAX_CONTENT_LENGTH"] = 10 * 1024 * 1024

# Allowed extensions for ID image
ALLOWED_EXTENSIONS = {"png", "jpg", "jpeg"}


def allowed_file(filename: str) -> bool:
    """Check if filename has an allowed image extension."""
    if not filename or "." not in filename:
        return False
    return filename.rsplit(".", 1)[-1].lower() in ALLOWED_EXTENSIONS


# -----------------------------------------------------------------------------
# Image preprocessing (OpenCV)
# -----------------------------------------------------------------------------
def preprocess_image(image_array: np.ndarray) -> np.ndarray:
    """
    Preprocess ID image for better OCR accuracy.
    Steps: grayscale -> denoise -> threshold.
    """
    # Step 1: Convert to grayscale (reduces noise, standard for OCR)
    if len(image_array.shape) == 3:
        gray = cv2.cvtColor(image_array, cv2.COLOR_BGR2GRAY)
    else:
        gray = image_array.copy()

    # Step 2: Remove noise (improves text clarity for Tesseract)
    denoised = cv2.fastNlMeansDenoising(gray, None, h=10, templateWindowSize=7, searchWindowSize=21)

    # Step 3: Apply binary threshold (Otsu) for clear text vs background
    _, thresh = cv2.threshold(denoised, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)

    return thresh


# -----------------------------------------------------------------------------
# OpenCV ID template: crop specific areas (Name, Birthdate, Address)
# -----------------------------------------------------------------------------
def crop_region(image: np.ndarray, x_ratio: float, y_ratio: float, w_ratio: float, h_ratio: float) -> np.ndarray:
    """
    Crop a region from the image using relative coordinates (0-1).
    Returns the cropped region as a numpy array.
    """
    h_img, w_img = image.shape[:2]
    x = int(x_ratio * w_img)
    y = int(y_ratio * h_img)
    w = int(w_ratio * w_img)
    h = int(h_ratio * h_img)
    # Clamp to image bounds
    x = max(0, min(x, w_img - 1))
    y = max(0, min(y, h_img - 1))
    w = max(1, min(w, w_img - x))
    h = max(1, min(h, h_img - y))
    return image[y : y + h, x : x + w].copy()


def crop_template_regions(image: np.ndarray, template: dict) -> dict[str, np.ndarray]:
    """
    Crop Name, Birthdate, and Address regions from the ID image using the template.
    Returns dict with keys 'name', 'birthdate', 'address', each value a cropped ROI (numpy array).
    """
    regions = {}
    for field, (x, y, w, h) in template.items():
        regions[field] = crop_region(image, x, y, w, h)
    return regions


def extract_text_from_region(region_image: np.ndarray) -> str:
    """Preprocess a single region (grayscale, denoise, threshold) and run OCR. Returns cleaned text."""
    processed = preprocess_image(region_image)
    text = pytesseract.image_to_string(processed)
    return (text or "").strip()


def extract_with_template(image_array: np.ndarray, template: dict) -> dict[str, str]:
    """
    Crop template regions (name, birthdate, address), run OCR on each, return extracted text per field.
    """
    result = {"name": "", "birthdate": "", "address": ""}
    try:
        regions = crop_template_regions(image_array, template)
        for field in ("name", "birthdate", "address"):
            if field in regions:
                result[field] = extract_text_from_region(regions[field])
                # Normalize: single line, collapse spaces
                result[field] = " ".join(result[field].split())
    except Exception:
        pass
    return result


# -----------------------------------------------------------------------------
# OCR text extraction (full image)
# -----------------------------------------------------------------------------
def extract_raw_text(image_array: np.ndarray) -> str:
    """Run pytesseract on preprocessed full image and return raw text."""
    processed = preprocess_image(image_array)
    text = pytesseract.image_to_string(processed)
    return (text or "").strip()


# -----------------------------------------------------------------------------
# Parse extracted text into structured fields (heuristic; ready for template)
# -----------------------------------------------------------------------------
def parse_id_fields(raw_text: str) -> dict:
    """
    Extract name, birthdate, address, id_number from raw OCR text using patterns.
    Designed for future ID template matching: replace with crop-by-region logic.
    """
    result = {"name": "", "birthdate": "", "address": "", "id_number": "", "raw_text": raw_text or ""}
    if not raw_text:
        return result

    text = raw_text.upper()
    lines = [ln.strip() for ln in raw_text.splitlines() if ln.strip()]

    # --- ID number: look for digit groups (e.g. 4750-3265-7605-4817 or similar)
    id_match = re.search(r"\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b", raw_text)
    if id_match:
        result["id_number"] = id_match.group(0).strip()

    # --- Birthdate: common date patterns
    date_patterns = [
        r"(?:DATE OF BIRTH|BIRTHDATE|PETSA NG KAPANGANAKAN|DOB)[:\s]*([^\n]+)",
        r"\b(\d{1,2}[/\-]\d{1,2}[/\-]\d{2,4})\b",
        r"\b(\d{4}[/\-]\d{1,2}[/\-]\d{1,2})\b",
        r"\b((?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+\d{1,2},?\s+\d{2,4})\b",
        r"\b(\d{1,2}\s+(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+\d{2,4})\b",
    ]
    for pat in date_patterns:
        m = re.search(pat, raw_text, re.IGNORECASE)
        if m:
            result["birthdate"] = m.group(1).strip()
            break

    # --- Name: look for LAST NAME / GIVEN NAMES / FULL NAME lines
    name_patterns = [
        r"(?:LAST NAME|APELYIDO)[:\s]*([^\n]+)",
        r"(?:GIVEN NAMES|MGA PANGALAN|FIRST NAME)[:\s]*([^\n]+)",
        r"(?:FULL NAME|NAME)[:\s]*([^\n]+)",
    ]
    name_parts = []
    for pat in name_patterns:
        m = re.search(pat, raw_text, re.IGNORECASE)
        if m:
            name_parts.append(m.group(1).strip())
    if name_parts:
        result["name"] = " ".join(name_parts)
    else:
        # Fallback: first non-empty line that looks like a name (letters and spaces)
        for line in lines:
            if re.match(r"^[A-Za-z\s\.\-]+$", line) and len(line) > 2 and not line.isdigit():
                result["name"] = line
                break

    # --- Address: look for ADDRESS / TIRAHAN / RESIDENCE
    addr_patterns = [
        r"(?:ADDRESS|TIRAHAN|RESIDENCE|TIRAHAAN)[:\s]*([^\n]+(?:\n[^\n]+)*)",
        r"(?:STREET|BARANGAY|CITY)[:\s]*([^\n]+)",
    ]
    for pat in addr_patterns:
        m = re.search(pat, raw_text, re.IGNORECASE)
        if m:
            result["address"] = m.group(1).strip().replace("\n", " ")
            break

    return result


# -----------------------------------------------------------------------------
# API: POST /scan-id
# -----------------------------------------------------------------------------
@app.route("/scan-id", methods=["POST"])
def scan_id():
    """
    Receive an uploaded ID image, preprocess with OpenCV, run OCR with pytesseract,
    parse fields, and return JSON. PHP can send the file via cURL (multipart).
    """
    # --- 1. Check that a file was uploaded
    if "image" not in request.files and "id_image" not in request.files and "file" not in request.files:
        return jsonify({
            "success": False,
            "error": "No image uploaded. Send the file as 'image', 'id_image', or 'file' in multipart/form-data.",
        }), 400

    file = request.files.get("image") or request.files.get("id_image") or request.files.get("file")
    if not file or file.filename == "":
        return jsonify({"success": False, "error": "No image selected or empty filename."}), 400

    if not allowed_file(file.filename):
        return jsonify({
            "success": False,
            "error": "Invalid file type. Allowed: PNG, JPG, JPEG.",
        }), 400

    try:
        # --- 2. Read file into bytes and decode with OpenCV
        file_bytes = np.frombuffer(file.read(), dtype=np.uint8)
        image_array = cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)
        if image_array is None:
            return jsonify({"success": False, "error": "Could not decode image. Use a valid PNG or JPEG."}), 400

        # --- 3. OpenCV ID template: crop Name, Birthdate, Address regions and run OCR on each
        template_name = request.args.get("template", "philid").lower()
        template = TEMPLATES.get(template_name, DEFAULT_TEMPLATE)
        template_result = extract_with_template(image_array, template)

        # --- 4. Full-image OCR for raw_text and id_number (and fallback for name/birthdate/address)
        raw_text = extract_raw_text(image_array)
        parsed = parse_id_fields(raw_text)

        # --- 5. Merge: prefer template-extracted fields when non-empty; else use full-image parse
        result = {
            "name": template_result.get("name") or parsed.get("name", ""),
            "birthdate": template_result.get("birthdate") or parsed.get("birthdate", ""),
            "address": template_result.get("address") or parsed.get("address", ""),
            "id_number": parsed.get("id_number", ""),
            "raw_text": raw_text,
        }
        result["success"] = True
        return jsonify(result)

    except Exception as e:
        return jsonify({"success": False, "error": str(e), "name": "", "birthdate": "", "address": "", "id_number": "", "raw_text": ""}), 500


# -----------------------------------------------------------------------------
# API: POST /scan-philid (PhilSys template-based scanner for PHP integration)
# -----------------------------------------------------------------------------
@app.route("/scan-philid", methods=["POST"])
def scan_philid():
    """
    Accept an uploaded ID image, run PhilSys template-based OCR (resize, crop
    name/birthdate/address regions, OCR each, clean). Return JSON: name, birthdate, address.
    """
    if "image" not in request.files and "id_image" not in request.files and "file" not in request.files:
        return jsonify({
            "success": False,
            "error": "No image uploaded. Send the file as 'image', 'id_image', or 'file' in multipart/form-data.",
            "name": "", "birthdate": "", "address": "", "raw_text": "",
        }), 400

    file = request.files.get("image") or request.files.get("id_image") or request.files.get("file")
    if not file or file.filename == "":
        return jsonify({"success": False, "error": "No image selected.", "name": "", "birthdate": "", "address": "", "raw_text": ""}), 400

    if not allowed_file(file.filename):
        return jsonify({
            "success": False,
            "error": "Invalid file type. Allowed: PNG, JPG, JPEG.",
            "name": "", "birthdate": "", "address": "", "raw_text": "",
        }), 400

    try:
        file_bytes = np.frombuffer(file.read(), dtype=np.uint8)
        image_array = cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)
        if image_array is None:
            return jsonify({"success": False, "error": "Could not decode image.", "name": "", "birthdate": "", "address": "", "raw_text": ""}), 400

        result = scan_philid_image(image_array)
        try:
            result["raw_text"] = extract_raw_text(image_array)
        except Exception:
            result["raw_text"] = ""
        result["success"] = True
        return jsonify(result)
    except Exception as e:
        return jsonify({
            "success": False,
            "error": str(e),
            "name": "", "birthdate": "", "address": "", "raw_text": "",
        }), 500


# -----------------------------------------------------------------------------
# Health check (optional; useful for PHP to verify service is up)
# -----------------------------------------------------------------------------
@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "service": "ocr_service"})


# -----------------------------------------------------------------------------
# Run server on http://127.0.0.1:5000
# -----------------------------------------------------------------------------
if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=True)
