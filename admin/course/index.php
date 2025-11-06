<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול תלמידים - קורס</title>
    <link rel="stylesheet" href="../admin.css">
    <script src="../utils.js"></script>
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
                <!-- Sort Dropdown -->
                <div class="sort-dropdown">
                    <button class="sort-btn" onclick="toggleSortMenu()">
                        🔽 מיון
                    </button>
                    <div class="sort-menu" id="sortMenu">
                        <div class="sort-option active" data-sort="id" onclick="setSortOption('id', 'desc')">
                            <span>לפי תאריך הוספה</span>
                            <span class="sort-option-icon">✓</span>
                        </div>
                        <div class="sort-option" data-sort="tz" onclick="setSortOption('tz', 'asc')">
                            <span>לפי ת.ז.</span>
                        </div>
                        <div class="sort-option" data-sort="progress" onclick="setSortOption('progress', 'desc')">
                            <span>לפי התקדמות</span>
                        </div>
                        <div class="sort-option" data-sort="last_login" onclick="setSortOption('last_login', 'desc')">
                            <span>לפי כניסה אחרונה</span>
                        </div>
                        <div class="sort-option" data-sort="tasks" onclick="setSortOption('tasks', 'desc')">
                            <span>לפי מספר משימות</span>
                        </div>
                    </div>
                </div>

                <button class="btn btn-secondary" onclick="exportAllUsers()">
                    📤 ייצוא הכל
                </button>
                <button class="btn btn-success" onclick="showCreateStudentModal()">
                    ➕ הוסף תלמיד
                </button>
                <button class="btn btn-secondary" onclick="showBulkImportModal()">
                    📥 ייבוא מרובה
                </button>
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
                <button class="filter-btn" data-filter="archived">ארכיון</button>
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
        let currentSort = { field: 'id', direction: 'desc' };

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            loadUsers();
            setupEventListeners();
        });

        // Sort menu toggle
        function toggleSortMenu() {
            const menu = document.getElementById('sortMenu');
            menu.classList.toggle('active');
        }

        // Set sort option
        function setSortOption(field, direction) {
            currentSort = { field, direction };

            // Update active state
            document.querySelectorAll('.sort-option').forEach(opt => {
                opt.classList.remove('active');
                opt.querySelector('.sort-option-icon')?.remove();
            });

            const activeOption = document.querySelector(`[data-sort="${field}"]`);
            activeOption.classList.add('active');
            activeOption.innerHTML += '<span class="sort-option-icon">✓</span>';

            // Close menu and re-render
            toggleSortMenu();
            filterAndRenderUsers();
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.sort-dropdown')) {
                document.getElementById('sortMenu')?.classList.remove('active');
            }
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
            // Search input with debouncing
            const debouncedSearch = debounce(() => {
                filterAndRenderUsers();
            }, 300);

            document.getElementById('searchInput').addEventListener('input', debouncedSearch);

            // Filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    e.target.classList.add('active');
                    const prevFilter = currentFilter;
                    currentFilter = e.target.dataset.filter;

                    // Reload users when switching to/from archived view
                    if ((prevFilter === 'archived' || currentFilter === 'archived') && prevFilter !== currentFilter) {
                        loadUsers();
                    } else {
                        filterAndRenderUsers();
                    }
                });
            });
        }

        async function loadUsers() {
            try {
                // Show archived users only when archived filter is selected
                const archived = currentFilter === 'archived' ? 1 : 0;

                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'get_all_users_with_progress',
                        archived: archived
                    })
                });

                if (!response.ok) {
                    throw new Error('Failed to load users');
                }

                const data = await response.json();

                if (data.success) {
                    allUsers = data.users;

                    // Show migration warning if needed (only once per session)
                    if (data.migration_needed && !sessionStorage.getItem('migration_warning_shown')) {
                        console.warn('Archive feature needs migration: run admin/migrate_archive.php');
                        sessionStorage.setItem('migration_warning_shown', 'true');
                    }

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
                    (user.full_name && user.full_name.toLowerCase().includes(searchTerm));

                // Status filter
                let matchesFilter = true;
                if (currentFilter === 'active') {
                    matchesFilter = !user.is_blocked;
                } else if (currentFilter === 'inactive') {
                    matchesFilter = user.is_blocked;
                } else if (currentFilter === 'archived') {
                    // Archived users are already filtered by API, just show all
                    matchesFilter = true;
                } else if (currentFilter === 'completed') {
                    matchesFilter = user.total_tasks > 0 && user.completed_tasks === user.total_tasks;
                } else if (currentFilter === 'in-progress') {
                    matchesFilter = user.completed_tasks > 0 && user.completed_tasks < user.total_tasks;
                } else if (currentFilter === 'not-started') {
                    matchesFilter = user.completed_tasks === 0;
                }

                return matchesSearch && matchesFilter;
            });

            // Apply sorting
            filtered.sort((a, b) => {
                let aVal, bVal;

                switch (currentSort.field) {
                    case 'tz':
                        aVal = a.tz;
                        bVal = b.tz;
                        break;
                    case 'progress':
                        aVal = a.total_tasks > 0 ? (a.completed_tasks / a.total_tasks) : 0;
                        bVal = b.total_tasks > 0 ? (b.completed_tasks / b.total_tasks) : 0;
                        break;
                    case 'last_login':
                        aVal = a.last_login ? new Date(a.last_login).getTime() : 0;
                        bVal = b.last_login ? new Date(b.last_login).getTime() : 0;
                        break;
                    case 'tasks':
                        aVal = a.total_tasks;
                        bVal = b.total_tasks;
                        break;
                    case 'id':
                    default:
                        aVal = a.id;
                        bVal = b.id;
                        break;
                }

                if (currentSort.direction === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
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
                const displayName = user.full_name || user.tz;

                return `
                    <div class="user-card ${!isActive ? 'inactive' : ''}" onclick="openUserDetail(${user.id})">
                        <!-- Batch Selection Checkbox -->
                        <input type="checkbox" class="user-card-checkbox" data-user-id="${user.id}"
                               onclick="event.stopPropagation(); batchManager.toggleItem(${user.id})">

                        <div class="user-card-header">
                            <div style="display: flex; align-items: center; flex: 1;">
                                <div class="user-avatar">${initials}</div>
                                <div class="user-info">
                                    <h3 class="user-name">${displayName}</h3>
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
                            ${user.is_archived ?
                                `<button class="user-action-btn warning" onclick="unarchiveUser(${user.id})" title="החזר מארכיון">
                                    📤
                                </button>` :
                                `<button class="user-action-btn warning" onclick="archiveUser(${user.id})" title="העבר לארכיון">
                                    📦
                                </button>`
                            }
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
            modalManager.show('הוסף תלמיד חדש', `
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
                    onclick: 'modalManager.close()'
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
                toast.warning('נא למלא מספר זיהוי');
                return;
            }

            const expectedLength = idType === 'personal_number' ? 7 : 9;
            const idTypeName = idType === 'personal_number' ? 'מספר אישי' : 'תעודת זהות';

            if (tz.length !== expectedLength || !/^\d+$/.test(tz)) {
                toast.error(`${idTypeName} חייב להיות ${expectedLength} ספרות`);
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
                    toast.success('תלמיד נוצר בהצלחה!');
                    modalManager.close();
                    loadUsers();
                } else {
                    toast.error(result.message || 'שגיאה ביצירת תלמיד');
                }
            } catch (error) {
                console.error('Error creating student:', error);
                toast.error('שגיאה ביצירת תלמיד');
            }
        }

        // Show bulk import modal
        function showBulkImportModal() {
            modalManager.show('ייבוא מרובה של תלמידים', `
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
                    onclick: 'modalManager.close()'
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
                toast.warning('נא להזין נתוני תלמידים');
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
                    toast.success(result.message);
                    if (result.errors && result.errors.length > 0) {
                        toast.warning('יש שגיאות בחלק מהשורות');
                    }
                    modalManager.close();
                    loadUsers();
                } else {
                    toast.error(result.message || 'שגיאה בייבוא תלמידים');
                }
            } catch (error) {
                console.error('Error importing students:', error);
                toast.error('שגיאה בייבוא תלמידים');
            }
        }

        // Show edit student modal
        async function showEditStudentModal(userId) {
            const user = allUsers.find(u => u.id == userId);

            if (!user) {
                toast.error('תלמיד לא נמצא');
                return;
            }

            modalManager.show('ערוך תלמיד', `
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
                    onclick: 'modalManager.close()'
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
                    toast.success('תלמיד עודכן בהצלחה!');
                    modalManager.close();
                    loadUsers();
                } else {
                    toast.error(result.message || 'שגיאה בעדכון תלמיד');
                }
            } catch (error) {
                console.error('Error updating student:', error);
                toast.error('שגיאה בעדכון תלמיד');
            }
        }

        // Toggle student status (activate/deactivate)
        async function toggleStudentStatus(userId, isBlocked) {
            const user = allUsers.find(u => u.id == userId);
            const action = isBlocked ? 'הפעלת' : 'השבתת';
            const newStatus = isBlocked ? 1 : 0;

            modalManager.confirm(
                `${action} תלמיד`,
                `האם אתה בטוח שברצונך ב${action} התלמיד ${user.tz}?`,
                async () => {
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
                            toast.success(`תלמיד ${isBlocked ? 'הופעל' : 'הושבת'} בהצלחה!`);
                            loadUsers();
                        } else {
                            toast.error(result.message || 'שגיאה בעדכון סטטוס');
                        }
                    } catch (error) {
                        console.error('Error toggling status:', error);
                        toast.error('שגיאה בעדכון סטטוס');
                    }
                }
            );
        }

        // Archive user
        async function archiveUser(userId) {
            const user = allUsers.find(u => u.id == userId);

            modalManager.confirm(
                'העבר לארכיון',
                `האם אתה בטוח שברצונך להעביר את ${user.tz} לארכיון?`,
                async () => {
                    try {
                        const response = await fetch('../api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({
                                action: 'archive_user',
                                user_id: userId
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            toast.success('התלמיד הועבר לארכיון בהצלחה!');
                            loadUsers();
                        } else {
                            toast.error(result.message || 'שגיאה בהעברה לארכיון');
                        }
                    } catch (error) {
                        console.error('Error archiving user:', error);
                        toast.error('שגיאה בהעברה לארכיון');
                    }
                }
            );
        }

        // Unarchive user
        async function unarchiveUser(userId) {
            const user = allUsers.find(u => u.id == userId);

            modalManager.confirm(
                'החזר מארכיון',
                `האם אתה בטוח שברצונך להחזיר את ${user.tz} מהארכיון?`,
                async () => {
                    try {
                        const response = await fetch('../api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({
                                action: 'unarchive_user',
                                user_id: userId
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            toast.success('התלמיד הוחזר מהארכיון בהצלחה!');
                            loadUsers();
                        } else {
                            toast.error(result.message || 'שגיאה בהחזרה מארכיון');
                        }
                    } catch (error) {
                        console.error('Error unarchiving user:', error);
                        toast.error('שגיאה בהחזרה מארכיון');
                    }
                }
            );
        }

        // Modal functions are now handled by modalManager from utils.js
    </script>

    <script src="../admin.js"></script>
    <script src="../components/mobile-menu.js"></script>
</body>
</html>
