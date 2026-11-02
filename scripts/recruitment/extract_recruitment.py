"""
HireWise - Recruitment Analysis
Text extraction: turns a stored PDF, image, or text file into a
single cleaned string of text, ready for the risk-analysis model
and rule-based checks (Phase 5).

This script does ONE job only: extraction and cleanup. It does not
classify risk, does not call any model, and does not touch the
database - that keeps it independently testable, per the project
plan.

Usage (from PowerShell, with the .venv-recruit interpreter):
    python extract_recruitment.py <input_type> <file_path>

    <input_type> is one of: pdf, image, text
    <file_path>  is the full path to the file in the private
                 recruitment_tmp storage folder.

Prints ONE line of JSON to stdout. Nothing else goes to stdout -
diagnostic/progress messages go to stderr instead, so PHP (in a
later phase) can safely json_decode() stdout without it being
corrupted by library warnings.
"""

import sys
import json
import re

sys.stdout.reconfigure(encoding="utf-8", errors="replace")
sys.stderr.reconfigure(encoding="utf-8", errors="replace")


# ============================================================
# 1. TEXT CLEANUP (shared by all three input types)
# ============================================================

def clean_text(raw_text):
    """
    Normalizes whitespace so downstream steps (spaCy, the model,
    the rule checks) see consistent, readable text regardless of
    which extraction method produced it.
    """
    if not raw_text:
        return ""

    # Defensive: strip a BOM character even if one somehow survives
    # into this string from elsewhere (belt-and-suspenders on top
    # of the utf-8-sig fix in extract_plain_text).
    raw_text = raw_text.replace("\ufeff", "")

    # Collapse 3+ blank lines down to a single blank line.
    text = re.sub(r"\n{3,}", "\n\n", raw_text)

    # Collapse runs of spaces/tabs (but keep newlines as-is).
    text = re.sub(r"[ \t]{2,}", " ", text)

    # Trim trailing whitespace on each line.
    lines = [line.rstrip() for line in text.split("\n")]

    return "\n".join(lines).strip()


# ============================================================
# 2. PDF EXTRACTION (pdfplumber)
# ============================================================

def extract_pdf_text(pdf_path):
    import pdfplumber

    text_parts = []

    with pdfplumber.open(pdf_path) as pdf:
        for page in pdf.pages:
            page_text = page.extract_text()
            if page_text:
                text_parts.append(page_text)

    return "\n".join(text_parts)


# ============================================================
# 3. IMAGE EXTRACTION (EasyOCR)
# ============================================================

def extract_image_text(image_path, reader=None):
    """
    reader: an already-loaded easyocr.Reader, or None to create one
    on the spot (used by this script's CLI mode). The Flask service
    (recruitment_api.py) loads a Reader ONCE at startup and passes
    it in here on every request, instead of reloading the model
    every time - that reload takes several seconds, which would
    otherwise happen on every single image analysis.
    """
    import easyocr

    if reader is None:
        print("Loading EasyOCR model (first run downloads weights)...",
              file=sys.stderr)
        # gpu=False: no CUDA setup on this laptop, so we stay on CPU.
        reader = easyocr.Reader(["en"], gpu=False, verbose=False)

    results = reader.readtext(image_path, detail=0)  # detail=0 -> plain strings only

    return "\n".join(results)


# ============================================================
# 4. PLAIN TEXT (already extracted in Phase 3, just read it back)
# ============================================================

def extract_plain_text(text_path):
    # utf-8-sig (not utf-8) strips a leading BOM if present. Some
    # Windows tools (including PowerShell's Out-File -Encoding utf8)
    # write one silently, and it is invisible but NOT whitespace,
    # so plain utf-8 + strip() would miss it and wrongly treat an
    # empty file as containing text.
    with open(text_path, "r", encoding="utf-8-sig", errors="replace") as f:
        return f.read()


# ============================================================
# 5. MAIN
# ============================================================

def main():
    if len(sys.argv) < 3:
        print(json.dumps({
            "success": False,
            "message": "Usage: extract_recruitment.py <pdf|image|text> <file_path>"
        }))
        sys.exit(1)

    input_type = sys.argv[1]
    file_path = sys.argv[2]

    if input_type not in ("pdf", "image", "text"):
        print(json.dumps({
            "success": False,
            "message": "input_type must be pdf, image, or text."
        }))
        sys.exit(1)

    try:
        if input_type == "pdf":
            raw_text = extract_pdf_text(file_path)
        elif input_type == "image":
            raw_text = extract_image_text(file_path)
        else:
            raw_text = extract_plain_text(file_path)

        cleaned = clean_text(raw_text)

        if not cleaned:
            # Not a Python-level failure - the file opened fine,
            # there just wasn't any usable text in it (e.g. a
            # scanned PDF with no text layer, or a blurry image).
            print(json.dumps({
                "success": False,
                "message": (
                    "No readable text could be extracted. "
                    "The file may be a scanned image with no "
                    "text layer, or the image may be too unclear "
                    "for OCR to read."
                )
            }))
            sys.exit(0)

        result = {
            "success": True,
            "input_type": input_type,
            "extracted_text": cleaned,
            "char_count": len(cleaned)
        }

        # Warn (but don't fail) when very little text came out -
        # useful for the frontend to show a soft caution message.
        if len(cleaned) < 40:
            result["warning"] = (
                "Very little text was extracted. Results may be unreliable."
            )

        print(json.dumps(result, ensure_ascii=False))

    except Exception as e:
        print(json.dumps({
            "success": False,
            "message": "Extraction failed: " + str(e)
        }))
        sys.exit(1)


if __name__ == "__main__":
    main()