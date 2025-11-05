<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>לוח בקרה - מערכת ניהול</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
    <?php
        $activePage = 'dashboard';
        $basePath = './';
        include __DIR__ . '/components/sidebar.php';
    ?>

    <!-- Main Content -->
    <main class="admin-main">
        <header class="admin-header">
            <h1>לוח בקרה</h1>
            <p>סקירה כללית של המערכת</p>
        </header>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">👥</div>
                <div class="stat-content">
                    <div class="stat-label">סה"כ משתמשים</div>
                    <div class="stat-value" id="total-users">-</div>
                    <div class="stat-trend">טוען...</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">📝</div>
                <div class="stat-content">
                    <div class="stat-label">טפסים פעילים</div>
                    <div class="stat-value" id="total-forms">-</div>
                    <div class="stat-trend">טוען...</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">✓</div>
                <div class="stat-content">
                    <div class="stat-label">טפסים שהושלמו</div>
                    <div class="stat-value" id="completed-forms">-</div>
                    <div class="stat-trend">טוען...</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">❓</div>
                <div class="stat-content">
                    <div class="stat-label">שאלות במאגר</div>
                    <div class="stat-value" id="total-questions">-</div>
                    <div class="stat-trend">טוען...</div>
                </div>
            </div>
        </div>

        <!-- Course Management Stats -->
        <div class="section-header" style="margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0;">
            <h2 style="font-size: 20px; color: #333;">סטטיסטיקות ניהול קורס</h2>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">📋</div>
                <div class="stat-content">
                    <div class="stat-label">סך המשימות</div>
                    <div class="stat-value" id="total-tasks">-</div>
                    <div class="stat-trend">טוען...</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">✅</div>
                <div class="stat-content">
                    <div class="stat-label">משימות שהושלמו</div>
                    <div class="stat-value" id="completed-tasks">-</div>
                    <div class="stat-trend">טוען...</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">⏳</div>
                <div class="stat-content">
                    <div class="stat-label">ממתינות לבדיקה</div>
                    <div class="stat-value" id="pending-reviews">-</div>
                    <div class="stat-trend">טוען...</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <section class="section">
            <h2 class="section-title">פעולות מהירות</h2>
            <div class="actions-grid">
                <a href="./users/?action=add" class="action-card">
                    <div class="action-icon">➕</div>
                    <h3>הוסף משתמש</h3>
                    <p>צור משתמש חדש במערכת</p>
                </a>

                <a href="./forms/?action=create" class="action-card">
                    <div class="action-icon">📄</div>
                    <h3>צור טופס</h3>
                    <p>בנה טופס חדש</p>
                </a>

                <a href="./questions/?action=create" class="action-card">
                    <div class="action-icon">➕</div>
                    <h3>הוסף שאלה</h3>
                    <p>הוסף שאלה לספרייה</p>
                </a>

                <a href="./responses/" class="action-card">
                    <div class="action-icon">📊</div>
                    <h3>צפה בתשובות</h3>
                    <p>סקור תשובות משתמשים</p>
                </a>
            </div>
        </section>

        <!-- Recent Activity -->
        <section class="section">
            <h2 class="section-title">פעילות אחרונה</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>תאריך</th>
                            <th>פעולה</th>
                            <th>ישות</th>
                            <th>משתמש</th>
                        </tr>
                    </thead>
                    <tbody id="activity-log">
                        <tr>
                            <td colspan="4" class="text-center">טוען פעילות...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="admin.js"></script>
    <script>
        // Mobile Menu Toggle

        // Load dashboard data
        async function loadDashboardData() {
            try {
                const response = await fetch('./api.php?action=dashboard_stats', {
                    credentials: 'include'
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('total-users').textContent = result.stats.total_users || 0;
                    document.getElementById('total-forms').textContent = result.stats.total_forms || 0;
                    document.getElementById('completed-forms').textContent = result.stats.completed_forms || 0;
                    document.getElementById('total-questions').textContent = result.stats.total_questions || 0;

                    // Course management stats
                    document.getElementById('total-tasks').textContent = result.stats.total_tasks || 0;
                    document.getElementById('completed-tasks').textContent = result.stats.completed_tasks || 0;
                    document.getElementById('pending-reviews').textContent = result.stats.pending_reviews || 0;

                    // Load activity log
                    loadActivityLog(result.stats.recent_activity || []);
                }
            } catch (error) {
                console.error('Error loading dashboard data:', error);
            }
        }

        function loadActivityLog(activities) {
            const tbody = document.getElementById('activity-log');

            if (activities.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center">אין פעילות אחרונה</td></tr>';
                return;
            }

            tbody.innerHTML = activities.map(activity => `
                <tr>
                    <td>${formatDate(activity.created_at)}</td>
                    <td>${getActionText(activity.action)}</td>
                    <td>${getEntityText(activity.entity_type)}</td>
                    <td>${activity.admin_name || 'מנהל'}</td>
                </tr>
            `).join('');
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('he-IL');
        }

        function getActionText(action) {
            const actions = {
                'login': 'התחבר',
                'logout': 'התנתק',
                'create': 'יצר',
                'update': 'עדכן',
                'delete': 'מחק'
            };
            return actions[action] || action;
        }

        function getEntityText(entity) {
            const entities = {
                'user': 'משתמש',
                'form': 'טופס',
                'question': 'שאלה',
                'admin_user': 'מנהל'
            };
            return entities[entity] || entity;
        }

        // Load data on page load
        loadDashboardData();
    </script>

    <script src="admin.js"></script>
    <script src="components/mobile-menu.js"></script>
</body>
</html>
