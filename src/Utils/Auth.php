<?php

namespace App\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    /**
     * HTTP 요청 헤더에서 JWT 토큰을 추출하고 검증하여 인증된 사용자 정보를 반환
     * @return object|null 검증 성공 시 토큰의 payload, 실패 시 null
     */
    public static function getAuthUser(): ?object
    {
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return null;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        $parts = explode(' ', $authHeader);
        if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
            return null;
        }
        
        $token = $parts[1];

        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            return $decoded->data; 
        } catch (\Exception $e) {
            return null;
        }
    }
}