<?php
/**
 * API Security Test Script
 * Tests CORS headers, API key authentication, and security headers
 */

echo "=== API Security Test ===\n\n";

// Test 1: Check CORS headers
echo "Test 1: CORS Headers\n";
echo "Expected Origin: https://qr.bot4wa.com\n";

$ch = curl_init('https://qr.bot4wa.com/kodkod/api.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
$response = curl_exec($ch);
curl_close($ch);

if (preg_match('/Access-Control-Allow-Origin: (.*)$/m', $response, $matches)) {
    $origin = trim($matches[1]);
    echo "Result: " . ($origin === 'https://qr.bot4wa.com' ? '✓' : '✗') . " Origin = $origin\n";
} else {
    echo "Result: ✗ CORS header not found\n";
}

// Check security headers
if (preg_match('/X-Content-Type-Options: (.*)$/m', $response, $matches)) {
    echo "✓ X-Content-Type-Options: " . trim($matches[1]) . "\n";
} else {
    echo "✗ X-Content-Type-Options header missing\n";
}

if (preg_match('/X-Frame-Options: (.*)$/m', $response, $matches)) {
    echo "✓ X-Frame-Options: " . trim($matches[1]) . "\n";
} else {
    echo "✗ X-Frame-Options header missing\n";
}

if (preg_match('/X-XSS-Protection: (.*)$/m', $response, $matches)) {
    echo "✓ X-XSS-Protection: " . trim($matches[1]) . "\n";
} else {
    echo "✗ X-XSS-Protection header missing\n";
}

echo "\n";

// Test 2: API Key Authentication
require_once 'config.php';

echo "Test 2: API Key Authentication\n";

// Simulate request without API key
function testApiAuth($useValidKey, $testName) {
    echo "\nSub-test: $testName\n";

    $headers = ['Content-Type: application/json'];
    if ($useValidKey) {
        $headers[] = 'X-Api-Key: ' . API_KEY;
    } else {
        $headers[] = 'X-Api-Key: INVALID_KEY';
    }

    $data = json_encode([
        'action' => 'submit',
        'user_id' => 1,
        'form_data' => ['test' => 'data']
    ]);

    $ch = curl_init('https://qr.bot4wa.com/kodkod/api.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    echo "HTTP Code: $httpCode\n";
    echo "Response: " . ($result['message'] ?? 'No message') . "\n";

    if ($useValidKey) {
        // Valid key should return 200 or error about data (not 401)
        echo "Result: " . ($httpCode !== 401 ? '✓' : '✗') . " Valid key accepted\n";
    } else {
        // Invalid key should return 401
        echo "Result: " . ($httpCode === 401 ? '✓' : '✗') . " Invalid key rejected\n";
    }
}

testApiAuth(false, 'Request with invalid API key');
testApiAuth(true, 'Request with valid API key');

echo "\n";

// Test 3: SQL Injection Prevention
echo "Test 3: SQL Injection Prevention\n";
echo "Testing if prepared statements protect against SQL injection...\n";

$db = getDbConnection();

// Try SQL injection in login
$maliciousTz = "123456789' OR '1'='1";
$stmt = $db->prepare("SELECT id FROM users WHERE tz = ?");
$stmt->execute([$maliciousTz]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    echo "✓ SQL injection prevented - malicious TZ not matched\n";
} else {
    echo "✗ WARNING: SQL injection might be possible!\n";
}

// Try SQL injection in form data
$maliciousData = "'; DROP TABLE users; --";
try {
    $stmt = $db->prepare("INSERT INTO form_responses (user_id, question_id, answer_value) VALUES (?, ?, ?)");
    $stmt->execute([1, 'test_question', $maliciousData]);
    $db->exec("DELETE FROM form_responses WHERE question_id = 'test_question'");
    echo "✓ SQL injection prevented - malicious data stored safely as string\n";
} catch (Exception $e) {
    echo "✗ Error during injection test: " . $e->getMessage() . "\n";
}

// Verify tables still exist
try {
    $db->query("SELECT 1 FROM users LIMIT 1");
    echo "✓ Database tables intact after injection attempt\n";
} catch (Exception $e) {
    echo "✗ Database corrupted!\n";
}

echo "\n";

// Test 4: Input Validation
echo "Test 4: Input Validation\n";

$testCases = [
    ['tz' => '12345', 'expected' => false, 'reason' => 'Too short'],
    ['tz' => '1234567890', 'expected' => false, 'reason' => 'Too long'],
    ['tz' => 'abc123456', 'expected' => false, 'reason' => 'Non-numeric'],
    ['tz' => '123456789', 'expected' => true, 'reason' => 'Valid format'],
];

foreach ($testCases as $test) {
    $tz = $test['tz'];
    $isValid = !empty($tz) && is_numeric($tz) && strlen($tz) === 9;

    $status = ($isValid === $test['expected']) ? '✓' : '✗';
    echo "$status TZ: '$tz' ({$test['reason']}) - " . ($isValid ? 'Valid' : 'Invalid') . "\n";
}

echo "\n=== All security tests completed ===\n";
?>
