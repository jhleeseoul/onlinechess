<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Shop
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 모든 상점 아이템 목록을 조회합니다.
     * @param int|null $userId (선택) 제공될 경우, 해당 유저의 아이템 소유 여부를 포함합니다.
     * @return array
     */
    public function getAllItems(?int $userId = null): array
    {
        if ($userId) {
            // 로그인한 유저: 소유 여부(is_owned)를 함께 조회
            $sql = "
                SELECT 
                    i.id, i.item_type, i.name, i.description, i.price, i.asset_path,
                    (CASE WHEN ui.id IS NOT NULL THEN 1 ELSE 0 END) AS is_owned
                FROM 
                    items i
                LEFT JOIN 
                    user_items ui ON i.id = ui.item_id AND ui.user_id = :userId
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId]);
        } else {
            // 비로그인 유저: 기본 정보만 조회 (is_owned는 0으로)
            $sql = "
                SELECT 
                    id, item_type, name, description, price, asset_path, 0 AS is_owned
                FROM 
                    items
            ";
            $stmt = $this->db->query($sql);
        }
        
        return $stmt->fetchAll();
    }

    /**
     * 사용자가 아이템을 구매합니다.
     * @param int $userId
     * @param int $itemId
     * @return array ['status' => 'success|error', 'message' => '...']
     */
    public function purchaseItem(int $userId, int $itemId): array
    {
        try {
            $this->db->beginTransaction();

            // 1. 아이템 정보와 유저 정보를 동시에 잠금(Lock)하여 조회 (동시성 문제 방지)
            $sql = "SELECT i.price, u.coins
                    FROM items i
                    JOIN users u ON u.id = :userId
                    WHERE i.id = :itemId
                    FOR UPDATE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['itemId' => $itemId, 'userId' => $userId]);
            $result = $stmt->fetch();
            
            if (!$result) {
                throw new \Exception('아이템 또는 유저를 찾을 수 없습니다.');
            }
            $itemPrice = $result['price'];
            $userCoins = $result['coins'];

            // 2. 재화 확인
            if ($userCoins < $itemPrice) {
                throw new \Exception('재화가 부족합니다.');
            }

            // 3. 중복 구매 확인
            $sqlCheck = "SELECT id FROM user_items WHERE user_id = :userId AND item_id = :itemId";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->execute(['userId' => $userId, 'itemId' => $itemId]);
            if ($stmtCheck->fetch()) {
                throw new \Exception('이미 보유하고 있는 아이템입니다.');
            }

            // 4. 구매 처리
            // 4-1. 유저 재화 차감
            $sqlUser = "UPDATE users SET coins = coins - :price WHERE id = :userId";
            $stmtUser = $this->db->prepare($sqlUser);
            $stmtUser->execute(['price' => $itemPrice, 'userId' => $userId]);

            // 4-2. 인벤토리에 아이템 추가
            $sqlInventory = "INSERT INTO user_items (user_id, item_id) VALUES (:userId, :itemId)";
            $stmtInventory = $this->db->prepare($sqlInventory);
            $stmtInventory->execute(['userId' => $userId, 'itemId' => $itemId]);

            $this->db->commit();
            return ['status' => 'success', 'message' => '구매가 완료되었습니다.'];

        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * 특정 사용자의 인벤토리(보유 아이템 목록)를 조회합니다.
     * @param int $userId
     * @return array
     */
        public function getUserInventory(int $userId): array
    {
        $sql = "
            SELECT 
                ui.id AS user_item_id, 
                i.id AS item_id,
                i.item_type,
                i.name,
                i.description,
                i.asset_path,
                ui.acquired_at,
                (CASE 
                    WHEN i.item_type = 'profile_icon' AND u.profile_icon_id = i.id THEN 1
                    WHEN i.item_type = 'board_skin' AND u.board_skin_id = i.id THEN 1
                    WHEN i.item_type = 'piece_skin' AND u.piece_skin_id = i.id THEN 1
                    ELSE 0 
                END) AS is_equipped
            FROM 
                user_items ui
            JOIN 
                items i ON ui.item_id = i.id
            JOIN
                users u ON ui.user_id = u.id
            WHERE 
                ui.user_id = :userId
            ORDER BY 
                ui.acquired_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}