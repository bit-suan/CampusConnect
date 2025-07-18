<?php
require_once 'config.php';
require_once 'includes/utils.php'; // Ensure Utils is available for sendError

// Set JSON content type for API responses
header('Content-Type: application/json');

// Simple router
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove base path if running in subdirectory (for php -S, this is usually just '/')
$basePath = '/';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// --- DEBUGGING LOGS ---
// These will write to your PHP error log file (e.g., C:\xampp\php\logs\php_error_log)
error_log("Incoming Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Parsed Path: " . $path);
// --- END DEBUGGING LOGS ---

// Route API requests
if (strpos($path, 'api/') === 0) {
    $apiPath = substr($path, 4); // Remove 'api/' prefix

    error_log("API Path after stripping 'api/': " . $apiPath); // Log the extracted API path

    switch (true) {
        // Authentication & Authorization
        case $apiPath === 'register':
        case $apiPath === 'login':
        case $apiPath === 'logout':
        case $apiPath === 'me':
            require_once 'api/auth.php';
            break;

        // Peer Profile & Matching
        case $apiPath === 'profile':
            require_once 'api/profile.php';
            break;
        case $apiPath === 'match':
        case $apiPath === 'friends':
        case preg_match('/^request\/\d+$/', $apiPath):
        case preg_match('/^accept\/\d+$/', $apiPath):
        case preg_match('/^friend\/\d+$/', $apiPath) && $_SERVER['REQUEST_METHOD'] === 'DELETE':
            require_once 'api/match.php';
            break;

        // Mentorship System (added based on SRS)
        case $apiPath === 'mentors':
        case $apiPath === 'mentorship-request':
        case $apiPath === 'mentorship/accept':
            require_once 'api/mentorship.php'; // Will need to create this file
            break;

        // Anonymous Confession Platform
        case $apiPath === 'confessions':
        case preg_match('/^confessions\/\d+\/(upvote|downvote)$/', $apiPath): // e.g., /api/confessions/1/upvote
            require_once 'api/confessions.php';
            break;

        // Study Buddy & Meetup Scheduling (added based on SRS)
        case preg_match('/^study-invite\/\d+$/', $apiPath): // e.g., /api/study-invite/123
        case $apiPath === 'schedule':
            require_once 'api/schedule.php'; // Will need to create this file
            break;

        // Reporting & Toxic Content Detection (added based on SRS)
        case preg_match('/^report\/\d+$/', $apiPath): // e.g., /api/report/123
            require_once 'api/report.php'; // Will need to create this file
            break;

        // Admin Features
        case $apiPath === 'admin/stats':
        case $apiPath === 'moderation/pending':
        case $apiPath === 'announcement':
        case $apiPath === 'announcements': // To list announcements
        case preg_match('/^moderation\/approve\/\d+$/', $apiPath): // e.g., /api/moderation/approve/1
        case preg_match('/^moderation\/delete\/\d+$/', $apiPath): // e.g., /api/moderation/delete/1
            require_once 'api/admin.php';
            break;

        default:
            // If no case matches, send a 404 error
            Utils::sendError('Endpoint not found', 404);
            break;
    }
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>CampusConnect API</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f3f4f6;
                color: #1f2937;
            }

            header {
                background-color: #2563eb;
                padding: 1.5rem;
                text-align: center;
                color: white;
                font-size: 1.5rem;
                font-weight: bold;
            }

            .container {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
                padding: 2rem;
                max-width: 1000px;
                margin: 0 auto;
            }

            .card {
                background-color: white;
                border-radius: 1rem;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                padding: 1.5rem;
                transition: transform 0.2s ease;
            }

            .card:hover {
                transform: translateY(-4px);
            }

            .card h3 {
                margin-top: 0;
                font-size: 1.25rem;
                color: #111827;
            }

            .card p {
                color: #4b5563;
                font-size: 0.95rem;
            }

            footer {
                text-align: center;
                margin-top: 3rem;
                color: #6b7280;
                font-size: 0.9rem;
            }

            a {
                color: #2563eb;
                text-decoration: none;
            }

            a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <header>
            🎓 CampusConnect API
        </header>
        <div class="container">
            <div class="card">
                <h3>🔐 Register</h3>
                <p><strong>POST</strong> <code>/api/register</code><br>Register as a new user.</p>
            </div>
            <div class="card">
                <h3>🔑 Login</h3>
                <p><strong>POST</strong> <code>/api/login</code><br>Log into the platform.</p>
            </div>
            <div class="card">
                <h3>👤 Get Me</h3>
                <p><strong>GET</strong> <code>/api/me</code><br>Get the logged-in user's profile.</p>
            </div>
            <div class="card">
                <h3>📢 Confessions</h3>
                <p><strong>POST</strong> / <strong>GET</strong> <code>/api/confessions</code><br>Submit or fetch confessions.</p>
            </div>
            <div class="card">
                <h3>🛠️ Admin Stats</h3>
                <p><strong>GET</strong> <code>/api/admin/stats</code><br>View system stats (admin only).</p>
            </div>
        </div>
        <footer>
            &copy; <?php echo date('Y'); ?> CampusConnect. All rights reserved.
        </footer>
    </body>
    </html>
    <?php
}
