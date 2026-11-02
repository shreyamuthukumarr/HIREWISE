<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireWise - Offer Comparison</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/offers.css?v=3">
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">
                <i data-lucide="briefcase-business"></i>
            </div>
            <div class="logo-text">
                <h2>HireWise</h2>
            </div>
        </div>
        <div class="nav-group">
            <p class="nav-title">OVERVIEW</p>
            <a href="dashboard.html" class="nav-link">
                <i data-lucide="layout-dashboard"></i>
                <span>Dashboard</span>
            </a>
        </div>
        <div class="nav-group">
            <p class="nav-title">MODULES</p>
            <a href="resume.php" class="nav-link">
                <i data-lucide="file-text"></i>
                <span>Resume Intelligence</span>
            </a>
            <a href="assessment.php" class="nav-link">
                <i data-lucide="brain-circuit"></i>
                <span>Placement Assessment</span>
            </a>
            <a href="recruitment.php" class="nav-link">
                <i data-lucide="shield-check"></i>
                <span>Recruitment Analysis</span>
            </a>
            <a href="offers.php" class="nav-link active">
                <i data-lucide="briefcase"></i>
                <span>Offer Comparison</span>
            </a>
        </div>
        <div class="nav-group">
            <p class="nav-title">ACCOUNT</p>
            <a href="#" class="nav-link">
                <i data-lucide="user-round"></i>
                <span>Profile</span>
            </a>
            <a href="index.html" class="nav-link">
                <i data-lucide="log-out"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>
    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1>Offer Comparison</h1>
                <p>
                    Compare job offers and make informed career decisions.
                </p>
            </div>
            <div class="topbar-right">
                <div class="user-profile">
                    <div class="avatar">
                        <i data-lucide="user"></i>
                    </div>
                    <div class="user-details">
                        <h4>
                            <?= htmlspecialchars($user['name'] ?? 'Student', ENT_QUOTES, 'UTF-8') ?>
                        </h4>
                        <p>
                            <?= htmlspecialchars(ucfirst($user['role'] ?? 'student'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <i class="dropdown-icon" data-lucide="chevron-down"></i>
                </div>
            </div>
        </div>

        <section class="offers-header">
            <h1>Compare Your Job Offers</h1>
            <p>
                Add offer letters to extract relevant details and
                compare compensation, benefits, and work conditions
                side by side.
            </p>
        </section>

        <!-- ADD OFFER -->
        <section class="offer-add-section">

            <div class="offer-status-banner" id="offerStatusBanner" hidden></div>

            <div class="method-tabs" role="tablist" aria-label="Choose input method">
                <button type="button" class="method-tab active" data-method="pdf" role="tab" aria-selected="true">
                    <i data-lucide="file-text"></i>
                    <span>Upload PDF</span>
                </button>
                <button type="button" class="method-tab" data-method="text" role="tab" aria-selected="false">
                    <i data-lucide="type"></i>
                    <span>Paste Text</span>
                </button>
            </div>

            <div class="method-panels">

                <!-- PDF PANEL -->
                <div class="method-panel" data-panel="pdf">
                    <p class="panel-description">
                        Upload the offer letter as a PDF. We'll extract
                        the company, role, salary, and other details
                        automatically.
                    </p>
                    <div class="dropzone" id="offerPdfDropzone" tabindex="0" role="button" aria-label="Upload PDF">
                        <input type="file" id="offerPdfInput" accept=".pdf,application/pdf" hidden>

                        <div class="dropzone-empty" id="offerPdfEmpty">
                            <div class="dropzone-icon">
                                <i data-lucide="file-up"></i>
                            </div>
                            <p class="dropzone-title">Drag and drop the offer letter here</p>
                            <p class="dropzone-sub">or</p>
                            <button type="button" class="browse-btn" id="offerPdfBrowseBtn">
                                Browse files
                            </button>
                            <span class="dropzone-note">PDF only &bull; Maximum file size 5 MB</span>
                        </div>

                        <div class="file-selected-card" id="offerPdfSelectedCard" hidden>
                            <div class="file-selected-icon">
                                <i data-lucide="file-text"></i>
                            </div>
                            <div class="file-selected-info">
                                <p class="file-selected-name" id="offerPdfFileName"></p>
                                <p class="file-selected-size" id="offerPdfFileSize"></p>
                            </div>
                            <button type="button" class="file-remove-btn" id="offerPdfRemoveBtn" aria-label="Remove selected PDF">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TEXT PANEL -->
                <div class="method-panel" data-panel="text" hidden>
                    <p class="panel-description">
                        Paste the full text of the offer letter or
                        offer email.
                    </p>
                    <div class="text-input-wrapper">
                        <textarea id="offerTextInput" maxlength="6000"
                            placeholder="Paste the offer letter text here..."></textarea>
                        <div class="text-input-footer">
                            <span class="char-counter" id="offerCharCounter">0 / 6000</span>
                            <button type="button" class="clear-btn" id="offerClearTextBtn">Clear</button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="analyze-action">
                <button type="button" class="analyze-btn" id="addOfferBtn" disabled>
                    <i data-lucide="plus-circle"></i>
                    <span id="addOfferBtnLabel">Add Offer</span>
                </button>
                <p class="analyze-hint" id="addOfferHint">
                    Upload a PDF or paste offer text to begin.
                </p>
            </div>

        </section>

        <!-- OFFERS ADDED COUNT -->
        <section class="offer-count-section">
            <div class="offer-count-card">
                <div class="count-icon">
                    <i data-lucide="layers-2"></i>
                </div>
                <div>
                    <span>Offers Added</span>
                    <strong id="offerCount">0</strong>
                </div>
            </div>
        </section>

        <!-- SAVED OFFERS + COMPARISON -->
        <section class="offers-list-section">
            <div class="section-heading">
                <div>
                    <h2>Your Offers</h2>
                    <p>Every offer you've added, extracted and saved to your account.</p>
                </div>
            </div>

            <div class="offers-empty-card" id="offersEmptyState">
                <div class="offers-empty-icon">
                    <i data-lucide="briefcase"></i>
                </div>
                <h3>No Offers Added Yet</h3>
                <p>Add your first offer above to get started.</p>
            </div>

            <div class="offer-cards-grid" id="offerCardsGrid" hidden>
                <!-- Offer cards are rendered here by js/offers.js -->
            </div>
        </section>

        <!-- COMPARISON TABLE -->
        <section class="comparison-section" id="comparisonSection" hidden>
            <div class="section-heading">
                <div>
                    <h2>Side-by-Side Comparison</h2>
                    <p>All your offers compared across the same fields.</p>
                </div>
            </div>
            <div class="comparison-table-wrapper">
                <table class="comparison-table" id="comparisonTable">
                    <!-- Rendered by js/offers.js -->
                </table>
            </div>
        </section>

    </main>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="js/offers.js?v=3"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>