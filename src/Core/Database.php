<?php

namespace App\Core;

use PDO;
use PDOException;
class Database
{
    private static ?PDO $pdoInstance = null;
    private static ?\Redis $redisInstance = null; // Redis 인스턴스 변수 추가


    // 생성자를 private으로 선언하여 외부에서 new 키워드로 인스턴스 생성을 막음
    private function __construct() {}

    // 복제 생성자를 private으로 선언하여 인스턴스 복제를 막음
    private function __clone() {}

    // 대신 생성자 역할을 함
    public static function getInstance(): PDO
    {
        if (self::$pdoInstance === null) {
            $dbHost = $_ENV['DB_HOST'];
            $dbPort = $_ENV['DB_PORT'];
            $dbName = $_ENV['DB_DATABASE'];
            $dbUser = $_ENV['DB_USERNAME'];
            $dbPass = $_ENV['DB_PASSWORD'];

            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4"; // data source name

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 에러 발생 시 예외(Exception)를 던짐
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 결과를 연관 배열 형태로 가져옴
                PDO::ATTR_EMULATE_PREPARES   => false,                  // SQL Injection 방지를 위한 설정
            ];

            try {
                self::$pdoInstance = new PDO($dsn, $dbUser, $dbPass, $options);
            } catch (PDOException $e) {
                // 실제 서비스에서는 로그를 남기고 사용자에게는 일반적인 에러 메시지를 보여줘야 함
                // 여기서는 개발 편의를 위해 에러를 그대로 출력
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$pdoInstance;
    }

    /**
     * Redis 연결 인스턴스를 반환합니다. (싱글턴)
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