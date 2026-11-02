// ============================================================
// HireWise - Admin Module
// Question Bank page logic: filters, table rendering, and the
// shared Add/Edit modal form.
// ============================================================

// Same topic lists already used by assessment-setup.js, kept in
// sync here rather than fetched from an extra endpoint, since
// these topic_code values are already stable, hardcoded constants
// elsewhere in the project.
const TOPIC_OPTIONS = {
    aptitude: [
        { value: 'quantitative', label: 'Quantitative Aptitude' },
        { value: 'logical', label: 'Logical Reasoning' },
        { value: 'verbal', label: 'Verbal Ability' }
    ],
    technical: [
        { value: 'programming', label: 'Programming' },
        { value: 'dbms', label: 'DBMS' },
        { value: 'os', label: 'Operating Systems' },
        { value: 'oop', label: 'Object-Oriented Programming' },
        { value: 'dsa', label: 'Data Structures & Algorithms' }
    ]
};

function topicLabel(topicCode) {
    const all = [...TOPIC_OPTIONS.aptitude, ...TOPIC_OPTIONS.technical];
    const found = all.find(t => t.value === topicCode);
    return found ? found.label : (topicCode || '\u2014');
}

// ------------------------------------------------------------
// Elements
// ------------------------------------------------------------

const filterCategory = document.getElementById('filterCategory');
const filterTopic = document.getElementById('filterTopic');
const filterDifficulty = document.getElementById('filterDifficulty');
const filterStatus = document.getElementById('filterStatus');

const questionsTableBody = document.getElementById('questionsTableBody');
const questionsEmptyNote = document.getElementById('questionsEmptyNote');
const questionsStatusBanner = document.getElementById('questionsStatusBanner');

const addQuestionBtn = document.getElementById('addQuestionBtn');
const modalOverlay = document.getElementById('questionModalOverlay');
const modalTitle = document.getElementById('questionModalTitle');
const modalCloseBtn = document.getElementById('questionModalCloseBtn');
const modalCancelBtn = document.getElementById('questionModalCancelBtn');
const questionForm = document.getElementById('questionForm');
const questionFormError = document.getElementById('questionFormError');
const questionFormSubmitBtn = document.getElementById('questionFormSubmitBtn');

const formCategory = document.getElementById('formCategory');
const formTopic = document.getElementById('formTopic');
const formDifficulty = document.getElementById('formDifficulty');
const formQuestionText = document.getElementById('formQuestionText');
const formOptionA = document.getElementById('formOptionA');
const formOptionB = document.getElementById('formOptionB');
const formOptionC = document.getElementById('formOptionC');
const formOptionD = document.getElementById('formOptionD');
const formCorrectOption = document.getElementById('formCorrectOption');
const formExplanation = document.getElementById('formExplanation');

let questions = [];
let editingQuestionId = null; // null = Add mode, otherwise Edit mode

// ------------------------------------------------------------
// Small helpers
// ------------------------------------------------------------

function showBanner(message, kind) {
    questionsStatusBanner.textContent = message;
    questionsStatusBanner.className = 'admin-status-banner status-' + kind;
    questionsStatusBanner.hidden = false;
}
function hideBanner() {
    questionsStatusBanner.hidden = true;
    questionsStatusBanner.textContent = '';
}
function createEl(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined) el.textContent = text;
    return el;
}

function populateTopicSelect(selectEl, category, includeAllOption) {
    selectEl.textContent = '';

    if (includeAllOption) {
        selectEl.appendChild(createEl('option', null, 'All Topics')).value = '';
    }

    const topics = category ? (TOPIC_OPTIONS[category] || []) : [...TOPIC_OPTIONS.aptitude, ...TOPIC_OPTIONS.technical];

    topics.forEach(t => {
        const opt = createEl('option', null, t.label);
        opt.value = t.value;
        selectEl.appendChild(opt);
    });
}

// ------------------------------------------------------------
// Filters
// ------------------------------------------------------------

populateTopicSelect(filterTopic, '', true);

filterCategory.addEventListener('change', () => {
    populateTopicSelect(filterTopic, filterCategory.value, true);
    loadQuestions();
});
filterTopic.addEventListener('change', loadQuestions);
filterDifficulty.addEventListener('change', loadQuestions);
filterStatus.addEventListener('change', loadQuestions);

// ------------------------------------------------------------
// Loading + rendering
// ------------------------------------------------------------

async function loadQuestions() {
    showBanner('Loading questions...', 'loading');

    const params = new URLSearchParams();
    if (filterCategory.value) params.set('category', filterCategory.value);
    if (filterTopic.value) params.set('topic_code', filterTopic.value);
    if (filterDifficulty.value) params.set('difficulty', filterDifficulty.value);
    params.set('status', filterStatus.value || 'all');

    try {
        const response = await fetch('api/admin-list-questions.php?' + params.toString());
        const result = await response.json();

        if (!result.success) {
            showBanner(result.message || 'Unable to load questions.', 'error');
            return;
        }

        questions = result.questions || [];
        hideBanner();
        renderQuestions();

    } catch (error) {
        console.error('Load questions error:', error);
        showBanner('Unable to reach the server. Please refresh the page.', 'error');
    }
}

