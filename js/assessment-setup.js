document.addEventListener("DOMContentLoaded", () => {
    // Read the assessment category from the URL.
    const params = new URLSearchParams(window.location.search);
    let assessmentCategory = params.get("type");

    const allowedCategories = ["aptitude", "technical", "full"];

    if (!allowedCategories.includes(assessmentCategory)) {
        assessmentCategory = "aptitude";
    }

    // Page elements
    const pageTitle = document.querySelector(".topbar-left h1");
    const pageSubtitle = document.querySelector(".topbar-left p");
    const setupHeading = document.querySelector(".setup-heading h2");
    const setupDescription = document.querySelector(".setup-heading p");

    const topicGrid = document.getElementById("topic-grid");
    const topicDescription = document.getElementById("topic-description");
    const topicError = document.getElementById("topic-error");
    const setupError = document.getElementById("setup-error");

    const countButtons = document.querySelectorAll(".count-btn");
    const beginButton = document.getElementById("begin-assessment");

    const assessmentTypeInputs =
        document.querySelectorAll('input[name="assessment-type"]');

    // Topic definitions
    const aptitudeTopics = [
        {
            value: "quantitative",
            label: "Quantitative Aptitude",
            icon: "calculator"
        },
        {
            value: "logical",
            label: "Logical Reasoning",
            icon: "puzzle"
        },
        {
            value: "verbal",
            label: "Verbal Ability",
            icon: "message-square-text"
        }
    ];

    const technicalTopics = [
        {
            value: "programming",
            label: "Programming",
            icon: "code"
        },
        {
            value: "dbms",
            label: "DBMS",
            icon: "database"
        },
        {
            value: "os",
            label: "Operating Systems",
            icon: "monitor"
        },
        {
            value: "oop",
            label: "Object-Oriented Programming",
            icon: "boxes"
        },
        {
            value: "dsa",
            label: "Data Structures & Algorithms",
            icon: "binary"
        }
    ];

    let selectedQuestionCount = 20;

    // Set up the page according to the category.
    function configurePage() {
        if (assessmentCategory === "aptitude") {
            pageTitle.textContent = "Aptitude Assessment";
            pageSubtitle.textContent =
                "Practice quantitative, logical and verbal aptitude.";

            setupHeading.textContent = "Configure Your Aptitude Test";
            setupDescription.textContent =
                "Choose your aptitude practice mode and topics.";

            topicDescription.textContent =
                "Choose a specific aptitude topic or practice all aptitude topics.";

            renderTopics(aptitudeTopics, "all_aptitude", "All Aptitude");

        } else if (assessmentCategory === "technical") {
            pageTitle.textContent = "Technical Assessment";
            pageSubtitle.textContent =
                "Practice programming and computer science topics.";

            setupHeading.textContent = "Configure Your Technical Test";
            setupDescription.textContent =
                "Choose your technical practice mode and topics.";

            topicDescription.textContent =
                "Choose a technical subject or practice all technical topics.";

            renderTopics(technicalTopics, "all_technical", "All Technical");

        } else {
            pageTitle.textContent = "Full Placement Assessment";
            pageSubtitle.textContent =
                "Assess your aptitude and technical readiness together.";

            setupHeading.textContent = "Configure Your Full Assessment";
            setupDescription.textContent =
                "This assessment includes both aptitude and technical questions.";

            topicDescription.textContent =
                "Full Test includes all aptitude and technical topics.";

            renderTopics([], "full", "Aptitude + Technical");
        }

        configureAssessmentModes();
    }

    // Generate topic cards safely using DOM methods.
    function renderTopics(topics, allValue, allLabel) {
        topicGrid.replaceChildren();

        const allTopics = [
            ...topics,
            {
                value: allValue,
                label: allLabel,
                icon: "layers-3"
            }
        ];

        allTopics.forEach((topic) => {
            const label = document.createElement("label");
            label.className = "topic-option";

            const input = document.createElement("input");
            input.type = "radio";
            input.name = "topic";
            input.value = topic.value;

            const content = document.createElement("div");
            content.className = "topic-content";

            const icon = document.createElement("i");
            icon.setAttribute("data-lucide", topic.icon);

            const text = document.createElement("span");
            text.textContent = topic.label;

            content.append(icon, text);
            label.append(input, content);

            topicGrid.appendChild(label);
        });

        // Refresh icons for the newly created topic cards.
        if (window.lucide) {
            lucide.createIcons();
        }

        topicError.textContent = "";
    }

    // Mixed Assessment = all topics in the chosen category.
    // Focused Practice = one specific topic.
    function configureAssessmentModes() {
        const focusedInput =
            document.querySelector('input[name="assessment-type"][value="focused"]');

        const mixedInput =
            document.querySelector('input[name="assessment-type"][value="mixed"]');

        const focusedCard = focusedInput.closest(".option-card");

        if (assessmentCategory === "full") {
            mixedInput.checked = true;
            focusedInput.disabled = true;
            focusedCard.classList.add("disabled");
            focusedCard.setAttribute("aria-disabled", "true");

            focusedCard.querySelector(".option-description").textContent =
                "Focused mode is unavailable for the full placement assessment.";

            // Full Test always includes both aptitude and technical topics.
            const fullTopic = document.querySelector(
                'input[name="topic"][value="full"]'
            );

            if (fullTopic) {
                fullTopic.checked = true;
                fullTopic.disabled = true;
            }

        } else {
            focusedInput.disabled = false;
            focusedCard.classList.remove("disabled");
            focusedCard.removeAttribute("aria-disabled");

            focusedCard.querySelector(".option-description").textContent =
                "Concentrate on one specific topic.";

            updateTopicMode();
        }

        assessmentTypeInputs.forEach((input) => {
            input.addEventListener("change", updateTopicMode);
        });
    }

    // Update topic availability when assessment mode changes.
    function updateTopicMode() {
        if (assessmentCategory === "full") {
            return;
        }

        const selectedMode =
            document.querySelector('input[name="assessment-type"]:checked');

        const topicInputs =
            document.querySelectorAll('input[name="topic"]');

        const allValue =
            assessmentCategory === "aptitude"
                ? "all_aptitude"
                : "all_technical";

        topicInputs.forEach((input) => {
            const isAllOption = input.value === allValue;

            if (selectedMode.value === "focused") {
                input.disabled = isAllOption;

                if (isAllOption && input.checked) {
                    input.checked = false;
                }
            } else {
                input.disabled = false;
            }
        });

        topicError.textContent = "";
    }

    // Question count selection.
    countButtons.forEach((button) => {
        button.addEventListener("click", () => {
            selectedQuestionCount = Number(button.dataset.count);

            countButtons.forEach((item) => {
                const isSelected = item === button;

                item.classList.toggle("active", isSelected);
                item.setAttribute("aria-pressed", String(isSelected));
            });

            setupError.textContent = "";
        });
    });

    // Begin Assessment: validate and save the configuration.
    beginButton.addEventListener("click", () => {
        setupError.textContent = "";
        topicError.textContent = "";

        const selectedMode =
            document.querySelector('input[name="assessment-type"]:checked');

        const selectedDifficulty =
            document.querySelector('input[name="difficulty"]:checked');

        const selectedTopic =
            document.querySelector('input[name="topic"]:checked');

        if (!selectedMode) {
            setupError.textContent =
                "Please select an assessment mode.";
            return;
        }

        if (!selectedDifficulty) {
            setupError.textContent =
                "Please select a difficulty level.";
            return;
        }

        if (!selectedTopic) {
            topicError.textContent =
                "Please select a topic before continuing.";
            return;
        }

        if (selectedTopic.disabled && assessmentCategory !== "full") {
            topicError.textContent =
                "Please choose an available topic for your selected mode.";
            return;
        }

        const configuration = {
            category: assessmentCategory,
            mode: selectedMode.value,
            topic: selectedTopic.value,
            difficulty: selectedDifficulty.value,
            questionCount: selectedQuestionCount
        };

        try {
            sessionStorage.setItem(
                "hirewiseAssessmentConfig",
                JSON.stringify(configuration)
            );

            window.location.href = "assessment-take.php";

        } catch (error) {
            console.error("Could not save assessment settings:", error);

            setupError.textContent =
                "Unable to save your settings. Please try again.";
        }
    });

    configurePage();
});