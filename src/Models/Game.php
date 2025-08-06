<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Game
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 새로운 게임을 생성하고 게임 ID를 반환합니다.
     * @param int $whitePlayerId
     * @param int $blackPlayerId
     * @param string $gameType
     * @return int|false 생성된 게임의 ID 또는 실패 시 false
     */
    public function createGame(int $whitePlayerId, int $blackPlayerId, string $gameType = 'rank'): int|false
    {
        $sql = "INSERT INTO games (white_player_id, black_player_id, game_type, result) 
                VALUES (:white_player_id, :black_player_id, :game_type, 'pending')";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':white_player_id', $whitePlayerId, PDO::PARAM_INT);
            $stmt->bindValue(':black_player_id', $blackPlayerId, PDO::PARAM_INT);
            $stmt->bindValue(':game_type', $gameType);
            $stmt->execute();
            return (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            // 로깅 필요
            return false;
        }
    }
    
    // 게임 결과 업데이트, 게임 정보 조회 등의 메소드는 나중에 추가...
}