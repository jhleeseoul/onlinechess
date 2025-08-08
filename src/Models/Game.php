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

    /**
     * ID로 게임 정보를 조회합니다.
     * @param int $gameId
     * @return array|false
     */
    public function getGameById(int $gameId): array|false
    {
        $sql = "SELECT * FROM games WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $gameId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * 게임 결과를 업데이트하고 플레이어의 점수를 조정합니다.
     * @param int $gameId
     * @param string $result 'white_win', 'black_win', 'draw'
     * @param string $endReason 'checkmate', 'stalemate', 'resign' 등
     * @return bool
     */
    public function updateGameResult(int $gameId, string $result, string $endReason): bool
    {
        $game = $this->getGameById($gameId);
        if (!$game) return false;

        $whitePlayerId = $game['white_player_id'];
        $blackPlayerId = $game['black_player_id'];
        
        // ELO 점수 계산 (간단한 K-factor 방식)
        $kFactor = 32;
        $userModel = new \App\Models\User();
        $whitePlayer = $userModel->findById($whitePlayerId);
        $blackPlayer = $userModel->findById($blackPlayerId);
        
        $whiteExpected = 1 / (1 + 10 ** (($blackPlayer['points'] - $whitePlayer['points']) / 400));
        $blackExpected = 1 / (1 + 10 ** (($whitePlayer['points'] - $blackPlayer['points']) / 400));
        
        $whiteActual = ($result === 'white_win') ? 1 : (($result === 'draw') ? 0.5 : 0);
        $blackActual = ($result === 'black_win') ? 1 : (($result === 'draw') ? 0.5 : 0);
        
        $whiteNewPoints = $whitePlayer['points'] + $kFactor * ($whiteActual - $whiteExpected);
        $blackNewPoints = $blackPlayer['points'] + $kFactor * ($blackActual - $blackExpected);
        
        // 재화 지급
        $winCoins = 100;
        $drawCoins = 25;
        $whiteNewCoins = $whitePlayer['coins'] + (($result === 'white_win') ? $winCoins : ($result === 'draw' ? $drawCoins : 0));
        $blackNewCoins = $blackPlayer['coins'] + (($result === 'black_win') ? $winCoins : ($result === 'draw' ? $drawCoins : 0));

        try {
            $this->db->beginTransaction();

            // 1. 게임 결과 업데이트
            $sqlGame = "UPDATE games SET result = :result, end_reason = :end_reason, end_at = NOW() WHERE id = :id";
            $stmtGame = $this->db->prepare($sqlGame);
            $stmtGame->execute(['result' => $result, 'end_reason' => $endReason, 'id' => $gameId]);
            
            // 2. 백 플레이어 점수 및 재화 업데이트
            $sqlWhite = "UPDATE users SET points = :points, coins = :coins WHERE id = :id";
            $stmtWhite = $this->db->prepare($sqlWhite);
            $stmtWhite->execute(['points' => round($whiteNewPoints), 'coins' => $whiteNewCoins, 'id' => $whitePlayerId]);
            
            // 3. 흑 플레이어 점수 및 재화 업데이트
            $sqlBlack = "UPDATE users SET points = :points, coins = :coins WHERE id = :id";
            $stmtBlack = $this->db->prepare($sqlBlack);
            $stmtBlack->execute(['points' => round($blackNewPoints), 'coins' => $blackNewCoins, 'id' => $blackPlayerId]);
            
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            // 로깅 필요
            return false;
        }
    }
}