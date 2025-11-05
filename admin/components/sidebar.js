/**
 * Admin Sidebar Navigation Component (JavaScript)
 *
 * Usage in HTML:
 * <div id="admin-sidebar"></div>
 * <script src="components/sidebar.js"></script>
 * <script>loadAdminSidebar('dashboard');</script>
 *
 * Or set data attribute:
 * <body class="admin-body" data-active-page="dashboard">
 */

function loadAdminSidebar(activePage) {
    // Auto-detect active page from data attribute if not provided
    if (!activePage) {
        activePage = document.body.getAttribute('data-active-page') || '';
    }

    // Determine base path based on current location
    const path = window.location.pathname;
    let basePath = '../';
    if (path.includes('/course/')) {
        basePath = '../';
    } else if (path.includes('/forms/') || path.includes('/questions/') || path.includes('/responses/')) {
        basePath = '../';
    } else if (path.includes('/admin/') && !path.includes('/course/')) {
        basePath = './';
    }

    const sidebarHTML = `
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleMobileMenu()">☰</button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileMenu()"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">👨‍💼</div>
            <h2>לוח ניהול</h2>
            <p class="admin-name" id="admin-name">טוען...</p>
        </div>

        <nav class="sidebar-nav">
            <a href="${basePath}dashboard.php" class="nav-item ${activePage === 'dashboard' ? 'active' : ''}">
                <span class="nav-icon">📊</span>
                <span>לוח בקרה</span>
            </a>

            <div style="padding: 10px 15px; font-size: 12px; color: #9ca3af; text-transform: uppercase; font-weight: 600; margin-top: 10px;">ניהול קורס</div>

            <a href="${basePath}course/index.php" class="nav-item ${activePage === 'students' ? 'active' : ''}">
                <span class="nav-icon">🎓</span>
                <span>תלמידים</span>
            </a>
            <a href="${basePath}course/tasks.php" class="nav-item ${activePage === 'tasks' ? 'active' : ''}">
                <span class="nav-icon">📝</span>
                <span>ספריית משימות</span>
            </a>
            <a href="${basePath}course/assign.php" class="nav-item ${activePage === 'assign' ? 'active' : ''}">
                <span class="nav-icon">📤</span>
                <span>הקצאת משימות</span>
            </a>
            <a href="${basePath}course/materials.php" class="nav-item ${activePage === 'materials' ? 'active' : ''}">
                <span class="nav-icon">📚</span>
                <span>חומרי לימוד</span>
            </a>
            <a href="${basePath}course/reports.php" class="nav-item ${activePage === 'reports' ? 'active' : ''}">
                <span class="nav-icon">📊</span>
                <span>דוחות וניתוח</span>
            </a>

            <div style="padding: 10px 15px; font-size: 12px; color: #9ca3af; text-transform: uppercase; font-weight: 600; margin-top: 20px;">ניהול טפסים</div>

            <a href="${basePath}forms/" class="nav-item ${activePage === 'forms' ? 'active' : ''}">
                <span class="nav-icon">📋</span>
                <span>בניית טפסים</span>
            </a>
            <a href="${basePath}questions/" class="nav-item ${activePage === 'questions' ? 'active' : ''}">
                <span class="nav-icon">❓</span>
                <span>ספריית שאלות</span>
            </a>
            <a href="${basePath}responses/" class="nav-item ${activePage === 'responses' ? 'active' : ''}">
                <span class="nav-icon">📄</span>
                <span>תשובות</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <button class="logout-btn" onclick="logout()">
                <span>🚪</span>
                <span>התנתק</span>
            </button>
        </div>
    </aside>
    `;

    // Insert at the beginning of body or replace placeholder
    const placeholder = document.getElementById('admin-sidebar');
    if (placeholder) {
        placeholder.outerHTML = sidebarHTML;
    } else {
        document.body.insertAdjacentHTML('afterbegin', sidebarHTML);
    }
}

// Auto-load if data attribute is present
document.addEventListener('DOMContentLoaded', function() {
    const activePage = document.body.getAttribute('data-active-page');
    if (activePage) {
        loadAdminSidebar(activePage);
    }
});
