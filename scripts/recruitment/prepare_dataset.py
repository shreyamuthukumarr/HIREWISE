"""
HireWise - Recruitment Analysis
Dataset preparation: cleans the EMSCAD job-postings CSV and produces
a stratified train/validation/test split for the MiniLM + Logistic
Regression classifier (Phase 5, training script comes next).

INPUT:  datasets/emscad_dataset.csv   (untouched - never modified)
OUTPUT: datasets/recruitment/train.csv
        datasets/recruitment/val.csv
        datasets/recruitment/test.csv

IMPORTANT (for the project report):
EMSCAD is job-posting text (title/description/requirements/benefits),
not recruitment messages. This split and the model trained on it
should be read as "similarity to known fake job postings," with the
domain-gap limitation stated explicitly - see the Phase 5 plan notes.

Run with (.venv-recruit):
    python prepare_dataset.py
"""

import re
import os
import html
import pandas as pd
from sklearn.model_selection import train_test_split

# ---------------------------------------------------------
# Configuration
# ---------------------------------------------------------

INPUT_PATH = "../../datasets/emscad_dataset.csv"
OUTPUT_DIR = "../../datasets/recruitment"
RANDOM_SEED = 42

TEXT_COLUMNS = ["title", "company_profile", "description", "requirements", "benefits"]
LABEL_COLUMN = "fraudulent"

TEST_SIZE = 0.15
VAL_SIZE = 0.15  # of the ORIGINAL total, not of the remaining 85%


# ---------------------------------------------------------
# Step 1: Load
# ---------------------------------------------------------

print("Loading:", INPUT_PATH)
df = pd.read_csv(INPUT_PATH)
print(f"Loaded {len(df)} rows.")

# in_balanced_dataset is Kaggle's own sampling flag, not a real
# feature of a posting - drop it so it can't leak into the model.
if "in_balanced_dataset" in df.columns:
    df = df.drop(columns=["in_balanced_dataset"])


# ---------------------------------------------------------
# Step 2: Clean each text field and combine into one string
# ---------------------------------------------------------

PLACEHOLDER_PATTERN = re.compile(r"#(URL|EMAIL|PHONE)_[0-9a-fA-F]+#")
HTML_TAG_PATTERN = re.compile(r"<[^>]+>")
WHITESPACE_PATTERN = re.compile(r"\s+")


def clean_field(value):
    if not isinstance(value, str):
        return ""

    # Replace anonymised placeholders with a generic token that
    # still signals "a link/email/phone was mentioned here",
    # without leaking the specific hashed identifiers.
    text = PLACEHOLDER_PATTERN.sub(
        lambda m: "[" + m.group(1) + "]",
        value
    )

    # Strip raw HTML tags (most descriptions contain them).
    text = HTML_TAG_PATTERN.sub(" ", text)

    # Decode HTML entities (&amp; -> &, &nbsp; -> space, etc.) -
    # tag-stripping alone leaves these behind since they contain
    # no angle brackets.
    text = html.unescape(text)

    # Collapse whitespace.
    text = WHITESPACE_PATTERN.sub(" ", text).strip()

    return text


print("Cleaning text fields...")

for col in TEXT_COLUMNS:
    df[col] = df[col].apply(clean_field)

df["combined_text"] = (
    df["title"] + ". " +
    df["company_profile"] + " " +
    df["description"] + " " +
    df["requirements"] + " " +
    df["benefits"]
).str.strip()

# Collapse the whitespace introduced by joining empty fields.
df["combined_text"] = df["combined_text"].apply(
    lambda t: WHITESPACE_PATTERN.sub(" ", t).strip()
)


# ---------------------------------------------------------
# Step 3: Drop rows with no usable text
# ---------------------------------------------------------

before = len(df)
df = df[df["combined_text"].str.len() > 0]
print(f"Dropped {before - len(df)} rows with empty combined text.")


# ---------------------------------------------------------
# Step 4: Deduplicate on the combined text
# ---------------------------------------------------------
# 1,632 postings were found to repeat another posting's text during
# Phase 1 inspection. Keeping duplicates would let the same text
# appear in both train and test, inflating evaluation scores
# (data leakage). We keep the FIRST occurrence of each unique text.

before = len(df)
df = df.drop_duplicates(subset=["combined_text"], keep="first")
print(f"Dropped {before - len(df)} duplicate-text rows.")


# ---------------------------------------------------------
# Step 5: Build the final modeling frame
# ---------------------------------------------------------

df["label"] = (df[LABEL_COLUMN] == "t").astype(int)

final = df[["combined_text", "label"]].rename(columns={"combined_text": "text"})

print(f"\nFinal dataset size: {len(final)}")
print("Class distribution:")
print(final["label"].value_counts())
print(f"Fraud rate: {final['label'].mean() * 100:.2f}%\n")


# ---------------------------------------------------------
# Step 6: Stratified train / validation / test split
# ---------------------------------------------------------
# Two-step split: first carve off the test set, then split what's
# left into train/val. Stratify=label on both steps keeps the same
# fraud rate in every split, since the classes are imbalanced.

train_val, test = train_test_split(
    final,
    test_size=TEST_SIZE,
    random_state=RANDOM_SEED,
    stratify=final["label"]
)

# VAL_SIZE was defined as a fraction of the ORIGINAL total, so we
# convert it to a fraction of what remains after removing the test set.
val_fraction_of_remaining = VAL_SIZE / (1 - TEST_SIZE)

train, val = train_test_split(
    train_val,
    test_size=val_fraction_of_remaining,
    random_state=RANDOM_SEED,
    stratify=train_val["label"]
)

print("=" * 55)
print("SPLIT SUMMARY")
print("=" * 55)
for name, split_df in [("train", train), ("val", val), ("test", test)]:
    print(
        f"{name:6s}  rows={len(split_df):6d}  "
        f"fraud={split_df['label'].sum():4d}  "
        f"fraud_rate={split_df['label'].mean() * 100:.2f}%"
    )


# ---------------------------------------------------------
# Step 7: Save
# ---------------------------------------------------------

os.makedirs(OUTPUT_DIR, exist_ok=True)

train.to_csv(os.path.join(OUTPUT_DIR, "train.csv"), index=False)
val.to_csv(os.path.join(OUTPUT_DIR, "val.csv"), index=False)
test.to_csv(os.path.join(OUTPUT_DIR, "test.csv"), index=False)

print(f"\nSaved train.csv, val.csv, test.csv to {OUTPUT_DIR}")