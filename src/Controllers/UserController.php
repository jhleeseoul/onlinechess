<?php

namespace App\Controllers;

use App\Models\User;

class UserController
{
    /**
     * 새로운 사용자 등록
     * @return void
     */
    public function register(): void
    {
        // 클라이언트에 받은 JSON 데이터를 php://input 스트림에서 읽음
        $input = (array)json_decode(file_get_contents('php://input'), true);

        // 입력값 검증
        if (!isset($input['username']) || !isset($input['password']) || !isset($input['nickname'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['message' => 'Username, password, and nickname are required.']);
            return;
        }
        
        $username = trim($input['username']);
        $password = $input['password'];
        $nickname = trim($input['nickname']);

        if (empty($username) || empty($password) || empty($nickname)) {
            http_response_code(400);
            echo json_encode(['message' => 'Input fields cannot be empty.']);
            return;
        }

        $userModel = new User();
        $result = $userModel->create($username, $password, $nickname);

        if ($result) {
            http_response_code(201); // Created
            echo json_encode(['message' => 'User created successfully.', 'userId' => $result]);
        } else {
            http_response_code(409); // Conflict (e.g., duplicate username/nickname)
            echo json_encode(['message' => 'User registration failed. Username or nickname may already exist.']);
        }
    }

    /**
     * 현재 로그인한 사용자의 정보를 반환
     * @return void
     */
    public function getMyInfo(): void
    {
        $authedUser = \App\Utils\Auth::getAuthUser();
        
        if ($authedUser === null) {
            http_response_code(401); // Unauthorized
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        // DB에서 전체 유저 정보 조회
        $userModel = new \App\Models\User();
        $userInfo = $userModel->findById($authedUser->userId);

        if (!$userInfo) {
            http_response_code(404); // Not Found
            echo json_encode(['message' => 'User not found.']);
            return;
        }

        // 3. 비밀번호 필드는 제외하고 응답
        unset($userInfo['password']);

        http_response_code(200);
        echo json_encode($userInfo);
    }

    /**
     * 현재 로그인한 사용자의 게임 전적을 조회
     * @return void
     */
    public function getMyMatches(): void
    {
        $authedUser = \App\Utils\Auth::getAuthUser();
        if ($authedUser === null) {
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $gameModel = new \App\Models\Game();
        $matches = $gameModel->getMatchesByUserId($authedUser->userId);

        http_response_code(200);
        echo json_encode($matches);
    }

    /**
     * 사용자 리더보드 조회
     * @return void
     */
    public function showLeaderboard(): void
    {
        $userModel = new \App\Models\User();
        // 추후 수정 : 클라이언트에서 limit 파라미터 받을 수 있음
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

        $leaderboard = $userModel->getLeaderboard($limit);

        http_response_code(200);
        echo json_encode($leaderboard);
    }

    /**
     * 사용자 아이템 장착
     * @param int $userItemId
     * @return void
     */
    public function equipUserItem(int $userItemId): void
    {
        $authedUser = \App\Utils\Auth::getAuthUser();
        if ($authedUser === null) {
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $userModel = new \App\Models\User();
        $success = $userModel->equipItem($authedUser->userId, $userItemId);

        if ($success) {
            http_response_code(200);
            echo json_encode(['message' => 'Item equipped successfully.']);
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Failed to equip item. You may not own this item.']);
        }
    }

    /**
     * 현재 로그인한 사용자의 정보 수정
     * @return void
     */
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
            // 비밀번호 길이 최소 8자 검사
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

    /**
     * 현재 로그인한 사용자의 진행 중인 게임 ID 조회
     * @return void
     */
    public function getCurrentGame(): void
    {
        $authedUser = \App\Utils\Auth::getAuthUser();
        if ($authedUser === null) {
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $userModel = new \App\Models\User();
        $currentGameId = $userModel->findCurrentGameByUserId($authedUser->userId);

        if ($currentGameId !== null) {
            http_response_code(200);
            echo json_encode(['game_id' => $currentGameId]);
        } else {
            http_response_code(200); // 게임이 없는 것도 정상적인 상태이므로 200 OK
            echo json_encode(['game_id' => null]);
        }
    }
}