"""
HireWise - Offer Comparison
Extracts structured fields from a job offer letter (PDF or pasted
text): company, role, salary details, location, work mode,
benefits, bond details, notice period, other conditions.

This is EXTRACTION, not scoring or judgement - nothing here ranks
offers or declares one "better." Every field is either the actual
matched text from the letter, or left blank (shown as "Not
specified" by the frontend) when nothing was found. Nothing is
invented.

Approach:
  - Company / Location: spaCy named-entity recognition (ORG / GPE),
    the same library already used by analyze_resume.py for skill
    matching - reused here for a different, well-suited purpose.
  - Role, Salary, Notice period: targeted regex over the cleaned
    text, capturing the matching phrase mostly verbatim so we don't
    misrepresent fixed/variable/annual/monthly pay as something
    the letter didn't actually say.
  - Benefits / Bond details / Other conditions: heading-based
    section capture (same idea as analyze_resume.py's section
    extraction), since these are usually written as labeled
    paragraphs in real offer letters.

Usage (from PowerShell, with the .venv311 interpreter):
    python extract_offer.py pdf  <path_to_pdf>
    python extract_offer.py text            (reads pasted text from stdin)

Prints ONE line of JSON to stdout; diagnostics go to stderr, same
convention as extract_recruitment.py and analyze_resume.py.
"""

import sys
import json
import re

sys.stdout.reconfigure(encoding="utf-8", errors="replace")
sys.stderr.reconfigure(encoding="utf-8", errors="replace")
sys.stdin.reconfigure(encoding="utf-8", errors="replace")


# ============================================================
# 1. TEXT CLEANUP
# ============================================================

def clean_text(raw_text):
    if not raw_text:
        return ""

    text = raw_text.replace("\ufeff", "")  # strip BOM if present
    text = re.sub(r"\n{3,}", "\n\n", text)
    text = re.sub(r"[ \t]{2,}", " ", text)
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
# 3. HEADING-BASED SECTION EXTRACTION
# ============================================================
# Lighter version of analyze_resume.py's section extraction:
# find a heading line, capture the lines that follow it until the
# next recognized heading (from ANY section, so one section doesn't
# swallow the next) or a max line count.

SECTION_HEADINGS = {
    "benefits": ["benefits", "perks", "employee benefits", "benefits and perks"],
    "bond_details": ["bond", "service agreement", "service bond", "obligation period"],
    "other_conditions": ["terms and conditions", "other terms", "conditions of employment",
                          "general terms", "additional terms"]
}

ALL_HEADING_PHRASES = set()
for phrases in SECTION_HEADINGS.values():
    ALL_HEADING_PHRASES.update(phrases)


def normalize_line(line):
    line = line.strip().lower()
    line = re.sub(r"^\s*(?:\d+[.)]|[•\-*])\s*", "", line)
    line = re.sub(r"[^a-z0-9\s]", " ", line)
    line = re.sub(r"\s+", " ", line).strip()
    return line


def extract_sections(text):
    lines = text.splitlines()
    sections = {key: [] for key in SECTION_HEADINGS}
    current_section = None

    for line in lines:
        cleaned_line = line.strip()
        if not cleaned_line:
            continue

        normalized = normalize_line(cleaned_line)

        # Does this line START a section we care about?
        matched_section = None
        for section_name, phrases in SECTION_HEADINGS.items():
            if normalized in phrases or any(normalized.startswith(p + " ") for p in phrases):
                matched_section = section_name
                break

        if matched_section:
            current_section = matched_section
            continue

        # Does this line look like ANY known heading (including ones
        # we're not collecting) - if so, stop the current section.
        if normalized in ALL_HEADING_PHRASES:
            current_section = None
            continue

        if current_section:
            sections[current_section].append(cleaned_line)

    return {name: "\n".join(lines_).strip() for name, lines_ in sections.items()}


# ============================================================
# 4. REGEX-BASED FIELD EXTRACTION
# ============================================================

