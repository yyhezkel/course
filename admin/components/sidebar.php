<?php
/**
 * Admin Sidebar Navigation Component
 *
 * Usage:
 * <?php $activePage = 'dashboard'; include __DIR__ . '/components/sidebar.php'; ?>
 *
 * Active page options:
 * - dashboard, students, tasks, assign, materials, reports
 * - forms, questions, responses
 */

$activePage = $activePage ?? '';

// Helper function to add active class
function isActive($page, $activePage) {
    return $page === $activePage ? 'active' : '';
}
?>

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
        <a href="<?php echo $basePath ?? '../'; ?>dashboard.html" class="nav-item <?php echo isActive('dashboard', $activePage); ?>">
            <span class="nav-icon">📊</span>
            <span>לוח בקרה</span>
        </a>

        <div style="padding: 10px 15px; font-size: 12px; color: #9ca3af; text-transform: uppercase; font-weight: 600; margin-top: 10px;">ניהול קורס</div>

        <a href="<?php echo $basePath ?? '../'; ?>course/index.html" class="nav-item <?php echo isActive('students', $activePage); ?>">
            <span class="nav-icon">🎓</span>
            <span>תלמידים</span>
        </a>
        <a href="<?php echo $basePath ?? '../'; ?>course/tasks.html" class="nav-item <?php echo isActive('tasks', $activePage); ?>">
            <span class="nav-icon">📝</span>
            <span>ספריית משימות</span>
        </a>
        <a href="<?php echo $basePath ?? '../'; ?>course/assign.html" class="nav-item <?php echo isActive('assign', $activePage); ?>">
            <span class="nav-icon">📤</span>
            <span>הקצאת משימות</span>
        </a>
        <a href="<?php echo $basePath ?? '../'; ?>course/materials.html" class="nav-item <?php echo isActive('materials', $activePage); ?>">
            <span class="nav-icon">📚</span>
            <span>חומרי לימוד</span>
        </a>
        <a href="<?php echo $basePath ?? '../'; ?>course/reports.html" class="nav-item <?php echo isActive('reports', $activePage); ?>">
            <span class="nav-icon">📊</span>
            <span>דוחות וניתוח</span>
        </a>

        <div style="padding: 10px 15px; font-size: 12px; color: #9ca3af; text-transform: uppercase; font-weight: 600; margin-top: 20px;">ניהול טפסים</div>

        <a href="<?php echo $basePath ?? '../'; ?>forms/" class="nav-item <?php echo isActive('forms', $activePage); ?>">
            <span class="nav-icon">📋</span>
            <span>בניית טפסים</span>
        </a>
        <a href="<?php echo $basePath ?? '../'; ?>questions/" class="nav-item <?php echo isActive('questions', $activePage); ?>">
            <span class="nav-icon">❓</span>
            <span>ספריית שאלות</span>
        </a>
        <a href="<?php echo $basePath ?? '../'; ?>responses/" class="nav-item <?php echo isActive('responses', $activePage); ?>">
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
