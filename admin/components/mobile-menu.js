/**
 * Mobile Menu Toggle
 * Include this at the end of admin pages to enable mobile navigation
 */

function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');

    if (sidebar.classList.contains('active')) {
        overlay.style.display = 'block';
        setTimeout(() => overlay.classList.add('active'), 10);
    } else {
        overlay.classList.remove('active');
        setTimeout(() => overlay.style.display = 'none', 300);
    }
}

// Close menu when clicking on navigation links (mobile)
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.sidebar .nav-item');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                toggleMobileMenu();
            }
        });
    });
});
