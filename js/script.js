/**
 * HireWise - Client-side JavaScript
 * Handles authentication forms (login/register), session checking, and logout.
 */

document.addEventListener('DOMContentLoaded', function () {
    // ==========================================
    // 1. Alert / Notification Helper
    // ==========================================
    function showAlert(message, type = 'error') {
        const alertBox = document.getElementById('alertBox');
        if (!alertBox) return;

        alertBox.textContent = message;
        alertBox.className = 'auth-alert ' + (type === 'success' ? 'success' : 'error');
    }

    function clearAlert() {
        const alertBox = document.getElementById('alertBox');
        if (!alertBox) return;

        alertBox.textContent = '';
        alertBox.className = 'auth-alert';
    }

    // ==========================================
    // 2. Registration Form Handler
    // ==========================================
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            clearAlert();

            const submitBtn = document.getElementById('submitBtn');
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            // Client-side validation checks
            if (!name || !email || !password || !confirmPassword) {
                showAlert('Please fill in all required fields.');
                return;
            }

            if (password !== confirmPassword) {
                showAlert('Passwords do not match. Please verify.');
                return;
            }

            if (password.length < 6) {
                showAlert('Password must be at least 6 characters long.');
                return;
            }

            // Set loading state
            const originalBtnText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';

            try {
                const response = await fetch('api/register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: name,
                        email: email,
                        password: password,
                        confirm_password: confirmPassword
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showAlert(result.message, 'success');
                    registerForm.reset();
                    setTimeout(() => {
                        window.location.href = 'login.html';
                    }, 1500);
                } else {
                    showAlert(result.message || 'Registration failed. Please check your information.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            } catch (err) {
                console.error('Registration network error:', err);
                showAlert('Network error: Unable to connect to server. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            }
        });
    }

    // ==========================================
    // 3. Login Form Handler
    // ==========================================
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            clearAlert();

            const loginBtn = document.getElementById('loginBtn');
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            if (!email || !password) {
                showAlert('Please enter both email and password.');
                return;
            }

            // Set loading state
            const originalBtnText = loginBtn.textContent;
            loginBtn.disabled = true;
            loginBtn.textContent = 'Logging In...';

            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showAlert('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect || 'dashboard.html';
                    }, 600);
                } else {
                    showAlert(result.message || 'Invalid email address or password.');
                    loginBtn.disabled = false;
                    loginBtn.textContent = originalBtnText;
                }
            } catch (err) {
                console.error('Login network error:', err);
                showAlert('Network error: Unable to communicate with server.');
                loginBtn.disabled = false;
                loginBtn.textContent = originalBtnText;
            }
        });
    }

    // ==========================================
    // 4. Dashboard Session Protection & User Data
    // ==========================================
    const greetingUser = document.getElementById('greetingUser');
    if (greetingUser) {
        // We are on dashboard.html - verify authentication immediately
        fetch('api/session.php', {
            headers: { 'Accept': 'application/json' }
        })
            .then(res => res.json())
            .then(data => {
                if (!data.authenticated || !data.user) {
                    // Unauthorized: redirect directly to login
                    window.location.replace('login.html');
                } else {
                    // Authenticated: display real user details
                    const user = data.user;
                    greetingUser.textContent = `Hello, ${user.name}`;

                    const profileName = document.getElementById('profileName');
                    if (profileName) profileName.textContent = user.name;

                    const profileRole = document.getElementById('profileRole');
                    if (profileRole) {
                        profileRole.textContent = user.role.charAt(0).toUpperCase() + user.role.slice(1);
                    }
                }
            })
            .catch(err => {
                console.error('Session verification error:', err);
                window.location.replace('login.html');
            });
    }

    // ==========================================
    // 5. Logout Handler
    // ==========================================
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            fetch('api/logout.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' }
            })
                .then(res => res.json())
                .then(data => {
                    window.location.href = data.redirect || 'login.html';
                })
                .catch(() => {
                    window.location.href = 'login.html';
                });
        });
    }
});
