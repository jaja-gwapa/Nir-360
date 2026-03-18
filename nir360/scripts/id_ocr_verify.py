 #!/usr/bin/env python3
"""
Government ID OCR verification script.
Uses pytesseract and OpenCV to extract text from an ID image, normalize it,
and compare with user input (full name, birthdate, address).
Returns JSON with match results, score, OCR confidence, and verification status.

Designed for integration with PHP backend (e.g. via subprocess/exec).
Tesseract path: C:/Program Files/Tesseract-OCR/tesseract.exe
"""

import json
import os
import re
import sys
from datetime import datetime
from pathlib import Path

import cv2
import pytesseract

# Default Tesseract path on Windows
TESSERACT_PATH = r"C:/Program Files/Tesseract-OCR/tesseract.exe"


def normalize_text(text: str) -> str:
    """Convert to uppercase, collapse spaces, remove extra special characters."""
    if not text or not isinstance(text, str):
        return ""
    # Uppercase
    text = text.upper().strip()
    # Replace multiple spaces/newlines with single space
    text = re.sub(r"\s+", " ", text)
    # Remove or reduce problematic special chars for comparison (keep alphanumeric, spaces, basic punctuation)
    text = re.sub(r"[^\w\s\-/.,]", " ", text)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def preprocess_image(image_path: str):
    """Load image with OpenCV, convert to grayscale, apply threshold for better OCR."""
    path = Path(image_path)
    if not path.is_file():
        raise FileNotFoundError(f"Image not found: {image_path}")

    img = cv2.imread(str(path))
    if img is None:
        raise ValueError(f"Could not load image: {image_path}")

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    # Threshold to get binary image (improves OCR on scanned IDs)
    _, thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    return thresh


def extract_text(image_path: str, tesseract_path: str | None = None) -> str:
    """Run Tesseract OCR on preprocessed image and return raw extracted text."""
    if tesseract_path:
        pytesseract.pytesseract.tesseract_cmd = tesseract_path
    img = preprocess_image(image_path)
    text = pytesseract.image_to_string(img)
    return text or ""


def extract_text_with_confidence(image_path: str, tesseract_path: str | None = None):
    """Run pytesseract image_to_data to get per-word confidence. Returns (full_text, list of conf values)."""
    if tesseract_path:
        pytesseract.pytesseract.tesseract_cmd = tesseract_path
    img = preprocess_image(image_path)
    data = pytesseract.image_to_data(img, output_type=pytesseract.Output.DICT)
    # Rebuild full text from 'text' field
    full_text = " ".join(t for t in data["text"] if t.strip())
    confidences = [int(c) for c in data["conf"] if c != "-1"]
    return full_text, confidences


def average_confidence(confidences: list) -> float:
    """Average of confidence values (0-100). Returns 0.0 if empty."""
    if not confidences:
        return 0.0
    return sum(confidences) / len(confidences)


def match_name(user_name: str, ocr_text: str) -> bool:
    """Check if user full name appears in OCR text (normalized) or significant words match."""
    user_n = normalize_text(user_name)
    ocr_n = normalize_text(ocr_text)
    if not user_n:
        return False
    if user_n in ocr_n:
        return True
    words = [w for w in user_n.split() if len(w) > 1]
    if len(words) < 2:
        return user_n in ocr_n or _fuzzy_in(user_n, ocr_n)
    found = sum(1 for w in words if w in ocr_n)
    return found >= min(2, len(words))


def _fuzzy_in(needle: str, haystack: str, min_similarity: float = 70.0) -> bool:
    """Rough substring/similarity check."""
    if not needle or not haystack:
        return False
    if needle in haystack:
        return True
    # Simple: check if most words of needle appear in haystack
    nw = set(needle.split())
    hw = set(haystack.split())
    overlap = len(nw & hw) / len(nw) if nw else 0
    return overlap * 100 >= min_similarity


def normalize_date_to_ymd(s: str) -> str:
    """Normalize date to YYYY-MM-DD. Handles YYYY/MM/DD, YYYY-MM-DD, MM/DD/YYYY, DD/MM/YYYY, DD-MM-YYYY, text months. Removes spaces/special chars."""
    s = re.sub(r"[\s.]+", " ", (s or "").strip())[:50]
    s = s.replace("/", "-")
    if not s:
        return ""
    ymd = _parse_date_to_ymd(s)
    if ymd:
        return ymd
    try:
        dt = datetime.strptime(s, "%Y-%m-%d")
        return dt.strftime("%Y-%m-%d")
    except Exception:
        pass
    return ""


def extract_birthdate_from_ocr(ocr_text: str) -> str:
    """Extract first date-like string from OCR for normalization."""
    patterns = [
        r"\d{4}[/\-]\d{1,2}[/\-]\d{1,2}",
        r"\d{1,2}[/\-]\d{1,2}[/\-]\d{2,4}",
        r"\d{1,2}\s+(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+\d{2,4}",
        r"(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+\d{1,2},?\s+\d{4}",
    ]
    for pat in patterns:
        m = re.search(pat, ocr_text, re.IGNORECASE)
        if m:
            return m.group(0).strip()
    return ""


def match_birthdate_tolerant(user_birthdate_raw: str, ocr_text: str) -> tuple[bool, str, str]:
    """Normalize OCR and user birthdate to YYYY-MM-DD; return (match, ocr_birthdate, input_birthdate)."""
    input_ymd = normalize_date_to_ymd(user_birthdate_raw)
    if not input_ymd:
        return False, "", input_ymd or ""
    extracted = extract_birthdate_from_ocr(ocr_text)
    ocr_ymd = normalize_date_to_ymd(extracted)
    match = bool(ocr_ymd and ocr_ymd == input_ymd)
    _log_birthdate(ocr_text, extracted, ocr_ymd, user_birthdate_raw, input_ymd, match)
    return match, ocr_ymd, input_ymd


