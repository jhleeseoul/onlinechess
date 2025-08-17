<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\Game;
use App\Models\User;
use App\Utils\Auth;

class MatchController
{
    private const MATCHMAKING_QUEUE = 'matchmaking_queue';

        public function requestRankMatch(): void
    {
        $authedUser = Auth::getAuthUser();
        if ($authedUser === null) {
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $redis = Database::getRedisInstance();
        $userModel = new User();
        // 내 정보는 이미 findById 개선으로 아이콘 경로까지 포함됨
        $myInfo = $userModel->findById($authedUser->userId);
        
        $minScore = $myInfo['points'] - 100;
        $maxScore = $myInfo['points'] + 100;
        $opponents = $redis->zRangeByScore(self::MATCHMAKING_QUEUE, $minScore, $maxScore);

        $opponentId = null;
        foreach ($opponents as $potentialOpponent) {
            if ((int)$potentialOpponent !== $myInfo['id']) {
                $opponentId = (int)$potentialOpponent;
                break;
            }
        }
        
        if ($opponentId) {
            // 2. 매칭 성공!
            // 2-1. 큐에서 나와 상대를 제거
            $redis->zRem(self::MATCHMAKING_QUEUE, $myInfo['id'], $opponentId);
            
            // 2-2. 상대방의 상세 정보를 조회
            $opponentInfo = $userModel->findById($opponentId);
            if (!$opponentInfo) {
                // 상대를 찾았지만 DB에 없는 경우 (예: 탈퇴), 나를 다시 큐에 넣고 대기
                $redis->zAdd(self::MATCHMAKING_QUEUE, $myInfo['points'], $myInfo['id']);
                http_response_code(202);
                echo json_encode(['status' => 'pending', 'message' => 'Opponent not found, retrying...']);
                return;
            }
            
            // 2-3. 게임 생성 (MySQL)
            $gameModel = new Game();
            $isWhite = (bool)rand(0, 1);
            $whitePlayerId = $isWhite ? $myInfo['id'] : $opponentId;
            $blackPlayerId = !$isWhite ? $myInfo['id'] : $opponentId;
            $gameId = $gameModel->createGame($whitePlayerId, $blackPlayerId);

            if (!$gameId) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to create a game.']);
                return;
            }

            // 2-4. 초기 게임 상태 생성 (Redis)
            $initialFen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
            $redisKey = "game:{$gameId}";
            $redis->hMSet($redisKey, [
                'fen' => $initialFen,
                'white_player_id' => $whitePlayerId,
                'black_player_id' => $blackPlayerId,
                'current_turn' => 'w',
                'status' => 'ongoing'
            ]);
            $redis->expire($redisKey, 3600);

            http_response_code(200);
            echo json_encode([
                'status' => 'matched',
                'message' => 'Match found!',
                'game_id' => $gameId,
                'my_color' => $isWhite ? 'white' : 'black',
                // 상대방 정보를 ID 대신 객체로 전달
                'opponent' => [
                    'id' => $opponentInfo['id'],
                    'nickname' => $opponentInfo['nickname'],
                    'points' => $opponentInfo['points'],
                    'profile_icon_path' => $opponentInfo['profile_icon_path']
                ]
            ]);

        } else {
            // 3. 매칭 실패 (상대가 없음) - 이전과 동일
            $redis->zAdd(self::MATCHMAKING_QUEUE, $myInfo['points'], $myInfo['id']);
            
            http_response_code(202);
            echo json_encode([
                'status' => 'pending',
                'message' => 'Finding an opponent...'
            ]);
        }
    }

    public function waitForMatch(): void
    {
        $authedUser = Auth::getAuthUser();
        if ($authedUser === null) {
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }
        
        $redis = Database::getRedisInstance();
        // 각 유저별로 매칭 결과를 받을 리스트 키 생성
        $userMatchResultList = "user_match_result:{$authedUser->userId}";
        error_log("User {$authedUser->userId} waiting for match on list: {$userMatchResultList} at " . date('Y-m-d H:i:s'));
        
        // 30초 동안 내 매칭 결과 리스트에 데이터가 들어오길 대기
        $message = $redis->brPop([$userMatchResultList], 30);
        
        if ($message) {
            error_log("User {$authedUser->userId} received match message at " . date('Y-m-d H:i:s') . ": " . $message[1]); 
        } else {
            http_response_code(200);
            echo json_encode(['status' => 'timeout']);
        }
    }
}