<?php
/**
 * Test Helper for SkillSwap (No Curl Version)
 * Handles CSRF tokens and cookies using stream_context
 */

$testCookiesFile = __DIR__ . '/cookie_jar.json';
if (file_exists($testCookiesFile)) unlink($testCookiesFile); 

function getClient() {
    return [
        'base_url' => 'http://127.0.0.1:8081/backend',
        'cookie_file' => __DIR__ . '/cookie_jar.json',
        'csrf_token' => null
    ];
}

// Load cookies from JSON file
function loadCookies($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?? [];
}

// Save cookies to JSON file
function saveCookies($file, $cookies) {
    file_put_contents($file, json_encode($cookies));
}

// Parse Set-Cookie header
function parseCookieHeader($header, &$cookies) {
    if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $header, $match)) {
        $cookies[$match[1]] = urldecode($match[2]); // Simple storage
    }
}

function makeRequest($endpoint, $method = 'GET', $data = null, &$client = null, $expectJson = true, $contentType = 'json') {
    if ($client === null) $client = getClient();
    
    $url = $client['base_url'] . $endpoint;
    $cookies = loadCookies($client['cookie_file']);
    
    // Build Headers
    $headers = [];
    if ($client['csrf_token']) {
        $headers[] = "X-CSRF-Token: " . $client['csrf_token'];
    }
    
    // Add Cookie Header
    if (!empty($cookies)) {
        $cookieStr = [];
        foreach ($cookies as $k => $v) {
            $cookieStr[] = "$k=" . urlencode($v);
        }
        $headers[] = "Cookie: " . implode('; ', $cookieStr);
    }
    
    // Method & Content
    if ($method === 'POST') {
        if ($contentType === 'json') {
            $jsonData = json_encode($data);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($jsonData);
            $content = $jsonData;
        } elseif ($contentType === 'form') {
            $content = http_build_query($data);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Content-Length: ' . strlen($content);
        }
    } else {
        $content = null;
    }
    
    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $content,
            'ignore_errors' => true // Don't throw warning on 4xx/5xx
        ]
    ];
    
    $context = stream_context_create($options);
    $body = file_get_contents($url, false, $context);
    
    // Capture Response Info
    $statusCode = 0;
    if (isset($http_response_header) && isset($http_response_header[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $match);
        $statusCode = isset($match[1]) ? (int)$match[1] : 0;
        
        // Update Cookies
        foreach ($http_response_header as $hdr) {
            if (stripos($hdr, 'Set-Cookie') === 0) {
                parseCookieHeader($hdr, $cookies);
            }
        }
        saveCookies($client['cookie_file'], $cookies);
    }
    
    $decodedBody = $expectJson ? json_decode($body, true) : $body;
    return ['status' => $statusCode, 'body' => $decodedBody, 'raw_body' => $body];
}

function initSession(&$client) {
    $res = makeRequest('/auth/whoami.php', 'GET', null, $client);
    
    $token = null;
    if (isset($res['body']['csrf_token'])) {
        $token = $res['body']['csrf_token'];
    } elseif (isset($res['body']['user']['csrf_token'])) {
        $token = $res['body']['user']['csrf_token'];
    }

    if ($token) {
        $client['csrf_token'] = $token;
        echo "<p style='color: gray;'>Initialized session. CSRF Token provided.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Warning: No CSRF token found in whoami response.</p>";
    }
    return $res;
}

// --- Common Auth Helpers ---

function registerUser(&$client, $name) {
    // Unique email based on time to avoid conflicts
    $email = strtolower(str_replace(' ', '', $name)) . '_' . uniqid() . '@university.edu';
    $data = ['name' => $name, 'email' => $email, 'password' => 'password123'];
    $res = makeRequest('/auth/register.php', 'POST', $data, $client);
    
    if ($res['status'] !== 201) {
        // If fail, try to return error info or die
        die("Failed to register $name ({$res['status']}): " . print_r($res['body'], true));
    }
    return ['email' => $email, 'id' => $res['body']['user']['id']];
}

function loginUser(&$client, $email) {
    $data = ['email' => $email, 'password' => 'password123'];
    $res = makeRequest('/auth/login.php', 'POST', $data, $client);
    
    if ($res['status'] !== 200) {
        die("Failed to login $email ({$res['status']}): " . print_r($res['body'], true));
    }
    
    // Refresh CSRF for this user context
    initSession($client);
    return $res;
}

// Alias for backwards compatibility if needed, or just use loginUser
function login(&$client, $email, $password) {
    return loginUser($client, $email);
}
?>
