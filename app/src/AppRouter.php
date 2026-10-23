<?php

namespace MediumDubb\ConnectFour;

use JetBrains\PhpStorm\NoReturn;

class AppRouter
{
    private static array $routes = [];

    private static AppRouter $router;

    private function __construct()
    {
    }

    /**
     * Get the singleton instance of the Router.
     *
     * @return AppRouter The singleton instance of the Router.
     */
    public static function getRouter(): AppRouter
    {
        if (!isset(self::$router))
        {

            self::$router = new AppRouter();
        }

        return self::$router;
    }

    public function get(string $route, array|callable $action): void
    {
        $this->register($route, 'GET', $action);
    }
    public function post(string $route, array|callable $action): void
    {
        $this->register($route, 'POST', $action);
    }
    public function put(string $route, array|callable $action): void
    {
        $this->register($route, 'PUT', $action);
    }
    public function delete(string $route, array|callable $action): void
    {
        $this->register($route, 'DELETE', $action);
    }

    // Match request and call controller method
    public function dispatch() {
        // Clean up URI and strip query strings
        $url_parts = parse_url($_SERVER['REQUEST_SCHEME'] .'://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
        $requestedRoute = trim($url_parts['path'], '/') ?? '/';
        $routes = self::$routes[$_SERVER['REQUEST_METHOD']];

        foreach ($routes as $route => $action)
        {
            // Transform route to regex pattern.
            $routeRegex = preg_replace_callback('/{\w+(:([^}]+))?}/', function ($matches)
            {
                return isset($matches[1]) ? '(' . $matches[2] . ')' : '([a-zA-Z0-9_-]+)';
            }, $route);

            // Add the start and end delimiters.
            $routeRegex = '@^' . $routeRegex . '$@';

            // Check if the requested route matches the current route pattern.
            if (preg_match($routeRegex, $requestedRoute, $matches))
            {
                // Get all user requested path params values after removing the first matches.
                array_shift($matches);
                $routeParamsValues = $matches;

                // Find all route params names from route and save in $routeParamsNames
                $routeParamsNames = [];
                if (preg_match_all('/{(\w+)(:[^}]+)?}/', $route, $matches))
                {
                    $routeParamsNames = $matches[1];
                }

                // Combine between route parameter names and user provided parameter values.
                $routeParams = array_combine($routeParamsNames, $routeParamsValues);

                return  $this->resolveAction($action, $routeParams);
            }
        }

        $this->abort();
    }

    /**
     * Execute the action for a matched route.
     *
     * @param array|callable $action The action to execute.
     * @param array $routeParams The parameters extracted from the route.
     *
     * @return mixed The result of the action executed.
     */
    private function resolveAction(array|callable $action, array $routeParams): mixed
    {
        if (is_callable($action))
        {
            return call_user_func_array($action, $routeParams);
        }
        else if (is_array($action))
        {
            return call_user_func_array([new $action[0], $action[1]], $routeParams);
        }

        return null;
    }

    private function register(string $route, string $method, array|callable $action): void
    {
        // Trim slashes
        $route = trim($route, '/');

        // Assign action to the passed route
        self::$routes[$method][$route] = $action;
    }

    #[NoReturn]
    private function abort(): void
    {
        http_response_code(404);
        echo "404 Page not found";
        exit;
    }
}
