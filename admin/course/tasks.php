<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ספריית משימות - ניהול קורס</title>
    <link rel="stylesheet" href="../admin.css">
</head>
<body class="admin-body">
    <?php
        $activePage = 'tasks';
        $basePath = '../';
        include __DIR__ . '/../components/sidebar.php';
    ?>

    <!-- Main Content -->
    <div class="admin-content">
        <div class="admin-container">
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1>ספריית משימות</h1>
                <p>ניהול כל המשימות והמטלות בקורס</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="../dashboard.html" class="btn btn-secondary">חזרה לדשבורד</a>
                <a href="task-editor.php" class="btn btn-primary">+ משימה חדשה</a>
            </div>
        </div>

        <!-- Search/Filter -->
        <div class="search-filter">
            <input type="text" id="searchInput" placeholder="חיפוש משימה..." onkeyup="filterTasks()">
        </div>

        <!-- Loading State -->
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p>טוען משימות...</p>
        </div>

        <!-- Tasks Table -->
        <div id="tasksContent" style="display: none;">
            <div class="tasks-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40%">משימה</th>
                            <th style="width: 15%">סוג</th>
                            <th style="width: 10%">נקודות</th>
                            <th style="width: 15%">הוקצתה ל</th>
                            <th style="width: 10%">סטטוס</th>
                            <th style="width: 10%">פעולות</th>
                        </tr>
                    </thead>
                    <tbody id="tasksTable"></tbody>
                </table>
            </div>
        </div>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle

        async function checkAuth() {
            try {
                const response = await fetch('../auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'check' })
                });

                const data = await response.json();
                if (!data.authenticated) {
                    window.location.href = '../index.php';
                    return;
                }

                document.getElementById('adminName').textContent = data.admin.full_name || data.admin.username;
            } catch (error) {
                console.error('Error checking auth:', error);
                window.location.href = '../index.php';
            }
        }

        function logout() {
            fetch('../auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ action: 'logout' })
            }).then(() => {
                window.location.href = '../index.php';
            });
        }
    </script>

    <script>
        // Mobile Menu Toggle

        let allTasks = [];

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            loadTasks();
        });

        async function loadTasks() {
            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'get_all_tasks' })
                });

                if (!response.ok) {
                    throw new Error('Failed to load tasks');
                }

                const data = await response.json();

                if (data.success) {
                    allTasks = data.tasks;
                    renderTasks(allTasks);
                } else {
                    throw new Error(data.message || 'Failed to load tasks');
                }
            } catch (error) {
                console.error('Error loading tasks:', error);
                document.getElementById('loading').innerHTML = `
                    <p style="color: red;">שגיאה בטעינת המשימות</p>
                    <p>${error.message}</p>
                `;
            }
        }

        function renderTasks(tasks) {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('tasksContent').style.display = 'block';

            const tasksTable = document.getElementById('tasksTable');

            if (tasks.length === 0) {
                tasksTable.innerHTML = `
                    <tr>
                        <td colspan="6">
                            <div class="no-tasks">
                                <div class="no-tasks-icon">📝</div>
                                <p>אין משימות במערכת</p>
                                <a href="task-editor.php" class="btn btn-primary">צור משימה ראשונה</a>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            tasksTable.innerHTML = tasks.map(task => `
                <tr>
                    <td>
                        <div class="task-title">${task.title}</div>
                        ${task.description ? `<div class="task-description">${task.description}</div>` : ''}
                        <div class="task-meta">
                            ${task.estimated_duration ? `<span>⏱️ ${task.estimated_duration} דקות</span>` : ''}
                            ${task.form_title ? `<span>📋 ${task.form_title}</span>` : ''}
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-type">${getTaskTypeText(task.task_type)}</span>
                    </td>
                    <td>
                        <strong>${task.points || 0}</strong> נקודות
                    </td>
                    <td>
                        ${task.assigned_count || 0} תלמידים<br>
                        <small style="color: #666;">${task.completed_count || 0} הושלמו</small>
                    </td>
                    <td>
                        <span class="badge ${task.is_active ? 'badge-active' : 'badge-inactive'}">
                            ${task.is_active ? 'פעיל' : 'לא פעיל'}
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="task-editor.php?id=${task.id}" class="btn btn-secondary btn-sm">ערוך</a>
                            <button onclick="deleteTask(${task.id})" class="btn btn-danger btn-sm">מחק</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function filterTasks() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const filtered = allTasks.filter(task =>
                task.title.toLowerCase().includes(search) ||
                (task.description && task.description.toLowerCase().includes(search))
            );
            renderTasks(filtered);
        }

        function getTaskTypeText(type) {
            const types = {
                'assignment': 'מטלה',
                'reading': 'קריאה',
                'form': 'טופס',
                'quiz': 'בחן',
                'video': 'וידאו'
            };
            return types[type] || type;
        }

        async function deleteTask(taskId) {
            if (!confirm('האם אתה בטוח שברצונך למחוק משימה זו? פעולה זו אינה הפיכה.')) {
                return;
            }

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'delete_task',
                        task_id: taskId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('המשימה נמחקה בהצלחה');
                    await loadTasks(); // Reload list
                } else {
                    alert('שגיאה: ' + data.message);
                }
            } catch (error) {
                console.error('Error deleting task:', error);
                alert('שגיאה במחיקת המשימה');
            }
        }
    </script>

    <script src="../admin.js"></script>
    <script src="../components/mobile-menu.js"></script>
</body>
</html>
