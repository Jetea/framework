<?php

namespace Jetea\Framework\Routing;

use Jetea\Framework\Application;
use Jetea\Support\Arr;

/**
 * 框架基础控制器
 */
abstract class Controller
{
    /**
     * @var Application
     */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected static $middleware = array();

    /**
     * @param string $action
     *
     * @return array
     */
    public static function getMiddleware($action)
    {
        return Arr::get(static::$middleware, $action, []);
    }
}
