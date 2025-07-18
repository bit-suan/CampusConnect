<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'campusconnect');
define('DB_USER', 'root');
define('DB_PASS', '');

// JWT Configuration
define('JWT_SECRET', 'your_super_secret_jwt_key_change_this_in_production');
define('JWT_ALGORITHM', 'HS256');

// API Configuration
define('API_VERSION', 'v1');
define('BASE_URL', 'http://localhost:8000');

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
