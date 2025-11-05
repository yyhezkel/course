<?php
require_once 'config.php';

$db = getDbConnection();

echo "=== USERS TABLE SCHEMA ===\n";
$result = $db->query("PRAGMA table_info(users)");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-20s %-15s %s\n", $row['name'], $row['type'], $row['notnull'] ? 'NOT NULL' : '');
}

echo "\n=== SAMPLE USER DATA ===\n";
$result = $db->query("SELECT id, tz, full_name, password_hash, id_type FROM users LIMIT 3");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}, TZ: {$row['tz']}, Name: {$row['full_name']}, Has Password: " . (empty($row['password_hash']) ? 'No' : 'Yes') . ", ID Type: {$row['id_type']}\n";
}

echo "\n=== FORM RESPONSES (SAMPLE) ===\n";
$result = $db->query("SELECT user_id, question_id, answer_value FROM form_responses WHERE user_id IN (SELECT id FROM users LIMIT 1) LIMIT 10");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "User {$row['user_id']}, Question {$row['question_id']}: {$row['answer_value']}\n";
}

echo "\n=== QUESTIONS (SAMPLE for identifying name fields) ===\n";
$result = $db->query("SELECT id, question_text, placeholder FROM questions WHERE question_text LIKE '%שם%' LIMIT 5");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "Q{$row['id']}: {$row['question_text']} (placeholder: {$row['placeholder']})\n";
}
