"""
Template-based OCR scanner for Philippine National ID (PhilSys).
Uses OpenCV for load, resize, and region crop; pytesseract for OCR.
Returns JSON-ready dict: name, birthdate, address for PHP integration.
"""

import json
import os
import re
import sys
from datetime import datetime

import cv2
import numpy as np
import pytesseract

# Tesseract path (Windows default)
TESSERACT_PATH = os.environ.get("TESSERACT_PATH", r"C:/Program Files/Tesseract-OCR/tesseract.exe")

# Standard resolution for consistent cropping (PhilID aspect ~1.586)
STANDARD_WIDTH = 900
STANDARD_HEIGHT = 568  # ~900/1.586

# PhilSys ID region definitions (x, y, width, height) as fraction of image dimensions
# Layout: photo left ~28%; name (Last/Given/Middle), then birthdate, then address
# Address region extended to capture full line including "CITY OF BAGO, NEGROS OCCIDENTAL"
PHILID_REGIONS = {
    "name": (0.28, 0.14, 0.70, 0.24),      # Apelyido, Mga Pangalan, Gitnang Apelyido
    "birthdate": (0.28, 0.32, 0.70, 0.24), # Petsa ng Kapanganakan – taller band for variation
    "address": (0.28, 0.50, 0.70, 0.45),   # Tirahan / Address block (taller to include city/province)
}
# Fallback: combined name+birthdate region to find date if birthdate crop misses it
PHILID_NAME_AND_BIRTHDATE_REGION = (0.28, 0.12, 0.70, 0.48)


def set_tesseract_path(path: str) -> None:
    """Set Tesseract executable path."""
    pytesseract.pytesseract.tesseract_cmd = path


def load_image(image_path: str) -> np.ndarray:
    """Load ID image from file path. Raises FileNotFoundError or ValueError if invalid."""
    if not os.path.isfile(image_path):
        raise FileNotFoundError(f"Image not found: {image_path}")
    img = cv2.imread(image_path)
    if img is None:
        raise ValueError(f"Could not decode image: {image_path}")
    return img


def resize_to_standard(image: np.ndarray) -> np.ndarray:
    """Resize image to standard resolution for consistent region cropping."""
    h, w = image.shape[:2]
    return cv2.resize(image, (STANDARD_WIDTH, STANDARD_HEIGHT), interpolation=cv2.INTER_AREA)


def crop_region(image: np.ndarray, x_ratio: float, y_ratio: float, w_ratio: float, h_ratio: float) -> np.ndarray:
    """Crop a region using relative coordinates (0-1)."""
    h_img, w_img = image.shape[:2]
    x = max(0, min(int(x_ratio * w_img), w_img - 1))
    y = max(0, min(int(y_ratio * h_img), h_img - 1))
    w = max(1, min(int(w_ratio * w_img), w_img - x))
    h = max(1, min(int(h_ratio * h_img), h_img - y))
    return image[y : y + h, x : x + w].copy()


