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
    case 'GET':
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        if (end($segments) === 'match') {
            handleGetMatches($db);
        } elseif (end($segments) === 'friends') {
            handleGetFriends($db);
        }
        break;
    case 'POST':
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (strpos($path, '/request/') !== false) {
            handleFriendRequest($db);
        } elseif (strpos($path, '/accept/') !== false) {
            handleAcceptRequest($db);
        }
        break;
    case 'DELETE': // Added for DELETE /api/friend/{id}
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (strpos($path, '/friend/') !== false) {
            handleRemoveFriend($db);
        }
        break;
    default:
        Utils::sendError('Method not allowed', 405);
}

function handleGetMatches($db) {
    $user = Auth::requireAuth();
    
    // Get current user's profile
    $stmt = $db->query("SELECT * FROM profiles WHERE user_id = ?", [$user['user_id']]);
    $currentProfile = $stmt->fetch();
    
    if (!$currentProfile) {
        Utils::sendError('Please complete your profile first');
    }
    
    // Get filters from query parameters
    $goal = $_GET['goal'] ?? null;
    $department = $_GET['department'] ?? null;
    $year = $_GET['year'] ?? null;
    $limit = min(intval($_GET['limit'] ?? 10), 50); // $limit is already an integer
    
    // Build query with filters
    $whereConditions = ["p.user_id != ?"];
    $params = [$user['user_id']];
    
    if ($goal) {
        $whereConditions[] = "p.goal = ?";
        $params[] = $goal;
    }
    
    if ($department) {
        $whereConditions[] = "p.department = ?";
        $params[] = $department;
    }
    
    if ($year) {
        $whereConditions[] = "p.year = ?";
        $params[] = $year;
    }
    
    // Exclude already connected users
    $whereConditions[] = "p.user_id NOT IN (
        SELECT CASE 
            WHEN requester_id = ? THEN receiver_id 
            ELSE requester_id 
        END 
        FROM friend_requests 
        WHERE (requester_id = ? OR receiver_id = ?) AND status = 'accepted'
    )";
    $params = array_merge($params, [$user['user_id'], $user['user_id'], $user['user_id']]);
    
    $sql = "SELECT u.id, u.email, u.campus, p.* 
            FROM users u 
            JOIN profiles p ON u.id = p.user_id 
            WHERE " . implode(' AND ', $whereConditions) . "
            ORDER BY p.updated_at DESC 
            LIMIT {$limit}"; // Directly embed the integer limit
    
    // Removed: $params[] = $limit; as it's now directly in the SQL string
    
    try {
        $stmt = $db->query($sql, $params);
        $matches = $stmt->fetchAll();
        
        // Calculate match scores
        foreach ($matches as &$match) {
            $match['match_score'] = Utils::calculateMatchScore($currentProfile, $match);
            unset($match['privacy_department'], $match['privacy_status'], $match['privacy_goals']);
        }
        
        // Sort by match score
        usort($matches, function($a, $b) {
            return $b['match_score'] - $a['match_score'];
        });
        
        Utils::sendResponse(['matches' => $matches]);
    } catch (Exception $e) {
        error_log("DEBUG: handleGetMatches failed: " . $e->getMessage()); // Added debug log
        Utils::sendError('Failed to fetch matches: ' . $e->getMessage(), 500);
    }
}

function handleFriendRequest($db) {
    $user = Auth::requireAuth();
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    $receiverId = end($segments);
    
    if (!is_numeric($receiverId)) {
        Utils::sendError('Invalid user ID');
    }
    
    // Check if receiver exists
    $stmt = $db->query("SELECT id FROM users WHERE id = ?", [$receiverId]);
    if (!$stmt->fetch()) {
        Utils::sendError('User not found', 404);
    }
    
    // Check if request already exists
    $stmt = $db->query(
        "SELECT id FROM friend_requests WHERE 
         ((requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)) 
         AND status IN ('pending', 'accepted')",
        [$user['user_id'], $receiverId, $receiverId, $user['user_id']]
    );
    
    if ($stmt->fetch()) {
        Utils::sendError('Friend request already exists or you are already friends');
    }
    
    try {
        $db->query(
            "INSERT INTO friend_requests (requester_id, receiver_id, status, created_at) VALUES (?, ?, 'pending', NOW())",
            [$user['user_id'], $receiverId]
        );
        
        Utils::sendResponse(['message' => 'Friend request sent successfully'], 201);
    } catch (Exception $e) {
        Utils::sendError('Failed to send friend request: ' . $e->getMessage(), 500);
    }
}

