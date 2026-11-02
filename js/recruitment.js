// ============================================================
// HireWise - Recruitment Analysis
// Handles: input method switching, PDF/image validation and
// preview, text input, mutual exclusivity between the three
// input methods, and submitting the analysis request.
//
// ============================================================

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
const MAX_TEXT_LENGTH = 6000;

let activeMethod = 'pdf';
let selectedPdfFile = null;
let selectedImageFile = null;

// ------------------------------------------------------------
// Small helpers
// ------------------------------------------------------------

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}

function showBanner(message, kind) {
    const banner = document.getElementById('recStatusBanner');
    banner.textContent = message;
    banner.className = 'rec-status-banner status-' + kind;
    banner.hidden = false;
}

function hideBanner() {
    const banner = document.getElementById('recStatusBanner');
    banner.hidden = true;
    banner.textContent = '';
}

// ------------------------------------------------------------
// Method tab switching
// ------------------------------------------------------------

function switchMethod(method) {
    activeMethod = method;

    document.querySelectorAll('.method-tab').forEach(tab => {
        const isActive = tab.dataset.method === method;
        tab.classList.toggle('active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    document.querySelectorAll('.method-panel').forEach(panel => {
        panel.hidden = panel.dataset.panel !== method;
    });

    // Clear the other two methods' input so only one source can
    // ever be submitted at a time.
    if (method !== 'pdf') clearPdfSelection();
    if (method !== 'image') clearImageSelection();
    if (method !== 'text') {
        document.getElementById('textInput').value = '';
        updateCharCounter();
    }

    hideBanner();
    updateAnalyzeButtonState();
}

document.querySelectorAll('.method-tab').forEach(tab => {
    tab.addEventListener('click', () => switchMethod(tab.dataset.method));
});

// ------------------------------------------------------------
// PDF handling
// ------------------------------------------------------------

const pdfDropzone = document.getElementById('pdfDropzone');
const pdfFileInput = document.getElementById('pdfFileInput');
const pdfBrowseBtn = document.getElementById('pdfBrowseBtn');
const pdfDropzoneEmpty = document.getElementById('pdfDropzoneEmpty');
const pdfSelectedCard = document.getElementById('pdfSelectedCard');
const pdfRemoveBtn = document.getElementById('pdfRemoveBtn');

function validatePdfFile(file) {
    const extension = file.name.split('.').pop().toLowerCase();

    if (extension !== 'pdf' || (file.type && file.type !== 'application/pdf')) {
        return 'Only PDF files are allowed.';
    }
    if (file.size <= 0) {
        return 'The selected PDF appears to be empty.';
    }
    if (file.size > MAX_FILE_SIZE) {
        return 'PDF must be under 5 MB.';
    }
    return null;
}

function setPdfFile(file) {
    const error = validatePdfFile(file);
    if (error) {
        showBanner(error, 'error');
        return;
    }

    selectedPdfFile = file;
    hideBanner();

    document.getElementById('pdfFileName').textContent = file.name;
    document.getElementById('pdfFileSize').textContent = formatFileSize(file.size);

    pdfDropzoneEmpty.hidden = true;
    pdfSelectedCard.hidden = false;

    updateAnalyzeButtonState();
}

function clearPdfSelection() {
    selectedPdfFile = null;
    pdfFileInput.value = '';
    pdfDropzoneEmpty.hidden = false;
    pdfSelectedCard.hidden = true;
    updateAnalyzeButtonState();
}

pdfBrowseBtn.addEventListener('click', () => pdfFileInput.click());
pdfDropzone.addEventListener('click', (e) => {
    if (e.target === pdfDropzone) pdfFileInput.click();
});
pdfDropzone.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        pdfFileInput.click();
    }
});

pdfFileInput.addEventListener('change', () => {
    if (pdfFileInput.files[0]) setPdfFile(pdfFileInput.files[0]);
});

pdfRemoveBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    clearPdfSelection();
});

