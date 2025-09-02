<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\ChessLogic;
use App\Models\Game;
use App\Models\User;
use App\Utils\Auth;

class GameController
{
    private function getGameData(int $gameId, int $userId): ?array
    {
        $redis = Database::getRedisInstance();
        $redisKey = "game:{$gameId}";
        $gameData = $redis->hGetAll($redisKey);

        // 1. Redis에 게임 데이터가 있는지 확인
        if (!empty($gameData)) {
            // Redis에 데이터가 있고, 현재 사용자가 게임의 플레이어인지 확인
            if ($userId != $gameData['white_player_id'] && $userId != $gameData['black_player_id']) {
                http_response_code(403);
                echo json_encode(['message' => 'You are not a player in this game.']);
                return null;
            }
            return $gameData;
        }

        // 2. Redis에 데이터가 없으면, DB에서 게임 데이터를 조회 (Cache Miss)
        $gameModel = new Game();
        $dbGameData = $gameModel->getGameById($gameId);

        if (!$dbGameData) {
            http_response_code(404);
            echo json_encode(['message' => 'Game not found.']);
            return null;
        }

        // 3. DB에 게임 데이터가 있고, 현재 사용자가 게임의 플레이어인지 확인
        if ($userId != $dbGameData['white_player_id'] && $userId != $dbGameData['black_player_id']) {
            http_response_code(403);
            echo json_encode(['message' => 'You are not a player in this game.']);
            return null;
        }

        // 4. DB에서 가져온 게임이 아직 진행 중인 경우, Redis에 복원 (Cache Hydration)
        if ($dbGameData['result'] === 'pending') {
            $gameDataToRedis = [
                'fen' => $dbGameData['fen'],
                'white_player_id' => $dbGameData['white_player_id'],
                'black_player_id' => $dbGameData['black_player_id'],
                'current_turn' => (new ChessLogic($dbGameData['fen']))->getCurrentTurn(), // FEN에서 현재 턴 추출
                'status' => 'ongoing'
            ];
            $redis->hMSet($redisKey, $gameDataToRedis);
            $redis->expire($redisKey, 3600); // 1시간 후 자동 소멸
            return $gameDataToRedis;
        } else {
            // 게임이 이미 종료된 경우, Redis에 저장하지 않고 DB 데이터 반환
            // 이 경우, GameController의 다른 메소드에서 'status' 등을 확인하여 적절히 처리해야 함
            return [
                'fen' => $dbGameData['fen'],
                'white_player_id' => $dbGameData['white_player_id'],
                'black_player_id' => $dbGameData['black_player_id'],
                'current_turn' => 'none', // 종료된 게임은 턴이 없음
                'status' => 'finished'
            ];
        }
    }

