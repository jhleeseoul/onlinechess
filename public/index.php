<?php

// 1. Composer 오토로더 불러오기
require_once __DIR__ . '/../vendor/autoload.php';

// 2. 환경변수 파일 로드 (.env)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// 3. 라우터 초기화 (객체 생성)
$router = new App\Core\Router();

// 4. 라우트(경로 규칙) 정의
// 테스트용 홈페이지 라우트, 아무경로 없이 접속하면 HomeController의 index 메서드가 호출됨
$router->addRoute('GET', '', [App\Controllers\HomeController::class, 'index']);

// 회원가입 API 라우트
$router->addRoute('POST', 'api/users', [App\Controllers\UserController::class, 'register']);

// 로그인 API 라우트
$router->addRoute('POST', 'api/auth/login', [App\Controllers\AuthController::class, 'login']);

// 내 정보 조회 API 라우트 (인증 필요)
$router->addRoute('GET', 'api/users/me', [App\Controllers\UserController::class, 'getMyInfo']);

// 체스 로직 테스트용 라우트
$router->addRoute('GET', 'api/test/parse-fen', [App\Controllers\GameController::class, 'testFenParser']);

// 5. 요청 처리
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = trim($_GET['url'] ?? '', '/');

$router->dispatch($requestMethod, $requestUri);