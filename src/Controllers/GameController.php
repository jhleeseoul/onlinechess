<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\ChessLogic;
use App\Models\Game;
use App\Utils\Auth;

class GameController
{
    private function getGameData(int $gameId, int $userId): ?array
    {
        $redis = Database::getRedisInstance();
        $redisKey = "game:{$gameId}";
        $gameData = $redis->hGetAll($redisKey);

        if (empty($gameData)) {
            http_response_code(404);
            echo json_encode(['message' => 'Game not found or has expired.']);
            return null;
        }

        if ($userId != $gameData['white_player_id'] && $userId != $gameData['black_player_id']) {
            http_response_code(403);
            echo json_encode(['message' => 'You are not a player in this game.']);
            return null;
        }
        
        return $gameData;
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
        
        // Redis 상태 업데이트
        $redis = Database::getRedisInstance();
        $redisKey = "game:{$gameId}";
        $redis->hMSet($redisKey, [
            'fen' => $newFen,
            'current_turn' => $newLogic->isCheckmate() || $newLogic->isStalemate() ? 'none' : $newLogic->getCurrentTurn()
        ]);
        
       // 롱 폴링을 위한 업데이트 알림 (PUBLISH 대신 LPUSH 사용)
        $updateListKey = "game_updates_list:{$gameId}";
        $redis->lPush($updateListKey, json_encode(['fen' => $newFen, 'isCheck' => $newLogic->isCheck()]));
        $redis->expire($updateListKey, 600); // 리스트는 10분 정도만 유지
       
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
            $gameModel->updateGameResult($gameId, $result, $endReason);
            $redis->hSet($redisKey, 'status', 'finished');
        }

        echo json_encode(['message' => 'Move successful', 'fen' => $newFen]);
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
        $success = $gameModel->updateGameResult($gameId, $result, $endReason);

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

    public function handleDrawOffer(int $gameId): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) { 
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $gameData = $this->getGameData($gameId, $authedUser->userId);
        if (!$gameData) return;

        if ($gameData['status'] === 'finished') {
            http_response_code(400);
            echo json_encode(['message' => 'This game has already finished.']);
            return;
        }

        $input = (array)json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? null;

        $redis = Database::getRedisInstance();
        $redisKey = "game:{$gameId}";
        $drawOfferBy = $redis->hGet($redisKey, 'draw_offer_by');

        $myColor = ($authedUser->userId == $gameData['white_player_id']) ? 'w' : 'b';

        switch ($action) {
            case 'offer':
                // 이미 제안이 있거나, 내가 제안한 상태면 안됨
                if ($drawOfferBy) {
                    http_response_code(409); // Conflict
                    echo json_encode(['message' => 'A draw offer is already pending.']);
                    return;
                }
                $redis->hSet($redisKey, 'draw_offer_by', $myColor);
                $this->notifyOpponent($gameId, ['type' => 'draw_offer', 'offered_by' => $myColor]);
                echo json_encode(['message' => 'Draw offer sent.']);
                break;

            case 'accept':
                // 상대방이 제안한 상태여야만 수락 가능
                if (!$drawOfferBy || $drawOfferBy === $myColor) {
                    http_response_code(400);
                    echo json_encode(['message' => 'No valid draw offer to accept.']);
                    return;
                }

                $gameModel = new Game();
                $gameModel->updateGameResult($gameId, 'draw', 'agreement');
                
                $redis->hMSet($redisKey, ['status' => 'finished', 'draw_offer_by' => '']);
                $this->notifyOpponent($gameId, ['type' => 'draw_accepted']);
                echo json_encode(['message' => 'Draw offer accepted. Game over.']);
                break;

            case 'decline':
                // 상대방이 제안한 상태여야만 거절 가능
                if (!$drawOfferBy || $drawOfferBy === $myColor) {
                    http_response_code(400);
                    echo json_encode(['message' => 'No valid draw offer to decline.']);
                    return;
                }
                $redis->hSet($redisKey, 'draw_offer_by', ''); // 제안 상태 초기화
                $this->notifyOpponent($gameId, ['type' => 'draw_declined']);
                echo json_encode(['message' => 'Draw offer declined.']);
                break;

            default:
                http_response_code(400);
                echo json_encode(['message' => 'Invalid action. Use "offer", "accept", or "decline".']);
        }
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

    public function updateMyInfo(): void
    {
        $authedUser = \App\Utils\Auth::getAuthUser();
        if ($authedUser === null) {
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $input = (array)json_decode(file_get_contents('php://input'), true);
        
        // 유효성 검사 (빈 값 방지)
        $dataToUpdate = [];
        if (!empty($input['nickname'])) {
            $dataToUpdate['nickname'] = trim($input['nickname']);
        }
        if (!empty($input['password'])) {
            // 비밀번호는 추가적인 유효성 검사가 필요할 수 있음 (예: 최소 길이)
            if (strlen($input['password']) < 8) {
                http_response_code(400);
                echo json_encode(['message' => 'Password must be at least 8 characters long.']);
                return;
            }
            $dataToUpdate['password'] = $input['password'];
        }

        if (empty($dataToUpdate)) {
            http_response_code(400);
            echo json_encode(['message' => 'No data to update.']);
            return;
        }

        $userModel = new \App\Models\User();
        $success = $userModel->updateUser($authedUser->userId, $dataToUpdate);

        if ($success) {
            http_response_code(200);
            echo json_encode(['message' => 'User information updated successfully.']);
        } else {
            http_response_code(409); // Conflict (예: 닉네임 중복)
            echo json_encode(['message' => 'Failed to update user information. Nickname may already be in use.']);
        }
    }
}