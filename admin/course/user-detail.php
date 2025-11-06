<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>פרטי תלמיד</title>
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
            <a href="index.php" class="back-link">← חזרה לרשימת התלמידים</a>

        <!-- Loading State -->
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p>טוען נתונים...</p>
        </div>

        <!-- User Detail Content -->
        <div id="userContent" style="display: none;">
            <!-- User Header -->
            <div class="user-header-card">
                <div class="user-header-content">
                    <div style="display: flex; align-items: center; flex: 1;">
                        <div class="user-avatar-large" id="userAvatar">??</div>
                        <div class="user-header-info">
                            <h1 id="userName">תלמיד</h1>
                            <div class="user-header-meta">
                                <span id="userTz">ת.ז.: -</span>
                                <span id="userLastLogin">כניסה אחרונה: -</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-white" onclick="assignNewTask()">הוספת משימה חדשה</button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" id="totalTasks">0</div>
                    <div class="stat-label">סך המשימות</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="completedTasks">0</div>
                    <div class="stat-label">הושלמו</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="pendingTasks">0</div>
                    <div class="stat-label">ממתינות</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="reviewTasks">0</div>
                    <div class="stat-label">לבדיקה</div>
                </div>
            </div>

            <!-- Progress Overview -->
            <div class="progress-overview">
                <h2>התקדמות כללית</h2>
                <div class="progress-bar-large">
                    <div class="progress-bar-fill-large" id="progressBar" style="width: 0%">0%</div>
                </div>
            </div>

            <!-- Tasks Section -->
            <div class="tasks-section">
                <h2>
                    <span>משימות התלמיד</span>
                    <button class="btn btn-white" onclick="assignNewTask()">+ הוספת משימה</button>
                </h2>
                <div id="tasksList"></div>
            </div>
        </div>
        </div>
    </div>

    <!-- Task Preview Modal -->
    <div id="taskPreviewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
        <div style="max-width: 900px; margin: 40px auto; background: white; border-radius: 12px; padding: 30px; position: relative;">
            <button onclick="closeTaskPreview()" style="position: absolute; left: 20px; top: 20px; background: #ef4444; color: white; border: none; border-radius: 6px; padding: 8px 16px; cursor: pointer;">✕ סגור</button>

            <div id="taskPreviewContent"></div>
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

        let userId = null;
        let userData = null;
        let userTasks = [];

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();

            const urlParams = new URLSearchParams(window.location.search);
            userId = urlParams.get('id');

            if (!userId) {
                alert('חסר מזהה משתמש');
                window.location.href = 'index.php';
                return;
            }

            loadUserData();
        });

        async function loadUserData() {
            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'get_user_detail',
                        user_id: userId
                    })
                });

                if (!response.ok) {
                    throw new Error('Failed to load user data');
                }

                const data = await response.json();

                if (data.success) {
                    userData = data.user;
                    userTasks = data.tasks;
                    renderUserDetail();
                } else {
                    throw new Error(data.message || 'Failed to load user data');
                }
            } catch (error) {
                console.error('Error loading user data:', error);
                document.getElementById('loading').innerHTML = `
                    <p style="color: red;">שגיאה בטעינת הנתונים</p>
                    <p>${error.message}</p>
                `;
            }
        }

        function renderUserDetail() {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('userContent').style.display = 'block';

            // User header
            const initials = userData.tz ? userData.tz.substring(0, 2) : '??';
            document.getElementById('userAvatar').textContent = initials;
            document.getElementById('userName').textContent = userData.tz || 'תלמיד';
            document.getElementById('userTz').textContent = `ת.ז.: ${userData.tz}`;
            if (userData.last_login) {
                const lastLogin = new Date(userData.last_login).toLocaleDateString('he-IL');
                document.getElementById('userLastLogin').textContent = `כניסה אחרונה: ${lastLogin}`;
            }

            // Stats
            const total = userTasks.length;
            const completed = userTasks.filter(t => t.status === 'completed' || t.status === 'approved').length;
            const pending = userTasks.filter(t => t.status === 'pending').length;
            const review = userTasks.filter(t => t.status === 'needs_review').length;
            const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;

            document.getElementById('totalTasks').textContent = total;
            document.getElementById('completedTasks').textContent = completed;
            document.getElementById('pendingTasks').textContent = pending;
            document.getElementById('reviewTasks').textContent = review;

            // Progress bar
            const progressBar = document.getElementById('progressBar');
            progressBar.style.width = percentage + '%';
            progressBar.textContent = percentage + '%';

            // Render tasks
            renderTasks();
        }

        function renderTasks() {
            const tasksList = document.getElementById('tasksList');

            if (userTasks.length === 0) {
                tasksList.innerHTML = `
                    <div class="no-tasks">
                        <p>אין משימות שהוקצו לתלמיד זה</p>
                        <button class="btn btn-primary" onclick="assignNewTask()">הוספת משימה ראשונה</button>
                    </div>
                `;
                return;
            }

            // Sort: needs_review first, then by sequence
            const sortedTasks = [...userTasks].sort((a, b) => {
                if (a.status === 'needs_review' && b.status !== 'needs_review') return -1;
                if (a.status !== 'needs_review' && b.status === 'needs_review') return 1;
                return (a.sequence_order || 0) - (b.sequence_order || 0);
            });

            tasksList.innerHTML = sortedTasks.map(task => {
                const statusText = getStatusText(task.status);
                const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString('he-IL') : null;
                const needsReview = task.status === 'needs_review';
                const hasGrade = task.grade !== null && task.grade !== undefined;
                const hasFeedback = task.feedback && task.feedback.trim();
                const hasSubmissions = task.submissions && task.submissions.length > 0;
                const submissionCount = task.submissions ? task.submissions.length : 0;

                return `
                    <div class="task-item ${task.status.replace('_', '-')}">
                        <div class="task-item-header">
                            <span class="task-item-title">${task.title}</span>
                            <span class="task-status-badge ${task.status.replace('_', '-')}">${statusText}</span>
                        </div>
                        ${task.description ? `<p style="font-size: 13px; color: #666; margin: 10px 0;">${task.description}</p>` : ''}
                        <div class="task-item-meta">
                            ${task.estimated_duration ? `<span>⏱️ ${task.estimated_duration} דקות</span>` : ''}
                            ${task.points ? `<span>⭐ ${task.points} נקודות</span>` : ''}
                            ${dueDate ? `<span>📅 ${dueDate}</span>` : ''}
                            ${hasGrade ? `<span style="font-weight: bold; color: #2563eb;">📊 ציון: ${task.grade}%</span>` : ''}
                            ${hasSubmissions ? `<span style="font-weight: bold; color: #059669;">📎 ${submissionCount} קבצים</span>` : ''}
                        </div>
                        ${hasFeedback ? `
                            <div style="background: #f3f4f6; padding: 10px; border-radius: 6px; margin: 10px 0; border-right: 3px solid #2563eb;">
                                <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">משוב מהמנחה:</div>
                                <div style="font-size: 13px; color: #374151;">${task.feedback}</div>
                            </div>
                        ` : ''}
                        ${hasSubmissions ? `
                            <div style="background: #f0fdf4; padding: 10px; border-radius: 6px; margin: 10px 0; border-right: 3px solid #059669;">
                                <div style="font-size: 12px; color: #065f46; margin-bottom: 6px; font-weight: 500;">📎 קבצים שהועלו:</div>
                                ${task.submissions.map(sub => `
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin: 4px 0; padding: 6px; background: white; border-radius: 4px;">
                                        <span style="font-size: 13px; color: #374151;">${sub.original_filename}</span>
                                        <a href="${sub.filepath}" target="_blank" style="color: #2563eb; text-decoration: none; font-size: 12px;">📥 הורדה</a>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                        <div class="task-actions">
                            <button class="task-action-btn view" onclick="previewTask(${task.id})" style="background: #2563eb;">👁️ צפייה מלאה</button>
                            ${needsReview || task.status === 'checking' ? `
                                <button class="task-action-btn approve" onclick="toggleReviewSection(${task.id}, 'approve')">✓ אישור</button>
                                <button class="task-action-btn" style="background: #f59e0b;" onclick="toggleReviewSection(${task.id}, 'return')">↩️ החזרה</button>
                                <button class="task-action-btn reject" onclick="toggleReviewSection(${task.id}, 'reject')">✗ דחייה</button>
                            ` : ''}
                            ${task.status === 'needs_review' ? `
                                <button class="task-action-btn" style="background: #c4a040;" onclick="setTaskChecking(${task.id})">🔍 סמן בבדיקה</button>
                            ` : ''}
                            <button class="task-action-btn view" onclick="viewTaskResponses(${task.id})">📋 תשובות טופס</button>
                            <button class="task-action-btn" style="background: #f59e0b;" onclick="resetTask(${task.id})">🔄 איפוס</button>
                            <button class="task-action-btn" style="background: #ef4444;" onclick="removeTask(${task.id})">🗑️ הסרה</button>
                        </div>
                        <div class="review-section" id="review-${task.id}">
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 5px;">
                                    ציון (0-100%):
                                </label>
                                <input
                                    type="number"
                                    id="review-grade-${task.id}"
                                    min="0"
                                    max="100"
                                    placeholder="הזן ציון (אופציונלי)"
                                    style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                                />
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 5px;">
                                    משוב/הסבר:
                                </label>
                                <textarea
                                    id="review-feedback-${task.id}"
                                    placeholder="הוסף משוב או הסבר לתלמיד..."
                                    rows="3"
                                    style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                                ></textarea>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 5px;">
                                    הערות פנימיות (לא נשלחות לתלמיד):
                                </label>
                                <textarea
                                    id="review-notes-${task.id}"
                                    placeholder="הערות פנימיות למנחה..."
                                    rows="2"
                                    style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                                ></textarea>
                            </div>
                            <div class="review-actions" id="review-actions-${task.id}">
                                <button class="task-action-btn approve" onclick="submitReview(${task.id}, 'approved')">✓ שלח אישור</button>
                                <button class="task-action-btn" style="background: #f59e0b;" onclick="submitReview(${task.id}, 'returned')">↩️ שלח החזרה</button>
                                <button class="task-action-btn reject" onclick="submitReview(${task.id}, 'rejected')">✗ שלח דחייה</button>
                                <button class="task-action-btn view" onclick="toggleReviewSection(${task.id})">ביטול</button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function toggleReviewSection(taskId, action) {
            const section = document.getElementById(`review-${taskId}`);
            section.classList.toggle('active');
        }

        async function submitReview(userTaskId, newStatus) {
            const reviewNotes = document.getElementById(`review-notes-${userTaskId}`).value;
            const feedback = document.getElementById(`review-feedback-${userTaskId}`).value;
            const gradeInput = document.getElementById(`review-grade-${userTaskId}`);
            const grade = gradeInput.value ? parseFloat(gradeInput.value) : null;

            // Validate grade if provided
            if (grade !== null && (grade < 0 || grade > 100)) {
                alert('הציון חייב להיות בין 0 ל-100');
                return;
            }

            if (newStatus === 'rejected' && !feedback.trim()) {
                alert('אנא הוסף משוב לדחייה');
                return;
            }

            const confirmMessages = {
                'approved': 'לאשר',
                'rejected': 'לדחות',
                'returned': 'להחזיר לתלמיד'
            };

            if (!confirm(`האם אתה בטוח שברצונך ${confirmMessages[newStatus] || 'לעדכן'} את המשימה?`)) {
                return;
            }

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'review_task',
                        user_task_id: userTaskId,
                        status: newStatus,
                        review_notes: reviewNotes,
                        grade: grade,
                        feedback: feedback
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert('הסטטוס עודכן בהצלחה');
                    await loadUserData(); // Reload data
                } else {
                    alert('שגיאה: ' + data.message);
                }
            } catch (error) {
                console.error('Error reviewing task:', error);
                alert('שגיאה בעדכון הסטטוס');
            }
        }

        async function setTaskChecking(userTaskId) {
            if (!confirm('האם לסמן את המשימה כ"בבדיקה"?')) {
                return;
            }

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'review_task',
                        user_task_id: userTaskId,
                        status: 'checking'
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert('המשימה סומנה כ"בבדיקה"');
                    await loadUserData();
                } else {
                    alert('שגיאה: ' + data.message);
                }
            } catch (error) {
                console.error('Error setting task as checking:', error);
                alert('שגיאה בעדכון הסטטוס');
            }
        }

        function previewTask(taskId) {
            const task = userTasks.find(t => t.id === taskId);
            if (!task) {
                alert('משימה לא נמצאה');
                return;
            }

            const hasSubmissions = task.submissions && task.submissions.length > 0;
            const hasGrade = task.grade !== null && task.grade !== undefined;
            const hasFeedback = task.feedback && task.feedback.trim();

            const modalContent = `
                <h2 style="margin-top: 0; margin-bottom: 20px; color: #1f2937;">${task.title}</h2>

                <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">סטטוס</div>
                            <div style="font-size: 14px; font-weight: 500; color: #1f2937;">${getStatusText(task.status)}</div>
                        </div>
                        ${task.points ? `
                            <div>
                                <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">נקודות</div>
                                <div style="font-size: 14px; font-weight: 500; color: #1f2937;">⭐ ${task.points}</div>
                            </div>
                        ` : ''}
                        ${hasGrade ? `
                            <div>
                                <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">ציון</div>
                                <div style="font-size: 14px; font-weight: 500; color: #2563eb;">📊 ${task.grade}%</div>
                            </div>
                        ` : ''}
                        ${task.estimated_duration ? `
                            <div>
                                <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">זמן משוער</div>
                                <div style="font-size: 14px; font-weight: 500; color: #1f2937;">⏱️ ${task.estimated_duration} דקות</div>
                            </div>
                        ` : ''}
                    </div>
                </div>

                ${task.description ? `
                    <div style="margin-bottom: 20px;">
                        <h3 style="font-size: 16px; color: #1f2937; margin-bottom: 10px;">תיאור המשימה</h3>
                        <p style="color: #4b5563; line-height: 1.6;">${task.description}</p>
                    </div>
                ` : ''}

                ${hasFeedback ? `
                    <div style="background: #eff6ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-right: 4px solid #2563eb;">
                        <h3 style="font-size: 16px; color: #1e40af; margin: 0 0 10px 0;">משוב מהמנחה</h3>
                        <p style="color: #1e3a8a; line-height: 1.6; margin: 0;">${task.feedback}</p>
                    </div>
                ` : ''}

                ${hasSubmissions ? `
                    <div style="margin-bottom: 20px;">
                        <h3 style="font-size: 16px; color: #1f2937; margin-bottom: 10px;">📎 קבצים שהועלו (${task.submissions.length})</h3>
                        <div style="background: #f0fdf4; padding: 15px; border-radius: 8px; border-right: 4px solid #059669;">
                            ${task.submissions.map(sub => {
                                const uploadDate = new Date(sub.uploaded_at).toLocaleString('he-IL');
                                const fileSize = (sub.filesize / 1024).toFixed(2);
                                return `
                                    <div style="background: white; padding: 12px; border-radius: 6px; margin-bottom: 10px; border: 1px solid #d1fae5;">
                                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                            <div style="flex: 1;">
                                                <div style="font-weight: 500; color: #065f46; margin-bottom: 4px;">${sub.original_filename}</div>
                                                <div style="font-size: 12px; color: #059669;">
                                                    ${fileSize} KB • הועלה ב-${uploadDate}
                                                </div>
                                                ${sub.description ? `<div style="font-size: 13px; color: #4b5563; margin-top: 4px;">${sub.description}</div>` : ''}
                                            </div>
                                            <a href="${sub.filepath}" target="_blank" download style="background: #059669; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; white-space: nowrap; margin-right: 10px;">
                                                📥 הורדה
                                            </a>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                ` : '<div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; color: #92400e;">אין קבצים שהועלו למשימה זו</div>'}

                ${task.submission_text ? `
                    <div style="margin-bottom: 20px;">
                        <h3 style="font-size: 16px; color: #1f2937; margin-bottom: 10px;">תשובה טקסטואלית</h3>
                        <div style="background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                            <p style="color: #4b5563; line-height: 1.6; white-space: pre-wrap;">${task.submission_text}</p>
                        </div>
                    </div>
                ` : ''}
            `;

            document.getElementById('taskPreviewContent').innerHTML = modalContent;
            document.getElementById('taskPreviewModal').style.display = 'block';
        }

        function closeTaskPreview() {
            document.getElementById('taskPreviewModal').style.display = 'none';
        }

        async function resetTask(userTaskId) {
            if (!confirm('האם אתה בטוח שברצונך לאפס את המשימה? כל ההתקדמות והציונים יימחקו.')) {
                return;
            }

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'reset_user_task',
                        user_task_id: userTaskId
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert('המשימה אופסה בהצלחה');
                    await loadUserData(); // Reload data
                } else {
                    alert('שגיאה: ' + data.message);
                }
            } catch (error) {
                console.error('Error resetting task:', error);
                alert('שגיאה באיפוס המשימה');
            }
        }

        async function removeTask(userTaskId) {
            if (!confirm('האם אתה בטוח שברצונך להסיר את המשימה לגמרי? פעולה זו לא ניתנת לביטול!')) {
                return;
            }

            try {
                const response = await fetch('../api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'remove_user_task',
                        user_task_id: userTaskId
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert('המשימה הוסרה בהצלחה');
                    await loadUserData(); // Reload data
                } else {
                    alert('שגיאה: ' + data.message);
                }
            } catch (error) {
                console.error('Error removing task:', error);
                alert('שגיאה בהסרת המשימה');
            }
        }

        function viewTaskResponses(userTaskId) {
            // Find the task to get form_id if exists
            const task = userTasks.find(t => t.id === userTaskId);
            if (task && task.form_id) {
                window.open(`../responses/index.php?user_id=${userId}&form_id=${task.form_id}`, '_blank');
            } else {
                alert('אין טופס מקושר למשימה זו');
            }
        }

        function assignNewTask() {
            window.location.href = `assign.html?user_id=${userId}`;
        }

        function getStatusText(status) {
            const statusMap = {
                'pending': 'ממתינה',
                'in_progress': 'בתהליך',
                'completed': 'הושלמה',
                'needs_review': 'לבדיקה',
                'checking': 'בבדיקה',
                'approved': 'אושרה',
                'rejected': 'נדחתה',
                'returned': 'הוחזרה לתלמיד'
            };
            return statusMap[status] || status;
        }
    </script>

    <script src="../admin.js"></script>
    <script src="../components/mobile-menu.js"></script>
</body>
</html>
