document.addEventListener("DOMContentLoaded", () => {
    const loadingState = document.getElementById("loading-state");
    const errorState = document.getElementById("error-state");
    const errorMessage = document.getElementById("error-message");
    const resultsWrapper = document.getElementById("results-wrapper");

    const scorePercentageEl = document.getElementById("score-percentage");
    const scoreHeadlineEl = document.getElementById("score-headline");
    const scoreSubtextEl = document.getElementById("score-subtext");
    const scoreTagsEl = document.getElementById("score-tags");
    const breakdownListEl = document.getElementById("question-breakdown-list");

    function showError(message) {
        loadingState.classList.add("hidden");
        resultsWrapper.classList.add("hidden");
        errorMessage.textContent = message;
        errorState.classList.remove("hidden");
    }

    function getAttemptIdFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const attemptId = Number(params.get("attempt_id"));
        return Number.isInteger(attemptId) && attemptId > 0 ? attemptId : null;
    }

    function headlineForScore(percentage) {
        if (percentage >= 80) return "Excellent Work!";
        if (percentage >= 60) return "Good Job!";
        if (percentage >= 40) return "Keep Practicing";
        return "Needs Improvement";
    }

    function renderSummary(summary) {
        scorePercentageEl.textContent = `${summary.score_percentage}%`;
        scoreHeadlineEl.textContent = headlineForScore(summary.score_percentage);
        scoreSubtextEl.textContent =
            `You got ${summary.correct_count} of ${summary.total_questions} questions correct.`;

        scoreTagsEl.replaceChildren();

        const tagValues = [
            summary.category,
            summary.topic_label,
            `${summary.difficulty} difficulty`
        ];

        tagValues.forEach((value) => {
            const span = document.createElement("span");
            span.textContent = value;
            scoreTagsEl.appendChild(span);
        });
    }

    function createIcon(name) {
        const icon = document.createElement("i");
        icon.setAttribute("data-lucide", name);
        return icon;
    }

    function renderOption(optionKey, optionText, question) {
        const row = document.createElement("div");
        row.className = "breakdown-option";

        const isCorrectAnswer = optionKey === question.correct_option;
        const isUserSelection = optionKey === question.selected_option;

        const label = document.createElement("span");
        label.textContent = `${optionKey}. ${optionText}`;
        row.appendChild(label);

        if (isCorrectAnswer) {
            row.classList.add("is-correct-answer");

            const tag = document.createElement("span");
            tag.className = "option-tag";
            tag.append(
                createIcon("check-circle-2"),
                document.createTextNode(isUserSelection ? "Your Answer" : "Correct Answer")
            );
            row.appendChild(tag);

        } else if (isUserSelection) {
            row.classList.add("is-wrong-selection");

            const tag = document.createElement("span");
            tag.className = "option-tag";
            tag.append(
                createIcon("x-circle"),
                document.createTextNode("Your Answer")
            );
            row.appendChild(tag);
        }

        return row;
    }

    function renderBreakdown(questions) {
        breakdownListEl.replaceChildren();

        questions.forEach((question) => {
            const card = document.createElement("div");
            card.className = `breakdown-card ${question.is_correct ? "correct" : "incorrect"}`;

            const header = document.createElement("div");
            header.className = "breakdown-header";

            const questionText = document.createElement("div");
            questionText.className = "breakdown-question-text";

            const number = document.createElement("span");
            number.className = "question-number";
            number.textContent = `Q${question.question_order}.`;

            questionText.append(number, document.createTextNode(question.question_text));

            const badge = document.createElement("span");
            badge.className = `result-badge ${question.is_correct ? "correct" : "incorrect"}`;
            badge.append(
                createIcon(question.is_correct ? "check-circle-2" : "x-circle"),
                document.createTextNode(question.is_correct ? "Correct" : "Incorrect")
            );

            header.append(questionText, badge);

            const optionsWrapper = document.createElement("div");
            optionsWrapper.className = "breakdown-options";

            Object.entries(question.options).forEach(([key, text]) => {
                optionsWrapper.appendChild(renderOption(key, text, question));
            });

            card.append(header, optionsWrapper);

            if (question.explanation) {
                const explanation = document.createElement("div");
                explanation.className = "breakdown-explanation";

                const strong = document.createElement("strong");
                strong.textContent = "Explanation: ";

                explanation.append(strong, document.createTextNode(question.explanation));
                card.appendChild(explanation);
            }

            breakdownListEl.appendChild(card);
        });
    }

    async function loadResults() {
        const attemptId = getAttemptIdFromUrl();

        if (!attemptId) {
            showError("No assessment result was specified.");
            return;
        }

        try {
            const response = await fetch(`api/get-results.php?attempt_id=${attemptId}`);
            const data = await response.json();

            if (!response.ok || !data.success) {
                showError(data.message || "Something went wrong while loading your results.");
                return;
            }

            renderSummary(data.summary);
            renderBreakdown(data.questions);

            if (window.lucide) {
                lucide.createIcons();
            }

            loadingState.classList.add("hidden");
            resultsWrapper.classList.remove("hidden");

        } catch (error) {
            console.error("Failed to load results:", error);
            showError("We couldn't reach the server. Please try again.");
        }
    }

    loadResults();
});