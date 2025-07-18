<?php
require_once 'config.php';

class Database {
    private $connection;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false, // Explicitly set to false for better parameter handling
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            error_log("DEBUG: Database query - SQL: " . $sql);
            error_log("DEBUG: Database query - Params: " . json_encode($params));

            $stmt = $this->connection->prepare($sql);
            
            if ($stmt === false) {
                $errorInfo = $this->connection->errorInfo();
                error_log("ERROR: Failed to prepare statement: " . $errorInfo[2]);
                throw new Exception('Failed to prepare statement: ' . $errorInfo[2]);
            }

            $executeResult = $stmt->execute($params); // This is line 33
            
            if ($executeResult === false) {
                $errorInfo = $stmt->errorInfo();
                error_log("ERROR: Failed to execute statement: " . $errorInfo[2]);
                throw new Exception('Failed to execute statement: ' . $errorInfo[2]);
            }

            return $stmt;
        } catch (PDOException $e) {
            error_log("ERROR: Query failed in Database::query: " . $e->getMessage());
            throw new Exception('Query failed: ' . $e->getMessage());
        }
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}
?>
