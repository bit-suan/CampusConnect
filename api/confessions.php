<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/utils.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = new Database();

switch ($method) {
    case 'POST':
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (strpos($path, '/upvote') !== false) {
            handleVote($db, 'upvote');
        } elseif (strpos($path, '/downvote') !== false) {
            handleVote($db, 'downvote');
        } else {
            handleCreateConfession($db);
        }
        break;
    case 'GET':
        handleGetConfessions($db);
        break;
    default:
        Utils::sendError('Method not allowed', 405);
}

function handleCreateConfession($db) {
    $user = Auth::requireAuth();
    $data = Utils::getRequestData();
    
    $required = ['content'];
    $missing = Utils::validateRequired($required, $data);
    
    if (!empty($missing)) {
        Utils::sendError('Missing required fields: ' . implode(', ', $missing));
    }
    
    if (strlen($data['content']) < 10) {
        Utils::sendError('Confession must be at least 10 characters long');
    }
    
    if (strlen($data['content']) > 1000) {
        Utils::sendError('Confession must be less than 1000 characters');
    }
    
    $mood = $data['mood'] ?? 'neutral';
    $tags = $data['tags'] ?? [];
    $tagsJson = json_encode($tags);
    
    // Get user's campus
    $stmt = $db->query("SELECT campus FROM users WHERE id = ?", [$user['user_id']]);
    $userInfo = $stmt->fetch();
    
    try {
        $db->query(
            "INSERT INTO confessions (user_id, content, mood, tags, campus, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$user['user_id'], Utils::sanitizeInput($data['content']), $mood, $tagsJson, $userInfo['campus']]
        );
        
        Utils::sendResponse(['message' => 'Confession posted successfully'], 201);
    } catch (Exception $e) {
        Utils::sendError('Failed to post confession: ' . $e->getMessage(), 500);
    }
}

function handleGetConfessions($db) {
    $campus = $_GET['campus'] ?? null;
    $mood = $_GET['mood'] ?? null;
    $tag = $_GET['tag'] ?? null;
    $limit = min(intval($_GET['limit'] ?? 20), 100);
    $offset = intval($_GET['offset'] ?? 0);
    
    $whereConditions = ["c.status = 'approved'"];
    $params = [];
    
    if ($campus) {
        $whereConditions[] = "c.campus = ?";
        $params[] = $campus;
    }
    
    if ($mood) {
        $whereConditions[] = "c.mood = ?";
        $params[] = $mood;
    }
    
    if ($tag) {
        $whereConditions[] = "JSON_CONTAINS(c.tags, ?)";
        $params[] = json_encode($tag);
    }
    
    $sql = "SELECT c.id, c.content, c.mood, c.tags, c.campus, c.created_at,
                   COALESCE(upvotes.count, 0) as upvotes,
                   COALESCE(downvotes.count, 0) as downvotes
            FROM confessions c
            LEFT JOIN (
                SELECT confession_id, COUNT(*) as count 
                FROM votes 
                WHERE vote_type = 'upvote' 
                GROUP BY confession_id
            ) upvotes ON c.id = upvotes.confession_id
            LEFT JOIN (
                SELECT confession_id, COUNT(*) as count 
                FROM votes 
                WHERE vote_type = 'downvote' 
                GROUP BY confession_id
            ) downvotes ON c.id = downvotes.confession_id
            WHERE " . implode(' AND ', $whereConditions) . "
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->query($sql, $params);
    $confessions = $stmt->fetchAll();
    
    // Decode tags JSON
    foreach ($confessions as &$confession) {
        $confession['tags'] = json_decode($confession['tags'], true) ?? [];
    }
    
    Utils::sendResponse(['confessions' => $confessions]);
}

function handleVote($db, $voteType) {
    $user = Auth::requireAuth();
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    
    // Find confession ID in the path
    $confessionId = null;
    foreach ($segments as $i => $segment) {
        if (is_numeric($segment) && isset($segments[$i + 1]) && in_array($segments[$i + 1], ['upvote', 'downvote'])) {
            $confessionId = $segment;
            break;
        }
    }
    
    if (!$confessionId) {
        Utils::sendError('Invalid confession ID');
    }
    
    // Check if confession exists
    $stmt = $db->query("SELECT id FROM confessions WHERE id = ?", [$confessionId]);
    if (!$stmt->fetch()) {
        Utils::sendError('Confession not found', 404);
    }
    
    // Check if user already voted
    $stmt = $db->query(
        "SELECT vote_type FROM votes WHERE user_id = ? AND confession_id = ?",
        [$user['user_id'], $confessionId]
    );
    
    $existingVote = $stmt->fetch();
    
    try {
        if ($existingVote) {
            if ($existingVote['vote_type'] === $voteType) {
                // Remove vote if same type
                $db->query(
                    "DELETE FROM votes WHERE user_id = ? AND confession_id = ?",
                    [$user['user_id'], $confessionId]
                );
                $message = ucfirst($voteType) . ' removed';
            } else {
                // Update vote type
                $db->query(
                    "UPDATE votes SET vote_type = ?, created_at = NOW() WHERE user_id = ? AND confession_id = ?",
                    [$voteType, $user['user_id'], $confessionId]
                );
                $message = 'Vote updated to ' . $voteType;
            }
        } else {
            // Create new vote
            $db->query(
                "INSERT INTO votes (user_id, confession_id, vote_type, created_at) VALUES (?, ?, ?, NOW())",
                [$user['user_id'], $confessionId, $voteType]
            );
            $message = ucfirst($voteType) . ' added';
        }
        
        Utils::sendResponse(['message' => $message]);
    } catch (Exception $e) {
        Utils::sendError('Failed to process vote: ' . $e->getMessage(), 500);
    }
}
?>
