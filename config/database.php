<?php
// database.php

require_once __DIR__ . '/env.php';

Env::load(); // .env 파일 로드

class Database {
    private $host;
    private $user;
    private $pass;
    private $dbname;
    private $charset;
    private $pdo;
    
    public function __construct() {
        $this->host = Env::get('DB_HOST', 'localhost');
        $this->user = Env::get('DB_USERNAME', 'root');
        $this->pass = Env::get('DB_PASSWORD', '');
        $this->dbname = Env::get('DB_DATABASE', '');
        $this->charset = Env::get('DB_CHARSET', 'utf8mb4');

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

    public function testConnection() {
        try {
            $this->pdo->query("SELECT 1");
            return "데이터베이스 연결 성공!";
        } catch (PDOException $e) {
            return "연결 실패: " . $e->getMessage();
        }
    }
}

// 전역 DB 인스턴스 생성
$database = new Database();
$pdo = $database->getConnection();
?>