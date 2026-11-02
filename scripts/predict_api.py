"""
HireWise - Module 3 (Placement Readiness)
Flask API that loads the trained Random Forest and serves predictions.

PHP calls this over HTTP (not directly running Python), so the model
only needs to be loaded once when this server starts, not on every
single prediction request.

Run with:
    python predict_api.py

This starts a local server at http://127.0.0.1:5000
"""

import joblib
import pandas as pd
from flask import Flask, request, jsonify

MODEL_PATH = "placement_readiness_model.pkl"

app = Flask(__name__)

# ---------------------------------------------------------
# Load the model ONCE at startup, not per-request.
# ---------------------------------------------------------

bundle = joblib.load(MODEL_PATH)
model = bundle["model"]
FEATURE_ORDER = bundle["feature_order"]

print(f"Model loaded. Expected feature order: {FEATURE_ORDER}")


@app.route("/health", methods=["GET"])
def health():
    """Simple check to confirm the server and model are up."""
    return jsonify({"status": "ok", "model_loaded": True})


@app.route("/predict", methods=["POST"])
def predict():
    """
    Expects a JSON body with exactly these six keys:
        skills_count, projects_count, experience_count,
        certifications_count, aptitude_score, technical_score

    Returns:
        {
          "success": true,
          "readiness": "Medium",
          "probabilities": {"Low": 0.12, "Medium": 0.71, "High": 0.17}
        }
    """
    data = request.get_json(silent=True)

    if not isinstance(data, dict):
        return jsonify({
            "success": False,
            "message": "Invalid or missing JSON body."
        }), 400

    # Validate every expected feature is present and numeric.
    features = {}
    for key in FEATURE_ORDER:
        if key not in data:
            return jsonify({
                "success": False,
                "message": f"Missing required field: {key}"
            }), 400

        try:
            features[key] = float(data[key])
        except (TypeError, ValueError):
            return jsonify({
                "success": False,
                "message": f"Field '{key}' must be a number."
            }), 400

    # Build a single-row DataFrame in the exact column order the
    # model was trained on.
    input_row = pd.DataFrame([features])[FEATURE_ORDER]

    predicted_label = model.predict(input_row)[0]
    probabilities = model.predict_proba(input_row)[0]

    # model.classes_ gives the label order matching predict_proba's columns
    probability_map = {
        label: round(float(prob), 4)
        for label, prob in zip(model.classes_, probabilities)
    }

    return jsonify({
        "success": True,
        "readiness": predicted_label,
        "probabilities": probability_map
    })


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=True)