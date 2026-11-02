// ============================================================
// HireWise - Offer Comparison
// Handles: input method switching, PDF validation/drag-drop, text
// input, adding an offer, loading the user's saved offers, and
// rendering both the offer cards and the comparison table.
//
// NOTE: api/add_offer.php, api/get_offers.php, and
// api/delete_offer.php do not exist yet (Phase 3). Until then,
// calls to them will correctly fail with a connection error -
// that's expected, not a bug in this file.
// ============================================================

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
const MAX_TEXT_LENGTH = 6000;

// Fields shown in both the comparison table and (a subset of) the
// offer cards. Centralized here so adding a field later means
// editing one list, not several render functions.
const FIELD_DEFS = [
    { key: 'company_name', label: 'Company' },
    { key: 'job_role', label: 'Role' },
    { key: 'salary_details', label: 'Salary / CTC' },
    { key: 'location', label: 'Location' },
    { key: 'work_mode', label: 'Work Mode' },
    { key: 'benefits', label: 'Benefits' },
    { key: 'bond_details', label: 'Bond Details' },
    { key: 'notice_period', label: 'Notice Period' },
    { key: 'other_conditions', label: 'Other Conditions' }
];

// Fields shown directly on the compact offer card (a subset -
// the full picture lives in the comparison table below).
const CARD_FIELD_KEYS = ['salary_details', 'location', 'work_mode', 'notice_period'];

let activeMethod = 'pdf';
let selectedPdfFile = null;
let offers = []; // loaded from the server, one object per saved offer


// ------------------------------------------------------------
// Small helpers
// ------------------------------------------------------------

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}

function showBanner(message, kind) {
    const banner = document.getElementById('offerStatusBanner');
    banner.textContent = message;
    banner.className = 'offer-status-banner status-' + kind;
    banner.hidden = false;
}

function hideBanner() {
    const banner = document.getElementById('offerStatusBanner');
    banner.hidden = true;
    banner.textContent = '';
}

function createEl(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined) el.textContent = text;
    return el;
}

function fieldValueOrPlaceholder(value) {
    const trimmed = (value || '').toString().trim();
    return trimmed === '' ? 'Not specified' : trimmed;
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

    if (method !== 'pdf') clearPdfSelection();
    if (method !== 'text') {
        offerTextInput.value = '';
        updateCharCounter();
    }

    hideBanner();
    updateAddOfferButtonState();
}

document.querySelectorAll('.method-tab').forEach(tab => {
    tab.addEventListener('click', () => switchMethod(tab.dataset.method));
});

// ------------------------------------------------------------
// PDF handling
// ------------------------------------------------------------

const pdfDropzone = document.getElementById('offerPdfDropzone');
const pdfFileInput = document.getElementById('offerPdfInput');
const pdfBrowseBtn = document.getElementById('offerPdfBrowseBtn');
const pdfDropzoneEmpty = document.getElementById('offerPdfEmpty');
const pdfSelectedCard = document.getElementById('offerPdfSelectedCard');
const pdfRemoveBtn = document.getElementById('offerPdfRemoveBtn');

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

    document.getElementById('offerPdfFileName').textContent = file.name;
    document.getElementById('offerPdfFileSize').textContent = formatFileSize(file.size);

    pdfDropzoneEmpty.hidden = true;
    pdfSelectedCard.hidden = false;

    updateAddOfferButtonState();
}

