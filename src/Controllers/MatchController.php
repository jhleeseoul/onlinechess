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
        $myInfo = $userModel->findById($authedUser->userId); // db 인스턴스
        
        // 1. 매칭 큐에서 나 자신과 비슷한 점수대의 상대를 찾음 (±100점)
        $minScore = $myInfo['points'] - 100;
        $maxScore = $myInfo['points'] + 100;
        $opponents = $redis->zRangeByScore(self::MATCHMAKING_QUEUE, $minScore, $maxScore);

        $opponentInfo = null;
        $opponentId = null;
        // 자기 자신은 제외하고 첫 번째 유저를 상대로 선택
        foreach ($opponents as $potentialOpponent) {
            if ((int)$potentialOpponent !== $myInfo['id']) {
                $opponentId = (int)$potentialOpponent;
                $opponentInfo = $userModel->findById($opponentId);
                break;
            }
        }
        
        if ($opponentId) {
            // 2. 매칭 성공!
            // 2-1. 큐에서 나와 상대를 제거
            $redis->zRem(self::MATCHMAKING_QUEUE, $myInfo['id'], $opponentId);
            
            // 2-2. 게임 생성 (MySQL)
            $gameModel = new Game();
            // 동전 던지기로 흑/백 결정
            $isWhite = (bool)rand(0, 1);
            $whitePlayerId = $isWhite ? $myInfo['id'] : $opponentId;
            $blackPlayerId = !$isWhite ? $myInfo['id'] : $opponentId;
            $gameId = $gameModel->createGame($whitePlayerId, $blackPlayerId);

            if (!$gameId) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to create a game.']);
                return;
            }

            // 2-3. 초기 게임 상태 생성 (Redis)
            $initialFen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
            $redisKey = "game:{$gameId}";
            $redis->hMSet($redisKey, [
                'fen' => $initialFen,
                'white_player_id' => $whitePlayerId,
                'black_player_id' => $blackPlayerId,
                'current_turn' => 'w',
                'status' => 'ongoing'
            ]);
            $redis->expire($redisKey, 3600); // 1시간 후 자동 소멸

            $myMatchData = [
                'status' => 'matched',
                'message' => 'Match found!',
                'game_id' => $gameId,
                'my_color' => $isWhite ? 'white' : 'black',
                'opponent' => [
                    'id' => $opponentInfo['id'],
                    'nickname' => $opponentInfo['nickname'],
                    'points' => $opponentInfo['points']
                ]
            ];
            $opponentMatchData = [
                'status' => 'matched',
                'message' => 'Match found!',
                'game_id' => $gameId,
                'my_color' => $isWhite ? 'black' : 'white',
                'opponent' => [
                    'id' => $myInfo['id'],
                    'nickname' => $myInfo['nickname'],
                    'points' => $myInfo['points']
                ]
            ];

            // 1. 두 번째 유저(나)에게는 즉시 응답
            http_response_code(200);
            echo json_encode($myMatchData);
            
            // 2. 첫 번째 유저(상대)의 결과 리스트에 데이터 PUSH
            $opponentResultList = "user_match_result:{$opponentId}";
            $redis->lPush($opponentResultList, json_encode($opponentMatchData));
            $redis->expire($opponentResultList, 60);

        } else {
            // 3. 매칭 실패 (상대가 없음)
            // 3-1. 나를 매칭 큐에 추가 (점수는 내 랭크 점수, 멤버는 내 ID)
            $redis->zAdd(self::MATCHMAKING_QUEUE, $myInfo['points'], $myInfo['id']);
            
            http_response_code(202); // Accepted
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
        
        // 30초 동안 내 매칭 결과 리스트에 데이터가 들어오길 대기
        $message = $redis->brPop([$userMatchResultList], 30);
        
        if ($message) {
            http_response_code(200);
            header('Content-Type: application/json');
            // $message[1] 에는 requestRankMatch에서 저장한 JSON 문자열이 들어있음
            echo $message[1]; 
        } else {
            http_response_code(200);
            echo json_encode(['status' => 'timeout']);
        }
    }
}