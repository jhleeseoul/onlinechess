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

// 체스 로직 테스트용 라우트 (새로운 메소드로 변경)
$router->addRoute('GET', 'api/test/piece-moves', [App\Controllers\GameController::class, 'testPieceMoves']);

// 랭크 매치 요청 API 라우트
$router->addRoute('POST', 'api/match/rank', [App\Controllers\MatchController::class, 'requestRankMatch']);

// 게임 관련 API 라우트
$router->addRoute('POST', 'api/game/{gameId}/move', [App\Controllers\GameController::class, 'makeMove']);
$router->addRoute('GET', 'api/game/{gameId}/wait-for-move', [App\Controllers\GameController::class, 'waitForMove']);
$router->addRoute('POST', 'api/game/{gameId}/resign', [App\Controllers\GameController::class, 'resignGame']);

// 상점 관련 API 라우트
$router->addRoute('GET', 'api/shop/items', [App\Controllers\ShopController::class, 'listItems']);
$router->addRoute('POST', 'api/shop/items/{itemId}/buy', [App\Controllers\ShopController::class, 'buyItem']);

// 사용자 게임 기록 조회 API 라우트
$router->addRoute('GET', 'api/users/me/matches', [App\Controllers\UserController::class, 'getMyMatches']);

// 사용자 아이템 인벤토리 조회 API 라우트
$router->addRoute('GET', 'api/users/me/items', [App\Controllers\ShopController::class, 'getMyInventory']);

// 사용자 랭킹 조회 API 라우트
$router->addRoute('GET', 'api/leaderboard', [App\Controllers\UserController::class, 'showLeaderboard']);

// 5. 요청 처리
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = trim($_GET['url'] ?? '', '/');

$router->dispatch($requestMethod, $requestUri);