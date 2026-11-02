<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireWise - Question Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css?v=2">
</head>
<body>
<div class="admin-shell">

    <div class="admin-topbar">
        <div class="admin-topbar-left">
            <div class="admin-logo">
                <i data-lucide="shield-check"></i>
                <h2>HireWise Admin</h2>
            </div>
        </div>
        <div class="admin-topbar-right">
            <div class="admin-user-chip">
                <i data-lucide="user"></i>
                <span><?= htmlspecialchars($user['name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button type="button" id="logoutBtn" class="admin-logout-btn">
                <i data-lucide="log-out"></i>
                Logout
            </button>
        </div>
    </div>

    <main class="admin-main">
        <a href="admin.php" class="admin-back-link">
            <i data-lucide="arrow-left"></i>
            Back to Admin Dashboard
        </a>

        <div class="admin-page-header">
            <h1>Question Bank</h1>
            <p>Add, edit, or remove aptitude and technical questions used in the placement assessment.</p>
        </div>

        <div class="admin-page-actions">
            <div class="admin-filter-bar">
                <select id="filterCategory">
                    <option value="">All Categories</option>
                    <option value="aptitude">Aptitude</option>
                    <option value="technical">Technical</option>
                </select>
                <select id="filterTopic">
                    <option value="">All Topics</option>
                </select>
                <select id="filterDifficulty">
                    <option value="">All Difficulties</option>
                    <option value="easy">Easy</option>
                    <option value="medium">Medium</option>
                    <option value="hard">Hard</option>
                </select>
                <select id="filterStatus">
                    <option value="all">All Statuses</option>
                    <option value="active" selected>Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </div>
            <button type="button" class="admin-primary-btn" id="addQuestionBtn">
                <i data-lucide="plus"></i>
                Add Question
            </button>
        </div>

        <div class="admin-status-banner" id="questionsStatusBanner" hidden></div>

        <div class="admin-table-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Category</th>
                        <th>Topic</th>
                        <th>Difficulty</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="questionsTableBody">
                    <!-- Rows rendered by js/admin-questions.js -->
                </tbody>
            </table>
            <div class="admin-empty-note" id="questionsEmptyNote" hidden>
                No questions match the selected filters.
            </div>
        </div>
    </main>

</div>

<!-- Add / Edit modal (shared by both actions) -->
<div class="admin-modal-overlay" id="questionModalOverlay" hidden>
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h3 id="questionModalTitle">Add Question</h3>
            <button type="button" class="admin-modal-close" id="questionModalCloseBtn" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>

        <p class="admin-form-error" id="questionFormError"></p>

        <form id="questionForm">
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label for="formCategory">Category</label>
                    <select id="formCategory" required>
                        <option value="aptitude">Aptitude</option>
                        <option value="technical">Technical</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label for="formTopic">Topic</label>
                    <select id="formTopic" required></select>
                </div>
            </div>

            <div class="admin-form-group">
                <label for="formDifficulty">Difficulty</label>
                <select id="formDifficulty" required>
                    <option value="easy">Easy</option>
                    <option value="medium">Medium</option>
                    <option value="hard">Hard</option>
                </select>
            </div>

            <div class="admin-form-group">
                <label for="formQuestionText">Question Text</label>
                <textarea id="formQuestionText" required maxlength="1000"></textarea>
            </div>

            <div class="admin-options-grid">
                <div class="admin-form-group">
                    <label for="formOptionA">Option A</label>
                    <input type="text" id="formOptionA" required>
                </div>
                <div class="admin-form-group">
                    <label for="formOptionB">Option B</label>
                    <input type="text" id="formOptionB" required>
                </div>
                <div class="admin-form-group">
                    <label for="formOptionC">Option C</label>
                    <input type="text" id="formOptionC" required>
                </div>
                <div class="admin-form-group">
                    <label for="formOptionD">Option D</label>
                    <input type="text" id="formOptionD" required>
                </div>
            </div>

            <div class="admin-form-group">
                <label for="formCorrectOption">Correct Option</label>
                <select id="formCorrectOption" required>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            </div>

            <div class="admin-form-group">
                <label for="formExplanation">Explanation</label>
                <textarea id="formExplanation" required></textarea>
            </div>

            <div class="admin-modal-actions">
                <button type="button" class="admin-secondary-btn" id="questionModalCancelBtn">Cancel</button>
                <button type="submit" class="admin-primary-btn" id="questionFormSubmitBtn">Save Question</button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script src="js/script.js"></script>
<script src="js/admin-questions.js?v=1"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>