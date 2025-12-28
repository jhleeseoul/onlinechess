<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\Game;
use App\Models\User;
use App\Utils\Auth;

class MatchController
{
    private const MATCHMAKING_QUEUE = 'matchmaking_queue';

    /**
     * 랭크 매치 요청
     * @return void
     */
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
            $redis->zRem(self::MATCHMAKING_QUEUE, $myInfo['id'], $opponentId);
            
            //상대방의 상세 정보를 조회
            $opponentInfo = $userModel->findById($opponentId);
            if (!$opponentInfo) {
                // 상대를 찾았지만 DB에 없는 경우 다시 큐에 넣고 대기
                $redis->zAdd(self::MATCHMAKING_QUEUE, $myInfo['points'], $myInfo['id']);
                http_response_code(202);
                echo json_encode(['status' => 'pending', 'message' => 'Opponent not found, retrying...']);
                return;
            }
            
            // 게임 생성 (MySQL)
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

            // 초기 게임 상태 생성 (Redis)
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

            // 대기 중이던 상대방에게 알림
            $opponentListKey = "match_wait_list:{$opponentId}";
            $redis->lPush($opponentListKey, json_encode($opponentMatchData));
            $redis->expire($opponentListKey, 60); // 1분 유지

            http_response_code(200);
            echo json_encode($myMatchData);

        } else {
            // 매칭 실패
            $redis->zAdd(self::MATCHMAKING_QUEUE, $myInfo['points'], $myInfo['id']);
            
            http_response_code(202);
            echo json_encode([
                'status' => 'pending',
                'message' => 'Finding an opponent...'
            ]);
        }
    }

    /**
     * 매칭이 성사될 때까지 대기 (롱 폴링)
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

    /**
     * 프라이빗 매치 방 생성
     */
    public function createPrivateMatch(): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) { 
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $redis = Database::getRedisInstance();
        
        // 6자리 랜덤 코드 생성 (중복 확인 포함)
        do {
            $roomCode = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
            $redisKey = "private_match:{$roomCode}";
        } while ($redis->exists($redisKey));

        // Redis에 방 정보 저장 (방 생성자 ID, 생성 시간)
        $redis->hMSet($redisKey, [
            'creator_id' => $authedUser->userId,
            'created_at' => time()
        ]);
        // 방은 10분간 유효
        $redis->expire($redisKey, 600);

        http_response_code(201); // Created
        echo json_encode(['room_code' => $roomCode]);
    }

    /**
     * 프라이빗 매치 대기 (롱 폴링)
     * @param string $roomCode
     */
    public function waitForPrivateMatch(string $roomCode): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) { 
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $redis = Database::getRedisInstance();
        
        // 이 방의 생성자가 맞는지 확인 (보안)
        $creatorId = $redis->hGet("private_match:{$roomCode}", 'creator_id');
        if ($creatorId != $authedUser->userId) {
            http_response_code(403); // Forbidden
            echo json_encode(['message' => 'You are not the creator of this room.']);
            return;
        }

        $listKey = "private_match_wait:{$roomCode}";
        
        $message = $redis->brPop([$listKey], 30);

        if ($message) {
            $matchData = json_decode($message[1], true);
            http_response_code(200);
            echo json_encode($matchData);
        } else {
            http_response_code(200);
            echo json_encode(['status' => 'pending', 'message' => 'Waiting for opponent...']);
        }
    }

    /**
     * 프라이빗 매치 참가
     */
    public function joinPrivateMatch(): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) { 
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }
        
        $input = (array)json_decode(file_get_contents('php://input'), true);
        $roomCode = $input['room_code'] ?? null;
        if (!$roomCode) {
            http_response_code(400);
            echo json_encode(['message' => 'Room code is required.']);
            return;
        }

        $redis = Database::getRedisInstance();
        $redisKey = "private_match:{$roomCode}";

        // 생성자 정보 조회
        $roomData = $redis->hGetAll($redisKey);
        if (empty($roomData)) {
            http_response_code(404);
            echo json_encode(['message' => 'Room not found or has expired.']);
            return;
        }
        $creatorId = (int)$roomData['creator_id'];

        if ($creatorId === $authedUser->userId) {
            http_response_code(400);
            echo json_encode(['message' => 'You cannot join your own room.']);
            return;
        }
        
        // 매칭 성공 처리
        $userModel = new User();
        $creatorInfo = $userModel->findById($creatorId);
        $joinerInfo = $userModel->findById($authedUser->userId);

        // 게임 생성
        $gameModel = new Game();
        $isCreatorWhite = (bool)rand(0, 1);
        $whitePlayerId = $isCreatorWhite ? $creatorInfo['id'] : $joinerInfo['id'];
        $blackPlayerId = !$isCreatorWhite ? $creatorInfo['id'] : $joinerInfo['id'];
        $gameId = $gameModel->createGame($whitePlayerId, $blackPlayerId, 'private');

        if (!$gameId) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create game.']);
            return;
        }
        
        // 초기 게임 상태 생성 (Redis)
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

            $myMatchData = [
                'status' => 'matched',
                'message' => 'Match found!',
                'game_id' => $gameId,
                'my_color' => $isCreatorWhite ? 'white' : 'black',
                'opponent' => [
                    'id' => $joinerInfo['id'],
                    'nickname' => $joinerInfo['nickname'],
                    'points' => $joinerInfo['points'],
                    'profile_icon_path' => $joinerInfo['profile_icon_path']
                ]
            ];

            $opponentMatchData = [
                'status' => 'matched',
                'message' => 'Match found!',
                'game_id' => $gameId,
                'my_color' => !$isCreatorWhite ? 'white' : 'black',
                'opponent' => [
                    'id' => $creatorInfo['id'],
                    'nickname' => $creatorInfo['nickname'],
                    'points' => $creatorInfo['points'],
                    'profile_icon_path' => $creatorInfo['profile_icon_path']
                ]
            ];

        // 응답 데이터 생성
        $joinerMatchData = [
            'status' => 'matched', 'game_id' => $gameId, 'my_color' => !$isCreatorWhite ? 'white' : 'black',
            'opponent' => ['id' => $creatorInfo['id'], 'nickname' => $creatorInfo['nickname'], 'points' => $creatorInfo['points'], 'profile_icon_path' => $creatorInfo['profile_icon_path'] /* ... */]
        ];
        $creatorMatchData = [
            'status' => 'matched', 'game_id' => $gameId, 'my_color' => $isCreatorWhite ? 'white' : 'black',
            'opponent' => ['id' => $joinerInfo['id'], 'nickname' => $joinerInfo['nickname'], 'points' => $joinerInfo['points'], 'profile_icon_path' => $joinerInfo['profile_icon_path'] /* ... */]
        ];

        $listKey = "private_match_wait:{$roomCode}";
        $redis->lPush($listKey, json_encode($creatorMatchData));
        $redis->expire($listKey, 60);

        $redis->del($redisKey);

        http_response_code(200);
        echo json_encode($joinerMatchData);
    }
}