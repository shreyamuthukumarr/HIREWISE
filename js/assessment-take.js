document.addEventListener("DOMContentLoaded", () => {
    const loadingState = document.getElementById("loading-state");
    const errorState = document.getElementById("error-state");
    const errorMessage = document.getElementById("error-message");
    const quizWrapper = document.getElementById("quiz-wrapper");
    const quizSummary = document.getElementById("quiz-summary");
    const questionList = document.getElementById("question-list");

    const submitBar = document.getElementById("submit-bar");
    const submitBtn = document.getElementById("submit-btn");
    const answeredCountEl = document.getElementById("answered-count");
    const totalCountEl = document.getElementById("total-count");

    // In-memory state for this attempt
    let attemptId = null;
    let questions = [];
    const selectedAnswers = {}; // { [question_id]: "A" | "B" | "C" | "D" }

    function showError(message) {
        loadingState.classList.add("hidden");
        quizWrapper.classList.add("hidden");
        submitBar.classList.add("hidden");
        errorMessage.textContent = message;
        errorState.classList.remove("hidden");
    }

    function readConfig() {
        const raw = sessionStorage.getItem("hirewiseAssessmentConfig");

        if (!raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            console.error("Could not parse saved assessment config:", error);
            return null;
        }
    }

    function renderSummary(config) {
        quizSummary.replaceChildren();

        const items = [
            config.category,
            config.topic.replace(/_/g, " "),
            config.difficulty,
            `${config.questionCount} questions`
        ];

        items.forEach((text) => {
            const span = document.createElement("span");
            span.textContent = text;
            quizSummary.appendChild(span);
        });
    }

    function renderQuestions() {
        questionList.replaceChildren();

        questions.forEach((question) => {
            const card = document.createElement("div");
            card.className = "question-card";

            const header = document.createElement("div");
            header.className = "question-header";

            const number = document.createElement("span");
            number.className = "question-number";
            number.textContent = `Q${question.question_order}.`;

            const text = document.createElement("span");
            text.className = "question-text";
            text.textContent = question.question_text;

            header.append(number, text);

            const optionList = document.createElement("div");
            optionList.className = "option-list";

            Object.entries(question.options).forEach(([key, optionText]) => {
                const label = document.createElement("label");
                label.className = "answer-option";

                const input = document.createElement("input");
                input.type = "radio";
                input.name = `question-${question.question_id}`;
                input.value = key;

                input.addEventListener("change", () => {
                    selectedAnswers[question.question_id] = key;
                    updateProgress();
                });

                const marker = document.createElement("span");
                marker.className = "answer-marker";

                const labelText = document.createElement("span");
                labelText.className = "answer-label";
                labelText.textContent = `${key}. ${optionText}`;

                label.append(input, marker, labelText);
                optionList.appendChild(label);
            });

            card.append(header, optionList);
            questionList.appendChild(card);
        });
    }

    function updateProgress() {
        const answered = Object.keys(selectedAnswers).length;
        answeredCountEl.textContent = String(answered);
        totalCountEl.textContent = String(questions.length);
    }

    async function startAssessment() {
        const config = readConfig();

        if (!config) {
            showError(
                "We couldn't find your assessment setup. Please go back and configure your assessment again."
            );
            return;
        }

        try {
            const response = await fetch("api/start-assessment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(config)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                showError(
                    data.message ||
                    "Something went wrong while starting your assessment."
                );
                return;
            }

            attemptId = data.attempt_id;
            questions = data.questions;

            renderSummary(config);
            renderQuestions();
            updateProgress();

            if (window.lucide) {
                lucide.createIcons();
            }

            loadingState.classList.add("hidden");
            quizWrapper.classList.remove("hidden");
            submitBar.classList.remove("hidden");

        } catch (error) {
            console.error("Failed to start assessment:", error);
            showError(
                "We couldn't reach the server. Please check your connection and try again."
            );
        }
    }

    submitBtn.addEventListener("click", async () => {
        const answered = Object.keys(selectedAnswers).length;

        if (answered < questions.length) {
            const proceed = window.confirm(
                `You've answered ${answered} of ${questions.length} questions. ` +
                "Unanswered questions will be marked incorrect. Submit anyway?"
            );

            if (!proceed) {
                return;
            }
        }

        const answers = questions.map((question) => ({
            question_id: question.question_id,
            selected_option: selectedAnswers[question.question_id] || null
        }));

        submitBtn.disabled = true;
        submitBtn.textContent = "Submitting...";

        try {
            const response = await fetch("api/submit-assessment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    attempt_id: attemptId,
                    answers: answers
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                submitBtn.disabled = false;
                submitBtn.textContent = "Submit Assessment";
                alert(
                    data.message ||
                    "Something went wrong while submitting your assessment."
                );
                return;
            }

            // The results page (built in a later step) will read this attempt_id
            // from the URL to fetch and display the score breakdown.
            window.location.href = `assessment-results.php?attempt_id=${attemptId}`;

        } catch (error) {
            console.error("Failed to submit assessment:", error);
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit Assessment";
            alert("We couldn't reach the server. Please try again.");
        }
    });

    startAssessment();
});