['dragover', 'dragenter'].forEach(evt => {
    pdfDropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        pdfDropzone.classList.add('drag-active');
    });
});
['dragleave', 'dragend'].forEach(evt => {
    pdfDropzone.addEventListener(evt, () => {
        pdfDropzone.classList.remove('drag-active');
    });
});
pdfDropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    pdfDropzone.classList.remove('drag-active');
    if (e.dataTransfer.files[0]) setPdfFile(e.dataTransfer.files[0]);
});

// ------------------------------------------------------------
// Image handling
// ------------------------------------------------------------

const imageDropzone = document.getElementById('imageDropzone');
const imageFileInput = document.getElementById('imageFileInput');
const imageBrowseBtn = document.getElementById('imageBrowseBtn');
const imageDropzoneEmpty = document.getElementById('imageDropzoneEmpty');
const imageSelectedCard = document.getElementById('imageSelectedCard');
const imageRemoveBtn = document.getElementById('imageRemoveBtn');
const imagePreview = document.getElementById('imagePreview');

const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png'];

function validateImageFile(file) {
    const extension = file.name.split('.').pop().toLowerCase();
    const validExtension = ['jpg', 'jpeg', 'png'].includes(extension);

    if (!validExtension || (file.type && !ALLOWED_IMAGE_TYPES.includes(file.type))) {
        return 'Only JPG and PNG images are allowed.';
    }
    if (file.size <= 0) {
        return 'The selected image appears to be empty.';
    }
    if (file.size > MAX_FILE_SIZE) {
        return 'Image must be under 5 MB.';
    }
    return null;
}

function setImageFile(file) {
    const error = validateImageFile(file);
    if (error) {
        showBanner(error, 'error');
        return;
    }

    selectedImageFile = file;
    hideBanner();

    document.getElementById('imageFileName').textContent = file.name;
    document.getElementById('imageFileSize').textContent = formatFileSize(file.size);

    imagePreview.classList.remove('preview-failed');
    imagePreview.alt = 'Selected screenshot preview';

    // Catches the browser failing to DECODE the loaded data as an
    // image (corrupt/unsupported file) - different from FileReader
    // failing to read the file's bytes, handled below.
    imagePreview.onerror = () => {
        console.error('Browser could not decode the image data (src set, but decode failed).');
        imagePreview.classList.add('preview-failed');
        imagePreview.alt = 'Preview unavailable - file may be corrupted';
        document.getElementById('imageFileSize').textContent =
            formatFileSize(file.size) + '  \u2014  DEBUG: image FAILED TO DECODE';
    };

    const reader = new FileReader();

    reader.onload = (e) => {
        const result = e.target.result || '';
        console.log('FileReader finished. Result length:', result.length);
        document.getElementById('imageFileSize').textContent =
            formatFileSize(file.size) + '  \u2014  DEBUG: read OK, ' + result.length + ' chars, prefix: ' + result.slice(0, 30);
        imagePreview.src = result;
    };

    reader.onerror = () => {
        console.error('FileReader failed to read the file:', reader.error);
        imagePreview.classList.add('preview-failed');
        imagePreview.alt = 'Preview unavailable';
        imagePreview.removeAttribute('src');
        document.getElementById('imageFileSize').textContent =
            formatFileSize(file.size) + '  \u2014  DEBUG: FileReader ERROR';
    };

    reader.readAsDataURL(file);

    imageDropzoneEmpty.hidden = true;
    imageSelectedCard.hidden = false;

    updateAnalyzeButtonState();
}

function clearImageSelection() {
    selectedImageFile = null;
    imageFileInput.value = '';
    imagePreview.src = '';
    imageDropzoneEmpty.hidden = false;
    imageSelectedCard.hidden = true;
    updateAnalyzeButtonState();
}

imageBrowseBtn.addEventListener('click', () => imageFileInput.click());
imageDropzone.addEventListener('click', (e) => {
    if (e.target === imageDropzone) imageFileInput.click();
});
imageDropzone.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        imageFileInput.click();
    }
});

imageFileInput.addEventListener('change', () => {
    if (imageFileInput.files[0]) setImageFile(imageFileInput.files[0]);
});

imageRemoveBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    clearImageSelection();
});

