"""
HireWise - Module 3 (Placement Readiness)
Train, evaluate, and save the Random Forest readiness classifier.

INPUT:  synthetic_students.csv (produced by generate_synthetic_dataset.py)
OUTPUT: placement_readiness_model.pkl (loaded later by the Flask API)

IMPORTANT (for the project report):
This model is trained on synthetic data labeled by our own rubric, not
on real historical placement outcomes. The evaluation below tells us
how well the model learned to APPROXIMATE THAT RUBRIC on unseen
synthetic students - it is not a measure of real-world hiring
prediction accuracy.
"""

import joblib
import numpy as np
import pandas as pd
from sklearn.model_selection import train_test_split, cross_val_score, StratifiedKFold
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import classification_report, confusion_matrix, accuracy_score

# ---------------------------------------------------------
# Configuration
# ---------------------------------------------------------

DATA_PATH = "synthetic_students.csv"
MODEL_OUTPUT_PATH = "placement_readiness_model.pkl"
RANDOM_SEED = 42

# Must match the column order the Flask API will send later.
FEATURE_COLUMNS = [
    "skills_count",
    "projects_count",
    "experience_count",
    "certifications_count",
    "aptitude_score",
    "technical_score"
]
LABEL_COLUMN = "readiness_label"

# ---------------------------------------------------------
# Step 1: Load the data
# ---------------------------------------------------------

data = pd.read_csv(DATA_PATH)

X = data[FEATURE_COLUMNS]
y = data[LABEL_COLUMN]

print(f"Loaded {len(data)} rows.")
print("Class distribution in full dataset:")
print(y.value_counts())
print()

# ---------------------------------------------------------
# Step 2: Train/test split (stratified, since classes are imbalanced)
# ---------------------------------------------------------

X_train, X_test, y_train, y_test = train_test_split(
    X, y,
    test_size=0.2,
    random_state=RANDOM_SEED,
    stratify=y
)

print(f"Training rows: {len(X_train)}   Test rows: {len(X_test)}")
print()

# ---------------------------------------------------------
# Step 3: Train the Random Forest
# ---------------------------------------------------------
# class_weight="balanced" tells the forest to pay more attention to the
# rare "High" class during training, rather than ignoring it because
# guessing "Medium" is right more often by default.
#
# max_depth and min_samples_leaf are capped to reduce the risk of the
# model memorizing the random noise we added to the labels, rather
# than learning the real underlying rubric pattern.

model = RandomForestClassifier(
    n_estimators=300,
    max_depth=12,
    min_samples_leaf=5,
    class_weight="balanced",
    random_state=RANDOM_SEED
)

model.fit(X_train, y_train)

# ---------------------------------------------------------
# Step 4: Evaluate on the held-out test set
# ---------------------------------------------------------

y_pred = model.predict(X_test)

print("=" * 55)
print("TEST SET EVALUATION (unseen synthetic students)")
print("=" * 55)
print(f"Accuracy: {accuracy_score(y_test, y_pred):.3f}")
print()
print("Classification report (precision/recall/f1 per class):")
print(classification_report(y_test, y_pred))
print("Confusion matrix (rows = actual, columns = predicted):")
print(f"Classes order: {sorted(y.unique())}")
print(confusion_matrix(y_test, y_pred, labels=sorted(y.unique())))
print()

# ---------------------------------------------------------
# Step 5: Cross-validation (more robust than a single split)
# ---------------------------------------------------------

cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=RANDOM_SEED)
cv_scores = cross_val_score(model, X, y, cv=cv, scoring="accuracy")

print("=" * 55)
print("5-FOLD CROSS-VALIDATION")
print("=" * 55)
print(f"Fold accuracies: {np.round(cv_scores, 3)}")
print(f"Mean accuracy: {cv_scores.mean():.3f}  (+/- {cv_scores.std():.3f})")
print()

# ---------------------------------------------------------
# Step 6: Feature importances
# ---------------------------------------------------------
# This tells us which features the model actually relies on. If one
# feature (e.g. technical_score) dominated overwhelmingly, that would
# mean the model isn't really using resume information in a
# meaningful way - worth checking against the project requirement
# that BOTH resume and assessment features matter.

importances = pd.Series(model.feature_importances_, index=FEATURE_COLUMNS)
importances = importances.sort_values(ascending=False)

print("=" * 55)
print("FEATURE IMPORTANCES")
print("=" * 55)
print(importances)
print()

# ---------------------------------------------------------
# Step 7: Save the trained model + its expected feature order
# ---------------------------------------------------------
# Bundling feature_order together with the model means the Flask API
# always knows the exact column order to build its input array in,
# without needing to hardcode it separately in two places.

bundle = {
    "model": model,
    "feature_order": FEATURE_COLUMNS
}

joblib.dump(bundle, MODEL_OUTPUT_PATH)

print(f"Model saved to {MODEL_OUTPUT_PATH}")