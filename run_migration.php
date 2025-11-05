<?php
/**
 * Migration Runner
 * Run this file via the web browser to execute the course system migration
 */

// Execute the migration script
ob_start();
include __DIR__ . '/migrate_course_system.php';
$output = ob_get_clean();

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הרצת Migration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #6366f1;
            padding-bottom: 10px;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #6366f1;
            overflow-x: auto;
            direction: ltr;
            text-align: left;
        }
        .success {
            color: #10b981;
            font-weight: bold;
        }
        .error {
            color: #dc2626;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 הרצת Migration - מערכת ניהול קורס</h1>

        <h2>תוצאות:</h2>
        <pre><?php echo htmlspecialchars($output); ?></pre>

        <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px;">
            <strong>✅ ה-Migration הושלם!</strong><br>
            <p>כעת תוכל לגשת לניהול המשימות והקורס.</p>
            <a href="admin/course/tasks.html" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px;">
                לספריית המשימות
            </a>
        </div>
    </div>
</body>
</html>