function renderQuestions() {
    questionsTableBody.textContent = '';

    if (questions.length === 0) {
        questionsEmptyNote.hidden = false;
        return;
    }
    questionsEmptyNote.hidden = true;

    questions.forEach((q) => {
        const row = document.createElement('tr');
        if (q.is_active === 0) row.classList.add('row-inactive');

        const textCell = createEl('td', 'question-text-cell', q.question_text);
        textCell.title = q.question_text;
        row.appendChild(textCell);

        row.appendChild(createEl('td', null, q.category.charAt(0).toUpperCase() + q.category.slice(1)));
        row.appendChild(createEl('td', null, topicLabel(q.topic_code)));

        const diffCell = document.createElement('td');
        diffCell.appendChild(createEl('span', 'difficulty-badge', q.difficulty));
        row.appendChild(diffCell);

        const statusCell = document.createElement('td');
        const active = q.is_active === 1;
        statusCell.appendChild(createEl(
            'span',
            'status-badge ' + (active ? 'status-active' : 'status-inactive'),
            active ? 'Active' : 'Inactive'
        ));
        row.appendChild(statusCell);

        const actionsCell = document.createElement('td');
        const actionsWrapper = createEl('div', 'admin-table-actions');

        const editBtn = createEl('button', 'admin-action-btn', 'Edit');
        editBtn.type = 'button';
        editBtn.addEventListener('click', () => openEditModal(q));

        const toggleBtn = createEl(
            'button',
            'admin-action-btn ' + (active ? 'danger' : ''),
            active ? 'Delete' : 'Restore'
        );
        toggleBtn.type = 'button';
        toggleBtn.addEventListener('click', () => toggleQuestionActive(q, !active));

        actionsWrapper.append(editBtn, toggleBtn);
        actionsCell.appendChild(actionsWrapper);
        row.appendChild(actionsCell);

        questionsTableBody.appendChild(row);
    });
}

// ------------------------------------------------------------
// Delete / restore
// ------------------------------------------------------------

async function toggleQuestionActive(question, makeActive) {
    const confirmMsg = makeActive
        ? 'Restore this question? It will become available in future assessments again.'
        : 'Delete this question? It will no longer be used in future assessments, but past results that included it are preserved.';

    if (!window.confirm(confirmMsg)) return;

    try {
        const response = await fetch('api/admin-delete-question.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ question_id: question.question_id, is_active: makeActive ? 1 : 0 })
        });
        const result = await response.json();

        if (!result.success) {
            showBanner(result.message || 'Unable to update this question.', 'error');
            return;
        }

        showBanner(makeActive ? 'Question restored.' : 'Question deleted.', 'info');
        loadQuestions();

    } catch (error) {
        console.error('Toggle question active error:', error);
        showBanner('Unable to reach the server. Please try again.', 'error');
    }
}

// ------------------------------------------------------------
// Modal: open / close
// ------------------------------------------------------------

function openAddModal() {
    editingQuestionId = null;
    modalTitle.textContent = 'Add Question';
    questionFormError.textContent = '';
    questionForm.reset();

    formCategory.value = 'aptitude';
    populateTopicSelect(formTopic, 'aptitude', false);
    formDifficulty.value = 'easy';
    formCorrectOption.value = 'A';

    modalOverlay.hidden = false;
}

function openEditModal(q) {
    editingQuestionId = q.question_id;
    modalTitle.textContent = 'Edit Question';
    questionFormError.textContent = '';

    formCategory.value = q.category;
    populateTopicSelect(formTopic, q.category, false);
    formTopic.value = q.topic_code || '';
    formDifficulty.value = q.difficulty;
    formQuestionText.value = q.question_text;
    formOptionA.value = q.option_a;
    formOptionB.value = q.option_b;
    formOptionC.value = q.option_c;
    formOptionD.value = q.option_d;
    formCorrectOption.value = q.correct_option;
    formExplanation.value = q.explanation;

    modalOverlay.hidden = false;
}

function closeModal() {
    modalOverlay.hidden = true;
    editingQuestionId = null;
}

addQuestionBtn.addEventListener('click', openAddModal);
modalCloseBtn.addEventListener('click', closeModal);
modalCancelBtn.addEventListener('click', closeModal);
modalOverlay.addEventListener('click', (e) => {
    if (e.target === modalOverlay) closeModal();
});

formCategory.addEventListener('change', () => {
    populateTopicSelect(formTopic, formCategory.value, false);
});

// ------------------------------------------------------------
// Modal: submit (Add or Edit, depending on editingQuestionId)
// ------------------------------------------------------------

questionForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    questionFormError.textContent = '';

    const payload = {
        category: formCategory.value,
        topic_code: formTopic.value,
        difficulty: formDifficulty.value,
        question_text: formQuestionText.value.trim(),
        option_a: formOptionA.value.trim(),
        option_b: formOptionB.value.trim(),
        option_c: formOptionC.value.trim(),
        option_d: formOptionD.value.trim(),
        correct_option: formCorrectOption.value,
        explanation: formExplanation.value.trim()
    };

    const isEdit = editingQuestionId !== null;
    if (isEdit) payload.question_id = editingQuestionId;

    const endpoint = isEdit ? 'api/admin-update-question.php' : 'api/admin-add-question.php';

    questionFormSubmitBtn.disabled = true;

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();

        if (!result.success) {
            questionFormError.textContent = result.message || 'Unable to save this question.';
            questionFormSubmitBtn.disabled = false;
            return;
        }

        closeModal();
        showBanner(isEdit ? 'Question updated.' : 'Question added.', 'info');
        loadQuestions();

    } catch (error) {
        console.error('Save question error:', error);
        questionFormError.textContent = 'Unable to reach the server. Please try again.';
    } finally {
        questionFormSubmitBtn.disabled = false;
    }
});

// ------------------------------------------------------------
// Init
// ------------------------------------------------------------

loadQuestions();