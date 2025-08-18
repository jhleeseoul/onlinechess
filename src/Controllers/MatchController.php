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

            // 응답 데이터 미리 준비
            $myMatchData = [
                'status' => 'matched',
                'message' => 'Match found!',
                'game_id' => $gameId,
                'my_color' => $isWhite ? 'white' : 'black',
                'opponent' => [
                    'id' => $opponentInfo['id'],
                    'nickname' => $opponentInfo['nickname'],
                    'points' => $opponentInfo['points'],
                    'profile_icon_path' => $opponentInfo['profile_icon_path']
                ]
            ];

            $opponentMatchData = [
                'status' => 'matched',
                'message' => 'Match found!',
                'game_id' => $gameId,
                'my_color' => !$isWhite ? 'white' : 'black',
                'opponent' => [
                    'id' => $myInfo['id'],
                    'nickname' => $myInfo['nickname'],
                    'points' => $myInfo['points'],
                    'profile_icon_path' => $myInfo['profile_icon_path']
                ]
            ];

            // 1. 대기 중이던 상대방에게 알림 보내기
            $opponentListKey = "match_wait_list:{$opponentId}";
            $redis->lPush($opponentListKey, json_encode($opponentMatchData));
            $redis->expire($opponentListKey, 60); // 1분 유지

            // 2. 지금 요청한 나에게는 바로 응답 보내기
            http_response_code(200);
            echo json_encode($myMatchData);

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

        /**
     * 매칭이 성사될 때까지 대기합니다. (롱 폴링)
     */
    public function waitForMatch(): void
    {
        $authedUser = Auth::getAuthUser();
        if ($authedUser === null) {
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $redis = Database::getRedisInstance();
        $myListKey = "match_wait_list:{$authedUser->userId}";

        // BRPOP으로 내 리스트에 데이터가 들어올 때까지 최대 30초 대기
        $message = $redis->brPop([$myListKey], 30);

        if ($message) {
            // $message[1]에 매칭 성공 데이터가 JSON 문자열로 들어있음
            $matchData = json_decode($message[1], true);
            http_response_code(200);
            echo json_encode($matchData);
        } else {
            // 30초 동안 매칭되지 않으면 타임아웃
            http_response_code(200);
            echo json_encode(['status' => 'pending', 'message' => 'Still searching...']);
        }
    }
}