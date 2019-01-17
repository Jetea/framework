<?php

namespace Jetea\Framework\Routing;

use FastRoute\RouteCollector;

/**
 * Class Routes
 * @package Jetea\Framework\Routing
 * @see https://github.com/nikic/FastRoute
 *
 * @method RouteCollector addRoute($httpMethod, $route, $handler)
 * @method RouteCollector addGroup($prefix, callable $callback)
 * @method RouteCollector get($route, $handler)
 * @method RouteCollector post($route, $handler)
 * @method RouteCollector put($route, $handler)
 * @method RouteCollector delete($route, $handler)
 * @method RouteCollector patch($route, $handler)
 * @method RouteCollector head($route, $handler)
 */
class Routes
{
    /**
     * @var RouteCollector
     */
    protected $routeCollector;

    public function __construct(RouteCollector $routeCollector)
    {
        $this->routeCollector = $routeCollector;
    }

    /**
     * @return RouteCollector
     */
    public function getRouteCollector()
    {
        return $this->routeCollector;
    }

    public function any($route, $handler)
    {
        $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $route, $handler);
    }

    public function __call($name, $arguments)
    {
        $this->routeCollector->{$name}(...$arguments);
    }
}
