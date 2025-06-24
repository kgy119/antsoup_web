<?php
/**
 * 수정된 includes/env.php
 * 주요 개선사항:
 * 1. getBool() 메서드 추가
 * 2. 더 강력한 파싱
 * 3. 에러 처리 개선
 */
class Env {
    private static $variables = [];
    private static $loaded = false;
    private static $loadedFile = null;
    
    /**
     * .env 파일 로드
     */
    public static function load($filePath) {
        if (self::$loaded && self::$loadedFile === $filePath) {
            return; // 이미 같은 파일이 로드됨
        }
        
        if (!file_exists($filePath)) {
            throw new Exception(".env file not found: {$filePath}");
        }
        
        if (!is_readable($filePath)) {
            throw new Exception(".env file is not readable: {$filePath}");
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("Failed to read .env file: {$filePath}");
        }
        
        self::parseContent($content, $filePath);
        self::$loaded = true;
        self::$loadedFile = $filePath;
    }
    
    /**
     * .env 파일 내용 파싱
     */
    private static function parseContent($content, $filePath) {
        $lines = explode("\n", $content);
        $lineNumber = 0;
        
        foreach ($lines as $line) {
            $lineNumber++;
            $line = trim($line);
            
            // 빈 줄이나 주석 건너뛰기
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // = 기호로 분할
            if (strpos($line, '=') === false) {
                error_log("Warning: Invalid line format in {$filePath} at line {$lineNumber}: {$line}");
                continue;
            }
            
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                error_log("Warning: Invalid variable format in {$filePath} at line {$lineNumber}: {$line}");
                continue;
            }
            
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            
            // 키 검증
            if (empty($key)) {
                error_log("Warning: Empty variable name in {$filePath} at line {$lineNumber}");
                continue;
            }
            
            // 값 처리 (따옴표 제거)
            $value = self::parseValue($value);
            
            // 환경 변수 설정
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
            self::$variables[$key] = $value;
        }
    }
    
    /**
     * 값 파싱 (따옴표 처리)
     */
    private static function parseValue($value) {
        $value = trim($value);
        
        // 빈 값 처리
        if (empty($value)) {
            return '';
        }
        
        // 따옴표로 감싸진 값 처리
        if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
            (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
            $value = substr($value, 1, -1);
        }
        
        return $value;
    }
    
    /**
     * 환경 변수 값 가져오기
     */
    public static function get($key, $default = null) {
        // 1. getenv()로 시도
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // 2. $_ENV에서 시도
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // 3. 내부 변수에서 시도
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }
        
        return $default;
    }
    
    /**
     * 불린 값 가져오기 (추가된 메서드)
     */
    public static function getBool($key, $default = false) {
        $value = self::get($key, $default);
        
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $lower = strtolower($value);
            return in_array($lower, ['true', '1', 'yes', 'on']);
        }
        
        return (bool) $value;
    }
    
    /**
     * 정수 값 가져오기
     */
    public static function getInt($key, $default = 0) {
        $value = self::get($key, $default);
        return (int) $value;
    }
    
    /**
     * 로드 상태 확인
     */
    public static function isLoaded() {
        return self::$loaded;
    }
    
    /**
     * 리셋 (테스트용)
     */
    public static function reset() {
        self::$variables = [];
        self::$loaded = false;
        self::$loadedFile = null;
    }
}

/**
 * 수정된 config/database.php
 * 주요 개선사항:
 * 1. 환경 변수명 통일 (DB_USERNAME -> DB_USER)
 * 2. 더 강력한 에러 처리
 * 3. 연결 상태 확인 개선
 * 4. 디버그 모드 지원
 */

// .env 파일 로드
require_once __DIR__ . '/env.php';

// 데이터베이스 연결 설정 (.env에서 읽어오기)
define('DB_HOST', Env::get('DB_HOST', 'localhost'));        
define('DB_USER', Env::get('DB_USERNAME', Env::get('DB_USER', 'root')));  // 두 가지 형태 지원    
define('DB_PASS', Env::get('DB_PASSWORD', Env::get('DB_PASS', '')));      // 두 가지 형태 지원
define('DB_NAME', Env::get('DB_DATABASE', Env::get('DB_NAME', 'antsoup')));  // 두 가지 형태 지원
define('DB_PORT', Env::getInt('DB_PORT', 3306));
define('DB_CHARSET', Env::get('DB_CHARSET', 'utf8mb4'));

