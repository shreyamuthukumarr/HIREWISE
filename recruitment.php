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
    <title>HireWise - Recruitment Analysis</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/recruitment.css?v=6">
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
            <a href="recruitment.php" class="nav-link active">
                <i data-lucide="shield-check"></i>
                <span>Recruitment Analysis</span>
            </a>
            <a href="offers.php" class="nav-link">
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
                <h1>Recruitment Analysis</h1>
                <p>
                    Analyze recruitment messages and job documents to identify
                    potential recruitment risks.
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

        <section class="rec-input-section">

            <div class="rec-status-banner" id="recStatusBanner" hidden></div>

            <div class="method-tabs" role="tablist" aria-label="Choose input method">
                <button type="button" class="method-tab active" data-method="pdf"
                        role="tab" aria-selected="true">
                    <i data-lucide="file-text"></i>
                    <span>PDF Document</span>
                </button>
                <button type="button" class="method-tab" data-method="image"
                        role="tab" aria-selected="false">
                    <i data-lucide="image"></i>
                    <span>Message Screenshot</span>
                </button>
                <button type="button" class="method-tab" data-method="text"
                        role="tab" aria-selected="false">
                    <i data-lucide="type"></i>
                    <span>Paste Text</span>
                </button>
            </div>

            <div class="method-panels">

                <!-- PDF PANEL -->
                <div class="method-panel" data-panel="pdf">
                    <p class="panel-description">
                        Upload an offer letter, recruitment email, or other
                        recruitment-related PDF.
                    </p>
                    <div class="dropzone" id="pdfDropzone" tabindex="0"
                         role="button" aria-label="Upload PDF">
                        <input type="file" id="pdfFileInput" accept=".pdf,application/pdf" hidden>

                        <div class="dropzone-empty" id="pdfDropzoneEmpty">
                            <div class="dropzone-icon">
                                <i data-lucide="file-up"></i>
                            </div>
                            <p class="dropzone-title">Drag and drop your PDF here</p>
                            <p class="dropzone-sub">or</p>
                            <button type="button" class="browse-btn" id="pdfBrowseBtn">
                                Browse files
                            </button>
                            <span class="dropzone-note">PDF only &bull; Maximum file size 5 MB</span>
                        </div>

                        <div class="file-selected-card" id="pdfSelectedCard" hidden>
                            <div class="file-selected-icon">
                                <i data-lucide="file-text"></i>
                            </div>
                            <div class="file-selected-info">
                                <p class="file-selected-name" id="pdfFileName"></p>
                                <p class="file-selected-size" id="pdfFileSize"></p>
                            </div>
                            <button type="button" class="file-remove-btn" id="pdfRemoveBtn"
                                    aria-label="Remove selected PDF">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- IMAGE PANEL -->
                <div class="method-panel" data-panel="image" hidden>
                    <p class="panel-description">
                        Upload a screenshot of a WhatsApp message, email, or
                        recruitment conversation.
                    </p>
                    <div class="dropzone" id="imageDropzone" tabindex="0"
                         role="button" aria-label="Upload image">
                        <input type="file" id="imageFileInput" accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden>

                        <div class="dropzone-empty" id="imageDropzoneEmpty">
                            <div class="dropzone-icon">
                                <i data-lucide="image-plus"></i>
                            </div>
                            <p class="dropzone-title">Drag and drop your screenshot here</p>
                            <p class="dropzone-sub">or</p>
                            <button type="button" class="browse-btn" id="imageBrowseBtn">
                                Browse files
                            </button>
                            <span class="dropzone-note">JPG, PNG &bull; Maximum file size 5 MB</span>
                        </div>

                        <div class="image-selected-card" id="imageSelectedCard" hidden>
                            <img class="image-preview" id="imagePreview" alt="Selected screenshot preview">
                            <div class="file-selected-info">
                                <p class="file-selected-name" id="imageFileName"></p>
                                <p class="file-selected-size" id="imageFileSize"></p>
                            </div>
                            <button type="button" class="file-remove-btn" id="imageRemoveBtn"
                                    aria-label="Remove selected image">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TEXT PANEL -->
                <div class="method-panel" data-panel="text" hidden>
                    <p class="panel-description">
                        Paste an email, WhatsApp message, SMS, LinkedIn
                        message, or other recruitment-related text.
                    </p>
                    <div class="text-input-wrapper">
                        <textarea id="textInput" maxlength="6000"
                            placeholder="Paste the recruitment message here..."></textarea>
                        <div class="text-input-footer">
                            <span class="char-counter" id="charCounter">0 / 6000</span>
                            <button type="button" class="clear-btn" id="clearTextBtn">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="analyze-action">
                <button type="button" class="analyze-btn" id="analyzeBtn" disabled>
                    <i data-lucide="shield-check"></i>
                    <span id="analyzeBtnLabel">Analyze Recruitment Content</span>
                </button>
                <p class="analyze-hint" id="analyzeHint">
                    Select a PDF, image, or paste text to begin.
                </p>
            </div>

        </section>

        <section class="analysis-result-section">
            <div class="section-heading">
                <div>
                    <h2>Analysis Result</h2>
                    <p>
                        Insights will appear here after the submitted
                        recruitment content has been analyzed.
                    </p>
                </div>
            </div>

            <!-- EMPTY STATE -->
            <div class="result-empty-card" id="resultsEmptyState">
                <div class="result-empty-icon">
                    <i data-lucide="scan-search"></i>
                </div>
                <h3>Ready to Analyze</h3>
                <p>
                    Submit a recruitment document, message screenshot,
                    or pasted text to begin the analysis.
                </p>
                <div class="analysis-flow">
                    <div class="flow-item" data-stage="extract">
                        <i data-lucide="file-search"></i>
                        <span>Content Extraction</span>
                    </div>
                    <div class="flow-line"></div>
                    <div class="flow-item" data-stage="analyze">
                        <i data-lucide="brain-circuit"></i>
                        <span>AI Analysis</span>
                    </div>
                    <div class="flow-line"></div>
                    <div class="flow-item" data-stage="risk">
                        <i data-lucide="shield-check"></i>
                        <span>Risk Assessment</span>
                    </div>
                </div>
            </div>

            <!-- PROCESSING STATE -->
            <div class="result-processing-card" id="resultsProcessingState" hidden>
                <div class="processing-spinner" aria-hidden="true"></div>
                <h3 id="processingTitle">Analyzing your submission&hellip;</h3>
                <p id="processingSubtitle">This usually takes a few seconds.</p>
            </div>

            <!-- REPORT STATE (populated once the backend exists) -->
            <div class="result-report-card" id="resultsReportState" hidden>
                <!-- Filled in by js/recruitment.js once the analysis API responds -->
            </div>
        </section>

    </main>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="js/recruitment.js?v=6"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>