    public function makeMove(int $gameId): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) { 
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $gameData = $this->getGameData($gameId, $authedUser->userId);
        if (!$gameData) return;
        
        $isWhite = ($authedUser->userId == $gameData['white_player_id']);
        $isMyTurn = ($isWhite && $gameData['current_turn'] === 'w') || (!$isWhite && $gameData['current_turn'] === 'b');
        
        if (!$isMyTurn) {
            http_response_code(400);
            echo json_encode(['message' => 'Not your turn.']);
            return;
        }

        $input = (array)json_decode(file_get_contents('php://input'), true);
        if (!isset($input['from']) || !isset($input['to'])) { 
            http_response_code(400);
            echo json_encode(['message' => '`from` and `to` fields are required.']);
            return;
        }

        $logic = new ChessLogic($gameData['fen']);
        $newLogic = $logic->move($input['from'], $input['to'], $input['promotion'] ?? null);

        if ($newLogic === null) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid move.']);
            return;
        }
        
        $newFen = $newLogic->toFen();
        $pgn = $newLogic->toPgn($logic, $input['from'], $input['to'], $input['promotion'] ?? null);
        
        // Redis 상태 업데이트
        $redis = Database::getRedisInstance();
        $redisKey = "game:{$gameId}";
        
        $currentPgn = $redis->hGet($redisKey, 'pgn') ?? '';
        $redis->hMSet($redisKey, [
            'fen' => $newFen,
            'current_turn' => $newLogic->isCheckmate() || $newLogic->isStalemate() ? 'none' : $newLogic->getCurrentTurn(),
            'pgn' => $currentPgn . $pgn
        ]);
        
       // 롱 폴링을 위한 업데이트 알림 (PUBLISH 대신 LPUSH 사용)
        $updateListKey = "game_updates_list:{$gameId}";
        $redis->lPush($updateListKey, json_encode(['fen' => $newFen, 'isCheck' => $newLogic->isCheck()]));
        $redis->expire($updateListKey, 600); // 리스트는 10분 정도만 유지

        $response = ['message' => 'Move successful', 'fen' => $newFen, 'status' => 'ongoing', 'isCheck' => $newLogic->isCheck()];

        // 게임 종료 확인 및 처리
        if ($newLogic->isCheckmate() || $newLogic->isStalemate()) {
            $gameModel = new Game();
            $result = '';
            $endReason = '';
            if ($newLogic->isCheckmate()) {
                $result = $isWhite ? 'white_win' : 'black_win';
                $endReason = 'checkmate';
            } else {
                $result = 'draw';
                $endReason = 'stalemate';
            }
            $finalPgn = $redis->hGet($redisKey, 'pgn');
            $gameModel->updateGameResult($gameId, $result, $endReason, $newFen, $finalPgn);
            $redis->hSet($redisKey, 'status', 'finished');

            // 응답에 게임 종료 정보 추가
            $response['status'] = 'finished';
            $response['result'] = ['result' => $result, 'reason' => $endReason];
        }

        echo json_encode($response);
    }

    public function waitForMove(int $gameId): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) { 
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return; 
        }

        $gameData = $this->getGameData($gameId, $authedUser->userId);
        if (!$gameData) return;

        // Blocking List Pop (BRPOP) 사용
        $redis = Database::getRedisInstance();
        $updateListKey = "game_updates_list:{$gameId}";
        
        // brPop은 [키, 값] 배열을 반환, 30초 동안 대기
        $message = $redis->brPop([$updateListKey], 30); 
        
        if ($message) {
            // $message[0]는 리스트 키, $message[1]는 값
            $updateData = json_decode($message[1], true);
            echo json_encode(['status' => 'updated', 'data' => $updateData]);
        } else {
            // 30초 동안 아무 일도 없으면 타임아웃
            echo json_encode(['status' => 'timeout']);
        }
    }

    public function resignGame(int $gameId): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) { 
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $gameData = $this->getGameData($gameId, $authedUser->userId);
        if (!$gameData) return;

        $isWhite = ($authedUser->userId == $gameData['white_player_id']);
        $isMyTurn = ($isWhite && $gameData['current_turn'] === 'w') || (!$isWhite && $gameData['current_turn'] === 'b');
        
        if (!$isMyTurn) {
            http_response_code(400);
            echo json_encode(['message' => 'You can only resign on your turn.']);
            return;
        }
        
        // 이미 종료된 게임인지 확인
        if ($gameData['status'] === 'finished') {
            http_response_code(400);
            echo json_encode(['message' => 'This game has already finished.']);
            return;
        }
        
        $isWhite = ($authedUser->userId == $gameData['white_player_id']);
        
        // 게임 결과 및 종료 사유 결정
        $result = $isWhite ? 'black_win' : 'white_win';
        $endReason = 'resign';

        // DB에 게임 결과 업데이트 및 점수/재화 정산
        $gameModel = new Game();
        $redis = Database::getRedisInstance();
        $redisKey = "game:{$gameId}";
        $finalPgn = $redis->hGet($redisKey, 'pgn');
        $success = $gameModel->updateGameResult($gameId, $result, $endReason, $gameData['fen'], $finalPgn);

        if (!$success) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to update game result.']);
            return;
        }

        // Redis 상태 업데이트
        $redis = Database::getRedisInstance();
        $redisKey = "game:{$gameId}";
        $redis->hMSet($redisKey, [
            'status' => 'finished',
            'current_turn' => 'none'
        ]);

        // 상대방에게 게임 종료 알림
        $updateData = json_encode([
            'status' => 'finished',
            'result' => $result,
            'reason' => $endReason
        ]);
        $updateListKey = "game_updates_list:{$gameId}";
        $redis->lPush($updateListKey, $updateData);

        http_response_code(200);
        echo json_encode(['message' => 'You have resigned from the game.']);
    }

    public function getGameResult(int $gameId): void
    {
        $authedUser = \App\Utils\Auth::getAuthUser();
        if ($authedUser === null) {
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $gameModel = new \App\Models\Game();
        $resultDetails = $gameModel->getGameResultDetails($gameId);

        if (!$resultDetails) {
            http_response_code(404);
            echo json_encode(['message' => 'Finished game result not found.']);
            return;
        }
        
        // 이 게임의 플레이어인지 확인
        if ($authedUser->userId != $resultDetails['white_player_id'] && $authedUser->userId != $resultDetails['black_player_id']) {
            http_response_code(403);
            echo json_encode(['message' => 'You are not a player in this game.']);
            return;
        }

        http_response_code(200);
        echo json_encode($resultDetails);
    }

    
    // public function handleDrawOffer(int $gameId): void
    // {
    //     $authedUser = Auth::getAuthUser();
    //     if (!$authedUser) { 
    //         http_response_code(401);
    //         echo json_encode(['message' => 'Authentication required.']);
    //         return;
    //     }

    //     $gameData = $this->getGameData($gameId, $authedUser->userId);
    //     if (!$gameData) return;

    //     if ($gameData['status'] === 'finished') {
    //         http_response_code(400);
    //         echo json_encode(['message' => 'This game has already finished.']);
    //         return;
    //     }

    //     $input = (array)json_decode(file_get_contents('php://input'), true);
    //     $action = $input['action'] ?? null;

    //     $redis = Database::getRedisInstance();
    //     $redisKey = "game:{$gameId}";
    //     $drawOfferBy = $redis->hGet($redisKey, 'draw_offer_by');

    //     $myColor = ($authedUser->userId == $gameData['white_player_id']) ? 'w' : 'b';

    //     switch ($action) {
    //         case 'offer':
    //             // 이미 제안이 있거나, 내가 제안한 상태면 안됨
    //             if ($drawOfferBy) {
    //                 http_response_code(409); // Conflict
    //                 echo json_encode(['message' => 'A draw offer is already pending.']);
    //                 return;
    //             }
    //             $redis->hSet($redisKey, 'draw_offer_by', $myColor);
    //             $this->notifyOpponent($gameId, ['type' => 'draw_offer', 'offered_by' => $myColor]);
    //             echo json_encode(['message' => 'Draw offer sent.']);
    //             break;

    //         case 'accept':
    //             // 상대방이 제안한 상태여야만 수락 가능
    //             if (!$drawOfferBy || $drawOfferBy === $myColor) {
    //                 http_response_code(400);
    //                 echo json_encode(['message' => 'No valid draw offer to accept.']);
    //                 return;
    //             }

    //             $gameModel = new Game();
    //             $finalPgn = $redis->hGet($redisKey, 'pgn');
    //             $gameModel->updateGameResult($gameId, 'draw', 'agreement', $gameData['fen'], $finalPgn);
                
    //             $redis->hMSet($redisKey, ['status' => 'finished', 'draw_offer_by' => '']);
    //             $this->notifyOpponent($gameId, ['type' => 'draw_accepted']);
    //             echo json_encode(['message' => 'Draw offer accepted. Game over.']);
    //             break;

    //         case 'decline':
    //             // 상대방이 제안한 상태여야만 거절 가능
    //             if (!$drawOfferBy || $drawOfferBy === $myColor) {
    //                 http_response_code(400);
    //                 echo json_encode(['message' => 'No valid draw offer to decline.']);
    //                 return;
    //             }
    //             $redis->hSet($redisKey, 'draw_offer_by', ''); // 제안 상태 초기화
    //             $this->notifyOpponent($gameId, ['type' => 'draw_declined']);
    //             echo json_encode(['message' => 'Draw offer declined.']);
    //             break;

    //         default:
    //             http_response_code(400);
    //             echo json_encode(['message' => 'Invalid action. Use "offer", "accept", or "decline".']);
    //     }
    // }

    public function getGameStatus(int $gameId): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) { 
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        // getGameData 헬퍼를 재사용하여 권한 체크 및 데이터 조회
        $gameData = $this->getGameData($gameId, $authedUser->userId);
        if (!$gameData) return;

        // 게임이 이미 종료되었는지 확인
        if (isset($gameData['status']) && $gameData['status'] === 'finished') {
            http_response_code(410);
            echo json_encode(['message' => 'This game has already finished.']);
            return;
        }

        // 추가적으로 양쪽 플레이어의 상세 정보도 함께 보내줌
        $userModel = new User();
        $whitePlayer = $userModel->findById((int)$gameData['white_player_id']);
        $blackPlayer = $userModel->findById((int)$gameData['black_player_id']);

        unset($whitePlayer['password']);
        unset($blackPlayer['password']);
        
        $response = [
            'game_data' => $gameData,
            'white_player' => $whitePlayer,
            'black_player' => $blackPlayer
        ];

        http_response_code(200);
        echo json_encode($response);
    }

    public function getValidMoves(int $gameId, string $coord): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) { 
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $gameData = $this->getGameData($gameId, $authedUser->userId);
        if (!$gameData) return;

        // 자신의 턴에만 경로를 조회할 수 있도록 함
        $isWhite = ($authedUser->userId == $gameData['white_player_id']);
        $isMyTurn = ($isWhite && $gameData['current_turn'] === 'w') || (!$isWhite && $gameData['current_turn'] === 'b');
        
        if (!$isMyTurn) {
            http_response_code(400);
            echo json_encode(['message' => 'Not your turn.']);
            return;
        }
        
        $logic = new ChessLogic($gameData['fen']);
        $validMoves = $logic->getValidMovesForPiece($coord);

        http_response_code(200);
        echo json_encode($validMoves);
    }

    /**
     * 상대방에게 롱 폴링 알림을 보내는 헬퍼 메소드
     * @param int $gameId
     * @param array $data
     */
    private function notifyOpponent(int $gameId, array $data): void
    {
        $redis = Database::getRedisInstance();
        $updateListKey = "game_updates_list:{$gameId}";
        $redis->lPush($updateListKey, json_encode($data));
        $redis->expire($updateListKey, 300);
    }
}