function handleAcceptRequest($db) {
    $user = Auth::requireAuth();
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    $requestId = end($segments);
    
    error_log("DEBUG: handleAcceptRequest called. Request ID from URL: " . $requestId);
    error_log("DEBUG: Authenticated User ID: " . ($user['user_id'] ?? 'N/A'));

    if (!is_numeric($requestId)) {
        Utils::sendError('Invalid request ID');
    }
    
    // First, check if the request exists at all for this receiver
    $sql = "SELECT * FROM friend_requests WHERE id = ? AND receiver_id = ?";
    $params = [$requestId, $user['user_id']];
    
    error_log("DEBUG: Querying for friend request existence: SQL: " . $sql . ", Params: " . json_encode($params));

    $stmt = $db->query($sql, $params);
    $request = $stmt->fetch();

    if (!$request) {
        error_log("DEBUG: Friend request NOT found for ID: " . $requestId . " and User ID: " . $user['user_id']);
        Utils::sendError('Friend request not found', 404);
    }

    // If found, check its status
    if ($request['status'] !== 'pending') {
        error_log("DEBUG: Friend request found but already processed. Status: " . $request['status']);
        Utils::sendError('Friend request already processed', 409); // 409 Conflict is a good status for this
    }
    
    error_log("DEBUG: Pending friend request found. Request details: " . json_encode($request));

    try {
        $db->query(
            "UPDATE friend_requests SET status = 'accepted', updated_at = NOW() WHERE id = ?",
            [$requestId]
        );
        
        Utils::sendResponse(['message' => 'Friend request accepted successfully']);
    } catch (Exception $e) {
        error_log("DEBUG: Failed to accept friend request: " . $e->getMessage());
        Utils::sendError('Failed to accept friend request: ' . $e->getMessage(), 500);
    }
}

function handleGetFriends($db) {
    $user = Auth::requireAuth();

    try {
        $stmt = $db->query("
            SELECT 
                CASE
                    WHEN fr.requester_id = ? THEN u2.id
                    ELSE u1.id
                END as friend_id,
                CASE
                    WHEN fr.requester_id = ? THEN u2.email
                    ELSE u1.email
                END as friend_email,
                p.name as friend_name,
                p.profile_picture as friend_profile_picture,
                p.bio as friend_bio
            FROM friend_requests fr
            JOIN users u1 ON fr.requester_id = u1.id
            JOIN users u2 ON fr.receiver_id = u2.id
            LEFT JOIN profiles p ON 
                (CASE WHEN fr.requester_id = ? THEN u2.id ELSE u1.id END) = p.user_id
            WHERE (fr.requester_id = ? OR fr.receiver_id = ?) AND fr.status = 'accepted'
        ", [$user['user_id'], $user['user_id'], $user['user_id'], $user['user_id'], $user['user_id']]);

        $friends = $stmt->fetchAll();
        Utils::sendResponse(['friends' => $friends]);

    } catch (Exception $e) {
        Utils::sendError('Failed to fetch friends: ' . $e->getMessage(), 500);
    }
}

function handleRemoveFriend($db) {
    $user = Auth::requireAuth();
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    $friendId = end($segments);

    if (!is_numeric($friendId)) {
        Utils::sendError('Invalid friend ID');
    }

    try {
        // Delete the friend request entry (which represents the friendship)
        $stmt = $db->query("
            DELETE FROM friend_requests 
            WHERE (requester_id = ? AND receiver_id = ?) 
               OR (requester_id = ? AND receiver_id = ?) 
               AND status = 'accepted'
        ", [$user['user_id'], $friendId, $friendId, $user['user_id']]);

        if ($stmt->rowCount() === 0) {
            Utils::sendError('Friendship not found or already removed', 404);
        }

        Utils::sendResponse(['message' => 'Friend removed successfully']);

    } catch (Exception $e) {
        Utils::sendError('Failed to remove friend: ' . $e->getMessage(), 500);
    }
}
?>