['dragover', 'dragenter'].forEach(evt => {
    imageDropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        imageDropzone.classList.add('drag-active');
    });
});
['dragleave', 'dragend'].forEach(evt => {
    imageDropzone.addEventListener(evt, () => {
        imageDropzone.classList.remove('drag-active');
    });
});
imageDropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    imageDropzone.classList.remove('drag-active');
    if (e.dataTransfer.files[0]) setImageFile(e.dataTransfer.files[0]);
});

// ------------------------------------------------------------
// Text handling
// ------------------------------------------------------------

const textInput = document.getElementById('textInput');
const charCounter = document.getElementById('charCounter');
const clearTextBtn = document.getElementById('clearTextBtn');

function updateCharCounter() {
    const length = textInput.value.length;
    charCounter.textContent = length + ' / ' + MAX_TEXT_LENGTH;
    charCounter.classList.toggle('char-counter-warn', length >= MAX_TEXT_LENGTH - 200);
    updateAnalyzeButtonState();
}

textInput.addEventListener('input', updateCharCounter);

clearTextBtn.addEventListener('click', () => {
    textInput.value = '';
    updateCharCounter();
});

// ------------------------------------------------------------
// Analyze button state
// ------------------------------------------------------------

const analyzeBtn = document.getElementById('analyzeBtn');
const analyzeHint = document.getElementById('analyzeHint');

function hasValidInput() {
    if (activeMethod === 'pdf') return selectedPdfFile !== null;
    if (activeMethod === 'image') return selectedImageFile !== null;
    if (activeMethod === 'text') return textInput.value.trim().length > 0;
    return false;
}

function updateAnalyzeButtonState() {
    const valid = hasValidInput();
    analyzeBtn.disabled = !valid;
    analyzeHint.textContent = valid
        ? 'Ready to analyze.'
        : 'Select a PDF, image, or paste text to begin.';
}

// ------------------------------------------------------------
// Results state helpers
// ------------------------------------------------------------

const resultsEmptyState = document.getElementById('resultsEmptyState');
const resultsProcessingState = document.getElementById('resultsProcessingState');
const resultsReportState = document.getElementById('resultsReportState');

function showResultsState(state) {
    resultsEmptyState.hidden = state !== 'empty';
    resultsProcessingState.hidden = state !== 'processing';
    resultsReportState.hidden = state !== 'report';
}

// ------------------------------------------------------------
// Report rendering
// ------------------------------------------------------------
// Every piece of text here comes from the user's own submitted
// content or from our own backend - but we still build the DOM
// with textContent/createElement only, never innerHTML, exactly
// like resume.php does. This means nothing extracted from a
// message (e.g. a scammer's own text) can ever run as script.

const RISK_INFO = {
    Low: { label: 'Low Risk', className: 'risk-low' },
    Medium: { label: 'Medium Risk', className: 'risk-medium' },
    High: { label: 'High Risk', className: 'risk-high' }
};

const PRECAUTIONS = [
    'Verify the company through its official website, not just the link or contact info given in the message.',
    'Never pay any fee, deposit, or "processing charge" to be hired - legitimate employers do not ask candidates to pay.',
    'Be cautious of recruiters using a personal email address (Gmail, Yahoo, etc.) instead of a company domain.',
    'Do not share bank details, ID numbers, OTPs, or card details with a recruiter.',
    'Cross-check the job posting and recruiter on LinkedIn or the company\u2019s own careers page.',
    'When in doubt, contact the company directly using contact details from their official website - not the ones provided in the message.'
];

function createEl(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined) el.textContent = text;
    return el;
}

