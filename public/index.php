<?php

// CORS (Cross-Origin Resource Sharing) 헤더 설정
// 로컬 개발 환경(VSCode Live Server)에서의 API 요청을 허용합니다.
header("Access-Control-Allow-Origin: http://127.0.0.1:5500"); // Live Server의 기본 주소
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// OPTIONS 요청에 대한 사전 처리 (Preflight Request)
// 브라우저는 실제 요청을 보내기 전에 OPTIONS 메소드로 서버가 요청을 허용하는지 먼저 확인합니다.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 1. Composer 오토로더 불러오기
require_once __DIR__ . '/../vendor/autoload.php';

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

// 내 현재 게임 정보 조회 API 라우트 (인증 필요)
$router->addRoute('GET', 'api/users/me/current-game', [App\Controllers\UserController::class, 'getCurrentGame']);

// 랭크 매치 요청 API 라우트
$router->addRoute('POST', 'api/match/rank', [App\Controllers\MatchController::class, 'requestRankMatch']);

// 매칭 결과 대기 API 라우트
$router->addRoute('GET', 'api/match/wait', [App\Controllers\MatchController::class, 'waitForMatch']);

// 프라이빗 매치 생성 및 대기 API 라우트
$router->addRoute('POST', 'api/match/private', [App\Controllers\MatchController::class, 'createPrivateMatch']);
$router->addRoute('GET', 'api/match/private/wait/{roomCode}', [App\Controllers\MatchController::class, 'waitForPrivateMatch']);
$router->addRoute('POST', 'api/match/private/join', [App\Controllers\MatchController::class, 'joinPrivateMatch']);

// 게임 관련 API 라우트

// 게임 보드 현황 API 라우트
$router->addRoute('GET', 'api/game/{gameId}/status', [App\Controllers\GameController::class, 'getGameStatus']);

// 게임 시작 및 이동 API 라우트
$router->addRoute('POST', 'api/game/{gameId}/move', [App\Controllers\GameController::class, 'makeMove']);

// 유효한 수 조회 API 라우트
$router->addRoute('GET', 'api/game/{gameId}/move/{coord}', [App\Controllers\GameController::class, 'getValidMoves']);

// 다음 수를 기다릴 때 불러오는 API 라우트
$router->addRoute('GET', 'api/game/{gameId}/wait-for-move', [App\Controllers\GameController::class, 'waitForMove']);

// 게임 포기 API 라우트
$router->addRoute('POST', 'api/game/{gameId}/resign', [App\Controllers\GameController::class, 'resignGame']);

// 게임 결과 조회 API 라우트
$router->addRoute('GET', 'api/game/{gameId}/result', [App\Controllers\GameController::class, 'getGameResult']);

// 상점 아이템 목록 조회 API 라우트
$router->addRoute('GET', 'api/shop/items', [App\Controllers\ShopController::class, 'listItems']);

// 아이템 구매 API 라우트
$router->addRoute('POST', 'api/shop/items/{itemId}/buy', [App\Controllers\ShopController::class, 'buyItem']);

// 사용자 게임 기록 조회 API 라우트
$router->addRoute('GET', 'api/users/me/matches', [App\Controllers\UserController::class, 'getMyMatches']);

// 사용자 아이템 인벤토리 조회 API 라우트
$router->addRoute('GET', 'api/users/me/items', [App\Controllers\ShopController::class, 'getMyInventory']);

// 사용자 아이템 장착 API 라우트
$router->addRoute('POST', 'api/users/me/items/{userItemId}/equip', [App\Controllers\UserController::class, 'equipUserItem']);

// 사용자 랭킹 조회 API 라우트
$router->addRoute('GET', 'api/leaderboard', [App\Controllers\UserController::class, 'showLeaderboard']);

// 무승부 제안 처리 API 라우트
$router->addRoute('POST', 'api/game/{gameId}/draw', [App\Controllers\GameController::class, 'handleDrawOffer']);

// 사용자 정보 업데이트 API 라우트
$router->addRoute('PATCH', 'api/users/me', [App\Controllers\UserController::class, 'updateMyInfo']);

// 5. 요청 처리
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = trim($_GET['url'] ?? '', '/');

$router->dispatch($requestMethod, $requestUri);