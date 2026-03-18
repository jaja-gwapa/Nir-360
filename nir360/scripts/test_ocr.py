#!/usr/bin/env python3
"""
Test script for Government ID OCR verification.
Run with no args: creates a sample image with text and runs OCR (expect 3/3 match).
Run with image path: tests OCR on your own image and optional name/birthdate/address.
"""

import json
import sys
from pathlib import Path

# Add script dir so we can import id_ocr_verify
SCRIPT_DIR = Path(__file__).resolve().parent
sys.path.insert(0, str(SCRIPT_DIR))

import cv2
import numpy as np

from id_ocr_verify import verify, TESSERACT_PATH


def create_sample_id_image(output_path: str) -> str:
    """Create a simple test image with name, birthdate, address (ID-like text)."""
    # White background, ID-card like size
    w, h = 640, 400
    img = np.ones((h, w, 3), dtype=np.uint8) * 255

    font = cv2.FONT_HERSHEY_SIMPLEX
    scale = 0.7
    thickness = 2
    color = (0, 0, 0)

    lines = [
        "GOVERNMENT ID - SAMPLE",
        "FULL NAME: JUAN DELA CRUZ",
        "BIRTHDATE: 1990-01-15",
        "ADDRESS: 123 MAIN STREET MANILA",
    ]
    y = 60
    for line in lines:
        cv2.putText(img, line, (30, y), font, scale, color, thickness, cv2.LINE_AA)
        y += 50

    Path(output_path).parent.mkdir(parents=True, exist_ok=True)
    cv2.imwrite(output_path, img)
    return output_path


def main():
    if len(sys.argv) >= 2:
        # User provided image path
        image_path = sys.argv[1]
        full_name = sys.argv[2] if len(sys.argv) > 2 else ""
        birthdate = sys.argv[3] if len(sys.argv) > 3 else ""
        address = sys.argv[4] if len(sys.argv) > 4 else ""
        if not Path(image_path).is_file():
            print(json.dumps({"error": f"File not found: {image_path}"}), file=sys.stderr)
            sys.exit(1)
    else:
        # Create sample image and test with matching data
        sample_path = SCRIPT_DIR / "test_sample_id.jpg"
        create_sample_id_image(str(sample_path))
        image_path = str(sample_path)
        full_name = "Juan Dela Cruz"
        birthdate = "1990-01-15"
        address = "123 Main Street Manila"
        print("Using generated sample image. Matching data: Juan Dela Cruz, 1990-01-15, 123 Main Street Manila", file=sys.stderr)

    try:
        result = verify(image_path, full_name, birthdate, address, TESSERACT_PATH)
        print(json.dumps(result, indent=2))
    except FileNotFoundError as e:
        print(json.dumps({"error": str(e)}), file=sys.stderr)
        sys.exit(2)
    except Exception as e:
        print(json.dumps({"error": str(e)}), file=sys.stderr)
        sys.exit(3)


if __name__ == "__main__":
    main()
