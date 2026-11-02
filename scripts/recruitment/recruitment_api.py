"""
HireWise - Recruitment Analysis
Flask API that loads the embedding model, the trained classifier,
and EasyOCR ONCE at startup, then serves /analyze requests.

Mirrors the existing predict_api.py pattern (Module 3, port 5000):
PHP calls this over HTTP so heavy models load once, not per-request.

Run with (.venv-recruit):
    python recruitment_api.py

Starts a local server at http://127.0.0.1:5001
"""

import joblib
from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer
import easyocr

from extract_recruitment import (
    extract_pdf_text,
    extract_image_text,
    extract_plain_text,
    clean_text
)
from risk_rules import scan_text, combine_risk

MODEL_PATH = "recruitment_risk_model.pkl"

app = Flask(__name__)

# ---------------------------------------------------------
# Load everything ONCE at startup, not per-request.
# ---------------------------------------------------------

print("Loading recruitment risk model bundle...")
bundle = joblib.load(MODEL_PATH)
classifier = bundle["classifier"]
embedding_model_name = bundle["embedding_model_name"]

print(f"Loading embedding model: {embedding_model_name}")
embedder = SentenceTransformer(embedding_model_name)

print("Loading EasyOCR model...")
ocr_reader = easyocr.Reader(["en"], gpu=False, verbose=False)

print("Recruitment API ready.")


@app.route("/health", methods=["GET"])
def health():
    """Simple check to confirm the server and models are up."""
    return jsonify({
        "status": "ok",
        "model_loaded": True,
        "embedding_model": embedding_model_name
    })


@app.route("/analyze", methods=["POST"])
def analyze():
    """
    Expects JSON body:
        {
          "input_type": "pdf" | "image" | "text",
          "file_path": "C:\\...\\rec_1_....pdf"   (or .jpg/.png/.txt)
        }

    Returns:
        {
          "success": true,
          "extracted_text": "...",
          "char_count": 812,
          "model_score": 0.7123,
          "risk_category": "High",
          "indicators": [ {code, label, severity, evidence}, ... ],
          "rule_summary": {...},
          "note": "..."
        }
    """

    data = request.get_json(silent=True)

    if not isinstance(data, dict):
        return jsonify({
            "success": False,
            "message": "Invalid or missing JSON body."
        }), 400

    input_type = data.get("input_type")
    file_path = data.get("file_path")

    if input_type not in ("pdf", "image", "text"):
        return jsonify({
            "success": False,
            "message": "input_type must be pdf, image, or text."
        }), 400

    if not file_path:
        return jsonify({
            "success": False,
            "message": "file_path is required."
        }), 400

    # --------------------------------------------------------
    # 1. Extraction
    # --------------------------------------------------------

    try:
        if input_type == "pdf":
            raw_text = extract_pdf_text(file_path)
        elif input_type == "image":
            raw_text = extract_image_text(file_path, reader=ocr_reader)
        else:
            raw_text = extract_plain_text(file_path)
    except FileNotFoundError:
        return jsonify({
            "success": False,
            "message": "The file could not be found on the server."
        }), 404
    except Exception as e:
        return jsonify({
            "success": False,
            "message": "Extraction failed: " + str(e)
        }), 422

    cleaned = clean_text(raw_text)

    if not cleaned:
        return jsonify({
            "success": False,
            "message": (
                "No readable text could be extracted. The file may be "
                "a scanned image with no text layer, or the image may "
                "be too unclear for OCR to read."
            )
        }), 422

    warning = None
    if len(cleaned) < 40:
        warning = "Very little text was extracted. Results may be unreliable."

    # --------------------------------------------------------
    # 2. Model score (similarity to known fake job postings)
    # --------------------------------------------------------

    embedding = embedder.encode([cleaned], convert_to_numpy=True)
    # predict_proba returns [P(class=0), P(class=1)] - class 1 is
    # "fraudulent" (see prepare_dataset.py's label encoding).
    model_score = float(classifier.predict_proba(embedding)[0][1])

    # --------------------------------------------------------
    # 3. Rule-based indicators (transparent, evidence-backed)
    # --------------------------------------------------------

    indicators = scan_text(cleaned)

    # --------------------------------------------------------
    # 4. Combine into a final risk category
    # --------------------------------------------------------

    combined = combine_risk(model_score, indicators)

    response = {
        "success": True,
        "extracted_text": cleaned,
        "char_count": len(cleaned),
        "model_score": round(model_score, 4),
        "risk_category": combined["risk_category"],
        "indicators": indicators,
        "rule_summary": {
            "total_indicators": combined["rule_indicator_count"],
            "high_severity_hits": combined["high_severity_rule_hits"],
            "medium_severity_hits": combined["medium_severity_rule_hits"]
        },
        "note": (
            "The model score reflects textual similarity to known fake "
            "job postings from a public research dataset (EMSCAD), not "
            "a direct probability that this specific message is a "
            "scam. The risk category combines this score with explicit "
            "rule-based warning signs found in the text - see "
            "'indicators' for the exact evidence behind each one. This "
            "is a decision-support tool, not a guaranteed scam detector."
        )
    }

    if warning:
        response["warning"] = warning

    return jsonify(response)


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5001, debug=True, use_reloader=False)