/**
 * Admin Panel Common JavaScript
 * Shared functions for all admin pages
 */

// ============================================
// Authentication & Session Management
// ============================================

/**
 * Check if admin is authenticated
 */
async function checkAuth() {
    try {
        // Determine correct auth path based on current location
        const currentPath = window.location.pathname;
        const authPath = (currentPath.includes('/users/') ||
                         currentPath.includes('/forms/') ||
                         currentPath.includes('/questions/') ||
                         currentPath.includes('/responses/'))
            ? '../auth.php'
            : './auth.php';

        const response = await fetch(`${authPath}?action=check`, {
            credentials: 'include'
        });
        const result = await response.json();

        if (!result.authenticated) {
            const loginPath = authPath.includes('../') ? '../index.html' : './index.html';
            window.location.href = loginPath;
            return false;
        }

        // Update admin name in sidebar if element exists
        const adminNameEl = document.getElementById('admin-name');
        if (adminNameEl && result.admin) {
            adminNameEl.textContent = result.admin.full_name || result.admin.username;
        }

        return true;
    } catch (error) {
        console.error('Auth check error:', error);
        const currentPath = window.location.pathname;
        const loginPath = (currentPath.includes('/users/') ||
                          currentPath.includes('/forms/') ||
                          currentPath.includes('/questions/') ||
                          currentPath.includes('/responses/'))
            ? '../index.html'
            : './index.html';
        window.location.href = loginPath;
        return false;
    }
}

/**
 * Logout admin
 */
async function logout() {
    if (!confirm('האם אתה בטוח שברצונך להתנתק?')) {
        return;
    }

    try {
        // Determine correct auth path based on current location
        const currentPath = window.location.pathname;
        const authPath = (currentPath.includes('/users/') ||
                         currentPath.includes('/forms/') ||
                         currentPath.includes('/questions/') ||
                         currentPath.includes('/responses/'))
            ? '../auth.php'
            : './auth.php';

        await fetch(authPath, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ action: 'logout' })
        });

        localStorage.removeItem('admin');
        const loginPath = authPath.includes('../') ? '../index.html' : './index.html';
        window.location.href = loginPath;
    } catch (error) {
        console.error('Logout error:', error);
        alert('שגיאה בהתנתקות');
    }
}

// ============================================
// API Helper Functions
// ============================================

/**
 * Make authenticated API request
 */
async function apiRequest(action, data = {}, method = 'GET') {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        };

        // Determine correct API path based on current location
        const currentPath = window.location.pathname;
        let apiPath = './api.php';

        // If we're in a subdirectory (users/, forms/, questions/, responses/)
        if (currentPath.includes('/users/') ||
            currentPath.includes('/forms/') ||
            currentPath.includes('/questions/') ||
            currentPath.includes('/responses/')) {
            apiPath = '../api.php';
        }

        let url = `${apiPath}?action=${action}`;

        if (method === 'GET' && Object.keys(data).length > 0) {
            const params = new URLSearchParams(data);
            url += `&${params.toString()}`;
        } else if (method !== 'GET') {
            options.body = JSON.stringify({ action, ...data });
        }

        const response = await fetch(url, options);

        // Handle unauthorized
        if (response.status === 401) {
            // Redirect to login - adjust path based on location
            const loginPath = currentPath.includes('/users/') ||
                             currentPath.includes('/forms/') ||
                             currentPath.includes('/questions/') ||
                             currentPath.includes('/responses/')
                ? '../index.html'
                : './index.html';
            window.location.href = loginPath;
            return null;
        }

        const result = await response.json();
        return result;
    } catch (error) {
        console.error('API request error:', error);
        throw error;
    }
}

// ============================================
// UI Helper Functions
// ============================================

/**
 * Show success message
 */
function showSuccess(message, containerId = 'message-container') {
    showMessage(message, 'success', containerId);
}

/**
 * Show error message
 */
function showError(message, containerId = 'message-container') {
    showMessage(message, 'error', containerId);
}

/**
 * Show message
 */
