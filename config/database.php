<?php
// 데이터베이스 연결 설정
define('DB_HOST', 'localhost');        
define('DB_USER', 'antsoup');    
define('DB_PASS', 'roalxkd1712');
define('DB_NAME', 'antsoup');   
define('DB_CHARSET', 'utf8mb4');

// PDO를 이용한 데이터베이스 연결 클래스
class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $charset = DB_CHARSET;
    private $pdo;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            
        } catch (PDOException $e) {
            die("데이터베이스 연결 실패: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    // 연결 테스트 메서드
    public function testConnection() {
        try {
            $stmt = $this->pdo->query("SELECT 1");
            return "데이터베이스 연결 성공!";
        } catch (PDOException $e) {
            return "연결 실패: " . $e->getMessage();
        }
    }
}

// 전역 데이터베이스 연결 인스턴스
$database = new Database();
$pdo = $database->getConnection();
?>