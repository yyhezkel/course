<?php
/**
 * Export User Responses as PDF
 * Simple PDF generation without external libraries
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

// Require authentication
requireAuth();

$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    die('User ID required');
}

$db = getDbConnection();

// Get user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('User not found');
}

// Get all responses with categories
$stmt = $db->prepare("
    SELECT
        fr.*,
        q.question_text,
        qt.type_name,
        c.name as category_name
    FROM form_responses fr
    JOIN questions q ON fr.question_id = q.id
    JOIN question_types qt ON q.question_type_id = qt.id
    LEFT JOIN form_questions fq ON q.id = fq.question_id AND fq.form_id = 1
    LEFT JOIN categories c ON fq.category_id = c.id
    WHERE fr.user_id = ?
    ORDER BY c.sequence_order ASC, fq.sequence_order ASC
");
$stmt->execute([$userId]);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="submission_' . $user['tz'] . '_' . date('Y-m-d') . '.pdf"');

// Generate PDF using HTML + wkhtmltopdf or basic approach
// For now, we'll use a simple HTML to PDF conversion approach

// Since we don't have PDF libraries, let's create an HTML version that browsers can print to PDF
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>תשובות משתמש - <?php echo htmlspecialchars($user['tz']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }

        body {
            font-family: 'Arial', sans-serif;
            direction: rtl;
            color: #333;
            line-height: 1.6;
        }

        .header {
            border-bottom: 3px solid #4A90E2;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #4A90E2;
            margin: 0 0 10px 0;
        }

        .user-info {
            background: #f5f7fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .user-info p {
            margin: 5px 0;
        }

        .category-section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }

        .category-title {
            background: #4A90E2;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 15px;
        }

        .question-answer {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            page-break-inside: avoid;
        }

        .question {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .answer {
            padding: 10px;
            background: #f9f9f9;
            border-right: 3px solid #4A90E2;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .metadata {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.9em;
        }

        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>תשובות טופס</h1>
        <p>נוצר ב: <?php echo date('d/m/Y H:i'); ?></p>
    </div>

    <div class="user-info">
        <p><strong>תעודת זהות:</strong> <?php echo htmlspecialchars($user['tz']); ?></p>
        <?php if ($user['full_name']): ?>
        <p><strong>שם מלא:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
        <?php endif; ?>
        <p><strong>סך השאלות שנענו:</strong> <?php echo count($responses); ?></p>
        <p><strong>עדכון אחרון:</strong> <?php echo date('d/m/Y H:i', strtotime($responses[0]['submitted_at'] ?? 'now')); ?></p>
    </div>

    <div class="no-print" style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px;">
        <p><strong>הוראות הדפסה:</strong></p>
        <p>1. לחץ Ctrl+P (או Cmd+P במק)</p>
        <p>2. בחר "שמור כ-PDF" כמדפסת היעד</p>
        <p>3. לחץ שמור</p>
        <button onclick="window.print()" style="padding: 10px 20px; background: #4A90E2; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">📄 הדפס/שמור PDF</button>
    </div>

    <?php
    $currentCategory = '';
    foreach ($responses as $response):
        $category = $response['category_name'] ?? 'ללא קטגוריה';

        if ($category !== $currentCategory):
            if ($currentCategory !== ''): ?>
                </div>
            <?php endif;
            $currentCategory = $category;
            ?>
            <div class="category-section">
                <div class="category-title"><?php echo htmlspecialchars($category); ?></div>
        <?php endif; ?>

        <div class="question-answer">
            <div class="question">
                <?php echo htmlspecialchars($response['question_text']); ?>
            </div>
            <div class="answer">
                <?php echo htmlspecialchars($response['answer_value'] ?? $response['answer_json'] ?? ''); ?>
            </div>
            <div class="metadata">
                סוג: <?php echo htmlspecialchars($response['type_name']); ?> •
                נשמר: <?php echo date('d/m/Y H:i', strtotime($response['submitted_at'])); ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($currentCategory !== ''): ?>
        </div>
    <?php endif; ?>

    <div class="footer">
        <p>מסמך זה נוצר אוטומטית ממערכת הטפסים הדיגיטליים</p>
        <p>© <?php echo date('Y'); ?> - כל הזכויות שמורות</p>
    </div>

    <script>
        // Auto-trigger print dialog after page loads (optional)
        // window.addEventListener('load', () => {
        //     setTimeout(() => window.print(), 1000);
        // });
    </script>
</body>
</html>
<?php
?>
