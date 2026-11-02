<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/resume.css">
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
    <a href="resume.php" class="nav-link active">
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
            <h1>Resume Intelligence</h1>
            <p>Upload and analyze your resume</p>
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
                <i class="dropdown-icon"
                   data-lucide="chevron-down"></i>
            </div>
        </div>
    </div>
<section class="upload-section">
    <div class="upload-card">
        <div class="upload-icon">
            <i data-lucide="file-up"></i>
        </div>
        <h2>Upload Your Resume</h2>
        <p>
            Drag & drop your resume here or choose a PDF
            to begin your AI-powered resume analysis.
        </p>
        <input
    type="file"
    id="resumeFile"
    accept=".pdf,application/pdf"
    hidden
>
<button class="upload-btn" type="button" id="chooseResumeBtn">
    Choose PDF
</button>
        <span class="upload-note">
    PDF only &bull; Maximum file size 5 MB
</span>
    </div>
</section>

<section class="resume-insights">
    <div class="insights-heading">
        <h2>Resume Insights</h2>
        <p>
            Key information and insights extracted from your resume.
        </p>
    </div>

    <div id="resumeStatusBanner" class="status-banner" hidden></div>

    <div class="insight-card">
        <div class="insight-card-header">
            <div class="insight-icon">
                <i data-lucide="sparkles"></i>
            </div>
            <div>
                <h3>Skills Identified</h3>
                <p>
                    Technical and professional skills detected from your resume.
                </p>
            </div>
        </div>
        <div class="skills-list" id="skillsList">
            <!-- Skills are loaded from the database -->
        </div>
    </div>

    <div class="insight-card education-card">
        <div class="insight-card-header">
            <div class="insight-icon">
                <i data-lucide="graduation-cap"></i>
            </div>
            <div>
                <h3>Education</h3>
                <p>
                    Academic qualifications identified from your resume.
                </p>
            </div>
        </div>
        <div class="section-text" id="educationText">
            <!-- Loaded dynamically -->
        </div>
    </div>

    <div class="insight-card experience-card">
        <div class="insight-card-header">
            <div class="insight-icon">
                <i data-lucide="briefcase-business"></i>
            </div>
            <div>
                <h3>Experience</h3>
                <p>
                    Work experience identified from your resume.
                </p>
            </div>
        </div>
        <div class="section-text" id="experienceText">
            <!-- Loaded dynamically -->
        </div>
    </div>

    <div class="insight-card projects-card">
        <div class="insight-card-header">
            <div class="insight-icon">
                <i data-lucide="folder-kanban"></i>
            </div>
            <div>
                <h3>Projects</h3>
                <p>
                    Projects and technologies identified from your resume.
                </p>
            </div>
        </div>
        <div class="section-text" id="projectsText">
            <!-- Loaded dynamically -->
        </div>
    </div>

    <div class="insight-card certifications-card">
        <div class="insight-card-header">
            <div class="insight-icon">
                <i data-lucide="award"></i>
            </div>
            <div>
                <h3>Certifications</h3>
                <p>
                    Certifications and achievements identified from your resume.
                </p>
            </div>
        </div>
        <div class="section-text" id="certificationsText">
            <!-- Loaded dynamically -->
        </div>
    </div>
</section>

</main>
<script src="https://unpkg.com/lucide@latest"></script>