function clearPdfSelection() {
    selectedPdfFile = null;
    pdfFileInput.value = '';
    pdfDropzoneEmpty.hidden = false;
    pdfSelectedCard.hidden = true;
    updateAddOfferButtonState();
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
// Text handling
// ------------------------------------------------------------

const offerTextInput = document.getElementById('offerTextInput');
const offerCharCounter = document.getElementById('offerCharCounter');
const offerClearTextBtn = document.getElementById('offerClearTextBtn');

function updateCharCounter() {
    const length = offerTextInput.value.length;
    offerCharCounter.textContent = length + ' / ' + MAX_TEXT_LENGTH;
    offerCharCounter.classList.toggle('char-counter-warn', length >= MAX_TEXT_LENGTH - 200);
    updateAddOfferButtonState();
}

offerTextInput.addEventListener('input', updateCharCounter);

offerClearTextBtn.addEventListener('click', () => {
    offerTextInput.value = '';
    updateCharCounter();
});

// ------------------------------------------------------------
// Add Offer button state
// ------------------------------------------------------------

const addOfferBtn = document.getElementById('addOfferBtn');
const addOfferHint = document.getElementById('addOfferHint');

function hasValidInput() {
    if (activeMethod === 'pdf') return selectedPdfFile !== null;
    if (activeMethod === 'text') return offerTextInput.value.trim().length > 0;
    return false;
}

function updateAddOfferButtonState() {
    const valid = hasValidInput();
    addOfferBtn.disabled = !valid;
    addOfferHint.textContent = valid
        ? 'Ready to add this offer.'
        : 'Upload a PDF or paste offer text to begin.';
}

// ------------------------------------------------------------
// Rendering: offer count, offer cards, comparison table
// ------------------------------------------------------------

const offerCountEl = document.getElementById('offerCount');
const offersEmptyState = document.getElementById('offersEmptyState');
const offerCardsGrid = document.getElementById('offerCardsGrid');
const comparisonSection = document.getElementById('comparisonSection');
const comparisonTable = document.getElementById('comparisonTable');

function renderAll() {
    offerCountEl.textContent = offers.length;

    if (offers.length === 0) {
        offersEmptyState.hidden = false;
        offerCardsGrid.hidden = true;
        comparisonSection.hidden = true;
        // Clear stale content too, not just hide it - otherwise an
        // old card/table row can linger in the DOM from before the
        // last offer was removed.
        offerCardsGrid.textContent = '';
        comparisonTable.textContent = '';
        return;
    }

    offersEmptyState.hidden = true;
    offerCardsGrid.hidden = false;
    comparisonSection.hidden = false;

    renderOfferCards();
    renderComparisonTable();
}

function renderOfferCards() {
    offerCardsGrid.textContent = '';

    offers.forEach(offer => {
        const card = createEl('div', 'offer-card');

        const header = createEl('div', 'offer-card-header');
        const titleBlock = createEl('div', 'offer-card-title');
        titleBlock.appendChild(createEl('h3', null, fieldValueOrPlaceholder(offer.company_name)));
        titleBlock.appendChild(createEl('p', null, fieldValueOrPlaceholder(offer.job_role)));
        header.appendChild(titleBlock);

        const removeBtn = createEl('button', 'offer-card-remove');
        removeBtn.type = 'button';
        removeBtn.setAttribute('aria-label', 'Remove this offer');
        const removeIcon = document.createElement('i');
        removeIcon.setAttribute('data-lucide', 'trash-2');
        removeBtn.appendChild(removeIcon);
        removeBtn.addEventListener('click', () => deleteOffer(offer.offer_id));
        header.appendChild(removeBtn);

        card.appendChild(header);

        CARD_FIELD_KEYS.forEach(key => {
            const def = FIELD_DEFS.find(f => f.key === key);
            const row = createEl('div', 'offer-card-field');
            row.appendChild(createEl('span', 'offer-card-field-label', def.label));

            const value = (offer[key] || '').toString().trim();
            const valueEl = createEl(
                'span',
                'offer-card-field-value' + (value === '' ? ' not-specified' : ''),
                value === '' ? 'Not specified' : value
            );
            row.appendChild(valueEl);
            card.appendChild(row);
        });

        offerCardsGrid.appendChild(card);
    });

    // Re-run lucide for the trash icons just added.
    if (window.lucide) window.lucide.createIcons();
}

function renderComparisonTable() {
    comparisonTable.textContent = '';

    // Header row: blank corner + one column per offer.
    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    headRow.appendChild(createEl('th', 'row-label-cell', ''));

    offers.forEach(offer => {
        headRow.appendChild(createEl('th', null, fieldValueOrPlaceholder(offer.company_name)));
    });
    thead.appendChild(headRow);
    comparisonTable.appendChild(thead);

    // One row per field, one cell per offer.
    const tbody = document.createElement('tbody');

    FIELD_DEFS.forEach(def => {
        const row = document.createElement('tr');
        row.appendChild(createEl('td', 'row-label-cell', def.label));

        offers.forEach(offer => {
            const value = (offer[def.key] || '').toString().trim();
            const cell = createEl(
                'td',
                value === '' ? 'not-specified' : null,
                value === '' ? 'Not specified' : value
            );
            row.appendChild(cell);
        });

        tbody.appendChild(row);
    });

    comparisonTable.appendChild(tbody);
}

// ------------------------------------------------------------
// Loading saved offers
// ------------------------------------------------------------

async function loadOffers() {
    try {
        const response = await fetch('api/get_offers.php');
        const result = await response.json();

        if (!result.success) {
            // No offers yet, or endpoint not built yet (Phase 3) -
            // either way, show the empty state quietly rather than
            // alarming the user with an error banner on page load.
            offers = [];
            renderAll();
            return;
        }

        offers = result.offers || [];
        renderAll();

    } catch (error) {
        console.error('Error loading offers:', error);
        offers = [];
        renderAll();
    }
}

// ------------------------------------------------------------
// Adding an offer
// ------------------------------------------------------------

addOfferBtn.addEventListener('click', async () => {
    if (!hasValidInput()) return;

    const formData = new FormData();
    formData.append('input_type', activeMethod);

    if (activeMethod === 'pdf') {
        formData.append('file', selectedPdfFile);
    } else {
        formData.append('text', offerTextInput.value.trim());
    }

    addOfferBtn.disabled = true;
    hideBanner();
    showBanner('Extracting offer details...', 'loading');

    try {
        const response = await fetch('api/add_offer.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            showBanner(result.message || 'Unable to add this offer. Please try again.', 'error');
            return;
        }

        hideBanner();
        showBanner('Offer added successfully.', 'info');

        // Reset the input panel for the next offer.
        clearPdfSelection();
        offerTextInput.value = '';
        updateCharCounter();

        await loadOffers();

    } catch (error) {
        console.error('Add offer error:', error);
        showBanner(
            'Unable to reach the server. This is expected until the backend endpoint is built.',
            'error'
        );
    } finally {
        updateAddOfferButtonState();
    }
});

// ------------------------------------------------------------
// Deleting an offer
// ------------------------------------------------------------

async function deleteOffer(offerId) {
    try {
        const response = await fetch('api/delete_offer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ offer_id: offerId })
        });

        const result = await response.json();

        if (!result.success) {
            showBanner(result.message || 'Unable to remove this offer.', 'error');
            return;
        }

        offers = offers.filter(o => o.offer_id !== offerId);
        renderAll();

    } catch (error) {
        console.error('Delete offer error:', error);
        showBanner('Unable to reach the server to remove this offer.', 'error');
    }
}

// ------------------------------------------------------------
// Init
// ------------------------------------------------------------

updateCharCounter();
updateAddOfferButtonState();
loadOffers();