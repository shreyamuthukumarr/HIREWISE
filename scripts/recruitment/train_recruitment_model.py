"""
HireWise - Recruitment Analysis
Trains the recruitment-risk text classifier: frozen MiniLM sentence
embeddings (all-MiniLM-L6-v2) + Logistic Regression on top.

Why frozen embeddings instead of fine-tuning MiniLM: with only ~500
fraud examples in the training split and no GPU, fine-tuning the
transformer itself risks overfitting for little practical benefit.
Frozen embeddings + a simple classifier is standard practice for
small labeled datasets and is fully explainable in a project review.

INPUT:  datasets/recruitment/train.csv
        datasets/recruitment/val.csv
        datasets/recruitment/test.csv   (evaluated ONCE at the end -
                                          never used to tune anything)
OUTPUT: recruitment_risk_model.pkl

IMPORTANT (for the project report):
This model learns "how similar is this text to known fake EMSCAD
job postings" - not "is this a scam recruitment message". EMSCAD is
job-posting text, not messages, so this score is one signal among
several in the final report (Phase 6), never a standalone verdict.

Run with (.venv-recruit):
    python train_recruitment_model.py
"""

import time
import joblib
import numpy as np
import pandas as pd
from sentence_transformers import SentenceTransformer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    classification_report,
    confusion_matrix,
    precision_recall_fscore_support,
    roc_auc_score
)

# ---------------------------------------------------------
# Configuration
# ---------------------------------------------------------

TRAIN_PATH = "../../datasets/recruitment/train.csv"
VAL_PATH = "../../datasets/recruitment/val.csv"
TEST_PATH = "../../datasets/recruitment/test.csv"

MODEL_NAME = "all-MiniLM-L6-v2"
MODEL_OUTPUT_PATH = "recruitment_risk_model.pkl"
RANDOM_SEED = 42


# ---------------------------------------------------------
# Step 1: Load the prepared splits
# ---------------------------------------------------------

print("Loading prepared splits...")
train_df = pd.read_csv(TRAIN_PATH)
val_df = pd.read_csv(VAL_PATH)
test_df = pd.read_csv(TEST_PATH)

print(f"train={len(train_df)}  val={len(val_df)}  test={len(test_df)}")


# ---------------------------------------------------------
# Step 2: Load MiniLM and embed each split
# ---------------------------------------------------------
# The model is downloaded automatically on first use (~80MB) and
# cached locally afterwards - later runs will skip straight to
# encoding.

print(f"\nLoading sentence-transformer model: {MODEL_NAME}")
print("(First run downloads the model - this can take a minute.)")

embedder = SentenceTransformer(MODEL_NAME)

def embed_texts(texts, label):
    start = time.time()
    embeddings = embedder.encode(
        texts.tolist(),
        batch_size=32,
        show_progress_bar=True,
        convert_to_numpy=True
    )
    elapsed = time.time() - start
    print(f"Embedded {len(texts)} {label} texts in {elapsed:.1f}s "
          f"-> shape {embeddings.shape}")
    return embeddings

X_train = embed_texts(train_df["text"], "train")
X_val = embed_texts(val_df["text"], "val")
X_test = embed_texts(test_df["text"], "test")

y_train = train_df["label"].values
y_val = val_df["label"].values
y_test = test_df["label"].values


# ---------------------------------------------------------
# Step 3: Train Logistic Regression
# ---------------------------------------------------------
# class_weight="balanced" (same technique used in train_model.py
# for the Random Forest) makes the classifier pay attention to the
# rare fraud class instead of always predicting "real", which a
# 95.5% real / 4.5% fraud dataset would otherwise reward.

print("\nTraining Logistic Regression classifier...")

classifier = LogisticRegression(
    class_weight="balanced",
    max_iter=2000,
    random_state=RANDOM_SEED
)

classifier.fit(X_train, y_train)


# ---------------------------------------------------------
# Step 4: Evaluate on VALIDATION (used to judge/tune the approach)
# ---------------------------------------------------------

def evaluate(X, y_true, split_name):
    y_pred = classifier.predict(X)
    y_proba = classifier.predict_proba(X)[:, 1]

    print("=" * 55)
    print(f"{split_name.upper()} SET EVALUATION")
    print("=" * 55)
    print(classification_report(
        y_true, y_pred,
        target_names=["real", "fraudulent"],
        digits=3
    ))

    precision, recall, f1, _ = precision_recall_fscore_support(
        y_true, y_pred, average="binary", pos_label=1
    )
    auc = roc_auc_score(y_true, y_proba)

    print(f"Fraud-class  precision={precision:.3f}  recall={recall:.3f}  f1={f1:.3f}")
    print(f"ROC-AUC: {auc:.3f}")
    print("\nConfusion matrix (rows=actual, cols=predicted, order=[real, fraud]):")
    print(confusion_matrix(y_true, y_pred, labels=[0, 1]))
    print()

    return {"precision": precision, "recall": recall, "f1": f1, "auc": auc}

val_metrics = evaluate(X_val, y_val, "validation")


# ---------------------------------------------------------
# Step 5: Evaluate on TEST - once only, as a final honest check
# ---------------------------------------------------------
# This number is reported AS-IS, even if worse than validation.
# The test set must never be used to adjust the model - that would
# just move the leakage problem instead of fixing it.

test_metrics = evaluate(X_test, y_test, "test (final, held-out)")


# ---------------------------------------------------------
# Step 6: Save the trained bundle
# ---------------------------------------------------------
# Bundles the classifier with which MiniLM model produced its
# input embeddings, so nothing downstream (the Flask service, in
# Phase 6) can accidentally embed with a different/mismatched model.

bundle = {
    "classifier": classifier,
    "embedding_model_name": MODEL_NAME,
    "val_metrics": val_metrics,
    "test_metrics": test_metrics,
    "trained_on": "EMSCAD (cleaned, deduplicated)",
    "note": (
        "Score reflects similarity to known fake EMSCAD job "
        "postings, not a direct scam-message classifier. Domain "
        "gap: EMSCAD is job-posting text, not recruitment messages."
    )
}

joblib.dump(bundle, MODEL_OUTPUT_PATH)

print(f"Model bundle saved to {MODEL_OUTPUT_PATH}")
print("\nDone.")