function showMessage(message, type = 'info', containerId = 'message-container') {
    const container = document.getElementById(containerId);
    if (!container) {
        alert(message);
        return;
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.animation = 'slideIn 0.3s ease';

    container.innerHTML = '';
    container.appendChild(alertDiv);

    // Auto remove after 5 seconds
    setTimeout(() => {
        alertDiv.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}

/**
 * Show loading spinner
 */
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    }
}

/**
 * Confirm action
 */
function confirmAction(message = 'האם אתה בטוח?') {
    return confirm(message);
}

/**
 * Format date to Hebrew locale
 */
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString('he-IL', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Format date (date only)
 */
function formatDateOnly(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('he-IL', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

/**
 * Truncate text
 */
function truncateText(text, maxLength = 50) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// Table Helper Functions
// ============================================

/**
 * Create sortable table header
 */
function createSortableHeader(headers, onSort) {
    const thead = document.createElement('thead');
    const tr = document.createElement('tr');

    headers.forEach(header => {
        const th = document.createElement('th');
        th.textContent = header.label;
        if (header.sortable) {
            th.style.cursor = 'pointer';
            th.onclick = () => onSort(header.field);
        }
        tr.appendChild(th);
    });

    thead.appendChild(tr);
    return thead;
}

/**
 * Render empty table message
 */
function renderEmptyTable(message, colspan) {
    return `<tr><td colspan="${colspan}" class="text-center">${message}</td></tr>`;
}

// ============================================
// Pagination Helper
// ============================================

/**
 * Render pagination
 */
function renderPagination(pagination, onPageChange) {
    if (!pagination || pagination.pages <= 1) return '';

    let html = '<div class="pagination">';

    // Previous button
    if (pagination.page > 1) {
        html += `<button class="btn btn-secondary" onclick="${onPageChange}(${pagination.page - 1})">← קודם</button>`;
    }

    // Page numbers
    html += `<span class="pagination-info">עמוד ${pagination.page} מתוך ${pagination.pages}</span>`;

    // Next button
    if (pagination.page < pagination.pages) {
        html += `<button class="btn btn-secondary" onclick="${onPageChange}(${pagination.page + 1})">הבא →</button>`;
    }

    html += '</div>';
    return html;
}

// ============================================
// Form Validation
// ============================================

/**
 * Validate required fields
 */
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = 'var(--danger-color)';
            isValid = false;
        } else {
            field.style.borderColor = '';
        }
    });

    if (!isValid) {
        showError('נא למלא את כל השדות הנדרשים');
    }

    return isValid;
}

/**
 * Validate Israeli ID (Teudat Zehut)
 */
function validateIsraeliID(id) {
    id = String(id).trim();
    if (id.length !== 9) return false;

    let sum = 0;
    for (let i = 0; i < 9; i++) {
        let digit = Number(id[i]);
        let step = (i % 2) + 1;
        let result = digit * step;
        if (result > 9) result -= 9;
        sum += result;
    }

    return sum % 10 === 0;
}

/**
 * Validate phone number (Israeli format)
 */
function validatePhoneNumber(phone) {
    phone = phone.replace(/[^0-9]/g, '');
    return phone.length === 10 && phone.startsWith('0');
}

/**
 * Validate email
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// ============================================
// Modal Helper
// ============================================

/**
 * Create and show modal
 */
function showModal(title, content, buttons = []) {
    // Remove existing modal
    const existingModal = document.getElementById('admin-modal');
    if (existingModal) existingModal.remove();

    // Create modal
    const modal = document.createElement('div');
    modal.id = 'admin-modal';
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-backdrop" onclick="closeModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                ${content}
            </div>
            <div class="modal-footer">
                ${buttons.map(btn => `
                    <button class="btn ${btn.class || 'btn-secondary'}"
                            onclick="${btn.onclick}">${btn.text}</button>
                `).join('')}
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 10);
}

/**
 * Close modal
 */
function closeModal() {
    const modal = document.getElementById('admin-modal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

// ============================================
// Initialize
// ============================================

// Check auth on page load
window.addEventListener('DOMContentLoaded', () => {
    // Only check auth if not on login page
    if (!window.location.pathname.includes('index.html') &&
        !window.location.pathname.endsWith('/admin/')) {
        checkAuth();
    }
});

// Add CSS animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }

    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .modal.show {
        opacity: 1;
    }

    .modal-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        min-width: 400px;
        max-width: 90%;
        max-height: 90vh;
        overflow: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--gray-200);
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 2rem;
        cursor: pointer;
        color: var(--gray-400);
        line-height: 1;
        padding: 0;
        width: 30px;
        height: 30px;
    }

    .modal-close:hover {
        color: var(--gray-600);
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        display: flex;
        gap: 1rem;
        justify-content: flex-start;
        padding: 1.5rem;
        border-top: 1px solid var(--gray-200);
    }

    .pagination {
        display: flex;
        gap: 1rem;
        align-items: center;
        justify-content: center;
        margin-top: 2rem;
    }

    .pagination-info {
        color: var(--gray-600);
        font-size: 0.875rem;
    }
`;
document.head.appendChild(style);
