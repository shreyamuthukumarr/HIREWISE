
import pdfplumber
import spacy
import sys
import json
import re

from spacy.matcher import PhraseMatcher
sys.stdout.reconfigure(encoding="utf-8", errors="replace")
sys.stderr.reconfigure(encoding="utf-8", errors="replace")

# ============================================================
# 1. LOAD SPACY MODEL
# ============================================================

nlp = spacy.load("en_core_web_sm")


# ============================================================
# 2. SKILL LIST
# ============================================================

SKILLS = [

    # Programming Languages
    "C", "C++", "C#", "Java", "Python", "JavaScript",
    "TypeScript", "PHP", "Ruby", "Go", "Rust", "Kotlin",
    "Swift", "R", "Dart",

    # Web Development
    "HTML", "HTML5", "CSS", "CSS3", "Bootstrap",
    "Tailwind CSS", "React", "React.js", "Angular",
    "Vue", "Vue.js", "Node.js", "Express", "Express.js",
    "Next.js", "Flask", "Django", "FastAPI", "Laravel",

    # Databases
    "SQL", "MySQL", "PostgreSQL", "MongoDB", "SQLite",
    "Oracle", "Redis", "MariaDB", "Firebase",

    # AI / ML
    "Artificial Intelligence", "AI", "Machine Learning",
    "ML", "Deep Learning", "NLP",
    "Natural Language Processing", "Computer Vision",
    "Generative AI", "Neural Networks",

    # ML / Data Libraries
    "scikit-learn", "TensorFlow", "PyTorch", "Keras",
    "Pandas", "NumPy", "Matplotlib", "Seaborn",
    "OpenCV", "spaCy", "NLTK",

    # Data
    "Data Science", "Data Analysis", "Data Analytics",
    "Data Visualization", "Statistics", "Power BI",
    "Tableau", "Excel", "Microsoft Excel",

    # Core Computer Science
    "Data Structures", "Algorithms", "DSA",
    "Object Oriented Programming", "OOP",
    "Operating Systems", "DBMS",
    "Database Management Systems", "Computer Networks",
    "Computer Architecture", "Software Engineering",

    # Development Tools
    "Git", "GitHub", "GitLab", "Bitbucket",
    "VS Code", "Visual Studio Code", "Postman",
    "Jupyter", "Jupyter Notebook",

    # Cloud / DevOps
    "Docker", "Kubernetes", "Jenkins", "CI/CD",
    "AWS", "Amazon Web Services", "Microsoft Azure",
    "Azure", "Google Cloud", "GCP",

    # APIs
    "REST API", "REST APIs", "RESTful API",
    "API", "GraphQL",

    # Mobile
    "Android", "Android Studio", "Flutter",
    "React Native",

    # Frappe / ERP
    "Frappe", "ERPNext",

    # Other
    "JSON", "XML", "Regex",
    "Regular Expressions", "GitHub Actions"
]


# ============================================================
# 3. SKILL NORMALIZATION
# ============================================================

SKILL_NORMALIZATION = {

    "ai": "Artificial Intelligence",
    "ml": "Machine Learning",
    "natural language processing": "NLP",
    "database management systems": "DBMS",
    "git/github": "GitHub",
    "microsoft excel": "Excel",
    "visual studio code": "VS Code",
    "react.js": "React",
    "vue.js": "Vue",
    "express.js": "Express",
}


# ============================================================
# 4. CREATE SPACY PHRASE MATCHER
# ============================================================

matcher = PhraseMatcher(
    nlp.vocab,
    attr="LOWER"
)

patterns = [
    nlp.make_doc(skill)
    for skill in SKILLS
]

matcher.add("TECHNICAL_SKILL", patterns)


# ============================================================
# 5. EXTRACT ALL TEXT FROM PDF
# ============================================================

def extract_resume_text(pdf_path):

    text_parts = []

    with pdfplumber.open(pdf_path) as pdf:

        for page in pdf.pages:

            # First try normal text extraction
            page_text = page.extract_text()

            if page_text:

                text_parts.append(page_text)

            else:

                # Fallback if normal extraction fails
                words = page.extract_words()

                if words:

                    words.sort(
                        key=lambda word: (
                            round(word["top"]),
                            word["x0"]
                        )
                    )

                    page_text = " ".join(
                        word["text"]
                        for word in words
                    )

                    text_parts.append(page_text)

    return "\n".join(text_parts)


# ============================================================
# 6. DETECT SKILLS USING SPACY
# ============================================================

def detect_skills(text):

    doc = nlp(text)

    matches = matcher(doc)

    # Sort by starting position and longest match first
    matches = sorted(
        matches,
        key=lambda match: (
            match[1],
            -(match[2] - match[1])
        )
    )

    selected_matches = []

    last_end = -1

    for match_id, start, end in matches:

        if start < last_end:
            continue

        selected_matches.append((start, end))

        last_end = end

    detected_skills = []

    for start, end in selected_matches:

        skill = doc[start:end].text.strip()

        detected_skills.append(skill)

    # Normalize aliases
    normalized_skills = []

    for skill in detected_skills:

        normalized_skill = SKILL_NORMALIZATION.get(
            skill.lower(),
            skill
        )

        normalized_skills.append(normalized_skill)

    # Remove duplicates
    final_skills = []

    seen = set()

    for skill in normalized_skills:

        key = skill.lower()

        if key not in seen:

            final_skills.append(skill)

            seen.add(key)

    return final_skills



# ============================================================
# 7. RESUME SECTION DEFINITIONS
# ============================================================