function renderReport(result) {
    const container = resultsReportState;
    container.textContent = '';

    const riskInfo = RISK_INFO[result.risk_category] || RISK_INFO.Low;

    // --- Header: risk badge + model score ---
    const header = createEl('div', 'report-header');

    const badge = createEl('span', 'risk-badge ' + riskInfo.className, riskInfo.label);
    header.appendChild(badge);

    if (typeof result.model_score === 'number') {
        const scoreText = createEl(
            'span',
            'model-score-text',
            'Model similarity score: ' + Math.round(result.model_score * 100) + '%'
        );
        header.appendChild(scoreText);
    }

    container.appendChild(header);

    // --- Warning (e.g. very little text extracted) ---
    if (result.warning) {
        container.appendChild(createEl('div', 'report-warning', result.warning));
    }

    // --- Explanation note (model's real basis, in plain language) ---
    if (result.note) {
        const noteSection = createEl('div', 'report-note');
        noteSection.appendChild(createEl('h4', null, 'How to read this result'));
        noteSection.appendChild(createEl('p', null, result.note));
        container.appendChild(noteSection);
    }

    // --- Rule-based indicators (evidence-backed) ---
    const indicatorsSection = createEl('div', 'report-section');
    indicatorsSection.appendChild(createEl('h4', null, 'Warning Signs Found'));

    const indicators = result.indicators || [];

    if (indicators.length === 0) {
        indicatorsSection.appendChild(createEl(
            'p', 'empty-note',
            'No specific rule-based warning signs were found in this text.'
        ));
    } else {
        const list = createEl('div', 'indicator-list');
        indicators.forEach(ind => {
            const card = createEl('div', 'indicator-card severity-' + ind.severity);

            const labelRow = createEl('div', 'indicator-label-row');
            labelRow.appendChild(createEl('span', 'indicator-severity-tag', ind.severity.toUpperCase()));
            labelRow.appendChild(createEl('span', 'indicator-label', ind.label));
            card.appendChild(labelRow);

            if (ind.evidence) {
                card.appendChild(createEl('p', 'indicator-evidence', '\u201C' + ind.evidence + '\u201D'));
            }

            list.appendChild(card);
        });
        indicatorsSection.appendChild(list);
    }

    container.appendChild(indicatorsSection);

    // --- Suggested precautions ---
    const precautionsSection = createEl('div', 'report-section');
    precautionsSection.appendChild(createEl('h4', null, 'Suggested Precautions'));
    const precautionsList = createEl('ul', 'precautions-list');
    PRECAUTIONS.forEach(tip => {
        precautionsList.appendChild(createEl('li', null, tip));
    });
    precautionsSection.appendChild(precautionsList);
    container.appendChild(precautionsSection);

    // --- Extracted content (what was actually analyzed) ---
    const extractedSection = createEl('div', 'report-section');
    extractedSection.appendChild(createEl('h4', null, 'Content That Was Analyzed'));
    const extractedBox = createEl('pre', 'extracted-text-box', result.extracted_text || '');
    extractedSection.appendChild(extractedBox);
    container.appendChild(extractedSection);

    // --- Disclaimer footer ---
    container.appendChild(createEl(
        'p', 'report-disclaimer',
        'This is a decision-support tool, not a guaranteed scam detector. Use your own judgement alongside this report.'
    ));
}

// ------------------------------------------------------------
// Submission
// ------------------------------------------------------------

analyzeBtn.addEventListener('click', async () => {
    if (!hasValidInput()) return;

    const formData = new FormData();
    formData.append('input_type', activeMethod);

    if (activeMethod === 'pdf') {
        formData.append('file', selectedPdfFile);
    } else if (activeMethod === 'image') {
        formData.append('file', selectedImageFile);
    } else if (activeMethod === 'text') {
        formData.append('text', textInput.value.trim());
    }

    analyzeBtn.disabled = true;
    hideBanner();
    showResultsState('processing');
    document.getElementById('processingTitle').textContent = 'Analyzing your submission\u2026';
    document.getElementById('processingSubtitle').textContent =
        'Extracting content and checking for recruitment risk indicators.';

    try {
        const response = await fetch('api/analyze_recruitment.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            showResultsState('empty');
            showBanner(result.message || 'Analysis failed. Please try again.', 'error');
            return;
        }

        hideBanner();
        renderReport(result);
        showResultsState('report');

    } catch (error) {
        console.error('Recruitment analysis error:', error);
        showResultsState('empty');
        showBanner(
            'Unable to reach the analysis service. This is expected until the backend endpoint is built.',
            'error'
        );
    } finally {
        updateAnalyzeButtonState();
    }
});

// ------------------------------------------------------------
// Init
// ------------------------------------------------------------

updateCharCounter();
updateAnalyzeButtonState();