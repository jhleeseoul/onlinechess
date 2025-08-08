<?php

namespace App\Core;

class Router
{
    protected array $routes = [];

    public function addRoute(string $method, string $uri, array $action): void
    {
        // {placeholder} 형식을 정규식으로 변환
        $uri = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_]+)', $uri);
        $this->routes[] = [
            'method' => $method,
            'uri' => '#^' . $uri . '$#', // URI 패턴의 시작과 끝을 명시
            'action' => $action
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['uri'], $uri, $matches)) {
                // 라우트의 파라미터(예: {gameId})를 추출
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                [$controller, $methodName] = $route['action'];
                
                if (class_exists($controller) && method_exists($controller, $methodName)) {
                    $controllerInstance = new $controller();
                    // 추출한 파라미터를 컨트롤러 메소드의 인자로 전달
                    call_user_func_array([$controllerInstance, $methodName], $params);
                    return; // 일치하는 첫 번째 라우트에서 실행 종료
                }
            }
        }
        
        http_response_code(404);
        echo json_encode(['message' => 'Not Found']);
    }
}