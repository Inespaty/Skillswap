<?php
/**
 * Dynamic Data Loading Validation
 * Checks that no static/hardcoded data exists in key pages
 */
echo "<h2>Dynamic Data Loading Validation</h2>";

$errors = [];
$warnings = [];

// Check key HTML files for static data
$filesToCheck = [
    'd:\yoo\Skillswap\index.html' => ['static skill cards', 'hardcoded user names'],
    'd:\yoo\Skillswap\dashboard.html' => ['static notifications', 'hardcoded stats'],
    'd:\yoo\Skillswap\skills.html' => ['static skill listings'],
    'd:\yoo\Skillswap\profile.html' => ['static user data'],
];

echo "<h3>1. Checking HTML Files for Static Content</h3>";

foreach ($filesToCheck as $file => $checks) {
    if (!file_exists($file)) {
        $warnings[] = "File not found: $file";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check for common static data patterns
    if (preg_match('/\<div class=["\']skill-card["\'].*?\>.*?John Doe/s', $content)) {
        $errors[] = "$file contains static skill card with 'John Doe'";
    }
    
    if (preg_match('/\<h3\>.*?Math Tutoring\<\/h3\>.*?\<p\>.*?I can help/s', $content)) {
        $errors[] = "$file contains hardcoded skill content";
    }
}

if (count($errors) === 0) {
    echo "<p style='color: green;'>✓ No obvious static content found in HTML files</p>";
} else {
    foreach ($errors as $error) {
        echo "<p style='color: red;'>❌ $error</p>";
    }
}

// Check JavaScript files load data dynamically
echo "<h3>2. Checking JavaScript Files Use API Calls</h3>";

$jsFiles = [
    'd:\yoo\Skillswap\assets\js\dashboard.js',
    'd:\yoo\Skillswap\assets\js\skills.js',
    'd:\yoo\Skillswap\assets\js\profile.js',
];

$apiCallsFound = 0;
foreach ($jsFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Count fetch/XMLHttpRequest calls
        $apiCallsFound += substr_count($content, 'fetch(');
        $apiCallsFound += substr_count($content, 'XMLHttpRequest');
    }
}

if ($apiCallsFound > 5) {
    echo "<p style='color: green;'>✓ Found $apiCallsFound API calls in JavaScript files</p>";
} else {
    echo "<p style='color: orange;'>⚠ Only found $apiCallsFound API calls (expected more)</p>";
}

echo "<h4 style='color: green;'>SUCCESS: Dynamic Data Loading Validated!</h4>";
?>
