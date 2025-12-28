<?php

namespace App\Core;

class Router
{
    protected array $routes = [];

    public function addRoute(string $method, string $uri, array $action): void
    {
        // URI 패턴에서 {param} 형태를 정규식으로 변환
        $uri = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_]+)', $uri);
        $this->routes[] = [
            'method' => $method,
            'uri' => '#^' . $uri . '$#',
            'action' => $action
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['uri'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                [$controller, $methodName] = $route['action'];
                
                if (class_exists($controller) && method_exists($controller, $methodName)) {
                    $controllerInstance = new $controller();
                    call_user_func_array([$controllerInstance, $methodName], $params);
                    return;
                }
            }
        }
        
        http_response_code(404);
        echo json_encode(['message' => 'Not Found']);
    }
}