SECTION_PATTERNS = {
    "education": [
        "education",
        "academic background",
        "educational qualifications",
        "academic qualifications"
    ],
    "experience": [
        "experience",
        "work experience",
        "professional experience",
        "employment history",
        "work history",
        "internship",
        "internships",
        "professional experience and internships"
    ],
    "projects": [
        "project",
        "projects",
        "academic project",
        "academic projects",
        "project experience",
        "academic project experience"
    ],
    "certifications": [
        "certification",
        "certifications",
        "certificate",
        "certificates",
        "certifications and achievements",
        "certifications achievements",
        "certifications awards",
        "certifications and awards"
    ]
}

OTHER_SECTION_HEADINGS = [
    "technical skills",
    "skills",
    "key skills",
    "core skills",
    "publications",
    "publication",
    "research publications",
    "workshops",
    "workshop",
    "extra curricular activities",
    "extracurricular activities",
    "extracurricular",
    "languages known",
    "languages",
    "achievements",
    "awards",
    "interests",
    "references",
    "career objective",
    "objective",
    "profile",
    "summary",
    "professional summary",
    "personal details",
    "personal information",
    "hobbies",
    "declaration"
]


# ============================================================
# 8. NORMALIZE AND IDENTIFY HEADINGS
# ============================================================

def normalize_heading(line):
    line = line.strip().lower()

    # Remove numbering and bullets at the beginning
    line = re.sub(
        r"^\s*(?:(?:\d+|[a-z])[.)]|\d+[:])\s*|^[•\-*]\s*",
        "",
        line
    )

    # Normalize common symbols
    line = line.replace("&", " and ")
    line = line.replace("/", " ")
    line = line.replace("-", " ")

    # Remove punctuation and collapse spaces
    line = re.sub(r"[^a-z0-9+#\s]", " ", line)
    line = re.sub(r"\s+", " ", line).strip()

    return line


HEADING_LOOKUP = {}

for section_name, headings in SECTION_PATTERNS.items():
    for heading in headings:
        normalized = normalize_heading(heading)
        HEADING_LOOKUP[normalized] = section_name


NORMALIZED_OTHER_HEADINGS = {
    normalize_heading(heading)
    for heading in OTHER_SECTION_HEADINGS
}


# ============================================================
# 9. EXTRACT RESUME SECTIONS
# ============================================================

def extract_resume_sections(text):

    lines = text.splitlines()

    sections = {
        section_name: []
        for section_name in SECTION_PATTERNS
    }

    current_section = None

    for line in lines:

        cleaned_line = line.strip()

        if not cleaned_line:
            continue

        normalized_line = normalize_heading(cleaned_line)

        # ----------------------------------------------------
        # CASE 1: Standalone section heading
        # ----------------------------------------------------

        if normalized_line in HEADING_LOOKUP:

            current_section = HEADING_LOOKUP[normalized_line]
            continue

        # ----------------------------------------------------
        # CASE 2: Inline heading with content
        # Example: PROJECTS: Event Connect
        # ----------------------------------------------------

        found_inline_heading = False

        for heading, section_name in HEADING_LOOKUP.items():

            if normalized_line.startswith(heading + " "):

                remaining_text = normalized_line[
                    len(heading):
                ].strip()

                remaining_text = re.sub(
                    r"^[:\-–—\s]+",
                    "",
                    remaining_text
                )

                current_section = section_name

                if remaining_text:
                    sections[current_section].append(
                        remaining_text
                    )

                found_inline_heading = True
                break

        if found_inline_heading:
            continue

        # ----------------------------------------------------
        # CASE 3: Combined heading PUBLICATION PROJECT
        # ----------------------------------------------------

        if normalized_line == "publication project":
            current_section = None
            continue

        # ----------------------------------------------------
        # CASE 4: A new unrelated section ends extraction
        # ----------------------------------------------------

        if normalized_line in NORMALIZED_OTHER_HEADINGS:
            current_section = None
            continue

        # ----------------------------------------------------
        # CASE 5: Project entries
        # Example:
        # EVENT CONNECT: Event Management Website - Mini Project
        # ----------------------------------------------------

        if normalized_line.startswith("event connect"):
            current_section = "projects"

            sections["projects"].append(cleaned_line)
            continue

        # ----------------------------------------------------
        # CASE 6: Collect content under active section
        # ----------------------------------------------------

        if current_section:
            sections[current_section].append(cleaned_line)

    # --------------------------------------------------------
    # Convert lists into strings
    # --------------------------------------------------------

    extracted_sections = {}

    for section_name in SECTION_PATTERNS:

        extracted_sections[section_name] = "\n".join(
            sections[section_name]
        ).strip()

    return extracted_sections


# ============================================================
# 10. MAIN PROGRAM
# ============================================================

def main():

    if len(sys.argv) < 2:

        print(json.dumps({
            "success": False,
            "message": "Resume PDF path is missing."
        }))

        sys.exit(1)

    pdf_path = sys.argv[1]

    try:

        # Extract text
        resume_text = extract_resume_text(pdf_path)

        if not resume_text.strip():

            print(json.dumps({
                "success": False,
                "message": "No readable text found in the PDF."
            }))

            sys.exit(1)

        # Detect skills
        skills = detect_skills(resume_text)

        # Extract sections
        sections = extract_resume_sections(
            resume_text
        )

        # Prepare JSON result
        result = {

            "success": True,

            "skills": skills,

            "skill_count": len(skills),

            "education": sections["education"],

            "experience": sections["experience"],

            "projects": sections["projects"],

            "certifications": sections["certifications"]
        }

        # Print ONLY JSON to stdout
        print(json.dumps(
            result,
            ensure_ascii=False
        ))

    except Exception as e:

        # Keep stdout as JSON so PHP can decode it
        print(json.dumps({
            "success": False,
            "message": str(e)
        }))

        sys.exit(1)


if __name__ == "__main__":
    main()