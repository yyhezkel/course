<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>תצוגת טבלה - מערכת ניהול</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
    <?php
        $activePage = 'responses';
        $basePath = './';
        include __DIR__ . '/components/sidebar.php';
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1>📋 תצוגת טבלה</h1>
            <div class="admin-info">
                <span id="adminName">מנהל</span>
                <span class="admin-badge">Admin</span>
            </div>
        </div>

        <div class="content-area">
            <div class="error-message" id="errorMessage"></div>
            <div class="success-message" id="successMessage"></div>

            <div class="table-container">
                <div class="table-header">
                    <div class="form-selector">
                        <label for="formSelect" style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">
                            בחר טופס:
                        </label>
                        <select id="formSelect" onchange="loadTableData()">
                            <option value="">-- בחר טופס --</option>
                        </select>
                    </div>
                    <button class="export-btn" onclick="exportToExcel()" id="exportBtn" disabled>
                        <span>📥</span>
                        <span>ייצא ל-Excel</span>
                    </button>
                </div>

                <div id="loadingSpinner" class="loading" style="display: none;">
                    <div class="spinner"></div>
                    <p>טוען נתונים...</p>
                </div>

                <div id="emptyState" class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3>בחר טופס להצגת הנתונים</h3>
                    <p>הנתונים יוצגו בטבלה לאחר בחירת טופס</p>
                </div>

                <div class="data-table" id="dataTableContainer" style="display: none;">
                    <table id="dataTable">
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle

        console.log('🚀 Table View page loaded');

        // Check authentication
        function checkAuth() {
            console.log('Checking authentication...');
            const adminLoggedIn = localStorage.getItem('admin_logged_in');
            const adminId = localStorage.getItem('admin_id');

            console.log('Admin logged in:', adminLoggedIn);
            console.log('Admin ID:', adminId);

            if (!adminLoggedIn || adminLoggedIn !== 'true') {
                console.log('❌ Not authenticated, redirecting to login...');
                window.location.href = 'index.php';
                return false;
            }

            console.log('✅ Authentication check passed');
            return true;
        }

        // Load forms dropdown
        async function loadForms() {
            console.log('📋 Loading forms...');
            try {
                const response = await fetch('api.php?action=get_forms', {
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                console.log('Forms response status:', response.status);

                const data = await response.json();
                console.log('Forms data:', data);

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to load forms');
                }

                if (data.success && data.data) {
                    const select = document.getElementById('formSelect');
                    data.data.forEach(form => {
                        const option = document.createElement('option');
                        option.value = form.id;
                        option.textContent = form.title;
                        select.appendChild(option);
                        console.log(`Added form: ${form.title} (ID: ${form.id})`);
                    });
                    console.log('✅ Forms loaded successfully');
                } else {
                    throw new Error('Invalid response format');
                }
            } catch (error) {
                console.error('❌ Error loading forms:', error);
                showError('שגיאה בטעינת הטפסים: ' + error.message);
            }
        }

        // Load table data
        async function loadTableData() {
            const formId = document.getElementById('formSelect').value;
            console.log('Loading table data for form ID:', formId);

            if (!formId) {
                console.log('No form selected');
                document.getElementById('emptyState').style.display = 'block';
                document.getElementById('dataTableContainer').style.display = 'none';
                document.getElementById('exportBtn').disabled = true;
                return;
            }

            showLoading(true);
            hideError();

            try {
                const response = await fetch(`api.php?action=get_table_data&form_id=${formId}`, {
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                console.log('Table data response status:', response.status);

                const data = await response.json();
                console.log('Table data:', data);

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to load table data');
                }

                if (data.success && data.data) {
                    renderTable(data.data);
                    document.getElementById('exportBtn').disabled = false;
                    console.log('✅ Table rendered successfully');
                } else {
                    throw new Error('Invalid response format');
                }
            } catch (error) {
                console.error('❌ Error loading table data:', error);
                showError('שגיאה בטעינת הנתונים: ' + error.message);
                document.getElementById('emptyState').style.display = 'block';
                document.getElementById('dataTableContainer').style.display = 'none';
            } finally {
                showLoading(false);
            }
        }

        // Render table
        function renderTable(data) {
            console.log('Rendering table with data:', data);

            const { questions, users, answers } = data;
            const tableHead = document.getElementById('tableHead');
            const tableBody = document.getElementById('tableBody');

            // Clear existing content
            tableHead.innerHTML = '';
            tableBody.innerHTML = '';

            // Build header
            const headerRow = document.createElement('tr');
            headerRow.innerHTML = `
                <th>ת.ז</th>
                <th>שם מלא</th>
                ${questions.map(q => `<th>${q.question_text}</th>`).join('')}
            `;
            tableHead.appendChild(headerRow);

            // Build body
            users.forEach(user => {
                const row = document.createElement('tr');
                let rowHtml = `
                    <td>${user.tz}</td>
                    <td>${user.full_name || '-'}</td>
                `;

                questions.forEach(question => {
                    // API returns answers with key format "userId_questionId"
                    const key = user.id + '_' + question.id;
                    const answerObj = answers[key];
                    const answer = answerObj?.answer_value || '-';
                    rowHtml += `<td>${answer}</td>`;
                });

                row.innerHTML = rowHtml;
                tableBody.appendChild(row);
            });

            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('dataTableContainer').style.display = 'block';

            console.log(`✅ Rendered ${users.length} rows with ${questions.length} questions`);
        }

        // Export to Excel
        function exportToExcel() {
            const formId = document.getElementById('formSelect').value;
            if (!formId) {
                showError('אנא בחר טופס לייצוא');
                return;
            }

            console.log('Exporting form ID:', formId);
            showSuccess('מייצא לאקסל...');

            window.location.href = `api.php?action=export_excel&form_id=${formId}`;
        }

        // Utility functions
        function showLoading(show) {
            document.getElementById('loadingSpinner').style.display = show ? 'block' : 'none';
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }

        function hideError() {
            document.getElementById('errorMessage').style.display = 'none';
        }

        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 3000);
        }

        function logout() {
            console.log('Logging out...');
            localStorage.removeItem('admin_logged_in');
            localStorage.removeItem('admin_id');
            localStorage.removeItem('admin_username');
            window.location.href = 'index.php';
        }

        // Initialize
        console.log('Initializing table view...');
        if (checkAuth()) {
            console.log('Loading forms...');
            loadForms();

            // Set admin name
            const adminUsername = localStorage.getItem('admin_username');
            if (adminUsername) {
                document.getElementById('adminName').textContent = adminUsername;
            }
        }
    </script>

    <script src="admin.js"></script>
    <script src="components/mobile-menu.js"></script>
</body>
</html>
