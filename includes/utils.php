<?php

class Utils {
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
    
    public static function validateRequired($fields, $data) {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
    
    public static function sendResponse($data, $status = 200) {
        http_response_code($status);
        error_log("DEBUG: sendResponse - Status: " . $status . ", Data: " . json_encode($data));
        echo json_encode($data);
        // Attempt to flush output immediately
        if (ob_get_length() > 0) {
            ob_end_flush(); // End and flush current buffer
        }
        flush(); // Flush system output buffer
        exit();
    }
    
    public static function sendError($message, $status = 400) {
        http_response_code($status);
        error_log("DEBUG: sendError - Status: " . $status . ", Message: " . $message);
        echo json_encode(['error' => $message]);
        // Attempt to flush output immediately
        if (ob_get_length() > 0) {
            ob_end_flush(); // End and flush current buffer
        }
        flush(); // Flush system output buffer
        exit();
    }
    
    public static function getRequestData() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    public static function calculateMatchScore($user1, $user2) {
        $score = 0;
        
        // Goal matching (30 points)
        if ($user1['goal'] === $user2['goal']) {
            $score += 30;
        }
        
        // Department matching (25 points)
        if ($user1['department'] === $user2['department']) {
            $score += 25;
        }
        
        // Year matching (20 points)
        if (abs($user1['year'] - $user2['year']) <= 1) {
            $score += 20;
        }
        
        // Personality matching (15 points)
        if ($user1['personality'] === $user2['personality']) {
            $score += 15;
        }
        
        // Religion matching (10 points)
        if ($user1['religion'] === $user2['religion']) {
            $score += 10;
        }
        
        return $score;
    }
}
?>
