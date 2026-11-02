// ============================================================
// HireWise - Admin Module
// User Management page logic: load users, render the table,
// handle role changes and activate/deactivate actions.
// ============================================================

const usersTableBody = document.getElementById('usersTableBody');
const usersEmptyNote = document.getElementById('usersEmptyNote');
const usersStatusBanner = document.getElementById('usersStatusBanner');

let users = [];

function showBanner(message, kind) {
    usersStatusBanner.textContent = message;
    usersStatusBanner.className = 'admin-status-banner status-' + kind;
    usersStatusBanner.hidden = false;
}

function hideBanner() {
    usersStatusBanner.hidden = true;
    usersStatusBanner.textContent = '';
}

function createEl(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined) el.textContent = text;
    return el;
}

function formatDate(isoString) {
    const d = new Date(isoString);
    if (isNaN(d.getTime())) return isoString;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

// ------------------------------------------------------------
// Rendering
// ------------------------------------------------------------

function renderUsers() {
    usersTableBody.textContent = '';

    if (users.length === 0) {
        usersEmptyNote.hidden = false;
        return;
    }
    usersEmptyNote.hidden = true;

    users.forEach((user) => {
        const isSelf = user.user_id === window.CURRENT_ADMIN_ID;
        const row = document.createElement('tr');

        row.appendChild(createEl('td', null, user.name));
        row.appendChild(createEl('td', null, user.email));

        // Role cell
        const roleCell = document.createElement('td');
        roleCell.appendChild(createEl(
            'span',
            'role-badge role-' + user.role,
            user.role.charAt(0).toUpperCase() + user.role.slice(1)
        ));
        row.appendChild(roleCell);

        // Status cell
        const statusCell = document.createElement('td');
        const statusActive = user.is_active === 1;
        statusCell.appendChild(createEl(
            'span',
            'status-badge ' + (statusActive ? 'status-active' : 'status-inactive'),
            statusActive ? 'Active' : 'Inactive'
        ));
        row.appendChild(statusCell);

        row.appendChild(createEl('td', null, formatDate(user.created_at)));

        // Actions cell
        const actionsCell = document.createElement('td');
        const actionsWrapper = createEl('div', 'admin-table-actions');

        const roleBtn = createEl(
            'button',
            'admin-action-btn',
            user.role === 'admin' ? 'Make Student' : 'Make Admin'
        );
        roleBtn.type = 'button';
        roleBtn.disabled = isSelf;
        roleBtn.title = isSelf ? "You can't change your own role." : '';
        roleBtn.addEventListener('click', () => {
            const newRole = user.role === 'admin' ? 'student' : 'admin';
            const confirmMsg = newRole === 'admin'
                ? `Grant admin privileges to ${user.name}?`
                : `Remove admin privileges from ${user.name}?`;
            if (window.confirm(confirmMsg)) {
                updateUser(user.user_id, { role: newRole }, roleBtn);
            }
        });

        const statusBtn = createEl(
            'button',
            'admin-action-btn ' + (statusActive ? 'danger' : ''),
            statusActive ? 'Deactivate' : 'Activate'
        );
        statusBtn.type = 'button';
        statusBtn.disabled = isSelf;
        statusBtn.title = isSelf ? "You can't change your own status." : '';
        statusBtn.addEventListener('click', () => {
            const newActive = statusActive ? 0 : 1;
            const confirmMsg = statusActive
                ? `Deactivate ${user.name}? They will not be able to log in until reactivated.`
                : `Reactivate ${user.name}?`;
            if (window.confirm(confirmMsg)) {
                updateUser(user.user_id, { is_active: newActive }, statusBtn);
            }
        });

        actionsWrapper.append(roleBtn, statusBtn);
        actionsCell.appendChild(actionsWrapper);
        row.appendChild(actionsCell);

        usersTableBody.appendChild(row);
    });
}

// ------------------------------------------------------------
// Loading
// ------------------------------------------------------------

async function loadUsers() {
    showBanner('Loading users...', 'loading');

    try {
        const response = await fetch('api/admin-list-users.php');
        const result = await response.json();

        if (!result.success) {
            showBanner(result.message || 'Unable to load users.', 'error');
            return;
        }

        users = result.users || [];
        hideBanner();
        renderUsers();

    } catch (error) {
        console.error('Load users error:', error);
        showBanner('Unable to reach the server. Please refresh the page.', 'error');
    }
}

// ------------------------------------------------------------
// Updating
// ------------------------------------------------------------

async function updateUser(userId, changes, triggerBtn) {
    if (triggerBtn) triggerBtn.disabled = true;
    hideBanner();

    try {
        const response = await fetch('api/admin-update-user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, ...changes })
        });

        const result = await response.json();

        if (!result.success) {
            showBanner(result.message || 'Unable to update this user.', 'error');
            if (triggerBtn) triggerBtn.disabled = false;
            return;
        }

        // Update local state and re-render that one user's row by
        // re-rendering the whole table (simple and fast enough for
        // an admin user list, which is not expected to be huge).
        const index = users.findIndex(u => u.user_id === userId);
        if (index !== -1) {
            users[index] = result.user;
        }
        showBanner('Updated successfully.', 'info');
        renderUsers();

    } catch (error) {
        console.error('Update user error:', error);
        showBanner('Unable to reach the server. Please try again.', 'error');
        if (triggerBtn) triggerBtn.disabled = false;
    }
}

// ------------------------------------------------------------
// Init
// ------------------------------------------------------------

loadUsers();