class Database {
    private static $instance = null;
    private $connection;
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $port = DB_PORT;
    private $charset = DB_CHARSET;
    private $lastError = null;
    
    private function __construct() {
        $this->connect();
    }
    
    private function __clone() {}
    
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
    
    /**
     * 싱글톤 인스턴스 가져오기
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 데이터베이스 연결
     */
    private function connect() {
        try {
            // DSN 구성 (포트 포함)
            $dsn = "mysql:host={$this->host}";
            if ($this->port != 3306) {
                $dsn .= ";port={$this->port}";
            }
            $dsn .= ";dbname={$this->dbname};charset={$this->charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_TIMEOUT => 30 // 30초 타임아웃
            ];
            
            $this->connection = new PDO($dsn, $this->user, $this->pass, $options);
            
            // 개발 환경에서 추가 설정
            if (Env::getBool('APP_DEBUG', false)) {
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
                error_log("Database connected successfully to {$this->host}:{$this->port}/{$this->dbname}");
            }
            
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            $errorMessage = "Database connection failed: " . $e->getMessage();
            error_log($errorMessage);
            
            // 개발 환경에서만 상세 에러 표시
            if (Env::getBool('APP_DEBUG', false)) {
                throw new Exception("데이터베이스 연결 실패: " . $e->getMessage() . " (Host: {$this->host}:{$this->port}, DB: {$this->dbname}, User: {$this->user})");
            } else {
                throw new Exception("데이터베이스 연결에 실패했습니다.");
            }
        }
    }
    
    /**
     * PDO 연결 객체 반환
     */
    public function getConnection() {
        // 연결 상태 확인 및 재연결
        if (!$this->isConnected()) {
            $this->connect();
        }
        
        return $this->connection;
    }
    
    /**
     * 연결 테스트
     */
    public function testConnection() {
        try {
            if (!$this->connection) {
                throw new Exception("No connection established");
            }
            
            $stmt = $this->connection->query("SELECT 1 as test, NOW() as current_time");
            $result = $stmt->fetch();
            
            return [
                'success' => true,
                'message' => '데이터베이스 연결 성공!',
                'connection_details' => [
                    'host' => $this->host,
                    'port' => $this->port,
                    'database' => $this->dbname,
                    'user' => $this->user,
                    'charset' => $this->charset
                ],
                'server_info' => [
                    'version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
                    'connection_status' => $this->connection->getAttribute(PDO::ATTR_CONNECTION_STATUS),
                    'server_info' => $this->connection->getAttribute(PDO::ATTR_SERVER_INFO),
                    'client_version' => $this->connection->getAttribute(PDO::ATTR_CLIENT_VERSION),
                    'driver_name' => $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME)
                ],
                'test_query' => $result,
                'dsn_used' => "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}"
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '연결 실패: ' . $e->getMessage(),
                'connection_details' => [
                    'host' => $this->host,
                    'port' => $this->port,
                    'database' => $this->dbname,
                    'user' => $this->user,
                    'charset' => $this->charset
                ],
                'error_code' => $e->getCode(),
                'dsn_used' => "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '연결 오류: ' . $e->getMessage(),
                'last_error' => $this->lastError
            ];
        }
    }
    
    /**
     * 연결 상태 확인
     */
    public function isConnected() {
        try {
            if (!$this->connection) {
                return false;
            }
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * 트랜잭션 시작
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * 트랜잭션 커밋
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * 트랜잭션 롤백
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * 마지막 삽입 ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * 준비된 쿼리 실행
     */
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    /**
     * 직접 쿼리 실행
     */
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    /**
     * 쿼리 실행 및 결과 반환
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $errorMessage = "Query execution failed: " . $e->getMessage() . " | SQL: " . $sql;
            error_log($errorMessage);
            
            if (Env::getBool('APP_DEBUG', false)) {
                throw new Exception("쿼리 실행 실패: " . $e->getMessage() . " | SQL: " . $sql);
            } else {
                throw new Exception("데이터베이스 오류가 발생했습니다.");
            }
        }
    }
    
    /**
     * 단일 행 조회
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 모든 행 조회
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * 마지막 에러 가져오기
     */
    public function getLastError() {
        return $this->lastError;
    }
}

/**
 * 수정된 test/test_connection.php
 * 더 강력한 진단 기능 포함
 */

// test/test_connection.php
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// 에러 리포팅 설정
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // 설정 파일 로드
    require_once '../config/database.php';
    
    // Env 클래스 로드 상태 확인
    if (!class_exists('Env')) {
        throw new Exception('Env class not found. Check includes/env.php');
    }
    
    if (!Env::isLoaded()) {
        throw new Exception('.env file not loaded. Check config/env.php');
    }
    
    // 환경 변수 확인
    $envCheck = [
        'DB_HOST' => Env::get('DB_HOST'),
        'DB_USER' => Env::get('DB_USERNAME', Env::get('DB_USER')),
        'DB_NAME' => Env::get('DB_DATABASE', Env::get('DB_NAME')),
        'DB_PORT' => Env::get('DB_PORT', 3306),
        'DB_CHARSET' => Env::get('DB_CHARSET', 'utf8mb4'),
        'APP_DEBUG' => Env::getBool('APP_DEBUG', false)
    ];
    
    // 필수 환경 변수 확인
    $missingEnvVars = [];
    foreach (['DB_HOST', 'DB_USER', 'DB_NAME'] as $requiredVar) {
        if (empty($envCheck[$requiredVar])) {
            $missingEnvVars[] = $requiredVar;
        }
    }
    
    if (!empty($missingEnvVars)) {
        throw new Exception('Missing required environment variables: ' . implode(', ', $missingEnvVars));
    }
    
    // 데이터베이스 연결 테스트
    $database = Database::getInstance();
    $connectionTest = $database->testConnection();
    
    if (!$connectionTest['success']) {
        throw new Exception('Database connection test failed: ' . $connectionTest['message']);
    }
    
    // PDO 객체 가져오기
    $pdo = $database->getConnection();
    
    // 사용자 테이블 존재 확인
    $tableCheck = [];
    $requiredTables = ['users', 'email_verifications', 'password_resets'];
    
    foreach ($requiredTables as $tableName) {
        $query = "SHOW TABLES LIKE ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tableName]);
        $exists = $stmt->rowCount() > 0;
        
        $tableCheck[$tableName] = [
            'exists' => $exists,
            'structure' => null,
            'count' => 0
        ];
        
        if ($exists) {
            // 테이블 구조 확인
            $query = "DESCRIBE {$tableName}";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $tableCheck[$tableName]['structure'] = $stmt->fetchAll();
            
            // 레코드 수 확인
            $query = "SELECT COUNT(*) as count FROM {$tableName}";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $tableCheck[$tableName]['count'] = $result['count'];
        }
    }
    
    // PHP 확장 모듈 확인
    $extensions = [
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'mysql' => extension_loaded('mysql'),
        'mysqli' => extension_loaded('mysqli'),
        'openssl' => extension_loaded('openssl'),
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json')
    ];
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'message' => 'Database connection and environment check successful',
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => [
            'variables' => $envCheck,
            'php_version' => phpversion(),
            'extensions' => $extensions,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ],
        'connection' => $connectionTest,
        'tables' => $tableCheck,
        'database_info' => $database->getConnection()->query("SELECT VERSION() as mysql_version, @@sql_mode as sql_mode, @@time_zone as timezone")->fetch()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    
    // 상세한 에러 정보 (개발 환경에서만)
    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // 개발 환경에서 추가 디버그 정보 제공
    if (defined('APP_DEBUG') && APP_DEBUG || (class_exists('Env') && Env::getBool('APP_DEBUG', false))) {
        $errorResponse['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'env_loaded' => class_exists('Env') ? Env::isLoaded() : false,
            'constants' => [
                'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'undefined',
                'DB_USER' => defined('DB_USER') ? DB_USER : 'undefined',
                'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'undefined',
                'DB_PORT' => defined('DB_PORT') ? DB_PORT : 'undefined'
            ]
        ];
    }
    
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>