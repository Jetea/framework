<?php

namespace Jetea\Framework;

use Jetea\Exception\HandleExceptions;
use Jetea\Exception\Handler;
use Jetea\Framework\Pipeline\Pipeline;
use Jetea\Framework\Routing\Router;
use Jetea\Framework\Routing\Routes;
use Jetea\Support\Arr;
use Jetea\Framework\Foundation\Response;

class Application
{
    /**
     * 私有克隆函数，防止外办克隆对象
     */
    private function __clone()
    {
    }

    /**
     * 框架 Application 单例，静态变量保存全局实例
     */
    private static $instance = null;

    /**
     * 应用单例
     *
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    protected function __construct()
    {
    }

    /**
     * 运行
     */
    public function run()
    {
        $this->handle()->send();
    }

    /**
     * 启动初始化
     *
     * @return Response
     */
    public function handle()
    {
        $this->handleExceptions();

        $this->initRuntime();

        $this->initRouter();

        return $this->sendThroughPipeline($this->getMiddleware(), function () {
            return $this->router->execute($this);
        });
    }

    /**
     * web 全局中间件
     */
    protected $middleware = [];

    protected function getMiddleware()
    {
        $middleware = [];
        if (PHP_SAPI != 'cli') { //命令行模式不走全局中间件
            $middleware = $this->middleware;
        }

        return array_merge(
            $middleware,
            $this->router->getRouteMiddleware()
        );
    }

    /**
     * Send the request through the pipeline with the given callback.
     *
     * @param  array  $middleware
     * @param  \Closure  $then
     * @return Response
     */
    protected function sendThroughPipeline(array $middleware, \Closure $then)
    {
        if (count($middleware) > 0) {
            return (new Pipeline())
                ->send($this)
                ->through($middleware)
                ->then($then);
        }

        return $then();
    }

    /**
     * 异常接管
     */
    protected function handleExceptions()
    {
        (new HandleExceptions($this->getExceptionsHandler()))->handle();
    }

    /**
     * @return Handler
     */
    protected function getExceptionsHandler()
    {
        return new Handler();
    }

    protected function initRuntime()
    {
        mb_internal_encoding('UTF-8');

        header(sprintf("X-Powered-By: %s", 'Jetea/1.0'));

        // 设置中国时区
        date_default_timezone_set('PRC');
    }

    /**
     * 应用配置
     *
     * - xhprof_dir util包路径
     *
     * @var array
     */
    protected $config = [
        'debug'         => false,
    ];

    /**
     * 获取应用配置
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function config($key = null, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * @var Router
     */
    protected $router;

    /**
     * 初始化路由
     *
     * @throws
     */
    protected function initRouter()
    {
        $this->router = new Router(function (Routes $routes) {

        });
    }

    /**
     * 获取路由
     *
     * @return Router
     */
    public function getRouter()
    {
        return $this->router;
    }

    public function getAttr($key = null, $default = null)
    {
        return $this->router->getAttr($key, $default);
    }
}
