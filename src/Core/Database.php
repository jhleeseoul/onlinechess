<?php

namespace App\Core;

use PDO;
use PDOException;
class Database
{
    private static ?PDO $pdoInstance = null;
    private static ?\Redis $redisInstance = null;


    private function __construct() {}

    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$pdoInstance === null) {
            $dbHost = $_ENV['DB_HOST'];
            $dbPort = $_ENV['DB_PORT'];
            $dbName = $_ENV['DB_DATABASE'];
            $dbUser = $_ENV['DB_USERNAME'];
            $dbPass = $_ENV['DB_PASSWORD'];

            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4"; 

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
                PDO::ATTR_EMULATE_PREPARES   => false,                  
            ];

            try {
                self::$pdoInstance = new PDO($dsn, $dbUser, $dbPass, $options);
            } catch (PDOException $e) {
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$pdoInstance;
    }

    /**
     * Redis 연결 인스턴스 반환
     * @return \Redis
     */
    public static function getRedisInstance(): \Redis
    {
        if (self::$redisInstance === null) {
            self::$redisInstance = new \Redis();
            try {
                // .env 에 REDIS_HOST, REDIS_PORT 를 추가할 수 있습니다.
                $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
                $port = $_ENV['REDIS_PORT'] ?? 6379;
                self::$redisInstance->connect($host, $port);
            } catch (\RedisException $e) {
                // 실제 서비스에서는 로깅 후 에러 응답
                die('Redis connection failed: ' . $e->getMessage());
            }
        }
        return self::$redisInstance;
    }
}