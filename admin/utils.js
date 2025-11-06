/**
 * Admin Panel Utilities
 * Toast notifications, modals, and UI enhancements
 */

// ============================================
// TOAST NOTIFICATIONS
// ============================================

class ToastManager {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Create toast container if it doesn't exist
        if (!document.getElementById('toast-container')) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.getElementById('toast-container');
        }
    }

    show(message, type = 'info', duration = 4000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        const icon = this.getIcon(type);

        toast.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div class="toast-content">
                <div class="toast-title">${this.getTitle(type)}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;

        this.container.appendChild(toast);

        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => {
                this.remove(toast);
            }, duration);
        }

        return toast;
    }

    remove(toast) {
        toast.classList.add('removing');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    }

    getIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }

    getTitle(type) {
        const titles = {
            success: 'הצלחה',
            error: 'שגיאה',
            warning: 'אזהרה',
            info: 'מידע'
        };
        return titles[type] || titles.info;
    }

    success(message, duration) {
        return this.show(message, 'success', duration);
    }

    error(message, duration) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration) {
        return this.show(message, 'info', duration);
    }
}

// Create global toast instance
const toast = new ToastManager();

// ============================================
// ENHANCED MODALS
// ============================================

class ModalManager {
    constructor() {
        this.currentModal = null;
    }