ROLE_PATTERNS = [
    re.compile(r"designation\s*[:\-]\s*([A-Za-z][A-Za-z0-9&/,\-\s]{2,60}?)(?=[.,\n]|$)", re.IGNORECASE),
    re.compile(r"(?:position of|role of|joining as|hired as)\s+([A-Za-z][A-Za-z0-9&/,\-\s]{2,60}?)(?=[.,\n]|\s+at\s+|\s+in\s+|\s+with\s+|$)", re.IGNORECASE),
    re.compile(r"offer(?:ing)? you the (?:position|role) of\s+([A-Za-z][A-Za-z0-9&/,\-\s]{2,60}?)(?=[.,\n]|\s+at\s+|\s+in\s+|\s+with\s+|$)", re.IGNORECASE),
]

SALARY_KEYWORDS_PATTERN = re.compile(
    r"[^.\n]{0,10}(?:CTC|salary|compensation|remuneration|stipend)[^.\n]{0,140}",
    re.IGNORECASE
)

WORK_MODE_PATTERNS = [
    (re.compile(r"work[\s-]?from[\s-]?home|\bremote\b", re.IGNORECASE), "Remote"),
    (re.compile(r"\bhybrid\b", re.IGNORECASE), "Hybrid"),
    (re.compile(r"on[\s-]?site|in[\s-]?office", re.IGNORECASE), "On-site"),
]

NOTICE_PERIOD_PATTERN = re.compile(
    r"notice period\s*[:\-]?\s*([A-Za-z0-9][A-Za-z0-9\s]{1,40}?)(?=[.,\n]|$)",
    re.IGNORECASE
)

# Explicit "Company: X" / "Location: X" style labels - tried FIRST,
# before anything fuzzier, same reasoning as the role/salary/notice
# patterns above: an explicit label is far more reliable than NER.
COMPANY_LABEL_PATTERN = re.compile(
    r"(?:company\s*name|company|organi[sz]ation|employer)\s*[:\-]\s*([A-Za-z][A-Za-z0-9&.,\-\s]{2,80}?)(?=[.,\n]|$)",
    re.IGNORECASE
)

# Catches company names by their legal-entity suffix (Pvt. Ltd.,
# Technologies, etc.) even with no explicit "Company:" label -
# this is what correctly found "Nexora Technologies Pvt. Ltd."
# and is more reliable here than generic NER.
COMPANY_SUFFIX_PATTERN = re.compile(
    # 1-5 consecutive Capitalized-word tokens immediately followed
    # by a legal-entity suffix - deliberately does NOT allow
    # lowercase connector words (at/the/of) in between, which is
    # what previously let it swallow "Software Engineer at Zoho
    # Corporation" instead of just "Zoho Corporation".
    r"((?:[A-Z][\w&.\'-]*\s+){1,5}(?:Pvt\.?\s*Ltd\.?|Private Limited|Ltd\.?|LLC|Inc\.?|"
    r"Corporation|Corp\.?|Technologies|Solutions|Systems|Enterprises))"
)

LOCATION_LABEL_PATTERN = re.compile(
    r"(?:job\s*location|location|place of posting|based at|based in)\s*[:\-]\s*"
    r"([A-Za-z][A-Za-z\s,]{1,50}?)(?=[.,\n]|$)",
    re.IGNORECASE
)


def first_regex_match(patterns, text):
    for pattern in patterns:
        match = pattern.search(text)
        if match:
            return match.group(1).strip() if match.groups() else match.group(0).strip()
    return None


def extract_salary(text):
    match = SALARY_KEYWORDS_PATTERN.search(text)
    if match:
        return match.group(0).strip(" :-\u2022")
    return None


def extract_work_mode(text):
    for pattern, label in WORK_MODE_PATTERNS:
        if pattern.search(text):
            return label
    return None


# ============================================================
# 5. NAMED ENTITY EXTRACTION (spaCy) - company + location
# ============================================================

def overlaps_with_role(candidate, job_role):
    """
    Guards against the exact failure seen in testing: spaCy's small
    model misreading a job-title phrase (e.g. "Software Development
    Intern") as an ORG, or a stray word from the role (e.g. "AI" in
    "AI and Software Development Intern") as a GPE. If a candidate
    is contained in - or contains - the already-extracted role
    text, it's almost certainly a misfire, not a real company/place.
    """
    if not candidate or not job_role:
        return False
    c = candidate.lower().strip()
    r = job_role.lower().strip()
    return c in r or r in c


