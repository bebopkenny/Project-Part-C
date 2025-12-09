<?php


// Used for data sanitization !!!
function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES,"UTF-8");
}


// Generate a token for security
function csrf_token($str) {
    if (!isset($_SESSION['crsf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Use a token for security !!!!!!!!!!!!!
function check_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'],  $token);
}

// Allows variables to have a default if result isnt found
// Ex: 
// $id = param('id', 0, 'GET');
// if failed to GET id, id will be 0, else id will be what was recieved
function param(string $key, $default = null, string $source = 'REQUEST') {
    switch (strtoupper($source)) {
        case 'GET':
            return $_GET[$key] ?? $default;
        case 'POST':
            return $_POST[$key] ?? $default;
        case 'REQUEST':
        default:
            return $_REQUEST[$key] ?? $default;
    }
}
?>