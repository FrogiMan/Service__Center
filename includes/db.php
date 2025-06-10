<?php
// Параметры подключения
define('DB_HOST', 'localhost');
define('DB_NAME', 'service_center');
define('DB_USER', 'root');
define('DB_PASS', 'root');

class Database {
    private $conn;
    
    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        
        $this->conn->set_charset("utf8mb4");
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function escape($data) {
        return $this->conn->real_escape_string($data);
    }
    
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
}

$db = new Database();
$conn = $db->getConnection();
?>