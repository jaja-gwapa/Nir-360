#!/usr/bin/env python3
"""
Print extracted (OCR) text from an ID image to the console.
Usage: python extract_id_text.py <image_path>
       python extract_id_text.py <image_path> --json   (output JSON with raw_text)
"""

import json
import sys
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
sys.path.insert(0, str(SCRIPT_DIR))

from id_ocr_verify import extract_text, TESSERACT_PATH


def main():
    if len(sys.argv) < 2:
        print("Usage: python extract_id_text.py <image_path> [--json]", file=sys.stderr)
        print("Example: python extract_id_text.py c:\\path\\to\\id.png", file=sys.stderr)
        sys.exit(1)
    image_path = sys.argv[1]
    output_json = "--json" in sys.argv
    if not Path(image_path).is_file():
        print(f"Error: File not found: {image_path}", file=sys.stderr)
        sys.exit(2)
    try:
        raw_text = extract_text(image_path, TESSERACT_PATH)
        if output_json:
            print(json.dumps({"raw_text": raw_text, "image_path": image_path}, indent=2))
        else:
            print("--- Extracted text from ID (raw OCR) ---")
            print(raw_text if raw_text.strip() else "(no text detected)")
            print("--- End ---")
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(3)


if __name__ == "__main__":
    main()
