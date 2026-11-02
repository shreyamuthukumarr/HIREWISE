"""
HireWise - Recruitment Analysis
Rule-based risk indicators + the logic that combines them with the
MiniLM model score into one final risk category.

Design principle (matches the project requirements): rule hits are
transparent and evidence-backed - each one quotes the exact matched
text. The model score alone can NEVER produce "High" risk; only an
explicit rule match can. This stops the system from ever declaring
a company fraudulent purely because of an opaque model number.

This file has no side effects and does not touch stdin/stdout on
import - it's meant to be imported by the Flask service (Phase 6,
next step) and can also be run directly for quick manual testing.
"""

import re


# ============================================================
# 1. RULE DEFINITIONS
# ============================================================
# Each rule: a short code, a human label, a severity, and one or
# more regex patterns. HIGH severity rules are the classic
# advance-fee / data-harvesting scam patterns - on their own they
# are enough to warrant a High risk category. MEDIUM/LOW rules are
# supporting signals that need to combine with something else.

RULES = [
    {
        "code": "FEE_REQUEST",
        "label": "Requests a fee, deposit, or payment",
        "severity": "high",
        "patterns": [
            r"registration fee", r"processing fee", r"security deposit",
            r"refundable deposit", r"training fee", r"advance payment",
            r"pay\s+(?:rs\.?|inr|\$|usd)?\s*\d",
            r"pay\s+(?:before|to start|to join|to confirm)"
        ]
    },
    {
        "code": "SENSITIVE_DATA_REQUEST",
        "label": "Asks for bank, ID, or card details",
        "severity": "high",
        "patterns": [
            r"bank account number", r"ifsc code", r"aadhaar", r"aadhar",
            r"pan card", r"\botp\b", r"\bcvv\b", r"debit card",
            r"credit card number"
        ]
    },
    {
        "code": "WHATSAPP_ONLY",
        "label": "Directs contact only to WhatsApp/Telegram",
        "severity": "medium",
        "patterns": [
            r"whatsapp", r"telegram"
        ]
    },
    {
        "code": "FREE_EMAIL_DOMAIN",
        "label": "Recruiter uses a free email address, not a company domain",
        "severity": "medium",
        "patterns": [
            r"[\w.+-]+@(?:gmail|yahoo|hotmail|outlook|rediffmail)\.\w+"
        ]
    },
    {
        "code": "URGENCY_PRESSURE",
        "label": "Uses urgency or pressure language",
        "severity": "medium",
        "patterns": [
            r"immediate joining", r"limited seats", r"reply within",
            r"apply within \d+ hours?", r"act now", r"hurry",
            r"only today"
        ]
    },
    {
        "code": "TOO_GOOD_TO_BE_TRUE",
        "label": "Promises guaranteed placement or skips normal hiring steps",
        "severity": "medium",
        "patterns": [
            r"no interview required", r"guaranteed job",
            r"100%\s*placement", r"guaranteed placement",
            r"no experience needed"
        ]
    },
    {
        "code": "ID_DOCUMENT_UPLOAD",
        "label": "Asks you to send a copy of an ID document",
        "severity": "medium",
        "patterns": [
            r"send.{0,20}(?:id proof|passport copy|aadhaar copy|id copy)"
        ]
    },
]

# Pre-compile all patterns once (case-insensitive).
for rule in RULES:
    rule["compiled"] = [re.compile(p, re.IGNORECASE) for p in rule["patterns"]]


# ============================================================
# 2. SCAN TEXT FOR INDICATORS
# ============================================================

def extract_evidence(text, match, context_chars=40):
    """
    Returns a short, readable snippet of text around a match, so
    the report can show the person exactly what triggered a rule -
    never just the rule name on its own.
    """
    start = max(0, match.start() - context_chars)
    end = min(len(text), match.end() + context_chars)

    snippet = text[start:end].strip()

    if start > 0:
        snippet = "..." + snippet
    if end < len(text):
        snippet = snippet + "..."

    return snippet


def scan_text(text):
    """
    Runs every rule against the text once. Returns a list of
    matched indicators, each with its code, label, severity, and
    the exact evidence snippet that triggered it. A rule matches
    at most once (first occurrence) - the report shows what kind
    of warning sign is present, not how many times it repeats.
    """
    if not text:
        return []

    indicators = []

    for rule in RULES:
        for pattern in rule["compiled"]:
            match = pattern.search(text)
            if match:
                indicators.append({
                    "code": rule["code"],
                    "label": rule["label"],
                    "severity": rule["severity"],
                    "evidence": extract_evidence(text, match)
                })
                break  # one match per rule is enough

    return indicators


# ============================================================
# 3. COMBINE RULES + MODEL SCORE INTO A FINAL RISK CATEGORY
# ============================================================
# This logic is intentionally simple and stated explicitly here so
# it can be quoted directly in a project review - not a black box.
#
#   HIGH   : any HIGH-severity rule matched, OR 2+ MEDIUM rules
#            matched together with a model score >= 0.5
#   MEDIUM : 1+ rule matched (any severity), OR model score >= 0.75
#            on its own with no rule support
#   LOW    : otherwise
#
# The model score can raise Low -> Medium by itself, but can NEVER
# push anything to High without at least one rule match backing it.

def combine_risk(model_score, indicators):
    """
    Threshold tuned from real test evidence (see project notes):
    short, generic-but-benign messages (e.g. brief HR acknowledgements)
    scored 0.62-0.67 with zero rule hits, while a genuine detailed
    interview invite scored 0.575 and a confirmed scam scored 0.79.
    0.75 sits below the confirmed scam and above both benign false
    positives, based on this evidence - not an arbitrary guess.
    """
    high_hits = [i for i in indicators if i["severity"] == "high"]
    medium_hits = [i for i in indicators if i["severity"] == "medium"]

    if len(high_hits) >= 1:
        category = "High"
    elif len(medium_hits) >= 2 and model_score is not None and model_score >= 0.5:
        category = "High"
    elif len(indicators) >= 1:
        category = "Medium"
    elif model_score is not None and model_score >= 0.75:
        category = "Medium"
    else:
        category = "Low"

    return {
        "risk_category": category,
        "rule_indicator_count": len(indicators),
        "high_severity_rule_hits": len(high_hits),
        "medium_severity_rule_hits": len(medium_hits),
        "model_score": model_score
    }


# ============================================================
# 4. MANUAL TEST (run this file directly to sanity check)
# ============================================================

if __name__ == "__main__":
    samples = [
        (
            "Clean job listing",
            "We are hiring a Junior Data Analyst. Please apply via our "
            "careers page at careers.acmecorp.com. Interviews will be "
            "conducted over the next two weeks.",
            0.10
        ),
        (
            "Classic advance-fee scam",
            "Contact us only on WhatsApp +91XXXXXXXXXX. To confirm your "
            "position you must pay a refundable registration fee of "
            "Rs 2000 before joining. Limited seats, reply within 2 hours!",
            0.82
        ),
        (
            "Borderline / mixed signals",
            "For queries reach us on WhatsApp. No interview required, "
            "100% placement guaranteed for freshers.",
            0.55
        ),
    ]

    for name, text, score in samples:
        found = scan_text(text)
        result = combine_risk(score, found)

        print("=" * 60)
        print(name)
        print("Model score:", score)
        print("Indicators found:", len(found))
        for ind in found:
            print(f"  - [{ind['severity'].upper()}] {ind['label']}")
            print(f"    evidence: \"{ind['evidence']}\"")
        print("=>", result["risk_category"], "risk")
        print()