<?php
// Test script for registration API
header('Content-Type: application/json');

// Test data
$testData = [
    'title' => 'Mr.',
    'gender' => 'male',
    'firstName' => 'John',
    'lastName' => 'Doe',
    'phone' => '+1234567890',
    'email' => 'john.doe@example.com',
    'ageRange' => '25-34',
    'attendanceType' => 'in-person',
    'country' => 'US',
    'countryName' => 'United States',
    'stateOfOrigin' => 'CA',
    'howDidYouHear' => 'social-media'
];

echo "<h1>Registration API Test</h1>";
echo "<h2>Test Data:</h2>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Test the registration endpoint
$url = 'http://localhost:8080/Backend/api/register.php';
$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($testData)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "<h2>API Response:</h2>";
if ($result === FALSE) {
    echo "<p style='color: red;'>Error: Could not connect to API</p>";
} else {
    $response = json_decode($result, true);
    if ($response) {
        echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
        echo json_encode($response, JSON_PRETTY_PRINT);
        echo "</pre>";
        
        if (isset($response['success']) && $response['success']) {
            echo "<p style='color: green; font-weight: bold;'>✅ Registration Test PASSED!</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>❌ Registration Test FAILED!</p>";
        }
    } else {
        echo "<p style='color: red;'>Error: Invalid JSON response</p>";
        echo "<pre>Raw response: " . htmlspecialchars($result) . "</pre>";
    }
}

// Test health endpoint
echo "<h2>Health Check:</h2>";
$healthUrl = 'http://localhost:8080/Backend/api/health.php';
$healthResult = file_get_contents($healthUrl);

if ($healthResult !== FALSE) {
    $healthResponse = json_decode($healthResult, true);
    if ($healthResponse) {
        echo "<pre style='background: #e8f5e8; padding: 10px; border-radius: 5px;'>";
        echo json_encode($healthResponse, JSON_PRETTY_PRINT);
        echo "</pre>";
    }
}
?>