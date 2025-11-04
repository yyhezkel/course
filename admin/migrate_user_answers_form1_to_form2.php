<?php
/**
 * Migrate User Answers from Form 1 to Form 2
 * Maps similar questions and transfers user answers automatically
 */

$db = new PDO('sqlite:/www/wwwroot/qr.bot4wa.com/kodkod/form_data.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// User ID to migrate
$userId = 1; // User 123456789

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Migrating Answers: Form 1 → Form 2 for User ID $userId         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Define question mappings: Form 1 Question ID => Form 2 Question ID
// Mapping to the ACTIVE category-based form (Q461-Q535 range)
$questionMappings = [
    // Personal Information
    220 => 461,  // שם פרטי → מה השם שלך?
    221 => 462,  // שם משפחה → מה שם המשפחה
    222 => 463,  // תאריך לידה (לועזי) → מה תאריך הלידה שלך?
    // Note: Q222 has both Hebrew and Gregorian dates
    224 => 466,  // מצב משפחתי → מצב משפחתי
    227 => 467,  // כתובת מגורים → עיר מגורים
    236 => 465,  // פרופיל רפואי → מה הפרופיל הרפואי שלך?
    237 => 470,  // רקע לימודי → מה הרקע הלימודי מקצועי שלך?
    238 => 471,  // רקע צבאי → מה אתה יכול לשתף אותנו על הרקע הצבאי שלך?

    // Background and Personal Development
    239 => 474,  // רקע אישי: ספר על משפחת המוצא
    240 => 475,  // נקודות החוזק → התבוננות אישי -מהם נקודות החוזק שלך
    241 => 476,  // נקודות החולשה → התבוננות אישי -מהם נקודות החולשה שלך
    242 => 477,  // האם יש לך פקודים?
    244 => 478,  // עיקרי התפקיד → מהם עיקרי התפקיד שלך בחירום?
    245 => 479,  // מצב רפואי - רגישות → מצב רפואי - רגישות מיוחדת למזון ותרופות
    246 => 481,  // כושר גופני → כושר גופני
    247 => 482,  // מידת חולצת ספורט
    248 => 483,  // תחביבים ותחומי עניין
    249 => 484,  // נושאים אישיים ו\או אחרים הדורשים התייחסות סגל
    250 => 485,  // מדוע הגעת לשירות צבאי → מדוע הינך בשירות צבאי?

    // Service Experience
    251 => 486,  // האירוע המשמעותי ביותר → מהו האירוע המאתגר ביותר
    252 => 487,  // מה אתה אוהב בשירותך → מה אתה אוהב בשירותך/השירות הצבאי
    253 => 488,  // מה אתה לא אוהב → מה אתה לא אוהב בשירותך/תפקידך
    254 => 489,  // מפקד שהשפיע עליך → ציין מפקד אחד שהערכת אותו

    // Goals and Values
    255 => 490,  // היעדים האישיים → מהם היעדים האישים שאתה מפקיד לעצמך בקורס
    256 => 491,  // הערך העליון → מהו הערך העליון עליו אינך מוכן להתפשר
    257 => 492,  // תכונה שאינך מעריך → ציין תכונה אנשושית אחת שאינך מעריך
    258 => 493,  // המודל לחיקוי → מיהו המודל לחיקוי שלך ומדוע?
    259 => 496,  // כשאנשים פוגשים אותך → כשאנשים פוגשים אותך בפעם ראשונה

    // Expectations
    260 => 472,  // ציפיות מהמועמד → מה הציפיות שלך מההכשרה?
    261 => 500,  // ציפיות מסגל התוכנית → מה הציפיות שלך מסגל ההכשרה
    262 => 501,  // נושא נוסף → אם יש משהו חשוב ששכחנו לשאול

    // Additional mappings from different question sets (older questions)
    177 => 461,  // שם פרטי (older question) → מה השם שלך?
    196 => 474,  // רקע אישי (older) → רקע אישי
    205 => 483,  // תחביבים (older) → מהם התחביבים ותחומי העניין שלך?
    212 => 490,  // היעדים האישיים (older) → מהם היעדים האישים
];

// Get user's Form 1 answers
$stmt = $db->prepare("
    SELECT fr.question_id, fr.answer_value
    FROM form_responses fr
    JOIN form_questions fq ON fr.question_id = fq.question_id
    WHERE fr.user_id = ? AND fq.form_id = 1
");
$stmt->execute([$userId]);
$form1Answers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo "1. Found " . count($form1Answers) . " Form 1 answers for user\n";
echo "2. Mapping table has " . count($questionMappings) . " question mappings\n\n";

// Begin transaction
$db->beginTransaction();

try {
    $migrated = 0;
    $skipped = 0;
    $errors = 0;

    echo "3. Starting migration...\n\n";

    foreach ($questionMappings as $form1QuestionId => $form2QuestionId) {
        // Check if user has an answer for this Form 1 question
        if (!isset($form1Answers[$form1QuestionId])) {
            continue; // No answer for this question
        }

        $answerValue = $form1Answers[$form1QuestionId];

        // Get question texts for logging
        $stmt = $db->prepare("SELECT question_text FROM questions WHERE id = ?");
        $stmt->execute([$form1QuestionId]);
        $form1Text = $stmt->fetchColumn();

        $stmt->execute([$form2QuestionId]);
        $form2Text = $stmt->fetchColumn();

        // Check if Form 2 question already has an answer
        $stmt = $db->prepare("
            SELECT answer_value FROM form_responses
            WHERE user_id = ? AND question_id = ?
        ");
        $stmt->execute([$userId, $form2QuestionId]);
        $existingAnswer = $stmt->fetchColumn();

        if ($existingAnswer !== false && $existingAnswer !== '') {
            echo sprintf("  ⊘ Q%d → Q%d: Already has answer, skipping\n",
                $form1QuestionId, $form2QuestionId);
            echo "     \"" . mb_substr($form2Text, 0, 40) . "\" = \"" . mb_substr($existingAnswer, 0, 20) . "\"\n";
            $skipped++;
            continue;
        }

        // Insert the answer into Form 2 question
        try {
            $stmt = $db->prepare("
                INSERT INTO form_responses (user_id, question_id, answer_value, submitted_at)
                VALUES (?, ?, ?, datetime('now'))
                ON CONFLICT(user_id, question_id)
                DO UPDATE SET answer_value = excluded.answer_value, submitted_at = datetime('now')
            ");
            $stmt->execute([$userId, $form2QuestionId, $answerValue]);

            echo sprintf("  ✓ Q%d → Q%d: Migrated\n", $form1QuestionId, $form2QuestionId);
            echo "     F1: \"" . mb_substr($form1Text, 0, 35) . "\"\n";
            echo "     F2: \"" . mb_substr($form2Text, 0, 35) . "\"\n";
            echo "     Value: \"" . mb_substr($answerValue, 0, 30) . "\"\n\n";

            $migrated++;
        } catch (Exception $e) {
            echo sprintf("  ✗ Q%d → Q%d: Error - %s\n", $form1QuestionId, $form2QuestionId, $e->getMessage());
            $errors++;
        }
    }

    if ($errors === 0) {
        $db->commit();
        echo "\n═══════════════════════════════════════════════════════════════════\n";
        echo "✓ Migration completed successfully!\n\n";
    } else {
        $db->rollBack();
        echo "\n═══════════════════════════════════════════════════════════════════\n";
        echo "✗ Migration rolled back due to errors\n\n";
    }

    // Summary
    echo "SUMMARY:\n";
    echo "  ✓ Migrated: $migrated answers\n";
    echo "  ⊘ Skipped (already exists): $skipped answers\n";
    echo "  ✗ Errors: $errors answers\n\n";

    // Check new status
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM form_responses fr
        JOIN form_questions fq ON fr.question_id = fq.question_id
        WHERE fr.user_id = ? AND fq.form_id = 2
    ");
    $stmt->execute([$userId]);
    $form2AnswerCount = $stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM form_questions
        WHERE form_id = 2 AND is_active = 1
    ");
    $stmt->execute();
    $form2QuestionCount = $stmt->fetchColumn();

    echo "CURRENT STATUS:\n";
    echo "  Form 2 answers: $form2AnswerCount / $form2QuestionCount questions\n";
    echo "  Progress: " . round(($form2AnswerCount / $form2QuestionCount) * 100, 1) . "%\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "\n✗ FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
