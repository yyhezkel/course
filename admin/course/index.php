<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול תלמידים - קורס</title>
    <link rel="stylesheet" href="../admin.css">
</head>
<body class="admin-body">
    <?php
        $activePage = 'students';
        $basePath = '../';
        include __DIR__ . '/../components/sidebar.php';
    ?>

    <!-- Main Content -->
    <div class="admin-content">
        <div class="admin-container">
        <div class="page-header">
            <h1>ניהול תלמידים</h1>
            <div class="header-actions">
                <button class="btn btn-success" onclick="showCreateStudentModal()">
                    ➕ הוסף תלמיד
                </button>
                <button class="btn btn-secondary" onclick="showBulkImportModal()">
                    📥 ייבוא מרובה
                </button>
                <a href="../dashboard.html" class="btn btn-secondary">חזרה לדשבורד</a>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter-section">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="חיפוש תלמיד לפי שם, ת.ז. או מספר אישי...">
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">הכל</button>
                <button class="filter-btn" data-filter="active">פעילים</button>
                <button class="filter-btn" data-filter="inactive">לא פעילים</button>
                <button class="filter-btn" data-filter="completed">סיימו</button>
                <button class="filter-btn" data-filter="in-progress">בתהליך</button>
                <button class="filter-btn" data-filter="not-started">לא התחילו</button>
            </div>
        </div>

        <!-- Loading State -->
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p>טוען נתונים...</p>
        </div>

        <!-- Users Grid -->
        <div id="usersGrid" class="users-grid" style="display: none;"></div>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle

        let allUsers = [];
        let currentFilter = 'all';

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            loadUsers();
            setupEventListeners();
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

        function setupEventListeners() {
            // Search input
            document.getElementById('searchInput').addEventListener('input', (e) => {
                filterAndRenderUsers();
            });

            // Filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    e.target.classList.add('active');
                    currentFilter = e.target.dataset.filter;
                    filterAndRenderUsers();
                });
            });
        }

        async function loadUsers() {
            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'get_all_users_with_progress' })
                });

                if (!response.ok) {
                    throw new Error('Failed to load users');
                }

                const data = await response.json();

                if (data.success) {
                    allUsers = data.users;
                    filterAndRenderUsers();
                } else {
                    throw new Error(data.message || 'Failed to load users');
                }
            } catch (error) {
                console.error('Error loading users:', error);
                document.getElementById('loading').innerHTML = `
                    <p style="color: red;">שגיאה בטעינת הנתונים</p>
                    <p>${error.message}</p>
                `;
            }
        }

        function filterAndRenderUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();

            let filtered = allUsers.filter(user => {
                // Search filter
                const matchesSearch = !searchTerm ||
                    user.tz.toLowerCase().includes(searchTerm) ||
                    (user.name && user.name.toLowerCase().includes(searchTerm));

                // Status filter
                let matchesFilter = true;
                if (currentFilter === 'active') {
                    matchesFilter = !user.is_blocked;
                } else if (currentFilter === 'inactive') {
                    matchesFilter = user.is_blocked;
                } else if (currentFilter === 'completed') {
                    matchesFilter = user.total_tasks > 0 && user.completed_tasks === user.total_tasks;
                } else if (currentFilter === 'in-progress') {
                    matchesFilter = user.completed_tasks > 0 && user.completed_tasks < user.total_tasks;
                } else if (currentFilter === 'not-started') {
                    matchesFilter = user.completed_tasks === 0;
                }

                return matchesSearch && matchesFilter;
            });

            renderUsers(filtered);
        }

        function renderUsers(users) {
            document.getElementById('loading').style.display = 'none';
            const grid = document.getElementById('usersGrid');
            grid.style.display = 'grid';

            if (users.length === 0) {
                grid.innerHTML = `
                    <div class="no-users" style="grid-column: 1 / -1;">
                        <div class="no-users-icon">👥</div>
                        <p>לא נמצאו תלמידים</p>
                    </div>
                `;
                return;
            }

            grid.innerHTML = users.map(user => {
                const progressPercentage = user.total_tasks > 0
                    ? Math.round((user.completed_tasks / user.total_tasks) * 100)
                    : 0;

                const initials = user.tz ? user.tz.substring(0, 2) : '??';
                const isActive = !user.is_blocked;
                const statusBadge = isActive ? 'active' : 'inactive';
                const statusText = isActive ? 'פעיל' : 'לא פעיל';

                return `
                    <div class="user-card ${!isActive ? 'inactive' : ''}" onclick="openUserDetail(${user.id})">
                        <div class="user-card-header">
                            <div style="display: flex; align-items: center; flex: 1;">
                                <div class="user-avatar">${initials}</div>
                                <div class="user-info">
                                    <h3 class="user-name">${user.tz}</h3>
                                    <p class="user-id">מספר זיהוי: ${user.tz}</p>
                                </div>
                            </div>
                            <span class="user-status-badge ${statusBadge}">${statusText}</span>
                        </div>

                        <div class="user-stats">
                            <div class="stat-item">
                                <div class="stat-number">${user.total_tasks}</div>
                                <div class="stat-label">משימות</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">${user.completed_tasks}</div>
                                <div class="stat-label">הושלמו</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">${user.pending_tasks}</div>
                                <div class="stat-label">ממתינות</div>
                            </div>
                        </div>

                        <div class="progress-section">
                            <div class="progress-label">
                                <span>התקדמות כללית</span>
                                <span>${progressPercentage}%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: ${progressPercentage}%"></div>
                            </div>
                        </div>

                        <div class="user-actions" onclick="event.stopPropagation()">
                            <button class="user-action-btn primary" onclick="openUserDetail(${user.id})" title="פרטים מלאים">
                                👁️
                            </button>
                            <button class="user-action-btn secondary" onclick="showEditStudentModal(${user.id})" title="ערוך">
                                ✏️
                            </button>
                            <button class="user-action-btn secondary" onclick="toggleStudentStatus(${user.id}, ${user.is_blocked})" title="${isActive ? 'השבת' : 'הפעל'}">
                                ${isActive ? '🔒' : '🔓'}
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function openUserDetail(userId) {
            window.location.href = `user-detail.php?id=${userId}`;
        }

        function assignTask(userId) {
            window.location.href = `assign.php`;
        }

        // ========================================
        // Student Management Functions
        // ========================================

        // Show create student modal
        function showCreateStudentModal() {
            showModal('הוסף תלמיד חדש', `
                <form id="create-student-form">
                    <div class="form-group">
                        <label class="form-label">סוג מזהה *</label>
                        <select id="new-id-type" class="form-select" onchange="updateIdPlaceholder()">
                            <option value="tz">תעודת זהות (9 ספרות)</option>
                            <option value="personal_number">מספר אישי (7 ספרות)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" id="id-label">תעודת זהות *</label>
                        <input type="text" id="new-tz" class="form-input" required maxlength="9" placeholder="123456789">
                        <small style="color: #666; font-size: 0.875rem;" id="id-help">הזן 9 ספרות</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">שם מלא</label>
                        <input type="text" id="new-fullname" class="form-input" placeholder="שם פרטי ושם משפחה">
                    </div>

                    <div class="alert alert-info" style="background: #e3f2fd; color: #1976d2; padding: 10px; border-radius: 5px; margin-top: 10px;">
                        💡 <strong>הערה:</strong> תלמידים מתחברים עם מספר זיהוי בלבד, ללא סיסמה
                    </div>
                </form>
            `, [
                {
                    text: 'בטל',
                    class: 'btn-secondary',
                    onclick: 'closeModal()'
                },
                {
                    text: 'הוסף תלמיד',
                    class: 'btn-success',
                    onclick: 'createStudent()'
                }
            ]);
        }

        // Update ID field based on ID type
        function updateIdPlaceholder() {
            const idType = document.getElementById('new-id-type').value;
            const idInput = document.getElementById('new-tz');
            const idLabel = document.getElementById('id-label');
            const idHelp = document.getElementById('id-help');

            if (idType === 'personal_number') {
                idLabel.textContent = 'מספר אישי *';
                idInput.placeholder = '1234567';
                idInput.maxLength = 7;
                idHelp.textContent = 'הזן 7 ספרות';
            } else {
                idLabel.textContent = 'תעודת זהות *';
                idInput.placeholder = '123456789';
                idInput.maxLength = 9;
                idHelp.textContent = 'הזן 9 ספרות';
            }
            idInput.value = '';
        }

        // Create student
        async function createStudent() {
            const tz = document.getElementById('new-tz').value.trim();
            const idType = document.getElementById('new-id-type').value;
            const fullName = document.getElementById('new-fullname').value.trim();

            if (!tz) {
                alert('נא למלא מספר זיהוי');
                return;
            }

            const expectedLength = idType === 'personal_number' ? 7 : 9;
            const idTypeName = idType === 'personal_number' ? 'מספר אישי' : 'תעודת זהות';

            if (tz.length !== expectedLength || !/^\d+$/.test(tz)) {
                alert(`${idTypeName} חייב להיות ${expectedLength} ספרות`);
                return;
            }

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'create_user',
                        tz: tz,
                        id_type: idType,
                        full_name: fullName
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('תלמיד נוצר בהצלחה!');
                    closeModal();
                    loadUsers();
                } else {
                    alert(result.message || 'שגיאה ביצירת תלמיד');
                }
            } catch (error) {
                console.error('Error creating student:', error);
                alert('שגיאה ביצירת תלמיד');
            }
        }

        // Show bulk import modal
        function showBulkImportModal() {
            showModal('ייבוא מרובה של תלמידים', `
                <form id="bulk-import-form">
                    <div class="form-group">
                        <label class="form-label">נתוני תלמידים *</label>
                        <textarea id="bulk-csv-data" class="form-textarea" rows="10"
                            placeholder="הזן נתונים בפורמט CSV או מופרד בפסיקים:&#10;123456789,ישראל ישראלי&#10;987654321,שרה כהן&#10;111222333,דוד לוי&#10;&#10;פורמט: מספר_זיהוי,שם_מלא&#10;&#10;כל שורה מייצגת תלמיד אחד"></textarea>
                    </div>

                    <div class="alert alert-info" style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 5px; margin-top: 10px;">
                        <strong>💡 הוראות:</strong><br>
                        • כל שורה מייצגת תלמיד אחד<br>
                        • פורמט: מספר_זיהוי,שם_מלא<br>
                        • מספר זיהוי הוא שדה חובה (9 או 7 ספרות)<br>
                        • שם מלא הוא אופציונלי<br>
                        • ניתן להפריד בפסיק או בעזרת CSV
                    </div>
                </form>
            `, [
                {
                    text: 'בטל',
                    class: 'btn-secondary',
                    onclick: 'closeModal()'
                },
                {
                    text: 'ייבא תלמידים',
                    class: 'btn-success',
                    onclick: 'bulkImportStudents()'
                }
            ]);
        }

        // Bulk import students
        async function bulkImportStudents() {
            const csvData = document.getElementById('bulk-csv-data').value.trim();

            if (!csvData) {
                alert('נא להזין נתוני תלמידים');
                return;
            }

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'bulk_import_users',
                        csv_data: csvData
                    })
                });

                const result = await response.json();

                if (result.success) {
                    let message = result.message;
                    if (result.errors && result.errors.length > 0) {
                        message += '\\n\\nשגיאות:\\n' + result.errors.join('\\n');
                    }
                    alert(message);
                    closeModal();
                    loadUsers();
                } else {
                    alert(result.message || 'שגיאה בייבוא תלמידים');
                }
            } catch (error) {
                console.error('Error importing students:', error);
                alert('שגיאה בייבוא תלמידים');
            }
        }

        // Show edit student modal
        async function showEditStudentModal(userId) {
            const user = allUsers.find(u => u.id == userId);

            if (!user) {
                alert('תלמיד לא נמצא');
                return;
            }

            showModal('ערוך תלמיד', `
                <form id="edit-student-form">
                    <input type="hidden" id="edit-user-id" value="${userId}">

                    <div class="form-group">
                        <label class="form-label">מספר זיהוי</label>
                        <input type="text" class="form-input" value="${user.tz}" disabled>
                        <small style="color: #666;">לא ניתן לשנות מספר זיהוי</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">שם מלא</label>
                        <input type="text" id="edit-fullname" class="form-input" value="${user.full_name || user.tz}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">סטטוס</label>
                        <select id="edit-active" class="form-select">
                            <option value="1" ${!user.is_blocked ? 'selected' : ''}>פעיל</option>
                            <option value="0" ${user.is_blocked ? 'selected' : ''}>לא פעיל</option>
                        </select>
                    </div>
                </form>
            `, [
                {
                    text: 'בטל',
                    class: 'btn-secondary',
                    onclick: 'closeModal()'
                },
                {
                    text: 'שמור שינויים',
                    class: 'btn-success',
                    onclick: 'updateStudent()'
                }
            ]);
        }

        // Update student
        async function updateStudent() {
            const userId = document.getElementById('edit-user-id').value;
            const fullName = document.getElementById('edit-fullname').value.trim();
            const isActive = document.getElementById('edit-active').value;

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'update_user',
                        user_id: userId,
                        full_name: fullName,
                        is_active: isActive
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('תלמיד עודכן בהצלחה!');
                    closeModal();
                    loadUsers();
                } else {
                    alert(result.message || 'שגיאה בעדכון תלמיד');
                }
            } catch (error) {
                console.error('Error updating student:', error);
                alert('שגיאה בעדכון תלמיד');
            }
        }

        // Toggle student status (activate/deactivate)
        async function toggleStudentStatus(userId, isBlocked) {
            const user = allUsers.find(u => u.id == userId);
            const action = isBlocked ? 'הפעלת' : 'השבתת';
            const newStatus = isBlocked ? 1 : 0;

            if (!confirm(`האם אתה בטוח שברצונך ב${action} התלמיד ${user.tz}?`)) {
                return;
            }

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'update_user',
                        user_id: userId,
                        is_active: newStatus
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert(`תלמיד ${isBlocked ? 'הופעל' : 'הושבת'} בהצלחה!`);
                    loadUsers();
                } else {
                    alert(result.message || 'שגיאה בעדכון סטטוס');
                }
            } catch (error) {
                console.error('Error toggling status:', error);
                alert('שגיאה בעדכון סטטוס');
            }
        }

        // ========================================
        // Modal Functions
        // ========================================

        function showModal(title, content, buttons) {
            // Create modal HTML
            const modalHtml = `
                <div id="modalOverlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center;" onclick="if(event.target.id==='modalOverlay') closeModal()">
                    <div style="background: white; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);" onclick="event.stopPropagation()">
                        <div style="padding: 20px; border-bottom: 1px solid #e0e0e0;">
                            <h2 style="margin: 0; color: #333;">${title}</h2>
                        </div>
                        <div style="padding: 20px;">
                            ${content}
                        </div>
                        <div style="padding: 15px 20px; border-top: 1px solid #e0e0e0; display: flex; gap: 10px; justify-content: flex-end;">
                            ${buttons.map(btn => `
                                <button class="btn ${btn.class}" onclick="${btn.onclick}">${btn.text}</button>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal
            closeModal();

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        function closeModal() {
            const modal = document.getElementById('modalOverlay');
            if (modal) {
                modal.remove();
            }
        }
    </script>

    <script src="../admin.js"></script>
    <script src="../components/mobile-menu.js"></script>
</body>
</html>