def preprocess_for_ocr(region: np.ndarray) -> np.ndarray:
    """Grayscale, denoise, and threshold a region for better OCR."""
    if len(region.shape) == 3:
        gray = cv2.cvtColor(region, cv2.COLOR_BGR2GRAY)
    else:
        gray = region.copy()
    denoised = cv2.fastNlMeansDenoising(gray, None, h=10, templateWindowSize=7, searchWindowSize=21)
    _, thresh = cv2.threshold(denoised, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    return thresh


def run_ocr_on_region(region: np.ndarray, preprocess: bool = True) -> str:
    """Run pytesseract on a region. If preprocess=True, use grayscale+denoise+Otsu; else grayscale only."""
    if preprocess:
        processed = preprocess_for_ocr(region)
    else:
        if len(region.shape) == 3:
            processed = cv2.cvtColor(region, cv2.COLOR_BGR2GRAY)
        else:
            processed = region.copy()
    return pytesseract.image_to_string(processed)


def clean_text(text: str) -> str:
    """Remove extra spaces and line breaks; trim."""
    if not text:
        return ""
    text = re.sub(r"[\r\n]+", " ", text)
    text = re.sub(r"\s+", " ", text)
    return text.strip()


# 3-letter month to number (locale-independent for PhilID "JUNE 24, 2003")
_MONTH3_TO_NUM = {
    "jan": "01", "feb": "02", "mar": "03", "apr": "04", "may": "05",
    "jun": "06", "jul": "07", "aug": "08", "sep": "09", "oct": "10",
    "nov": "11", "dec": "12",
}


def normalize_birthdate_to_ymd(raw: str) -> str:
    """Normalize birthdate string to YYYY-MM-DD. Handles JUNE 24, 2003 and similar."""
    raw = clean_text(raw)
    if not raw:
        return ""
    # Normalize separators and length
    raw = re.sub(r"[\s./]+", " ", raw).strip()[:60]
    # Try regex for "MONTH DD, YYYY" or "MONTH DD YYYY" (PhilID format; OCR may drop comma)
    month_name = r"(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*"
    m = re.search(rf"({month_name})\s+(\d{{1,2}}),?\s+(\d{{4}})", raw, re.IGNORECASE)
    if m:
        month3 = m.group(1).strip().lower()[:3]
        num = _MONTH3_TO_NUM.get(month3)
        if num:
            day = m.group(2).strip().zfill(2)
            year = m.group(3).strip()
            return f"{year}-{num}-{day}"
        # Fallback: title-case and use strptime (locale-dependent)
        try:
            s = f"{m.group(1).title()} {m.group(2)} {m.group(3)}"
            for fmt in ("%B %d %Y", "%b %d %Y"):
                try:
                    dt = datetime.strptime(s, fmt)
                    return dt.strftime("%Y-%m-%d")
                except ValueError:
                    continue
        except Exception:
            pass
    formats = [
        "%Y-%m-%d", "%Y/%m/%d", "%d-%m-%Y", "%d/%m/%Y", "%m-%d-%Y", "%m/%d/%Y",
        "%B %d, %Y", "%B %d %Y", "%d %B %Y", "%b %d, %Y", "%b %d %Y", "%d %b %Y",
        "%d-%m-%y", "%m-%d-%y",
    ]
    raw_normalized = raw.replace("/", "-").replace(".", " ").replace(",", " ")
    raw_normalized = re.sub(r"\s+", " ", raw_normalized).strip()
    for fmt in formats:
        try:
            dt = datetime.strptime(raw_normalized, fmt)
            return dt.strftime("%Y-%m-%d")
        except ValueError:
            continue
    return ""


def _extract_birthdate_from_text(text: str) -> str:
    """Try to get YYYY-MM-DD from any text containing a PhilID-style date (e.g. JUNE 24, 2003)."""
    if not text or not text.strip():
        return ""
    ymd = normalize_birthdate_to_ymd(text)
    if ymd:
        return ymd
    # Try raw OCR that might have extra chars: e.g. "JUNE 24, 2003" with newlines
    month_name = r"(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*"
    m = re.search(rf"({month_name})\s+(\d{{1,2}}),?\s*(\d{{4}})", text, re.IGNORECASE | re.DOTALL)
    if m:
        month3 = m.group(1).strip().lower()[:3]
        num = _MONTH3_TO_NUM.get(month3)
        if num:
            return f"{m.group(3).strip()}-{num}-{m.group(2).strip().zfill(2)}"
    return ""


def scan_philid_image(image_array: np.ndarray, tesseract_path: str | None = None) -> dict:
    """
    Run template-based OCR on a Philippine National ID image (numpy array from OpenCV).
    Steps: resize to standard -> crop name/birthdate/address -> OCR each -> clean.
    If birthdate region returns empty, runs OCR on name+birthdate combined region as fallback.
    Returns dict with keys: name, birthdate, address (JSON-ready for PHP).
    """
    if tesseract_path:
        set_tesseract_path(tesseract_path)
    if image_array is None or image_array.size == 0:
        return {"name": "", "birthdate": "", "address": ""}
    img = resize_to_standard(image_array)
    result = {"name": "", "birthdate": "", "address": ""}
    for field, (x, y, w, h) in PHILID_REGIONS.items():
        region = crop_region(img, x, y, w, h)
        raw = run_ocr_on_region(region)
        cleaned = clean_text(raw)
        if field == "birthdate":
            result[field] = _extract_birthdate_from_text(cleaned) or normalize_birthdate_to_ymd(cleaned) or cleaned
            if not result[field] or not normalize_birthdate_to_ymd(result[field]):
                raw_no_preprocess = run_ocr_on_region(region, preprocess=False)
                result[field] = _extract_birthdate_from_text(raw_no_preprocess) or result[field]
        else:
            result[field] = cleaned.upper() if cleaned else ""

    # Fallback: if birthdate still empty, OCR a larger name+birthdate region and search for date
    if not result["birthdate"] or not normalize_birthdate_to_ymd(result["birthdate"]):
        x, y, w, h = PHILID_NAME_AND_BIRTHDATE_REGION
        fallback_region = crop_region(img, x, y, w, h)
        fallback_raw = run_ocr_on_region(fallback_region)
        extracted = _extract_birthdate_from_text(fallback_raw)
        if extracted:
            result["birthdate"] = extracted

    # Ensure birthdate is YYYY-MM-DD when we have a parseable value
    if result["birthdate"]:
        ymd = normalize_birthdate_to_ymd(result["birthdate"])
        if ymd:
            result["birthdate"] = ymd

    return result


def scan_philid(image_path: str, tesseract_path: str | None = None) -> dict:
    """
    Run template-based OCR on a Philippine National ID image file.
    Loads image from path, then delegates to scan_philid_image().
    Returns dict with keys: name, birthdate, address (JSON-ready for PHP).
    """
    if tesseract_path:
        set_tesseract_path(tesseract_path)
    img = load_image(image_path)
    return scan_philid_image(img, tesseract_path=None)


def main() -> None:
    """CLI: python philsys_scanner.py <image_path> [tesseract_path]. Outputs JSON to stdout."""
    set_tesseract_path(TESSERACT_PATH)
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: philsys_scanner.py <image_path> [tesseract_path]"}), file=sys.stderr)
        sys.exit(1)
    image_path = sys.argv[1].strip()
    tesseract_path = sys.argv[2].strip() if len(sys.argv) > 2 else None
    try:
        out = scan_philid(image_path, tesseract_path)
        print(json.dumps(out, indent=2))
    except FileNotFoundError as e:
        print(json.dumps({"error": str(e), "name": "", "birthdate": "", "address": ""}), file=sys.stderr)
        sys.exit(2)
    except Exception as e:
        print(json.dumps({"error": str(e), "name": "", "birthdate": "", "address": ""}), file=sys.stderr)
        sys.exit(3)


if __name__ == "__main__":
    main()
