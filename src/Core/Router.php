<?php

namespace App\Core;

class Router
{
    protected array $routes = [];

    public function addRoute(string $method, string $uri, array $action): void
    {
        $this->routes[$method][$uri] = $action;
    }

    public function dispatch(string $method, string $uri): void
    {
        if (isset($this->routes[$method][$uri])) {
            [$controller, $methodName] = $this->routes[$method][$uri];
            
            if (class_exists($controller) && method_exists($controller, $methodName)) {
                $controllerInstance = new $controller();
                $controllerInstance->$methodName();
            } else {
                // 나중에 404 페이지 처리
                echo "404 Not Found - Controller or method not exists";
            }
        } else {
            // 나중에 404 페이지 처리
            echo "404 Not Found - Route not defined";
        }
    }
}