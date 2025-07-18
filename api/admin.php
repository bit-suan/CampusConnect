<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/utils.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

$db = new Database();

switch ($method) {
    case 'GET':
        if (in_array('stats', $segments)) {
            handleGetStats($db);
        } elseif (in_array('pending', $segments)) {
            handleGetPendingConfessions($db);
        }
        break;
    case 'POST':
        if (in_array('approve', $segments)) {
            handleApproveConfession($db);
        } elseif (in_array('announcement', $segments)) {
            handleCreateAnnouncement($db);
        }
        break;
    case 'DELETE':
        if (in_array('delete', $segments)) {
            handleDeleteConfession($db);
        }
        break;
    default:
        Utils::sendError('Method not allowed', 405);
}

function handleGetStats($db) {
    Auth::requireAdmin();
    
    try {
        // Total users
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $totalUsers = $stmt->fetch()['count'];
        
        // Total confessions
        $stmt = $db->query("SELECT COUNT(*) as count FROM confessions");
        $totalConfessions = $stmt->fetch()['count'];
        
        // Pending confessions
        $stmt = $db->query("SELECT COUNT(*) as count FROM confessions WHERE status = 'pending'");
        $pendingConfessions = $stmt->fetch()['count'];
        
        // Total friend connections
        $stmt = $db->query("SELECT COUNT(*) as count FROM friend_requests WHERE status = 'accepted'");
        $totalConnections = $stmt->fetch()['count'];
        
        // Most active users (by confessions)
        $stmt = $db->query("
            SELECT u.email, COUNT(c.id) as confession_count
            FROM users u
            LEFT JOIN confessions c ON u.id = c.user_id
            GROUP BY u.id
            ORDER BY confession_count DESC
            LIMIT 5
        ");
        $activeUsers = $stmt->fetchAll();
        
        // Mood distribution
        $stmt = $db->query("
            SELECT mood, COUNT(*) as count
            FROM confessions
            WHERE status = 'approved'
            GROUP BY mood
            ORDER BY count DESC
        ");
        $moodStats = $stmt->fetchAll();
        
        // Recent activity (last 7 days)
        $stmt = $db->query("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM confessions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $recentActivity = $stmt->fetchAll();
        
        Utils::sendResponse([
            'stats' => [
                'total_users' => $totalUsers,
                'total_confessions' => $totalConfessions,
                'pending_confessions' => $pendingConfessions,
                'total_connections' => $totalConnections,
                'active_users' => $activeUsers,
                'mood_stats' => $moodStats,
                'recent_activity' => $recentActivity
            ]
        ]);
        
    } catch (Exception $e) {
        Utils::sendError('Failed to fetch stats: ' . $e->getMessage(), 500);
    }
}

function handleGetPendingConfessions($db) {
    Auth::requireAdmin();
    
    $limit = min(intval($_GET['limit'] ?? 20), 100);
    $offset = intval($_GET['offset'] ?? 0);
    
    try {
        $stmt = $db->query("
            SELECT c.id, c.content, c.mood, c.tags, c.campus, c.created_at,
                   COUNT(r.id) as report_count
            FROM confessions c
            LEFT JOIN reports r ON c.id = r.confession_id
            WHERE c.status = 'pending'
            GROUP BY c.id
            ORDER BY report_count DESC, c.created_at ASC
            LIMIT ? OFFSET ?
        ", [$limit, $offset]);
        
        $confessions = $stmt->fetchAll();
        
        foreach ($confessions as &$confession) {
            $confession['tags'] = json_decode($confession['tags'], true) ?? [];
        }
        
        Utils::sendResponse(['confessions' => $confessions]);
        
    } catch (Exception $e) {
        Utils::sendError('Failed to fetch pending confessions: ' . $e->getMessage(), 500);
    }
}

function handleApproveConfession($db) {
    Auth::requireAdmin();

    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    $confessionId = end($parts);

    if (!is_numeric($confessionId)) {
        Utils::sendError('Invalid confession ID');
    }
    
    try {
        $stmt = $db->query("UPDATE confessions SET status = 'approved' WHERE id = ?", [$confessionId]);
        
        if ($stmt->rowCount() === 0) {
            Utils::sendError('Confession not found', 404);
        }
        
        Utils::sendResponse(['message' => 'Confession approved successfully']);
        
    } catch (Exception $e) {
        Utils::sendError('Failed to approve confession: ' . $e->getMessage(), 500);
    }
}

function handleDeleteConfession($db) {
    Auth::requireAdmin();

    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    $confessionId = end($parts);

    if (!is_numeric($confessionId)) {
        Utils::sendError('Invalid confession ID');
    }
    
    try {
        $stmt = $db->query("DELETE FROM confessions WHERE id = ?", [$confessionId]);
        
        if ($stmt->rowCount() === 0) {
            Utils::sendError('Confession not found', 404);
        }
        
        Utils::sendResponse(['message' => 'Confession deleted successfully']);
        
    } catch (Exception $e) {
        Utils::sendError('Failed to delete confession: ' . $e->getMessage(), 500);
    }
}

function handleCreateAnnouncement($db) {
    Auth::requireAdmin();
    
    $data = Utils::getRequestData();
    
    $required = ['title', 'content'];
    $missing = Utils::validateRequired($required, $data);
    
    if (!empty($missing)) {
        Utils::sendError('Missing required fields: ' . implode(', ', $missing));
    }
    
    try {
        $db->query(
            "INSERT INTO announcements (title, content, created_at) VALUES (?, ?, NOW())",
            [Utils::sanitizeInput($data['title']), Utils::sanitizeInput($data['content'])]
        );
        
        Utils::sendResponse(['message' => 'Announcement created successfully'], 201);
        
    } catch (Exception $e) {
        Utils::sendError('Failed to create announcement: ' . $e->getMessage(), 500);
    }
}
?>
