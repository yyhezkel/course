<?php
/**
 * Student Header Navigation Component
 *
 * Usage:
 * <?php $userName = 'משתמש'; include __DIR__ . '/components/student-header.php'; ?>
 */

$userName = $userName ?? 'משתמש';
?>

<!-- Welcome Section / Header -->
<div class="welcome-section">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1>שלום, <span id="userName"><?php echo htmlspecialchars($userName); ?></span>!</h1>
            <p>ברוך הבא ללוח המשימות שלך</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="profile.html" class="logout-btn" style="text-decoration: none;">👤 הפרופיל שלי</a>
            <a href="dashboard.html" class="logout-btn" style="text-decoration: none;">📊 לוח בקרה</a>
            <button class="logout-btn" onclick="logout()">יציאה</button>
        </div>
    </div>
</div>
