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
     * 새로운 사용자를 생성하고 기본 아이템을 지급합니다.
     * @param string $username
     * @param string $password
     * @param string $nickname
     * @return int|false 생성된 사용자의 ID 또는 실패 시 false
     */
    public function create(string $username, string $password, string $nickname): int|false
    {
        // 기본 아이템 ID (실제 서비스에서는 설정 파일 등에서 관리하는 것이 좋음)
        $defaultProfileIconId = 1;
        $defaultBoardSkinId = 2;
        $defaultPieceSkinId = 3;

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $this->db->beginTransaction();

            // 1. users 테이블에 사용자 생성 (기본 아이템 ID 포함)
            $sqlUser = "
                INSERT INTO users (username, password, nickname, profile_icon_id, board_skin_id, piece_skin_id) 
                VALUES (:username, :password, :nickname, :profile_icon_id, :board_skin_id, :piece_skin_id)
            ";
            $stmtUser = $this->db->prepare($sqlUser);
            $stmtUser->execute([
                ':username' => $username,
                ':password' => $hashedPassword,
                ':nickname' => $nickname,
                ':profile_icon_id' => $defaultProfileIconId,
                ':board_skin_id' => $defaultBoardSkinId,
                ':piece_skin_id' => $defaultPieceSkinId
            ]);
            
            $userId = (int)$this->db->lastInsertId();

            // 2. user_items 테이블에 기본 아이템 소유 관계 추가
            $defaultItems = [$defaultProfileIconId, $defaultBoardSkinId, $defaultPieceSkinId];
            $sqlItems = "INSERT INTO user_items (user_id, item_id) VALUES (:userId, :itemId)";
            $stmtItems = $this->db->prepare($sqlItems);
            $stmtItems->bindValue(':userId', $userId, PDO::PARAM_INT);

            foreach ($defaultItems as $itemId) {
                $stmtItems->bindValue(':itemId', $itemId, PDO::PARAM_INT);
                $stmtItems->execute();
            }

            $this->db->commit();
            return $userId;

        } catch (\PDOException $e) {
            $this->db->rollBack();
            // 로깅 필요
            return false;
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
     * ID로 사용자를 찾습니다. (장착한 아이템 정보 포함)
     * @param int $id
     * @return array|false 사용자 정보 또는 찾지 못했을 경우 false
     */
    public function findById(int $id): array|false
    {
        /*
         * LEFT JOIN을 사용한 이유:
         * 만약 유저가 아직 아무 아이템도 장착하지 않았다면 (e.g., profile_icon_id가 NULL),
         * 일반적인 INNER JOIN을 사용하면 해당 유저 정보가 아예 조회되지 않을 수 있습니다.
         * LEFT JOIN은 users 테이블을 기준으로, 일치하는 아이템이 없더라도 유저 정보는 항상 반환하고
         * 아이템 관련 필드는 NULL로 채워줍니다.
         */
        $sql = "
            SELECT 
                u.id, u.username, u.nickname, u.points, u.coins, u.password,
                u.profile_icon_id,
                u.board_skin_id,
                u.piece_skin_id,
                icon.asset_path AS profile_icon_path,
                board.asset_path AS board_skin_path,
                piece.asset_path AS piece_skin_path
            FROM 
                users u
            LEFT JOIN 
                items icon ON u.profile_icon_id = icon.id
            LEFT JOIN 
                items board ON u.board_skin_id = board.id
            LEFT JOIN 
                items piece ON u.piece_skin_id = piece.id
            WHERE 
                u.id = :id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * 랭킹/리더보드를 조회합니다. (프로필 아이콘 포함)
     * @param int $limit 조회할 사용자 수
     * @return array
     */
    public function getLeaderboard(int $limit = 100): array
    {
        $sql = "
            SELECT 
                ROW_NUMBER() OVER (ORDER BY u.points DESC) AS `rank`,
                u.id,
                u.nickname,
                u.points,
                i.asset_path AS profile_icon_path
            FROM 
                users u
            LEFT JOIN 
                items i ON u.profile_icon_id = i.id AND i.item_type = 'profile_icon'
            ORDER BY 
                u.points DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * 사용자가 소유한 아이템을 장착합니다.
     * @param int $userId
     * @param int $userItemId 사용자가 소유한 아이템의 고유 ID (user_items 테이블의 id)
     * @return bool 성공 여부
     */
    public function equipItem(int $userId, int $userItemId): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. 해당 아이템을 유저가 소유하고 있는지, 그리고 아이템의 타입을 확인
            $sqlCheck = "
                SELECT i.item_type, i.id as item_id
                FROM user_items ui
                JOIN items i ON ui.item_id = i.id
                WHERE ui.id = :userItemId AND ui.user_id = :userId
            ";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->execute(['userItemId' => $userItemId, 'userId' => $userId]);
            $itemInfo = $stmtCheck->fetch();

            if (!$itemInfo) {
                // 이 아이템을 소유하고 있지 않거나, 존재하지 않는 user_item_id
                throw new \Exception("Item not owned or does not exist.");
            }

            // 2. 아이템 타입에 따라 적절한 컬럼을 업데이트
            $columnToUpdate = match ($itemInfo['item_type']) {
                'profile_icon' => 'profile_icon_id',
                'board_skin' => 'board_skin_id',
                'piece_skin' => 'piece_skin_id',
                default => null,
            };

            if ($columnToUpdate === null) {
                throw new \Exception("Invalid item type for equipping.");
            }
            
            $sqlUpdate = "UPDATE users SET {$columnToUpdate} = :itemId WHERE id = :userId";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->execute(['itemId' => $itemInfo['item_id'], 'userId' => $userId]);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            // 로깅 필요
            return false;
        }
    }

    /**
     * 사용자 정보를 업데이트합니다.
     * @param int $userId
     * @param array $dataToUpdate ['nickname' => 'new_nick', 'password' => 'new_pass']
     * @return bool 성공 여부
     */
    public function updateUser(int $userId, array $dataToUpdate): bool
    {
        if (empty($dataToUpdate)) {
            return false;
        }

        // 비밀번호가 있다면 해싱 처리
        if (isset($dataToUpdate['password'])) {
            $dataToUpdate['password'] = password_hash($dataToUpdate['password'], PASSWORD_DEFAULT);
        }

        $setClauses = [];
        $params = ['userId' => $userId];

        foreach ($dataToUpdate as $key => $value) {
            // 허용된 필드만 업데이트하도록 필터링 (보안 강화)
            if (in_array($key, ['nickname', 'password'])) {
                $setClauses[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }

        if (empty($setClauses)) {
            return false; // 업데이트할 내용이 없음
        }
        
        $sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = :userId";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            // 닉네임 중복(UNIQUE 제약 조건 위반) 등
            // 로깅 필요
            return false;
        }
    }
}