<script>
    lucide.createIcons();

    const chooseResumeBtn = document.getElementById('chooseResumeBtn');
    const resumeFile = document.getElementById('resumeFile');
    const statusBanner = document.getElementById('resumeStatusBanner');

    // ------------------------------------------------------------
    // Small helpers - all use textContent only, never innerHTML,
    // so nothing extracted from a resume can ever run as script.
    // ------------------------------------------------------------

    function showBanner(message, kind) {
        // kind: 'loading' | 'error' | 'info'
        statusBanner.textContent = message;
        statusBanner.className = 'status-banner status-' + kind;
        statusBanner.hidden = false;
    }

    function hideBanner() {
        statusBanner.hidden = true;
        statusBanner.textContent = '';
    }

    function clearSkills() {
        const skillsList = document.getElementById('skillsList');
        skillsList.textContent = '';
    }

    function renderSkills(skills) {
        const skillsList = document.getElementById('skillsList');
        skillsList.textContent = '';

        if (!skills || skills.length === 0) {
            const emptyMsg = document.createElement('p');
            emptyMsg.className = 'empty-note';
            emptyMsg.textContent = 'No skills detected yet.';
            skillsList.appendChild(emptyMsg);
            return;
        }

        skills.forEach(skill => {
            const skillTag = document.createElement('span');
            skillTag.className = 'skill-tag';
            skillTag.textContent = skill;
            skillsList.appendChild(skillTag);
        });
    }

    // Renders a plain multi-line text block safely.
    // Each non-empty line becomes its own <p>, using textContent
    // so raw resume text is never interpreted as HTML.
    function renderTextSection(containerId, text, emptyMessage) {
        const container = document.getElementById(containerId);
        container.textContent = '';

        const trimmed = (text || '').trim();

        if (!trimmed) {
            const emptyMsg = document.createElement('p');
            emptyMsg.className = 'empty-note';
            emptyMsg.textContent = emptyMessage;
            container.appendChild(emptyMsg);
            return;
        }

        const lines = trimmed.split('\n');

        lines.forEach(line => {
            const cleanedLine = line.trim();
            if (cleanedLine.length === 0) {
                return;
            }
            const p = document.createElement('p');
            p.className = 'section-line';
            p.textContent = cleanedLine;
            container.appendChild(p);
        });
    }

    function renderNoResumeState() {
        clearSkills();

        const emptyMsg = document.createElement('p');
        emptyMsg.className = 'empty-note';
        emptyMsg.textContent = 'No resume uploaded yet.';
        document.getElementById('skillsList').appendChild(emptyMsg);

        const noResumeText = 'Upload a resume to see this section.';
        renderTextSection('educationText', '', noResumeText);
        renderTextSection('experienceText', '', noResumeText);
        renderTextSection('projectsText', '', noResumeText);
        renderTextSection('certificationsText', '', noResumeText);
    }

    // ------------------------------------------------------------
    // Main data loader - fetches the logged-in user's latest
    // resume and renders every section from the server response.
    // ------------------------------------------------------------

    async function loadResumeData() {

        showBanner('Loading your resume details...', 'loading');

        try {
            const response = await fetch('api/get_resume.php');
            const result = await response.json();

            console.log('Resume data:', result);

            if (!result.success) {
                hideBanner();
                renderNoResumeState();
                return;
            }

            hideBanner();

            renderSkills(result.skills);
            renderTextSection('educationText', result.education, 'No education details found.');
            renderTextSection('experienceText', result.experience, 'No experience details found.');
            renderTextSection('projectsText', result.projects, 'No project details found.');
            renderTextSection('certificationsText', result.certifications, 'No certifications found.');

        } catch (error) {
            console.error('Error loading resume data:', error);
            showBanner('Unable to load resume details. Please refresh the page.', 'error');
        }
    }

    chooseResumeBtn.addEventListener('click', () => {
        resumeFile.click();
    });

    resumeFile.addEventListener('change', async () => {

        const file = resumeFile.files[0];

        if (!file) {
            return;
        }

        console.log('Selected file:', file.name);

        const formData = new FormData();
        formData.append('resume', file);

        showBanner('Uploading and analyzing your resume...', 'loading');
        chooseResumeBtn.disabled = true;

        try {

            const response = await fetch('api/upload_resume.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            console.log('Upload response:', result);

            if (result.success) {
                showBanner('Resume uploaded successfully!', 'info');
                await loadResumeData();
            } else {
                showBanner(result.message || 'Upload failed. Please try again.', 'error');
            }

        } catch (error) {
            console.error('Upload error:', error);
            showBanner('Unable to upload resume. Please check your connection and try again.', 'error');
        } finally {
            chooseResumeBtn.disabled = false;
            resumeFile.value = '';
        }

    });

    loadResumeData();
</script>
</body>
</html>