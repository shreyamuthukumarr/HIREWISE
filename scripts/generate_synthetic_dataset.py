"""
HireWise - Module 3 (Placement Readiness)
Synthetic training data generator.

WHAT THIS DOES:
Creates a large number of *fake* students with realistic-looking resume
and assessment features, then labels each one Low / Medium / High
readiness using the rubric we designed together (equal-weight blend of
assessment scores and resume content).

WHY SYNTHETIC DATA:
We do not have real historical students with confirmed placement
outcomes. This script does NOT simulate real outcomes - it applies a
transparent, hand-designed scoring rubric to generate labels. The
Random Forest trained on this data learns to approximate that rubric's
behaviour (including its non-linear caps), not real hiring decisions.
This limitation must be stated clearly in the project report.

OUTPUT:
A CSV file with one row per synthetic student:
    skills_count, projects_count, experience_count,
    certifications_count, aptitude_score, technical_score, readiness_label

Only the six raw features and the final label are saved - the
intermediate sub-scores are NOT saved as columns, since including them
would leak the answer directly into the training features.
"""

import numpy as np
import pandas as pd

# ---------------------------------------------------------
# Configuration
# ---------------------------------------------------------

N_SAMPLES = 5000
RANDOM_SEED = 42

# Caps used in the rubric (must match the design we agreed on)
SKILLS_CAP = 15
PROJECTS_CAP = 3
EXPERIENCE_CAP = 2
CERTIFICATIONS_CAP = 6

# Bucket thresholds
LOW_MEDIUM_CUTOFF = 50
MEDIUM_HIGH_CUTOFF = 75

# How much random "judgment noise" to add before bucketing
# (simulates that even an expert rubric wouldn't be applied with
# perfect, robotic consistency every single time)
LABEL_NOISE_STD = 4

OUTPUT_PATH = "synthetic_students.csv"

rng = np.random.default_rng(RANDOM_SEED)


def clip_round(values, low, high):
    """Round to nearest integer and clip into [low, high]."""
    return np.clip(np.round(values), low, high).astype(int)


def clip_float(values, low, high):
    return np.clip(values, low, high)


# ---------------------------------------------------------
# Step 1: a hidden "effort/ability" factor per student
# ---------------------------------------------------------
# This isn't a feature the model ever sees directly - it's just a
# generation trick so that a student who tends to do more (more
# projects, more certifications) also tends to score a bit higher on
# assessments, instead of every feature being drawn independently at
# random. Real students behave more like this than pure randomness.

effort_factor = rng.normal(loc=0, scale=1, size=N_SAMPLES)  # roughly -3 to +3

# ---------------------------------------------------------
# Step 2: generate the six raw features
# ---------------------------------------------------------

skills_count = clip_round(
    rng.normal(loc=10, scale=4, size=N_SAMPLES) + effort_factor * 1.5,
    low=0, high=25
)

projects_count = clip_round(
    rng.normal(loc=1.4, scale=1.0, size=N_SAMPLES) + effort_factor * 0.4,
    low=0, high=5
)

experience_count = clip_round(
    rng.normal(loc=0.6, scale=0.7, size=N_SAMPLES) + effort_factor * 0.2,
    low=0, high=3
)

certifications_count = clip_round(
    rng.normal(loc=3.0, scale=2.5, size=N_SAMPLES) + effort_factor * 0.8,
    low=0, high=12
)

# Assessment scores share the same effort factor (with their own
# independent noise on top), so a highly-prepared student tends to
# score reasonably on both, without being perfectly identical.
aptitude_score = clip_float(
    rng.normal(loc=58, scale=15, size=N_SAMPLES) + effort_factor * 6,
    low=0, high=100
)
technical_score = clip_float(
    rng.normal(loc=56, scale=15, size=N_SAMPLES) + effort_factor * 6,
    low=0, high=100
)

# ---------------------------------------------------------
# Step 3: apply the rubric (must match get-readiness.php later exactly)
# ---------------------------------------------------------

skills_score = np.minimum(skills_count / SKILLS_CAP, 1) * 100
projects_score = np.minimum(projects_count / PROJECTS_CAP, 1) * 100
experience_score = np.minimum(experience_count / EXPERIENCE_CAP, 1) * 100
certifications_score = np.minimum(certifications_count / CERTIFICATIONS_CAP, 1) * 100

resume_score = (
    skills_score + projects_score + experience_score + certifications_score
) / 4

assessment_score = (aptitude_score + technical_score) / 2

final_readiness_score = 0.5 * assessment_score + 0.5 * resume_score

# Add judgment noise, then clip back into a valid 0-100 range
noisy_score = clip_float(
    final_readiness_score + rng.normal(0, LABEL_NOISE_STD, size=N_SAMPLES),
    low=0, high=100
)

# ---------------------------------------------------------
# Step 4: bucket into Low / Medium / High
# ---------------------------------------------------------

readiness_label = np.where(
    noisy_score < LOW_MEDIUM_CUTOFF, "Low",
    np.where(noisy_score < MEDIUM_HIGH_CUTOFF, "Medium", "High")
)

# ---------------------------------------------------------
# Step 5: assemble and save the dataset
# ---------------------------------------------------------

dataset = pd.DataFrame({
    "skills_count": skills_count,
    "projects_count": projects_count,
    "experience_count": experience_count,
    "certifications_count": certifications_count,
    "aptitude_score": np.round(aptitude_score, 2),
    "technical_score": np.round(technical_score, 2),
    "readiness_label": readiness_label
})

dataset.to_csv(OUTPUT_PATH, index=False)

# ---------------------------------------------------------
# Step 6: quick sanity summary printed to the console
# ---------------------------------------------------------

print(f"Generated {N_SAMPLES} synthetic students -> {OUTPUT_PATH}")
print()
print("Label distribution:")
print(dataset["readiness_label"].value_counts())
print()
print("Feature ranges (sanity check):")
print(dataset.describe())