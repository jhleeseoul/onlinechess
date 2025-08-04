<?php

namespace App\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;

class AuthController
{
    public function login(): void
    {
        $input = (array)json_decode(file_get_contents('php://input'), true);

        if (!isset($input['username']) || !isset($input['password'])) {
            http_response_code(400);
            echo json_encode(['message' => 'Username and password are required.']);
            return;
        }

        $userModel = new User();
        $user = $userModel->findByUsername($input['username']);

        // 1. 유저 존재 여부 및 비밀번호 검증
        if (!$user || !password_verify($input['password'], $user['password'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['message' => 'Invalid credentials.']);
            return;
        }

        // 2. JWT 페이로드(Payload) 생성
        $payload = [
            'iss' => "http://localhost/onlinechess", // 발급자 (누가 토큰을 발급했는가)
            'aud' => "http://localhost/onlinechess", // 수신자 (누가 토큰을 사용하는가)
            'iat' => time(),                         // 발급된 시간
            'exp' => time() + (60 * 60 * 24),        // 만료 시간 (예: 24시간)
            'data' => [                              // 실제 우리가 사용할 데이터
                'userId' => $user['id'],
                'username' => $user['username']
            ]
        ];

        // 3. JWT 토큰 생성
        $jwt = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

        http_response_code(200);
        echo json_encode([
            'message' => 'Login successful.',
            'token' => $jwt
        ]);
    }
}