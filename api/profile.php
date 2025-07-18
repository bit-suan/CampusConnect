<?php
ob_start(); // Start output buffering at the very beginning of the script
header('Content-Type: application/json'); // Explicitly set content type here

// Use __DIR__ to ensure paths are relative to the current file's directory
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/utils.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = new Database();

switch ($method) {
    case 'POST':
        handleUpdateProfile($db);
        break;
    case 'GET':
        handleGetProfile($db);
        break;
    default:
        Utils::sendError('Method not allowed', 405);
}

function handleUpdateProfile($db) {
    $user = Auth::requireAuth(); // This will exit if unauthorized
    $data = Utils::getRequestData();
    
    // --- DEBUGGING START ---
    error_log("DEBUG: handleUpdateProfile called. User ID: " . ($user['user_id'] ?? 'N/A'));
    error_log("DEBUG: Request data received: " . json_encode($data));
    // --- DEBUGGING END ---

    $allowedFields = [
        'name', 'bio', 'goal', 'personality', 'religion', 
        'relationship_status', 'year', 'department', 'hobbies',
        'profile_picture',
        'privacy_department', 'privacy_status', 'privacy_goals'
    ];
    
    $updateFields = [];
    $updateValues = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $updateValues[] = Utils::sanitizeInput($data[$field]);
        }
    }
    
    if (empty($updateFields)) {
        error_log("DEBUG: No valid fields to update. Sending 400 error.");
        Utils::sendError('No valid fields to update'); // This sends a 400 error
    }
    
    $updateValues[] = $user['user_id'];
    
    try {
        $sql = "UPDATE profiles SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE user_id = ?";
        error_log("DEBUG: Executing SQL: " . $sql . " with values: " . json_encode($updateValues));
        $db->query($sql, $updateValues);
        
        error_log("DEBUG: Profile updated successfully. Sending 200 OK response.");
        Utils::sendResponse(['message' => 'Profile updated successfully']); // This sends a 200 OK
    } catch (Exception $e) {
        error_log("DEBUG: Profile update failed: " . $e->getMessage());
        Utils::sendError('Profile update failed: ' . $e->getMessage(), 500);
    }
}

function handleGetProfile($db) {
    $user = Auth::requireAuth(); // This will exit if unauthorized
    
    // --- DEBUGGING START ---
    error_log("DEBUG: handleGetProfile called. User ID: " . ($user['user_id'] ?? 'N/A'));
    // --- DEBUGGING END ---

    $stmt = $db->query(
        "SELECT u.email, u.campus, u.role, p.* FROM users u 
         LEFT JOIN profiles p ON u.id = p.user_id 
         WHERE u.id = ?", 
        [$user['user_id']]
    );
    
    $profile = $stmt->fetch();
    
    if (!$profile) {
        error_log("DEBUG: Profile not found for user ID: " . $user['user_id'] . ". Sending 404 error.");
        Utils::sendError('Profile not found', 404);
    }
    
    error_log("DEBUG: Profile fetched successfully. Sending 200 OK response.");
    Utils::sendResponse(['profile' => $profile]);
}
?>