def extract_company_name(text, job_role, nlp_doc):
    # 1. Explicit label - most reliable.
    match = COMPANY_LABEL_PATTERN.search(text)
    if match:
        return match.group(1).strip()

    # 2. Legal-entity suffix (Pvt. Ltd., Technologies, etc.).
    match = COMPANY_SUFFIX_PATTERN.search(text)
    if match:
        return match.group(1).strip()

    # 3. NER fallback, filtered against obvious false positives.
    orgs = [ent.text.strip() for ent in nlp_doc.ents if ent.label_ == "ORG"]
    orgs = [o for o in orgs if len(o) > 2 and not overlaps_with_role(o, job_role)]

    if orgs:
        from collections import Counter
        return Counter(orgs).most_common(1)[0][0]

    return None


def extract_location(text, job_role, nlp_doc):
    # 1. Explicit label - most reliable.
    match = LOCATION_LABEL_PATTERN.search(text)
    if match:
        return match.group(1).strip()

    # 2. NER fallback, filtered against obvious false positives.
    #    Length > 2 excludes short acronyms (e.g. "AI", "US") that
    #    the small model sometimes mistags as GPE - a deliberate
    #    trade-off: we lose genuine 2-letter places in exchange for
    #    far fewer false positives, worth stating as a known
    #    limitation in the project write-up.
    locations = [ent.text.strip() for ent in nlp_doc.ents if ent.label_ in ("GPE", "LOC")]
    locations = [l for l in locations if len(l) > 2 and not overlaps_with_role(l, job_role)]

    return locations[0] if locations else None


# ============================================================
# 6. MAIN
# ============================================================

def main():
    if len(sys.argv) < 2 or sys.argv[1] not in ("pdf", "text"):
        print(json.dumps({
            "success": False,
            "message": "Usage: extract_offer.py <pdf|text> [file_path]"
        }))
        sys.exit(1)

    input_type = sys.argv[1]

    try:
        if input_type == "pdf":
            if len(sys.argv) < 3:
                print(json.dumps({"success": False, "message": "PDF path is required."}))
                sys.exit(1)
            raw_text = extract_pdf_text(sys.argv[2])
        else:
            raw_text = sys.stdin.read()

        cleaned = clean_text(raw_text)

        if not cleaned:
            print(json.dumps({
                "success": False,
                "message": (
                    "No readable text could be extracted. The PDF may be "
                    "a scanned image with no text layer."
                )
            }))
            sys.exit(0)

        print("Loading spaCy model...", file=sys.stderr)
        import spacy
        nlp = spacy.load("en_core_web_sm")
        doc = nlp(cleaned[:20000])  # cap length for performance/safety

        # job_role is extracted first - company/location extraction
        # uses it to filter out NER false positives that overlap
        # with the role text (see overlaps_with_role()).
        job_role = first_regex_match(ROLE_PATTERNS, cleaned)
        company_name = extract_company_name(cleaned, job_role, doc)
        location = extract_location(cleaned, job_role, doc)
        salary_details = extract_salary(cleaned)
        work_mode = extract_work_mode(cleaned)
        notice_period = first_regex_match([NOTICE_PERIOD_PATTERN], cleaned)

        sections = extract_sections(cleaned)

        result = {
            "success": True,
            "company_name": company_name,
            "job_role": job_role,
            "salary_details": salary_details,
            "location": location,
            "work_mode": work_mode,
            "benefits": sections["benefits"] or None,
            "bond_details": sections["bond_details"] or None,
            "notice_period": notice_period,
            "other_conditions": sections["other_conditions"] or None,
            "char_count": len(cleaned)
        }

        print(json.dumps(result, ensure_ascii=False))

    except Exception as e:
        print(json.dumps({
            "success": False,
            "message": "Extraction failed: " + str(e)
        }))
        sys.exit(1)


if __name__ == "__main__":
    main()