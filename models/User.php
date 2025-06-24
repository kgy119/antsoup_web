<?php
// models/User.php
class User {
    private $pdo;
    private $table_name = "users";

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // 회원가입
    public function create($user_data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                      (username, email, password, phone_number, provider, provider_id) 
                      VALUES (:username, :email, :password, :phone_number, :provider, :provider_id)";

            $stmt = $this->pdo->prepare($query);

            $result = $stmt->execute([
                ':username' => $user_data['username'],
                ':email' => $user_data['email'],
                ':password' => $user_data['password'],
                ':phone_number' => $user_data['phone_number'] ?? null,
                ':provider' => $user_data['provider'] ?? 'local',
                ':provider_id' => $user_data['provider_id'] ?? null
            ]);

            if ($result) {
                return $this->pdo->lastInsertId();
            }

            return false;

        } catch (PDOException $e) {
            error_log("User creation error: " . $e->getMessage());
            return false;
        }
    }

    // 이메일 중복 확인
    public function emailExists($email) {
        try {
            $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':email' => $email]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            error_log("Email check error: " . $e->getMessage());
            return false;
        }
    }

    // 사용자명 중복 확인
    public function usernameExists($username) {
        try {
            $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':username' => $username]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            error_log("Username check error: " . $e->getMessage());
            return false;
        }
    }

    // ID로 사용자 조회
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':id' => $id]);

            return $stmt->fetch();

        } catch (PDOException $e) {
            error_log("Get user by ID error: " . $e->getMessage());
            return false;
        }
    }

    // 이메일로 사용자 조회
    public function getByEmail($email) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':email' => $email]);

            return $stmt->fetch();

        } catch (PDOException $e) {
            error_log("Get user by email error: " . $e->getMessage());
            return false;
        }
    }

    // 로그인 인증
    public function authenticate($email, $password) {
        try {
            $user = $this->getByEmail($email);
            
            if ($user && password_verify($password, $user['password'])) {
                // 마지막 로그인 시간 업데이트
                $this->updateLastLogin($user['id']);
                
                // 비밀번호 제거 후 반환
                unset($user['password']);
                return $user;
            }

            return false;

        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }

    // 마지막 로그인 시간 업데이트
    public function updateLastLogin($user_id) {
        try {
            $query = "UPDATE " . $this->table_name . " SET last_login = NOW() WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute([':id' => $user_id]);

        } catch (PDOException $e) {
            error_log("Update last login error: " . $e->getMessage());
            return false;
        }
    }

    // 이메일 인증 상태 업데이트
    public function updateEmailVerification($user_id, $verified = true) {
        try {
            $query = "UPDATE " . $this->table_name . " SET email_verified = :verified WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute([
                ':verified' => $verified ? 1 : 0,
                ':id' => $user_id
            ]);

        } catch (PDOException $e) {
            error_log("Update email verification error: " . $e->getMessage());
            return false;
        }
    }

    // 사용자 정보 업데이트
    public function update($user_id, $data) {
        try {
            $set_clauses = [];
            $params = [':id' => $user_id];

            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $set_clauses[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }

            if (empty($set_clauses)) {
                return false;
            }

            $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $set_clauses) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);

        } catch (PDOException $e) {
            error_log("User update error: " . $e->getMessage());
            return false;
        }
    }

    // 비밀번호 업데이트
    public function updatePassword($user_id, $new_password) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $query = "UPDATE " . $this->table_name . " SET password = :password WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute([
                ':password' => $hashed_password,
                ':id' => $user_id
            ]);

        } catch (PDOException $e) {
            error_log("Password update error: " . $e->getMessage());
            return false;
        }
    }
}
?>