def _log_birthdate(raw_ocr: str, extracted: str, normalized_ocr: str, user_raw: str, normalized_input: str, match: bool) -> None:
    """Log birthdate validation for debugging."""
    import sys
    raw_preview = (raw_ocr[:200] + "...") if len(raw_ocr) > 200 else raw_ocr
    print("[OCR birthdate] raw_ocr_preview=" + raw_preview, file=sys.stderr)
    print("[OCR birthdate] extracted_birthdate=" + extracted, file=sys.stderr)
    print("[OCR birthdate] normalized_ocr_birthdate=" + normalized_ocr, file=sys.stderr)
    print("[OCR birthdate] user_input_birthdate_raw=" + user_raw, file=sys.stderr)
    print("[OCR birthdate] normalized_input_birthdate=" + normalized_input, file=sys.stderr)
    print("[OCR birthdate] birthdate_match=" + str(match).lower(), file=sys.stderr)


def match_birthdate(user_birthdate: str, ocr_text: str) -> bool:
    """Check if user birthdate matches OCR (tolerant normalization)."""
    match, _, _ = match_birthdate_tolerant(user_birthdate, ocr_text)
    return match


def _parse_date_to_ymd(s: str) -> str | None:
    """Try to parse a date string to YYYY-MM-DD. Handles YYYY/MM/DD, YYYY-MM-DD, MM/DD/YYYY, DD-MM-YYYY, text months."""
    s = re.sub(r"[\s.]+", " ", (s or "").strip())[:50].replace("/", "-")
    if not s:
        return None
    for fmt in (
        "%Y-%m-%d", "%d-%m-%Y", "%m-%d-%Y", "%d-%m-%y", "%m-%d-%y",
        "%B %d, %Y", "%d %B %Y", "%b %d, %Y", "%d %b %Y",
        "%Y %m %d", "%d %m %Y",
    ):
        try:
            dt = datetime.strptime(s, fmt)
            return dt.strftime("%Y-%m-%d")
        except Exception:
            continue
    return None


def match_address(user_address: str, ocr_text: str) -> bool:
    """Check if user address appears in OCR or fuzzy match."""
    user_a = normalize_text(user_address)
    ocr_n = normalize_text(ocr_text)
    if not user_a:
        return False
    if user_a in ocr_n:
        return True
    return _fuzzy_in(user_a, ocr_n)


def get_verification_status(match_score: int) -> str:
    """Map match score to verification status."""
    if match_score >= 3:
        return "verified"
    if match_score == 2:
        return "manual_review"
    return "rejected"


def verify(
    image_path: str,
    full_name: str,
    birthdate: str,
    address: str,
    tesseract_path: str | None = None,
) -> dict:
    """
    Main verification pipeline.
    Returns dict with: extracted_text, name_match, birthdate_match, address_match,
    match_score, ocr_confidence, verification_status.
    """
    tesseract_path = tesseract_path or TESSERACT_PATH
    pytesseract.pytesseract.tesseract_cmd = tesseract_path

    raw_text, confidences = extract_text_with_confidence(image_path, tesseract_path)
    normalized_text = normalize_text(raw_text)
    ocr_confidence = round(average_confidence(confidences), 2)

    name_match = match_name(full_name or "", normalized_text)
    birthdate_match, ocr_birthdate, input_birthdate = match_birthdate_tolerant(birthdate or "", raw_text or normalized_text)
    address_match = match_address(address or "", normalized_text)

    match_score = (1 if name_match else 0) + (1 if birthdate_match else 0) + (1 if address_match else 0)
    verification_status = get_verification_status(match_score)

    return {
        "extracted_text": normalized_text,
        "name_match": name_match,
        "birthdate_match": birthdate_match,
        "ocr_birthdate": ocr_birthdate,
        "input_birthdate": input_birthdate,
        "address_match": address_match,
        "match_score": match_score,
        "ocr_confidence": ocr_confidence,
        "verification_status": verification_status,
    }


def main():
    """CLI: read image_path, full_name, birthdate, address from argv or stdin JSON; output JSON."""
    tesseract_path = os.environ.get("TESSERACT_PATH", TESSERACT_PATH)

    if len(sys.argv) >= 5:
        image_path = sys.argv[1]
        full_name = sys.argv[2]
        birthdate = sys.argv[3]
        address = sys.argv[4]
    else:
        # Read JSON from stdin: {"image_path": "...", "full_name": "...", "birthdate": "...", "address": "..."}
        try:
            inp = json.load(sys.stdin)
            image_path = inp.get("image_path", "")
            full_name = inp.get("full_name", "")
            birthdate = inp.get("birthdate", "")
            address = inp.get("address", "")
        except Exception as e:
            print(json.dumps({"error": str(e), "usage": "argv: image_path full_name birthdate address | or JSON stdin"}), file=sys.stderr)
            sys.exit(1)

    if not image_path:
        print(json.dumps({"error": "image_path is required"}), file=sys.stderr)
        sys.exit(1)

    try:
        result = verify(image_path, full_name, birthdate, address, tesseract_path)
        print(json.dumps(result, indent=2))
    except FileNotFoundError as e:
        print(json.dumps({"error": str(e)}), file=sys.stderr)
        sys.exit(2)
    except Exception as e:
        print(json.dumps({"error": str(e)}), file=sys.stderr)
        sys.exit(3)


if __name__ == "__main__":
    main()
