<?php

namespace App\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    /**
     * HTTP 요청 헤더에서 JWT 토큰을 추출하고 검증합니다.
     * @return object|int 검증 성공 시 토큰의 payload, 실패 시 null
     */
    public static function getAuthUser(): object|int
    {
        // 1. Authorization 헤더 확인
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return 0;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        // "Bearer ajsdklfj..." 형식에서 토큰 부분만 추출
        $parts = explode(' ', $authHeader);
        if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
            return 1;
        }
        
        $token = $parts[1];

        // 2. JWT 토큰 디코딩 및 검증
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            // 성공 시, payload의 data 부분을 반환
            return $decoded->data; 
        } catch (\Exception $e) {
            // 토큰이 유효하지 않은 경우 (만료, 서명 불일치 등)
            return 2;
        }
    }
}