    show(title, content, buttons = []) {
        // Close existing modal
        if (this.currentModal) {
            this.close();
        }

        // Create modal HTML
        const modalHtml = `
            <div class="modal-overlay" id="modalOverlay">
                <div class="modal-content" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <h2 class="modal-title">${title}</h2>
                        <button class="modal-close-btn" onclick="modalManager.close()">×</button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                    ${buttons.length > 0 ? `
                        <div class="modal-footer">
                            ${buttons.map(btn => `
                                <button class="btn ${btn.class || 'btn-secondary'}"
                                        onclick="${btn.onclick || 'modalManager.close()'}"
                                        ${btn.disabled ? 'disabled' : ''}>
                                    ${btn.text}
                                </button>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;

        // Add to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        this.currentModal = document.getElementById('modalOverlay');

        // Close on overlay click
        this.currentModal.addEventListener('click', (e) => {
            if (e.target === this.currentModal) {
                this.close();
            }
        });

        // Close on escape key
        this.escapeHandler = (e) => {
            if (e.key === 'Escape') {
                this.close();
            }
        };
        document.addEventListener('keydown', this.escapeHandler);

        return this.currentModal;
    }

    close() {
        if (!this.currentModal) return;

        this.currentModal.classList.add('closing');

        setTimeout(() => {
            if (this.currentModal && this.currentModal.parentElement) {
                this.currentModal.remove();
            }
            this.currentModal = null;
        }, 200);

        document.removeEventListener('keydown', this.escapeHandler);
    }

    confirm(title, message, onConfirm, onCancel) {
        this.show(title, `<p>${message}</p>`, [
            {
                text: 'ביטול',
                class: 'btn-secondary',
                onclick: () => {
                    this.close();
                    if (onCancel) onCancel();
                }
            },
            {
                text: 'אישור',
                class: 'btn-primary',
                onclick: () => {
                    this.close();
                    if (onConfirm) onConfirm();
                }
            }
        ]);
    }
}

// Create global modal instance
const modalManager = new ModalManager();

// ============================================
// BATCH SELECTION MANAGER
// ============================================

class BatchSelectionManager {
    constructor() {
        this.selectedItems = new Set();
        this.toolbar = null;
    }

    init() {
        // Create toolbar if it doesn't exist
        if (!document.getElementById('batch-toolbar')) {
            const toolbar = document.createElement('div');
            toolbar.id = 'batch-toolbar';
            toolbar.className = 'batch-toolbar';
            toolbar.style.display = 'none';
            toolbar.innerHTML = `
                <div class="batch-count">
                    <span id="batch-count-text">0 נבחרו</span>
                </div>
                <div class="batch-actions">
                    <button class="batch-btn batch-btn-primary" onclick="batchManager.exportSelected()">
                        📥 ייצוא
                    </button>
                    <button class="batch-btn batch-btn-secondary" onclick="batchManager.activateSelected()">
                        🔓 הפעל
                    </button>
                    <button class="batch-btn batch-btn-secondary" onclick="batchManager.deactivateSelected()">
                        🔒 השבת
                    </button>
                    <button class="batch-btn batch-btn-danger" onclick="batchManager.deleteSelected()">
                        🗑️ מחק
                    </button>
                    <button class="batch-btn batch-btn-secondary" onclick="batchManager.clearSelection()">
                        ✕
                    </button>
                </div>
            `;
            document.body.appendChild(toolbar);
            this.toolbar = toolbar;
        } else {
            this.toolbar = document.getElementById('batch-toolbar');
        }
    }

    toggleItem(userId) {
        if (this.selectedItems.has(userId)) {
            this.selectedItems.delete(userId);
        } else {
            this.selectedItems.add(userId);
        }
        this.updateUI();
    }

    selectAll(userIds) {
        userIds.forEach(id => this.selectedItems.add(id));
        this.updateUI();
    }

    clearSelection() {
        this.selectedItems.clear();
        this.updateUI();
    }

    updateUI() {
        const count = this.selectedItems.size;

        // Update toolbar visibility
        if (count > 0) {
            this.toolbar.style.display = 'flex';
            document.getElementById('batch-count-text').textContent = `${count} נבחרו`;
        } else {
            this.toolbar.style.display = 'none';
        }

        // Update card selections
        document.querySelectorAll('.user-card-checkbox').forEach(checkbox => {
            const userId = parseInt(checkbox.dataset.userId);
            checkbox.checked = this.selectedItems.has(userId);

            const card = checkbox.closest('.user-card');
            if (this.selectedItems.has(userId)) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
    }

    getSelected() {
        return Array.from(this.selectedItems);
    }

    async exportSelected() {
        const selected = this.getSelected();
        if (selected.length === 0) {
            toast.warning('לא נבחרו תלמידים לייצוא');
            return;
        }

        try {
            const response = await fetch('../api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'export_users_csv',
                    user_ids: selected
                })
            });

            const data = await response.json();

            if (data.success) {
                // Create download
                const blob = new Blob([data.csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `students_${new Date().toISOString().slice(0, 10)}.csv`;
                link.click();

                toast.success(`${selected.length} תלמידים יוצאו בהצלחה`);
            } else {
                toast.error(data.message || 'שגיאה בייצוא');
            }
        } catch (error) {
            console.error('Export error:', error);
            toast.error('שגיאה בייצוא תלמידים');
        }
    }

    async activateSelected() {
        const selected = this.getSelected();
        if (selected.length === 0) {
            toast.warning('לא נבחרו תלמידים');
            return;
        }

        modalManager.confirm(
            'הפעלת תלמידים',
            `האם להפעיל ${selected.length} תלמידים?`,
            async () => {
                try {
                    const response = await fetch('../api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({
                            action: 'batch_update_users',
                            user_ids: selected,
                            is_active: 1
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        toast.success(`${selected.length} תלמידים הופעלו בהצלחה`);
                        this.clearSelection();
                        if (typeof loadUsers === 'function') loadUsers();
                    } else {
                        toast.error(data.message || 'שגיאה בהפעלה');
                    }
                } catch (error) {
                    console.error('Activate error:', error);
                    toast.error('שגיאה בהפעלת תלמידים');
                }
            }
        );
    }

    async deactivateSelected() {
        const selected = this.getSelected();
        if (selected.length === 0) {
            toast.warning('לא נבחרו תלמידים');
            return;
        }

        modalManager.confirm(
            'השבתת תלמידים',
            `האם להשבית ${selected.length} תלמידים?`,
            async () => {
                try {
                    const response = await fetch('../api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({
                            action: 'batch_update_users',
                            user_ids: selected,
                            is_active: 0
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        toast.success(`${selected.length} תלמידים הושבתו בהצלחה`);
                        this.clearSelection();
                        if (typeof loadUsers === 'function') loadUsers();
                    } else {
                        toast.error(data.message || 'שגיאה בהשבתה');
                    }
                } catch (error) {
                    console.error('Deactivate error:', error);
                    toast.error('שגיאה בהשבתת תלמידים');
                }
            }
        );
    }

    async deleteSelected() {
        const selected = this.getSelected();
        if (selected.length === 0) {
            toast.warning('לא נבחרו תלמידים');
            return;
        }

        modalManager.confirm(
            'מחיקת תלמידים',
            `האם למחוק ${selected.length} תלמידים? פעולה זו לא ניתנת לביטול.`,
            async () => {
                try {
                    const response = await fetch('../api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({
                            action: 'batch_delete_users',
                            user_ids: selected
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        toast.success(`${selected.length} תלמידים נמחקו בהצלחה`);
                        this.clearSelection();
                        if (typeof loadUsers === 'function') loadUsers();
                    } else {
                        toast.error(data.message || 'שגיאה במחיקה');
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    toast.error('שגיאה במחיקת תלמידים');
                }
            }
        );
    }
}

// Create global batch manager
const batchManager = new BatchSelectionManager();

// ============================================
// DEBOUNCE UTILITY
// ============================================

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ============================================
// EXPORT TO CSV
// ============================================

async function exportAllUsers() {
    try {
        const response = await fetch('../api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'export_all_users_csv' })
        });

        const data = await response.json();

        if (data.success) {
            // Create download
            const blob = new Blob([data.csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `all_students_${new Date().toISOString().slice(0, 10)}.csv`;
            link.click();

            toast.success('כל התלמידים יוצאו בהצלחה');
        } else {
            toast.error(data.message || 'שגיאה בייצוא');
        }
    } catch (error) {
        console.error('Export error:', error);
        toast.error('שגיאה בייצוא תלמידים');
    }
}

// ============================================
// INITIALIZE ON PAGE LOAD
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    // Initialize batch manager
    batchManager.init();
});
