<?php

namespace App\Controllers;

class HomeController
{
    public function index(): void
    {
        // JSON 응답 형식으로 통일
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Welcome to PHP-Chess API']);
    }
}
