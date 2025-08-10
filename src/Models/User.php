<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 새로운 사용자를 생성합니다.
     * @param string $username
     * @param string $password
     * @param string $nickname
     * @return int|false 생성된 사용자의 ID 또는 실패 시 false
     */
    public function create(string $username, string $password, string $nickname): int|false
    {
        // 비밀번호 해싱 (절대 평문으로 저장하면 안됩니다!)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, password, nickname) VALUES (:username, :password, :nickname)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':password', $hashedPassword);
            $stmt->bindValue(':nickname', $nickname);
            
            $stmt->execute();

            return (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            // 실제 서비스에서는 에러를 로깅해야 합니다.
            // 여기서는 일단 false를 반환하여 실패를 알립니다.
            // 에러 코드 23000은 UNIQUE 제약 조건 위반(중복된 username/nickname)입니다.
            if ($e->getCode() === '23000') {
                return false; 
            }
            // 그 외 다른 DB 에러
            throw $e;
        }
    }

    /**
     * 사용자 이름으로 사용자를 찾습니다.
     * @param string $username
     * @return array|false 사용자 정보 또는 찾지 못했을 경우 false
     */
    public function findByUsername(string $username): array|false
    {
        $sql = "SELECT * FROM users WHERE username = :username";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':username', $username);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * ID로 사용자를 찾습니다.
     * @param int $id
     * @return array|false 사용자 정보 또는 찾지 못했을 경우 false
     */
    public function findById(int $id): array|false
    {
        $sql = "SELECT * FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * 랭킹/리더보드를 조회합니다.
     * @param int $limit 조회할 사용자 수
     * @return array
     */
    public function getLeaderboard(int $limit = 100): array
    {
        // 랭크(순위)를 동적으로 계산하기 위해 변수 사용 (@rank)
        $sql = "
            SELECT 
                (@rank := @rank + 1) AS `rank`,
                id,
                nickname,
                points
            FROM 
                users, (SELECT @rank := 0) r
            ORDER BY 
                points DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}