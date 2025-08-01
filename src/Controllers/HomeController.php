<?php

namespace App\Controllers;

use App\Core\Database;

class HomeController
{
    public function index(): void
    {
        // JSON 응답 형식으로 통일
        try{
            $pdo = Database::getInstance();
            $status = $pdo->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
            $message = 'Welcome to onlineChess API. DB Connection Successful: ' . $status;
        } catch (\PDOException $e) {
            $message = 'Database connection failed: ' . $e->getMessage();
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => $message]);
    }
}
