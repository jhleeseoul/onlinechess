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
        // 게임 생성 시 초기 FEN 상태도 함께 저장
        $initialFen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $sql = "INSERT INTO games (white_player_id, black_player_id, game_type, result, fen) 
                VALUES (:white_player_id, :black_player_id, :game_type, 'pending', :fen)";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':white_player_id', $whitePlayerId, PDO::PARAM_INT);
            $stmt->bindValue(':black_player_id', $blackPlayerId, PDO::PARAM_INT);
            $stmt->bindValue(':game_type', $gameType);
            $stmt->bindValue(':fen', $initialFen);
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
     * @param string $fen 마지막 FEN 상태
     * @param string|null $pgn 전체 PGN
     * @return bool
     */
    public function updateGameResult(int $gameId, string $result, string $endReason, string $fen, ?string $pgn = null): bool
    {
        $game = $this->getGameById($gameId);
        if (!$game) return false;

        // 이미 게임이 종료되었다면 중복 처리 방지
        if ($game['result'] !== 'pending') {
            return false;
        }

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

            // 1. 게임 결과, 최종 FEN, PGN 업데이트
            $sqlGame = "UPDATE games SET result = :result, end_reason = :end_reason, fen = :fen, pgn = :pgn, end_at = NOW() WHERE id = :id";
            $stmtGame = $this->db->prepare($sqlGame);
            $stmtGame->execute(['result' => $result, 'end_reason' => $endReason, 'fen' => $fen, 'pgn' => $pgn, 'id' => $gameId]);
            
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
    
    /**
     * 특정 사용자의 모든 게임 전적을 조회합니다.
     * @param int $userId
     * @return array
     */
    public function getMatchesByUserId(int $userId): array
    {
        /*
         * 이 쿼리는 조금 복잡합니다.
         * 1. 내가 백일 때와 흑일 때를 모두 고려해야 합니다.
         * 2. 내가 백일 때는 흑 플레이어의 닉네임을, 내가 흑일 때는 백 플레이어의 닉네임을 'opponent_nickname'으로 가져와야 합니다.
         * 3. CASE 문을 사용하여 이 조건을 처리합니다.
         * 4. JOIN을 두 번 사용하여 white_player와 black_player의 닉네임을 각각 가져옵니다.
         */
        $sql = "
            SELECT 
                g.id, 
                g.game_type, 
                g.result, 
                g.end_reason, 
                g.start_at, 
                g.end_at,
                CASE 
                    WHEN g.white_player_id = :userId_case1 THEN 'white'
                    ELSE 'black' 
                END AS my_color,
                CASE 
                    WHEN g.white_player_id = :userId_case2 THEN u_black.nickname
                    ELSE u_white.nickname 
                END AS opponent_nickname
            FROM 
                games g
            JOIN 
                users u_white ON g.white_player_id = u_white.id
            JOIN 
                users u_black ON g.black_player_id = u_black.id
            WHERE 
                g.white_player_id = :userId_where1 OR g.black_player_id = :userId_where2
            ORDER BY 
                g.start_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'userId_case1' => $userId,
            'userId_case2' => $userId,
            'userId_where1' => $userId,
            'userId_where2' => $userId
        ]);
        return $stmt->fetchAll();
    }

        /**
     * 특정 게임의 상세 결과 정보를 조회합니다. (플레이어 아이콘 포함)
     * @param int $gameId
     * @return array|false
     */
    public function getGameResultDetails(int $gameId): array|false
    {
        $sql = "
            SELECT 
                g.id,
                g.result,
                g.end_reason,
                g.end_at,
                g.white_player_id,
                g.black_player_id,
                u_white.nickname AS white_nickname,
                u_white.points AS white_current_points,
                icon_white.asset_path AS white_icon_path,
                u_black.nickname AS black_nickname,
                u_black.points AS black_current_points,
                icon_black.asset_path AS black_icon_path
            FROM
                games g
            JOIN
                users u_white ON g.white_player_id = u_white.id
            JOIN
                users u_black ON g.black_player_id = u_black.id
            LEFT JOIN
                items icon_white ON u_white.profile_icon_id = icon_white.id
            LEFT JOIN
                items icon_black ON u_black.profile_icon_id = icon_black.id
            WHERE
                g.id = :gameId AND g.result != 'pending'
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':gameId', $gameId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }
}