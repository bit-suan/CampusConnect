<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/utils.php';

// Set JSON content type for all responses
header('Content-Type: application/json');

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Extract the last segment of the path, which is the actual endpoint (register, login, me, logout)
$segments = array_values(array_filter(explode('/', $path)));
// If path is /api/register, $segments will be ['api', 'register']
// If path is /api/me, $segments will be ['api', 'me']
$endpoint = end($segments) ?? ''; // This will correctly get 'register', 'login', 'me', 'logout'

$db = new Database();

// Route requests
switch ($method.':'.$endpoint) {
    case 'POST:register':
        handleRegister($db);
        break;
    case 'POST:login':
        handleLogin($db);
        break;
    case 'GET:me':
        handleGetMe($db);
        break;
    case 'GET:logout':
        handleLogout();
        break;
    default:
        Utils::sendError('Endpoint not found', 404);
}

/**
 * Handle user registration
 */
function handleRegister($db) {
    $data = Utils::getRequestData();
    
    // Validate required fields
    $required = ['email', 'password', 'campus'];
    $missing = Utils::validateRequired($required, $data);
    
    if (!empty($missing)) {
        Utils::sendError('Missing required fields: ' . implode(', ', $missing), 400);
    }
    
    // Validate email format
    if (!Utils::validateEmail($data['email'])) {
        Utils::sendError('Invalid email format', 400);
    }
    
    // Validate password strength
    if (strlen($data['password']) < 6) {
        Utils::sendError('Password must be at least 6 characters', 400);
    }
    
    // Check if user exists
    $stmt = $db->query("SELECT id FROM users WHERE email = ?", [$data['email']]);
    if ($stmt->fetch()) {
        Utils::sendError('User already exists', 409);
    }
    
    try {
        // Create user
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $db->query(
            "INSERT INTO users (email, password, campus, role, created_at) 
             VALUES (?, ?, ?, 'student', NOW())",
            [$data['email'], $hashedPassword, Utils::sanitizeInput($data['campus'])]
        );
        
        $userId = $db->lastInsertId();
        
        // Create empty profile
        $db->query(
            "INSERT INTO profiles (user_id, created_at) VALUES (?, NOW())",
            [$userId]
        );
        
        // Generate JWT token
        $payload = [
            'user_id' => $userId,
            'email' => $data['email'],
            'role' => 'student',
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ];
        
        $token = Auth::generateJWT($payload);
        
        // Return success response
        Utils::sendResponse([
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => [
                'id' => $userId,
                'email' => $data['email'],
                'campus' => $data['campus'],
                'role' => 'student'
            ]
        ], 201);
        
    } catch (Exception $e) {
        Utils::sendError('Registration failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle user login
 */
function handleLogin($db) {
    $data = Utils::getRequestData();
    
    $required = ['email', 'password'];
    $missing = Utils::validateRequired($required, $data);
    
    if (!empty($missing)) {
        Utils::sendError('Missing required fields: ' . implode(', ', $missing), 400);
    }
    
    // Find user by email
    $stmt = $db->query("SELECT * FROM users WHERE email = ?", [$data['email']]);
    $user = $stmt->fetch();
    
    // Verify credentials
    if (!$user || !password_verify($data['password'], $user['password'])) {
        Utils::sendError('Invalid credentials', 401);
    }
    
    // Generate JWT token
    $payload = [
        'user_id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'exp' => time() + (24 * 60 * 60) // 24 hours
    ];
    
    $token = Auth::generateJWT($payload);
    
    // Return success response
    Utils::sendResponse([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'campus' => $user['campus'],
            'role' => $user['role']
        ]
    ]);
}

/**
 * Get current user profile
 */
function handleGetMe($db) {
    $user = Auth::requireAuth(); // Validates JWT token
    
    $stmt = $db->query(
        "SELECT u.*, p.* 
         FROM users u 
         LEFT JOIN profiles p ON u.id = p.user_id 
         WHERE u.id = ?", 
        [$user['user_id']]
    );
    
    $userData = $stmt->fetch();
    
    if (!$userData) {
        Utils::sendError('User not found', 404);
    }
    
    // Remove sensitive data
    unset($userData['password']);
    
    Utils::sendResponse(['user' => $userData]);
}

/**
 * Handle user logout
 */
function handleLogout() {
    // In a real implementation, you might blacklist the token
    Utils::sendResponse(['message' => 'Logged out successfully']);
}
?>
