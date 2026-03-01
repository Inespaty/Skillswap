<?php
$url = 'http://localhost:8080/backend/api/skills.php';
$max = 110; // Global Limit is 100
$blocked = false;

echo "Testing GLOBAL rate limiting on $url...\n";

for ($i = 1; $i <= $max; $i++) {
    $options = [
        'http' => [
            'method'  => 'GET',
            'ignore_errors' => true,
            'header'  => "User-Agent: RateLimitTester/1.0\r\n"
        ]
    ];
    
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    // Parse headers
    $statusCode = 0;
    if (isset($http_response_header) && isset($http_response_header[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
        $statusCode = isset($matches[1]) ? (int)$matches[1] : 0;
    }
    
    // Only print every 10th request to reduce noise, or if blocked
    if ($i % 10 == 0 || $statusCode === 429) {
        echo "Request $i: HTTP $statusCode\n";
    }

    if ($statusCode === 429) {
        $blocked = true;
        echo "SUCCESS: Rate limit triggered at request $i\n";
        break;
    }
}

if (!$blocked) {
    echo "FAILURE: Rate limit was not triggered after $max requests.\n";
}
?>
