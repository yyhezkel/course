<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מעקב סטטוס משימות - ניהול קורס</title>
    <link rel="stylesheet" href="../admin.css">
    <style>
        .view-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0;
        }

        .view-tab {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.2s;
        }

        .view-tab:hover {
            color: #111827;
            background: #f9fafb;
        }

        .view-tab.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }

        .view-content {
            display: none;
        }

        .view-content.active {
            display: block;
        }

        /* Option A Styles */
        .task-selector-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .task-select {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
        }

        .assignments-grid {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .grid-header {
            background: #f9fafb;
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #111827;
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
            margin-top: 5px;
        }

        .assignments-table {
            width: 100%;
        }

        .assignments-table th {
            background: #f9fafb;
            padding: 12px;
            text-align: right;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }

        .assignments-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .assignments-table tr:hover {
            background: #f9fafb;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-in_progress { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-needs_review { background: #fed7aa; color: #9a3412; }
        .status-approved { background: #bbf7d0; color: #14532d; }
        .status-rejected { background: #fecaca; color: #991b1b; }
        .status-overdue { background: #fecaca; color: #991b1b; }

        .priority-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .priority-high { background: #fee2e2; color: #991b1b; }
        .priority-medium { background: #fef3c7; color: #92400e; }
        .priority-low { background: #e5e7eb; color: #374151; }

        /* Option B Styles */
        .task-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .task-row {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .task-row-header {
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }

        .task-row-header:hover {
            background: #f9fafb;
        }

        .task-info {
            flex: 1;
        }

        .task-name {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 5px;
        }

        .task-meta {
            display: flex;
            gap: 15px;
            font-size: 14px;
            color: #6b7280;
        }

        .task-stats {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .task-stat {
            text-align: center;
        }

        .task-stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
        }

        .task-stat-label {
            font-size: 12px;
            color: #6b7280;
        }

        .expand-icon {
            font-size: 20px;
            transition: transform 0.3s;
        }

        .expand-icon.expanded {
            transform: rotate(180deg);
        }

        .task-details {
            display: none;
            padding: 20px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .task-details.visible {
            display: block;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            transition: width 0.3s;
        }

        /* Option C Styles */
        .analytics-dashboard {
            display: grid;
            gap: 20px;
        }

        .analytics-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 15px;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
        }

        .summary-card.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .summary-card.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .summary-card.yellow { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .summary-card.red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

        .summary-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .filters-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            padding: 6px 12px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }

        .action-btn:hover {
            background: #1d4ed8;
        }

        .action-btn-secondary {
            background: #6b7280;
        }

        .action-btn-secondary:hover {
            background: #4b5563;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #2563eb;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .task-stats {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body class="admin-body">
    <?php
        $activePage = 'task-status';
        $basePath = '../';
        include __DIR__ . '/../components/sidebar.php';
    ?>

    <!-- Main Content -->
    <div class="admin-content">
        <div class="admin-container">
            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1>מעקב סטטוס משימות</h1>
                    <p>צפייה מפורטת בסטטוס כל משימה, מי ביצע ולמי היא הוקצתה</p>
                </div>
                <div>
                    <a href="../dashboard.php" class="btn btn-secondary">חזרה לדשבורד</a>
                </div>
            </div>

            <!-- View Tabs -->
            <div class="view-tabs">
                <button class="view-tab active" onclick="switchView('task-selector')">
                    📋 תצוגת משימה בודדת
                </button>
                <button class="view-tab" onclick="switchView('list-view')">
                    📊 תצוגת רשימה מורחבת
                </button>
                <button class="view-tab" onclick="switchView('analytics')">
                    📈 דשבורד אנליטיקה
                </button>
            </div>

            <!-- Option A: Single Task Selector View -->
            <div id="view-task-selector" class="view-content active">
                <div class="task-selector-container">
                    <label for="taskSelect" style="display: block; margin-bottom: 10px; font-weight: 600;">בחר משימה:</label>
                    <select id="taskSelect" class="task-select" onchange="loadTaskAssignments()">
                        <option value="">-- בחר משימה --</option>
                    </select>
                </div>

                <div id="taskAssignmentsContainer" style="display: none;">
                    <div class="assignments-grid">
                        <div class="grid-header">
                            <h3 id="selectedTaskTitle" style="margin: 0 0 5px 0;"></h3>
                            <p id="selectedTaskDescription" style="margin: 0; color: #6b7280;"></p>

                            <div class="stats-cards">
                                <div class="stat-card">
                                    <div class="stat-value" id="totalAssigned">0</div>
                                    <div class="stat-label">סך הכל הוקצו</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value" id="totalCompleted">0</div>
                                    <div class="stat-label">הושלמו</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value" id="totalPending">0</div>
                                    <div class="stat-label">בהמתנה</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value" id="totalOverdue">0</div>
                                    <div class="stat-label">באיחור</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value" id="avgGrade">-</div>
                                    <div class="stat-label">ממוצע ציונים</div>
                                </div>
                            </div>
                        </div>

                        <div class="filters-bar" style="padding: 15px; background: white;">
                            <input type="text" id="studentSearchA" class="filter-input" placeholder="חיפוש תלמיד..." onkeyup="filterAssignmentsA()">
                            <select id="statusFilterA" class="filter-input" onchange="filterAssignmentsA()">
                                <option value="">כל הסטטוסים</option>
                                <option value="pending">בהמתנה</option>
                                <option value="in_progress">בתהליך</option>
                                <option value="completed">הושלם</option>
                                <option value="needs_review">ממתין לבדיקה</option>
                                <option value="approved">אושר</option>
                                <option value="rejected">נדחה</option>
                            </select>
                            <select id="priorityFilterA" class="filter-input" onchange="filterAssignmentsA()">
                                <option value="">כל העדיפויות</option>
                                <option value="high">גבוהה</option>
                                <option value="medium">בינונית</option>
                                <option value="low">נמוכה</option>
                            </select>
                        </div>

                        <table class="assignments-table">
                            <thead>
                                <tr>
                                    <th>תלמיד</th>
                                    <th>סטטוס</th>
                                    <th>עדיפות</th>
                                    <th>תאריך יעד</th>
                                    <th>תאריך הגשה</th>
                                    <th>ציון</th>
                                    <th>פעולות</th>
                                </tr>
                            </thead>
                            <tbody id="assignmentsTableBody">
                                <tr>
                                    <td colspan="7" class="loading">
                                        <div class="spinner"></div>
                                        <p>טוען נתונים...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="export-buttons">
                        <button class="action-btn" onclick="exportToCSV('task-selector')">📥 ייצא ל-CSV</button>
                        <button class="action-btn action-btn-secondary" onclick="printView()">🖨️ הדפס</button>
                    </div>
                </div>
            </div>

            <!-- Option B: List View with Expandable Rows -->
            <div id="view-list-view" class="view-content">
                <div class="filters-bar">
                    <input type="text" id="taskSearchB" class="filter-input" placeholder="חיפוש משימה..." onkeyup="filterTasksB()">
                    <select id="taskTypeFilterB" class="filter-input" onchange="filterTasksB()">
                        <option value="">כל הסוגים</option>
                        <option value="assignment">מטלה</option>
                        <option value="reading">קריאה</option>
                        <option value="form">טופס</option>
                        <option value="quiz">בחן</option>
                        <option value="video">וידאו</option>
                    </select>
                </div>

                <div id="tasksList" class="task-list">
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>טוען משימות...</p>
                    </div>
                </div>

                <div class="export-buttons">
                    <button class="action-btn" onclick="exportToCSV('list-view')">📥 ייצא הכל ל-CSV</button>
                </div>
            </div>

            <!-- Option C: Analytics Dashboard -->
            <div id="view-analytics" class="view-content">
                <div class="analytics-dashboard">
                    <!-- Summary Cards -->
                    <div class="summary-grid">
                        <div class="summary-card blue">
                            <div class="summary-value" id="analyticsTotal">0</div>
                            <div class="summary-label">סך כל ההקצאות</div>
                        </div>
                        <div class="summary-card green">
                            <div class="summary-value" id="analyticsCompleted">0</div>
                            <div class="summary-label">הושלמו בהצלחה</div>
                        </div>
                        <div class="summary-card yellow">
                            <div class="summary-value" id="analyticsPending">0</div>
                            <div class="summary-label">ממתינות</div>
                        </div>
                        <div class="summary-card red">
                            <div class="summary-value" id="analyticsOverdue">0</div>
                            <div class="summary-label">באיחור</div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="analytics-section">
                        <h3 class="section-title">סטטוס משימות לפי קטגוריה</h3>
                        <div id="statusChart" class="chart-container"></div>
                    </div>

                    <div class="analytics-section">
                        <h3 class="section-title">ביצועי משימות - Top 10</h3>
                        <div id="taskPerformanceChart" class="chart-container"></div>
                    </div>

                    <div class="analytics-section">
                        <h3 class="section-title">התפלגות ציונים</h3>
                        <div id="gradeDistributionChart" class="chart-container"></div>
                    </div>

                    <div class="export-buttons">
                        <button class="action-btn" onclick="exportAnalytics()">📥 ייצא דוח מלא</button>
                        <button class="action-btn action-btn-secondary" onclick="refreshAnalytics()">🔄 רענן נתונים</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Authentication
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
                    return false;
                }

                const adminNameEl = document.querySelector('#admin-name');
                if (adminNameEl) {
                    adminNameEl.textContent = data.admin.full_name || data.admin.username;
                }
                return true;
            } catch (error) {
                console.error('Error checking auth:', error);
                window.location.href = '../index.php';
                return false;
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

        // Global data storage
        let allTasks = [];
        let allAssignments = {};
        let currentSelectedTaskId = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', async () => {
            if (await checkAuth()) {
                await loadAllData();
            }
        });

        // View switching
        function switchView(viewName) {
            // Update tabs
            document.querySelectorAll('.view-tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');

            // Update content
            document.querySelectorAll('.view-content').forEach(content => content.classList.remove('active'));
            document.getElementById(`view-${viewName}`).classList.add('active');

            // Load data for specific view if needed
            if (viewName === 'analytics') {
                renderAnalytics();
            }
        }

        // Load all data
        async function loadAllData() {
            try {
                // Load tasks
                const tasksResponse = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'get_all_tasks' })
                });

                const tasksData = await tasksResponse.json();
                if (tasksData.success) {
                    allTasks = tasksData.tasks;
                    populateTaskSelector();
                    renderListView();
                }

                // Load all assignments for analytics
                await loadAllAssignments();
            } catch (error) {
                console.error('Error loading data:', error);
            }
        }

        // Populate task selector (Option A)
        function populateTaskSelector() {
            const select = document.getElementById('taskSelect');
            select.innerHTML = '<option value="">-- בחר משימה --</option>';

            allTasks.forEach(task => {
                const option = document.createElement('option');
                option.value = task.id;
                option.textContent = `${task.title} (${task.assigned_count || 0} תלמידים)`;
                select.appendChild(option);
            });
        }

        // Load task assignments (Option A)
        async function loadTaskAssignments() {
            const taskId = document.getElementById('taskSelect').value;
            if (!taskId) {
                document.getElementById('taskAssignmentsContainer').style.display = 'none';
                return;
            }

            currentSelectedTaskId = taskId;
            const selectedTask = allTasks.find(t => t.id == taskId);

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'get_task_assignments',
                        task_id: taskId
                    })
                });

                const data = await response.json();
                if (data.success) {
                    allAssignments[taskId] = data.assignments;
                    renderTaskAssignments(selectedTask, data.assignments);
                    document.getElementById('taskAssignmentsContainer').style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading assignments:', error);
            }
        }

        // Render task assignments (Option A)
        function renderTaskAssignments(task, assignments) {
            // Update header
            document.getElementById('selectedTaskTitle').textContent = task.title;
            document.getElementById('selectedTaskDescription').textContent = task.description || '';

            // Calculate statistics
            const stats = calculateStats(assignments);
            document.getElementById('totalAssigned').textContent = stats.total;
            document.getElementById('totalCompleted').textContent = stats.completed;
            document.getElementById('totalPending').textContent = stats.pending;
            document.getElementById('totalOverdue').textContent = stats.overdue;
            document.getElementById('avgGrade').textContent = stats.avgGrade !== null ? `${stats.avgGrade}%` : '-';

            // Render table
            const tbody = document.getElementById('assignmentsTableBody');

            if (assignments.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">אין הקצאות למשימה זו</td></tr>';
                return;
            }

            tbody.innerHTML = assignments.map(a => {
                const isOverdue = a.due_date && new Date(a.due_date) < new Date() && a.status !== 'completed' && a.status !== 'approved';
                const statusClass = isOverdue ? 'status-overdue' : `status-${a.status}`;
                const statusText = isOverdue ? 'באיחור' : getStatusText(a.status);

                return `
                    <tr data-status="${a.status}" data-priority="${a.priority || 'medium'}" data-student="${a.full_name.toLowerCase()}">
                        <td>
                            <strong>${a.full_name}</strong><br>
                            <small style="color: #6b7280;">${a.email}</small>
                        </td>
                        <td>
                            <span class="status-badge ${statusClass}">${statusText}</span>
                        </td>
                        <td>
                            <span class="priority-badge priority-${a.priority || 'medium'}">${getPriorityText(a.priority)}</span>
                        </td>
                        <td>${a.due_date ? formatDate(a.due_date) : '-'}</td>
                        <td>${a.submitted_at ? formatDate(a.submitted_at) : '-'}</td>
                        <td>${a.grade !== null ? `${a.grade}%` : '-'}</td>
                        <td>
                            <button class="action-btn" onclick="viewUserDetail(${a.user_id})">צפה</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Calculate statistics
        function calculateStats(assignments) {
            const total = assignments.length;
            const completed = assignments.filter(a => a.status === 'completed' || a.status === 'approved').length;
            const pending = assignments.filter(a => a.status === 'pending').length;
            const overdue = assignments.filter(a => {
                return a.due_date && new Date(a.due_date) < new Date() && a.status !== 'completed' && a.status !== 'approved';
            }).length;

            const gradesAvailable = assignments.filter(a => a.grade !== null);
            const avgGrade = gradesAvailable.length > 0
                ? Math.round(gradesAvailable.reduce((sum, a) => sum + parseFloat(a.grade), 0) / gradesAvailable.length)
                : null;

            return { total, completed, pending, overdue, avgGrade };
        }

        // Filter assignments (Option A)
        function filterAssignmentsA() {
            const search = document.getElementById('studentSearchA').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilterA').value;
            const priorityFilter = document.getElementById('priorityFilterA').value;

            const rows = document.querySelectorAll('#assignmentsTableBody tr');
            rows.forEach(row => {
                const studentName = row.dataset.student || '';
                const status = row.dataset.status || '';
                const priority = row.dataset.priority || '';

                const matchesSearch = studentName.includes(search);
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesPriority = !priorityFilter || priority === priorityFilter;

                row.style.display = (matchesSearch && matchesStatus && matchesPriority) ? '' : 'none';
            });
        }

        // Load all assignments for all tasks
        async function loadAllAssignments() {
            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'get_all_task_assignments' })
                });

                const data = await response.json();
                if (data.success) {
                    // Group by task_id
                    allTasks.forEach(task => {
                        allAssignments[task.id] = data.assignments.filter(a => a.task_id == task.id);
                    });
                }
            } catch (error) {
                console.error('Error loading all assignments:', error);
            }
        }

        // Render list view (Option B)
        function renderListView() {
            const container = document.getElementById('tasksList');

            if (allTasks.length === 0) {
                container.innerHTML = '<div class="no-data">אין משימות במערכת</div>';
                return;
            }

            container.innerHTML = allTasks.map(task => {
                const assignments = allAssignments[task.id] || [];
                const stats = calculateStats(assignments);
                const completionRate = stats.total > 0 ? Math.round((stats.completed / stats.total) * 100) : 0;

                return `
                    <div class="task-row" data-task-type="${task.task_type}" data-task-name="${task.title.toLowerCase()}">
                        <div class="task-row-header" onclick="toggleTaskDetails(${task.id})">
                            <div class="task-info">
                                <div class="task-name">${task.title}</div>
                                <div class="task-meta">
                                    <span>📝 ${getTaskTypeText(task.task_type)}</span>
                                    <span>⭐ ${task.points || 0} נקודות</span>
                                    <span>👥 ${stats.total} תלמידים</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: ${completionRate}%"></div>
                                </div>
                            </div>
                            <div class="task-stats">
                                <div class="task-stat">
                                    <div class="task-stat-value">${completionRate}%</div>
                                    <div class="task-stat-label">הושלם</div>
                                </div>
                                <div class="task-stat">
                                    <div class="task-stat-value">${stats.completed}/${stats.total}</div>
                                    <div class="task-stat-label">תלמידים</div>
                                </div>
                                <span class="expand-icon" id="expand-icon-${task.id}">▼</span>
                            </div>
                        </div>
                        <div class="task-details" id="task-details-${task.id}">
                            <table class="assignments-table">
                                <thead>
                                    <tr>
                                        <th>תלמיד</th>
                                        <th>סטטוס</th>
                                        <th>עדיפות</th>
                                        <th>תאריך יעד</th>
                                        <th>ציון</th>
                                        <th>פעולות</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${assignments.length === 0 ? '<tr><td colspan="6" class="no-data">אין הקצאות</td></tr>' :
                                    assignments.map(a => {
                                        const isOverdue = a.due_date && new Date(a.due_date) < new Date() && a.status !== 'completed' && a.status !== 'approved';
                                        const statusClass = isOverdue ? 'status-overdue' : `status-${a.status}`;
                                        const statusText = isOverdue ? 'באיחור' : getStatusText(a.status);

                                        return `
                                            <tr>
                                                <td>${a.full_name}</td>
                                                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                                                <td><span class="priority-badge priority-${a.priority || 'medium'}">${getPriorityText(a.priority)}</span></td>
                                                <td>${a.due_date ? formatDate(a.due_date) : '-'}</td>
                                                <td>${a.grade !== null ? `${a.grade}%` : '-'}</td>
                                                <td><button class="action-btn" onclick="viewUserDetail(${a.user_id})">צפה</button></td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Toggle task details (Option B)
        function toggleTaskDetails(taskId) {
            const details = document.getElementById(`task-details-${taskId}`);
            const icon = document.getElementById(`expand-icon-${taskId}`);

            details.classList.toggle('visible');
            icon.classList.toggle('expanded');
        }

        // Filter tasks (Option B)
        function filterTasksB() {
            const search = document.getElementById('taskSearchB').value.toLowerCase();
            const typeFilter = document.getElementById('taskTypeFilterB').value;

            const rows = document.querySelectorAll('.task-row');
            rows.forEach(row => {
                const taskName = row.dataset.taskName || '';
                const taskType = row.dataset.taskType || '';

                const matchesSearch = taskName.includes(search);
                const matchesType = !typeFilter || taskType === typeFilter;

                row.style.display = (matchesSearch && matchesType) ? '' : 'none';
            });
        }

        // Render analytics (Option C)
        function renderAnalytics() {
            // Calculate overall statistics
            let totalAssignments = 0;
            let completedAssignments = 0;
            let pendingAssignments = 0;
            let overdueAssignments = 0;

            Object.values(allAssignments).forEach(assignments => {
                const stats = calculateStats(assignments);
                totalAssignments += stats.total;
                completedAssignments += stats.completed;
                pendingAssignments += stats.pending;
                overdueAssignments += stats.overdue;
            });

            document.getElementById('analyticsTotal').textContent = totalAssignments;
            document.getElementById('analyticsCompleted').textContent = completedAssignments;
            document.getElementById('analyticsPending').textContent = pendingAssignments;
            document.getElementById('analyticsOverdue').textContent = overdueAssignments;

            // Render simple charts
            renderStatusChart();
            renderTaskPerformanceChart();
            renderGradeDistributionChart();
        }

        // Simple bar chart for status distribution
        function renderStatusChart() {
            const statusCounts = {};
            Object.values(allAssignments).forEach(assignments => {
                assignments.forEach(a => {
                    statusCounts[a.status] = (statusCounts[a.status] || 0) + 1;
                });
            });

            const chartHtml = Object.entries(statusCounts).map(([status, count]) => {
                const percentage = (count / Object.values(statusCounts).reduce((a, b) => a + b, 0)) * 100;
                return `
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>${getStatusText(status)}</span>
                            <span><strong>${count}</strong> (${percentage.toFixed(1)}%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${percentage}%"></div>
                        </div>
                    </div>
                `;
            }).join('');

            document.getElementById('statusChart').innerHTML = chartHtml || '<div class="no-data">אין נתונים</div>';
        }

        // Task performance chart
        function renderTaskPerformanceChart() {
            const taskStats = allTasks.map(task => {
                const assignments = allAssignments[task.id] || [];
                const stats = calculateStats(assignments);
                const completionRate = stats.total > 0 ? (stats.completed / stats.total) * 100 : 0;

                return {
                    name: task.title,
                    rate: completionRate,
                    completed: stats.completed,
                    total: stats.total
                };
            }).sort((a, b) => b.rate - a.rate).slice(0, 10);

            const chartHtml = taskStats.map(task => `
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>${task.name}</span>
                        <span><strong>${task.completed}/${task.total}</strong> (${task.rate.toFixed(1)}%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${task.rate}%"></div>
                    </div>
                </div>
            `).join('');

            document.getElementById('taskPerformanceChart').innerHTML = chartHtml || '<div class="no-data">אין נתונים</div>';
        }

        // Grade distribution chart
        function renderGradeDistributionChart() {
            const gradeBuckets = { '0-20': 0, '21-40': 0, '41-60': 0, '61-80': 0, '81-100': 0 };

            Object.values(allAssignments).forEach(assignments => {
                assignments.forEach(a => {
                    if (a.grade !== null) {
                        const grade = parseFloat(a.grade);
                        if (grade <= 20) gradeBuckets['0-20']++;
                        else if (grade <= 40) gradeBuckets['21-40']++;
                        else if (grade <= 60) gradeBuckets['41-60']++;
                        else if (grade <= 80) gradeBuckets['61-80']++;
                        else gradeBuckets['81-100']++;
                    }
                });
            });

            const total = Object.values(gradeBuckets).reduce((a, b) => a + b, 0);
            const chartHtml = Object.entries(gradeBuckets).map(([range, count]) => {
                const percentage = total > 0 ? (count / total) * 100 : 0;
                return `
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>${range}%</span>
                            <span><strong>${count}</strong> (${percentage.toFixed(1)}%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${percentage}%"></div>
                        </div>
                    </div>
                `;
            }).join('');

            document.getElementById('gradeDistributionChart').innerHTML = chartHtml || '<div class="no-data">אין ציונים</div>';
        }

        // Export to CSV
        function exportToCSV(viewType) {
            let csvContent = '';
            let filename = '';

            if (viewType === 'task-selector' && currentSelectedTaskId) {
                const task = allTasks.find(t => t.id == currentSelectedTaskId);
                const assignments = allAssignments[currentSelectedTaskId] || [];

                csvContent = 'תלמיד,אימייל,סטטוס,עדיפות,תאריך יעד,תאריך הגשה,ציון\n';
                assignments.forEach(a => {
                    csvContent += `"${a.full_name}","${a.email}","${getStatusText(a.status)}","${getPriorityText(a.priority)}","${a.due_date || ''}","${a.submitted_at || ''}","${a.grade !== null ? a.grade + '%' : ''}"\n`;
                });
                filename = `task_${task.title}_${new Date().toISOString().split('T')[0]}.csv`;
            } else if (viewType === 'list-view') {
                csvContent = 'משימה,תלמיד,אימייל,סטטוס,עדיפות,תאריך יעד,ציון\n';
                allTasks.forEach(task => {
                    const assignments = allAssignments[task.id] || [];
                    assignments.forEach(a => {
                        csvContent += `"${task.title}","${a.full_name}","${a.email}","${getStatusText(a.status)}","${getPriorityText(a.priority)}","${a.due_date || ''}","${a.grade !== null ? a.grade + '%' : ''}"\n`;
                    });
                });
                filename = `all_tasks_${new Date().toISOString().split('T')[0]}.csv`;
            }

            downloadCSV(csvContent, filename);
        }

        // Export analytics
        function exportAnalytics() {
            let csvContent = 'משימה,סך הכל,הושלמו,בהמתנה,באיחור,אחוז השלמה,ממוצע ציונים\n';

            allTasks.forEach(task => {
                const assignments = allAssignments[task.id] || [];
                const stats = calculateStats(assignments);
                const completionRate = stats.total > 0 ? ((stats.completed / stats.total) * 100).toFixed(1) : 0;

                csvContent += `"${task.title}",${stats.total},${stats.completed},${stats.pending},${stats.overdue},${completionRate}%,${stats.avgGrade !== null ? stats.avgGrade + '%' : 'N/A'}\n`;
            });

            downloadCSV(csvContent, `analytics_${new Date().toISOString().split('T')[0]}.csv`);
        }

        // Download CSV helper
        function downloadCSV(content, filename) {
            const BOM = '\uFEFF';
            const blob = new Blob([BOM + content], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.click();
        }

        // Print view
        function printView() {
            window.print();
        }

        // Refresh analytics
        async function refreshAnalytics() {
            await loadAllData();
            renderAnalytics();
        }

        // Navigate to user detail
        function viewUserDetail(userId) {
            window.location.href = `user-detail.php?id=${userId}`;
        }

        // Helper functions
        function getStatusText(status) {
            const statuses = {
                'pending': 'בהמתנה',
                'in_progress': 'בתהליך',
                'completed': 'הושלם',
                'needs_review': 'ממתין לבדיקה',
                'checking': 'בבדיקה',
                'approved': 'אושר',
                'rejected': 'נדחה',
                'returned': 'הוחזר לתיקון'
            };
            return statuses[status] || status;
        }

        function getPriorityText(priority) {
            const priorities = {
                'high': 'גבוהה',
                'medium': 'בינונית',
                'low': 'נמוכה'
            };
            return priorities[priority] || 'בינונית';
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

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('he-IL');
        }
    </script>

    <script src="../admin.js"></script>
    <script src="../components/mobile-menu.js"></script>
</body>
</html>
