<?php
/**
 * Simple Migration Script: Populate questions table from questions.js
 * This script reads questions.js and inserts all questions into the database
 */

require_once 'config.php';

echo "=== Migrating Questions to Database ===\n\n";

$db = getDbConnection();

// Define all questions (converted from questions.js)
$questions = [
    // === 1. פרטים אישיים ===
    ["id" => "personal_ma", "question" => "מ.א. (מספר אישי):", "type" => "text", "required" => 1, "options" => null, "sort_order" => 1, "category" => "פרטים אישיים"],
    ["id" => "personal_rank", "question" => "דרגה:", "type" => "text", "required" => 1, "options" => null, "sort_order" => 2, "category" => "פרטים אישיים"],
    ["id" => "personal_lastName", "question" => "שם משפחה:", "type" => "text", "required" => 1, "options" => null, "sort_order" => 3, "category" => "פרטים אישיים"],
    ["id" => "personal_firstName", "question" => "שם פרטי:", "type" => "text", "required" => 1, "options" => null, "sort_order" => 4, "category" => "פרטים אישיים"],
    ["id" => "personal_birthDate", "question" => "תאריך לידה (עברי ולועזי):", "type" => "text", "required" => 1, "options" => null, "sort_order" => 5, "category" => "פרטים אישיים"],
    ["id" => "personal_age", "question" => "גיל:", "type" => "number", "required" => 1, "options" => null, "sort_order" => 6, "category" => "פרטים אישיים"],
    ["id" => "personal_status", "question" => "מצב משפחתי:", "type" => "text", "required" => 1, "options" => null, "sort_order" => 7, "category" => "פרטים אישיים"],
    ["id" => "personal_childrenCount", "question" => "מספר ילדים:", "type" => "number", "required" => 0, "options" => null, "sort_order" => 8, "category" => "פרטים אישיים"],
    ["id" => "personal_spouseName", "question" => "שם בן/בת הזוג:", "type" => "text", "required" => 0, "options" => null, "sort_order" => 9, "category" => "פרטים אישיים"],
    ["id" => "personal_address", "question" => "כתובת מגורים:", "type" => "text", "required" => 1, "options" => null, "sort_order" => 10, "category" => "פרטים אישיים"],
    ["id" => "personal_unit", "question" => "יחידה:", "type" => "text", "required" => 1, "options" => null, "sort_order" => 11, "category" => "פרטים אישיים"],
    ["id" => "personal_currentRole", "question" => "תפקיד נוכחי:", "type" => "text", "required" => 1, "options" => null, "sort_order" => 12, "category" => "פרטים אישיים"],
    ["id" => "personal_roleTime", "question" => "וותק בתפקיד (יחידות זמן):", "type" => "text", "required" => 1, "options" => null, "sort_order" => 13, "category" => "פרטים אישיים"],
    ["id" => "personal_futureRole", "question" => "תפקיד עתידי (אם ידוע):", "type" => "text", "required" => 0, "options" => null, "sort_order" => 14, "category" => "פרטים אישיים"],
    ["id" => "personal_phone", "question" => "טלפון:", "type" => "text", "required" => 1, "options" => null, "sort_order" => 15, "category" => "פרטים אישיים"],
    ["id" => "personal_mobile", "question" => "טלפון נייד:", "type" => "text", "required" => 1, "options" => null, "sort_order" => 16, "category" => "פרטים אישיים"],
    ["id" => "personal_militaryMobile", "question" => "מייל צבאי (כן/לא):", "type" => "text", "required" => 0, "options" => null, "sort_order" => 17, "category" => "פרטים אישיים"],
    ["id" => "personal_commanderName", "question" => "שם המפקד:", "type" => "text", "required" => 1, "options" => null, "sort_order" => 18, "category" => "פרטים אישיים"],

    // === 2. רקע וחינוך ===
    ["id" => "personal_medicalProfile", "question" => "פרופיל רפואי (ומגבלות):", "type" => "text", "required" => 0, "options" => null, "sort_order" => 19, "category" => "רקע וחינוך"],
    ["id" => "personal_educationalBackground", "question" => "רקע לימודי:", "type" => "textarea", "required" => 0, "options" => null, "sort_order" => 20, "category" => "רקע וחינוך"],
    ["id" => "personal_militaryBackground", "question" => "רקע צבאי:", "type" => "textarea", "required" => 0, "options" => null, "sort_order" => 21, "category" => "רקע וחינוך"],

    // === 3. רקע אישי וכושר גופני ===
    ["id" => "personalBackground", "question" => "רקע אישי: לספר על משפחת המוצא, מקום לידתך, מקום מגוריך בילדותך ועוד:", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 22, "category" => "רקע אישי וכושר"],
    ["id" => "strengths", "question" => "מהן 3 נקודות החוזק העיקריות שלך? (טקסט חופשי)", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 23, "category" => "רקע אישי וכושר"],
    ["id" => "weaknesses", "question" => "מהן 3 נקודות החולשה העיקריות שלך שאתה רוצה לשפר? (טקסט חופשי)", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 24, "category" => "רקע אישי וכושר"],
    ["id" => "hasSubordinates", "question" => "האם יש לך פקודים?", "type" => "radio", "required" => 1, "options" => '["כן", "לא"]', "sort_order" => 25, "category" => "רקע אישי וכושר"],
    ["id" => "subordinatesCount", "question" => "אם כן, כמה פקודים יש לך?", "type" => "number", "required" => 0, "options" => null, "sort_order" => 26, "category" => "רקע אישי וכושר"],
    ["id" => "roleSummary", "question" => "ציין את עיקרי התפקיד שלך ביחידה:", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 27, "category" => "רקע אישי וכושר"],
    ["id" => "medicalStatus", "question" => "מצב רפואי (ורגישות מיוחדות לשונות / תנאי תזונה):", "type" => "textarea", "required" => 0, "options" => null, "sort_order" => 28, "category" => "רקע אישי וכושר"],
    ["id" => "fitnessLevel", "question" => "מהי רמת הכושר הגופני / ספורטיבי שלך?", "type" => "select", "required" => 1, "options" => '["מצוין", "מעולה", "טוב מאוד", "טוב", "בינוני", "לא בכושר", "לא בכושר בכלל"]', "sort_order" => 29, "category" => "רקע אישי וכושר"],
    ["id" => "sportShirtSize", "question" => "מידת חולצת ספורט (נא לציין גזרה/שרוול אם רלוונטי):", "type" => "text", "required" => 1, "options" => null, "sort_order" => 30, "category" => "רקע אישי וכושר"],

    // === 4. שירות צבאי ותחביבים ===
    ["id" => "hobbies", "question" => "תחביבים ותחומי עניין:", "type" => "textarea", "required" => 0, "options" => null, "sort_order" => 31, "category" => "שירות צבאי"],
    ["id" => "personalExperiences", "question" => "נושאים אישיים/חוויות שהורישו התנהגות סגל:", "type" => "textarea", "required" => 0, "options" => null, "sort_order" => 32, "category" => "שירות צבאי"],
    ["id" => "whyMilitaryService", "question" => "מדוע הגעת לשירות צבאי?", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 33, "category" => "שירות צבאי"],
    ["id" => "mostSignificantEvent", "question" => "מהו האירוע המשמעותי ביותר שחווית בשירותך בצה\"ל?", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 34, "category" => "שירות צבאי"],
    ["id" => "whatILike", "question" => "מה אתה אוהב בשירותך / בתפקיד הצבאי?", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 35, "category" => "שירות צבאי"],
    ["id" => "whatIDontLike", "question" => "מה אתה לא אוהב בשירותך / בתפקידך?", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 36, "category" => "שירות צבאי"],
    ["id" => "influentialCommander", "question" => "ציין מפקד אחד שהשפיע עליך בשירותך, ומהן התכונות שגרמו לך להעריך אותו?", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 37, "category" => "שירות צבאי"],

    // === 5. יעדים וערכים ===
    ["id" => "courseGoals", "question" => "מהם היעדים האישיים שאתה מציב לעצמך בקורס?", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 38, "category" => "יעדים וערכים"],
    ["id" => "supremeValue", "question" => "מהו הערך העליון שעליו אינך מוכן להתפשר?", "type" => "text", "required" => 1, "options" => null, "sort_order" => 39, "category" => "יעדים וערכים"],
    ["id" => "unappreciatedTrait", "question" => "ציין תכונה אנושית אחת שאינך מעריך והסבר מדוע:", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 40, "category" => "יעדים וערכים"],
    ["id" => "roleModel", "question" => "מיהו המודל לחיקוי שלך ומדוע?", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 41, "category" => "יעדים וערכים"],
    ["id" => "initialImpression", "question" => "כשאנשים פוגשים אותך בפעם הראשונה, מה הם חושבים עליך? ובמה בטוח הם טועים לגביך?", "type" => "textarea", "required" => 0, "options" => null, "sort_order" => 42, "category" => "יעדים וערכים"],

    // === 6. ציפיות ונושאים נוספים ===
    ["id" => "candidateExpectations", "question" => "ציפיות מהמועמד: מהן הציפיות שלך מעצמך (כמועמד לקורס)?", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 43, "category" => "ציפיות"],
    ["id" => "staffExpectations", "question" => "ציפיות מסגל התוכנית: מה הציפיות שלך מהמפקדים שלך בתפקיד ההכשרה?", "type" => "textarea", "required" => 1, "options" => null, "sort_order" => 44, "category" => "ציפיות"],
    ["id" => "additionalNotes", "question" => "נושא נוסף שחשוב לך לציין ולא שאלנו:", "type" => "textarea", "required" => 0, "options" => null, "sort_order" => 45, "category" => "ציפיות"],
];

echo "Found " . count($questions) . " questions to migrate\n\n";

// Begin migration
$db->beginTransaction();
$successCount = 0;
$skipCount = 0;

try {
    $stmt = $db->prepare("
        INSERT OR IGNORE INTO questions
        (id, question_text, question_type, options, is_required, sort_order, category, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");

    foreach ($questions as $q) {
        $result = $stmt->execute([
            $q['id'],
            $q['question'],
            $q['type'],
            $q['options'],
            $q['required'],
            $q['sort_order'],
            $q['category']
        ]);

        if ($stmt->rowCount() > 0) {
            echo "✓ Inserted: {$q['id']} - {$q['question']}\n";
            $successCount++;
        } else {
            echo "⚠ Skipped (already exists): {$q['id']}\n";
            $skipCount++;
        }
    }

    $db->commit();

    echo "\n=== Migration Summary ===\n";
    echo "✓ Successfully inserted: $successCount questions\n";
    echo "⚠ Skipped (already exist): $skipCount questions\n";

    // Verify
    $totalQuestions = $db->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    echo "\nTotal questions in database: $totalQuestions\n";

    echo "\n✓ Migration completed successfully!\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "\n✗ Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
