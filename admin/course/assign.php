<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הקצאת משימות - ניהול קורס</title>
    <link rel="stylesheet" href="../admin.css">
</head>
<body class="admin-body">
    <?php
        $activePage = 'assign';
        $basePath = '../';
        include __DIR__ . '/../components/sidebar.php';
    ?>

    <!-- Main Content -->
    <div class="admin-content">
        <div class="admin-container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>הקצאת משימות לתלמידים</h1>
                    <p>בחר תלמידים ומשימה להקצאה קבוצתית</p>
                </div>
                <a href="./tasks.php" class="btn btn-secondary">חזרה לספריית משימות</a>
            </div>

            <!-- Alert Messages -->
            <div id="alertMessage" style="display: none;"></div>

            <!-- Step 1: Select Task -->
            <div class="editor-card">
                <h3>שלב 1: בחירת משימה</h3>

                <div class="form-group">
                    <label class="form-label required" for="taskSelect">בחר משימה להקצאה</label>
                    <select id="taskSelect" class="form-control" onchange="loadUsers()">
                        <option value="">-- בחר משימה --</option>
                    </select>
                    <div class="form-help">בחר את המשימה שברצונך להקצות לתלמידים</div>
                </div>

                <div id="taskDetails" style="display: none; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <div style="font-size: 12px; color: #666;">סוג משימה</div>
                            <div style="font-weight: 600;" id="taskType"></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #666;">משך משוער</div>
                            <div style="font-weight: 600;" id="taskDuration"></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #666;">נקודות</div>
                            <div style="font-weight: 600;" id="taskPoints"></div>
                        </div>
                    </div>
                    <div style="margin-top: 10px;">
                        <div style="font-size: 12px; color: #666;">תיאור</div>
                        <div id="taskDescription" style="color: #333;"></div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Select Users -->
            <div class="editor-card" id="userSelectionCard" style="display: none;">
                <h3>שלב 2: בחירת תלמידים</h3>

                <!-- Select All / Deselect All -->
                <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
                    <button class="btn btn-secondary btn-sm" onclick="selectAllUsers()">בחר הכל</button>
                    <button class="btn btn-secondary btn-sm" onclick="deselectAllUsers()">בטל בחירה</button>
                    <div style="margin-right: auto; color: #666; font-size: 14px;">
                        <span id="selectedCount">0</span> תלמידים נבחרו
                    </div>
                </div>

                <!-- Search Box -->
                <div class="form-group">
                    <input type="text" id="userSearch" class="form-control" placeholder="חפש תלמיד לפי שם או תעודת זהות..." onkeyup="filterUsers()">
                </div>

                <!-- Users List -->
                <div id="usersList" class="loading">
                    <div class="spinner"></div>
                    <p>טוען תלמידים...</p>
                </div>
            </div>

            <!-- Step 3: Assignment Options -->
            <div class="editor-card" id="optionsCard" style="display: none;">
                <h3>שלב 3: אפשרויות הקצאה</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="dueDate">תאריך יעד</label>
                        <input type="date" id="dueDate" class="form-control">
                        <div class="form-help">תאריך יעד להשלמת המשימה (אופציונלי)</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="priority">עדיפות</label>
                        <select id="priority" class="form-control">
                            <option value="low">נמוכה</option>
                            <option value="normal" selected>רגילה</option>
                            <option value="high">גבוהה</option>
                            <option value="urgent">דחופה</option>
                        </select>
                        <div class="form-help">רמת עדיפות למשימה</div>
                    </div>
                </div>

                <!-- Assignment Button -->
                <button class="btn btn-success btn-block" onclick="bulkAssign()" id="assignBtn" disabled>
                    הקצה משימה לתלמידים שנבחרו
                </button>
            </div>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle

        let allUsers = [];
        let selectedTaskId = null;

        // Load tasks on page load
        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            await loadTasks();
        });

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

        async function loadTasks() {
            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'get_all_tasks' })
                });

                const data = await response.json();

                if (data.success && data.tasks) {
                    const taskSelect = document.getElementById('taskSelect');

                    // Only show active tasks
                    const activeTasks = data.tasks.filter(task => task.is_active == 1);

                    activeTasks.forEach(task => {
                        const option = document.createElement('option');
                        option.value = task.id;
                        option.textContent = task.title;
                        option.dataset.task = JSON.stringify(task);
                        taskSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading tasks:', error);
                showAlert('שגיאה בטעינת המשימות', 'error');
            }
        }

        async function loadUsers() {
            const taskSelect = document.getElementById('taskSelect');
            selectedTaskId = taskSelect.value;

            if (!selectedTaskId) {
                document.getElementById('userSelectionCard').style.display = 'none';
                document.getElementById('optionsCard').style.display = 'none';
                document.getElementById('taskDetails').style.display = 'none';
                return;
            }

            // Show task details
            const selectedOption = taskSelect.options[taskSelect.selectedIndex];
            const task = JSON.parse(selectedOption.dataset.task);

            document.getElementById('taskType').textContent = getTaskTypeLabel(task.task_type);
            document.getElementById('taskDuration').textContent = task.estimated_duration ? `${task.estimated_duration} דקות` : 'לא צוין';
            document.getElementById('taskPoints').textContent = task.points || '0';
            document.getElementById('taskDescription').textContent = task.description || 'אין תיאור';
            document.getElementById('taskDetails').style.display = 'block';

            // Show user selection card
            document.getElementById('userSelectionCard').style.display = 'block';
            document.getElementById('optionsCard').style.display = 'block';

            // Load all users
            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'get_all_users_with_progress' })
                });

                const data = await response.json();

                if (data.success && data.users) {
                    allUsers = data.users;
                    renderUsers();
                }
            } catch (error) {
                console.error('Error loading users:', error);
                showAlert('שגיאה בטעינת התלמידים', 'error');
            }
        }

        function renderUsers() {
            const container = document.getElementById('usersList');

            if (allUsers.length === 0) {
                container.innerHTML = '<div class="no-users"><div class="no-users-icon">👥</div><p>אין תלמידים במערכת</p></div>';
                return;
            }

            container.innerHTML = '';
            container.style.display = 'grid';
            container.style.gridTemplateColumns = 'repeat(auto-fill, minmax(300px, 1fr))';
            container.style.gap = '15px';

            allUsers.forEach(user => {
                const userCard = document.createElement('div');
                userCard.className = 'user-card-checkbox';
                userCard.style.padding = '15px';
                userCard.style.background = 'white';
                userCard.style.border = '2px solid #e0e0e0';
                userCard.style.borderRadius = '8px';
                userCard.style.cursor = 'pointer';
                userCard.dataset.userId = user.id;

                // Check if user already has this task
                const hasTask = user.tasks && user.tasks.some(t => t.task_id == selectedTaskId);

                userCard.innerHTML = `
                    <label style="display: flex; align-items: start; gap: 12px; cursor: pointer;">
                        <input type="checkbox"
                               class="user-checkbox"
                               value="${user.id}"
                               onchange="updateSelectedCount()"
                               ${hasTask ? 'disabled' : ''}>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #333; margin-bottom: 4px;">${user.full_name || user.tz}</div>
                            <div style="font-size: 13px; color: #666;">ת.ז: ${user.tz}</div>
                            ${hasTask ? '<div style="font-size: 12px; color: #ff9800; margin-top: 8px;">⚠️ משימה כבר הוקצתה</div>' : ''}
                        </div>
                    </label>
                `;

                if (hasTask) {
                    userCard.style.opacity = '0.5';
                    userCard.style.background = '#f5f5f5';
                }

                container.appendChild(userCard);
            });

            updateSelectedCount();
        }

        function filterUsers() {
            const searchTerm = document.getElementById('userSearch').value.toLowerCase();
            const userCards = document.querySelectorAll('.user-card-checkbox');

            userCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function selectAllUsers() {
            document.querySelectorAll('.user-checkbox:not(:disabled)').forEach(cb => {
                cb.checked = true;
            });
            updateSelectedCount();
        }

        function deselectAllUsers() {
            document.querySelectorAll('.user-checkbox').forEach(cb => {
                cb.checked = false;
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selectedCount;
            document.getElementById('assignBtn').disabled = selectedCount === 0;
        }

        async function bulkAssign() {
            const selectedUserIds = Array.from(document.querySelectorAll('.user-checkbox:checked'))
                .map(cb => parseInt(cb.value));

            if (selectedUserIds.length === 0) {
                showAlert('אנא בחר לפחות תלמיד אחד', 'error');
                return;
            }

            if (!selectedTaskId) {
                showAlert('אנא בחר משימה', 'error');
                return;
            }

            const dueDate = document.getElementById('dueDate').value || null;
            const priority = document.getElementById('priority').value;

            const assignBtn = document.getElementById('assignBtn');
            assignBtn.disabled = true;
            assignBtn.textContent = 'מקצה...';

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'bulk_assign_task',
                        user_ids: selectedUserIds,
                        task_id: parseInt(selectedTaskId),
                        due_date: dueDate,
                        priority: priority
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(`הצלחה! הוקצו ${data.assigned_count} משימות. ${data.skipped_count > 0 ? `דולגו ${data.skipped_count} (כבר קיימים)` : ''}`, 'success');

                    // Reset selection
                    deselectAllUsers();

                    // Reload users to show updated state
                    await loadUsers();
                } else {
                    showAlert('שגיאה: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('שגיאה בהקצאת המשימה', 'error');
            } finally {
                assignBtn.disabled = false;
                assignBtn.textContent = 'הקצה משימה לתלמידים שנבחרו';
            }
        }

        function getTaskTypeLabel(type) {
            const types = {
                'assignment': 'מטלה',
                'reading': 'קריאה',
                'form': 'טופס',
                'quiz': 'בחן',
                'video': 'וידאו'
            };
            return types[type] || type;
        }

        function showAlert(message, type) {
            const alertDiv = document.getElementById('alertMessage');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            alertDiv.style.display = 'block';

            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
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

    <script src="../admin.js"></script>
    <script src="../components/mobile-menu.js"></script>
</body>
</html>
