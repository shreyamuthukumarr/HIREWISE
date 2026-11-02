document.addEventListener("DOMContentLoaded", () => {
    const API_URL = "api/get-readiness.php";

    const states = {
        loading: document.getElementById("loading-state"),
        error: document.getElementById("error-state"),
        needsData: document.getElementById("needs-data-state"),
        result: document.getElementById("result-state")
    };

    const errorMessage = document.getElementById("error-message");
    const retryBtn = document.getElementById("retry-btn");
    const todoList = document.getElementById("todo-list");

    const hero = document.getElementById("readiness-hero");
    const heroLabel = document.getElementById("readiness-label");
    const heroConfidence = document.getElementById("hero-confidence");
    const heroBasis = document.getElementById("hero-basis");
    const probabilityList = document.getElementById("probability-list");
    const resumeMetrics = document.getElementById("resume-metrics");
    const assessmentMetrics = document.getElementById("assessment-metrics");
    const improveList = document.getElementById("improve-list");
    const modelNote = document.getElementById("model-note");

    // Targets below match the rubric caps used to train the model
    // (generate_synthetic_dataset.py / readiness_features.php).
    const RESUME_AREAS = [
        { key: "skills", featureKey: "skills_count", label: "Skills listed", target: 15 },
        { key: "projects", featureKey: "projects_count", label: "Projects", target: 3 },
        { key: "experience", featureKey: "experience_count", label: "Experience entries", target: 2 },
        { key: "certifications", featureKey: "certifications_count", label: "Certifications & achievements", target: 6 }
    ];

    const ASSESSMENT_AREAS = [
        { key: "aptitude", label: "Aptitude", link: "assessment-setup.html?type=aptitude" },
        { key: "technical", label: "Technical", link: "assessment-setup.html?type=technical" }
    ];

    const SUGGESTIONS = {
        skills:
            "List the tools and languages you have genuinely worked with, and only ones you can back up. Around 15 relevant skills is a strong showing.",
        projects:
            "Add another project with a clear outcome. Around three solid projects gives a good picture of what you can build.",
        experience:
            "Internships, freelance work and open-source contributions all count as practical experience.",
        certifications:
            "Certifications and achievements add credibility. Consider a course that matches the roles you are aiming for.",
        aptitude:
            "Regular practice makes the difference here. Try a Focused Practice on the aptitude topic you find hardest.",
        technical:
            "Try a Focused Practice on the technical subject you are least comfortable with, such as DBMS, OS or DSA."
    };

    const LEVELS = ["low", "medium", "high"];
    const IMPROVE_BELOW = 75;
    const MAX_SUGGESTIONS = 3;

    // ---------------------------------------------------------
    // Small helpers
    // ---------------------------------------------------------

    function showState(name) {
        Object.entries(states).forEach(([key, element]) => {
            element.classList.toggle("hidden", key !== name);
        });
    }

    function showError(message) {
        errorMessage.textContent = message;
        showState("error");
    }

    function icon(name, className) {
        const element = document.createElement("i");
        element.setAttribute("data-lucide", name);
        if (className) {
            element.className = className;
        }
        return element;
    }

    function plural(count, word) {
        return `${count} ${word}${count === 1 ? "" : "s"}`;
    }

    function refreshIcons() {
        if (window.lucide) {
            lucide.createIcons();
        }
    }

    // ---------------------------------------------------------
    // "Needs data" state
    // ---------------------------------------------------------

    const TODO_ITEMS = {
        resume: {
            icon: "file-text",
            title: "Upload your resume",
            description: "We read your skills, projects and experience from it.",
            href: "resume.html"
        },
        aptitude: {
            icon: "calculator",
            title: "Complete an Aptitude assessment",
            description: "Any aptitude or full assessment counts.",
            href: "assessment-setup.html?type=aptitude"
        },
        technical: {
            icon: "code-2",
            title: "Complete a Technical assessment",
            description: "Any technical or full assessment counts.",
            href: "assessment-setup.html?type=technical"
        }
    };

    function renderNeedsData(missing) {
        todoList.replaceChildren();

        missing.forEach((key) => {
            const item = TODO_ITEMS[key];
            if (!item) {
                return;
            }

            const link = document.createElement("a");
            link.className = "todo-item";
            link.href = item.href;

            const iconWrap = document.createElement("div");
            iconWrap.className = "todo-icon";
            iconWrap.appendChild(icon(item.icon));

            const text = document.createElement("div");
            text.className = "todo-text";

            const title = document.createElement("strong");
            title.textContent = item.title;

            const description = document.createElement("span");
            description.textContent = item.description;

            text.append(title, description);
            link.append(iconWrap, text, icon("arrow-right", "todo-arrow"));
            todoList.appendChild(link);
        });

        showState("needsData");
        refreshIcons();
    }

    // ---------------------------------------------------------
    // Result state
    // ---------------------------------------------------------

    function renderHero(data) {
        const level = LEVELS.includes(String(data.readiness).toLowerCase())
            ? String(data.readiness).toLowerCase()
            : "medium";

        hero.className = `readiness-hero level-${level}`;
        heroLabel.textContent = data.readiness;

        const confidence = Math.round((data.probabilities[data.readiness] || 0) * 100);
        heroConfidence.textContent = `The model is ${confidence}% confident in this result.`;

        heroBasis.textContent =
            `Based on your latest resume and ${plural(data.attempts.aptitude, "aptitude attempt")} ` +
            `and ${plural(data.attempts.technical, "technical attempt")}.`;
    }

    function renderProbabilities(data) {
        probabilityList.replaceChildren();

        LEVELS.forEach((level) => {
            const label = level.charAt(0).toUpperCase() + level.slice(1);
            const value = data.probabilities[label] || 0;
            const percent = Math.round(value * 100);

            const row = document.createElement("div");
            row.className = "probability-row";
            if (label === data.readiness) {
                row.classList.add("is-predicted");
            }

            const name = document.createElement("span");
            name.textContent = label;

            const track = document.createElement("div");
            track.className = "bar-track";

            const fill = document.createElement("div");
            fill.className = `bar-fill level-${level}`;
            fill.style.width = "0%";
            track.appendChild(fill);

            const percentText = document.createElement("span");
            percentText.className = "percent";
            percentText.textContent = `${percent}%`;

            row.append(name, track, percentText);
            probabilityList.appendChild(row);

            requestAnimationFrame(() => {
                fill.style.width = `${percent}%`;
            });
        });
    }

    function metricRow(name, valueText, percent) {
        const wrapper = document.createElement("div");

        const head = document.createElement("div");
        head.className = "metric-head";

        const nameEl = document.createElement("span");
        nameEl.className = "metric-name";
        nameEl.textContent = name;

        const valueEl = document.createElement("span");
        valueEl.className = "metric-value";
        valueEl.textContent = valueText;

        head.append(nameEl, valueEl);

        const track = document.createElement("div");
        track.className = "bar-track metric-bar";

        const fill = document.createElement("div");
        fill.className = "bar-fill";
        fill.style.width = "0%";
        track.appendChild(fill);

        wrapper.append(head, track);

        requestAnimationFrame(() => {
            fill.style.width = `${Math.max(0, Math.min(percent, 100))}%`;
        });

        return wrapper;
    }

    function renderMetrics(data) {
        resumeMetrics.replaceChildren();
        assessmentMetrics.replaceChildren();

        RESUME_AREAS.forEach((area) => {
            const count = data.features[area.featureKey];
            resumeMetrics.appendChild(
                metricRow(area.label, `${count} · target ${area.target}`, data.breakdown[area.key])
            );
        });

        ASSESSMENT_AREAS.forEach((area) => {
            const score = data.breakdown[area.key];
            const attempts = data.attempts[area.key];
            assessmentMetrics.appendChild(
                metricRow(area.label, `${score}% average · ${plural(attempts, "attempt")}`, score)
            );
        });
    }

    function renderImprovements(data) {
        improveList.replaceChildren();

        const labels = {};
        RESUME_AREAS.concat(ASSESSMENT_AREAS).forEach((area) => {
            labels[area.key] = area.label;
        });

        const weakest = Object.keys(SUGGESTIONS)
            .map((key) => ({ key, score: data.breakdown[key] }))
            .filter((entry) => entry.score < IMPROVE_BELOW)
            .sort((a, b) => a.score - b.score)
            .slice(0, MAX_SUGGESTIONS);

        if (weakest.length === 0) {
            const empty = document.createElement("p");
            empty.className = "improve-empty";
            empty.textContent =
                "No major gaps stand out. Keep practising and keep your resume up to date.";
            improveList.appendChild(empty);
            return;
        }

        weakest.forEach((entry) => {
            const item = document.createElement("div");
            item.className = "improve-item";

            const body = document.createElement("div");

            const title = document.createElement("strong");
            title.textContent = labels[entry.key];

            const text = document.createElement("p");
            text.textContent = SUGGESTIONS[entry.key] + " ";

            const assessmentArea = ASSESSMENT_AREAS.find((area) => area.key === entry.key);
            if (assessmentArea) {
                const link = document.createElement("a");
                link.href = assessmentArea.link;
                link.textContent = "Practise now";
                text.appendChild(link);
            }

            body.append(title, text);
            item.append(icon("trending-up"), body);
            improveList.appendChild(item);
        });
    }

    function renderResult(data) {
        renderHero(data);
        renderProbabilities(data);
        renderMetrics(data);
        renderImprovements(data);

        modelNote.textContent =
            `${data.note} It shows how your profile compares against a readiness rubric ` +
            "designed for this project. It is not a prediction of real hiring decisions.";

        showState("result");
        refreshIcons();
    }

    // ---------------------------------------------------------
    // Load
    // ---------------------------------------------------------

    async function loadReadiness() {
        showState("loading");

        try {
            const response = await fetch(API_URL);
            const data = await response.json();

            if (!response.ok || !data.success) {
                showError(data.message || "Something went wrong while calculating your readiness.");
                return;
            }

            if (data.status === "needs_data") {
                renderNeedsData(data.missing || []);
                return;
            }

            renderResult(data);

        } catch (error) {
            console.error("Failed to load readiness:", error);
            showError("We couldn't reach the server. Please try again.");
        }
    }

    retryBtn.addEventListener("click", loadReadiness);

    loadReadiness();
});