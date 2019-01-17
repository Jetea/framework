<?php

namespace Jetea\Framework\Routing;

use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use Jetea\Framework\Application;
use Jetea\Framework\Exceptions\HttpException;
use Jetea\Framework\Foundation\Response;
use Jetea\Support\Arr;
use InvalidArgumentException;
use RuntimeException;

class Router
{
    /**
     * @var \Closure
     */
    protected $routeDefinitionCallback;

    /**
     * @var \Closure
     */
    protected $parseMapRouteCallback;

    /**
     * Path to fast route cache file. Set to false to disable route caching
     *
     * @var string|False
     */
    protected $cacheFile = false;

    /**
     * Router constructor.
     *
     * @param \Closure $routeDefinitionCallback
     * @param \Closure $parseMapRouteCallback
     * @param bool $cacheFile
     *
     * @throws \Exception
     */
    public function __construct(\Closure $routeDefinitionCallback, \Closure $parseMapRouteCallback = null, $cacheFile = false)
    {
        $this->routeDefinitionCallback = $routeDefinitionCallback;

        $this->parseMapRouteCallback = $parseMapRouteCallback;


        if (!is_string($cacheFile) && $cacheFile !== false) {
            throw new InvalidArgumentException('Router cacheFile must be a string or false');
        }

        if ($cacheFile !== false && !is_writable(dirname($cacheFile))) {
            throw new RuntimeException('Router cacheFile directory must be writable');
        }

        $this->cacheFile = $cacheFile;

        $this->dispatch();
    }

    /**
     * Dispatch the incoming request.
     *
     * @throws HttpException
     */
    protected function dispatch()
    {
        $routeInfo = $this->createDispatcher()->dispatch($this->getHttpMethod(), $this->getUri());

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new HttpException(404, 'NOT FOUND');
            case Dispatcher::METHOD_NOT_ALLOWED:
                // $allowedMethods = $routeInfo[1]; //允许的方法
                throw new HttpException(405, 'Method Not Allowed');
            case Dispatcher::FOUND:
                // $routeInfo = [Dispatcher::FOUND, 'handler', 'vars']
                break;
            default: //不会出现这种情况，这里运行不到
                throw new HttpException(404, 'NOT FOUND');
        }

        $this->parseRouteInfo($routeInfo[1], $routeInfo[2]);
    }

    /**
     * @return Dispatcher
     */
    protected function createDispatcher()
    {
        $routeDefinitionCallback = function (RouteCollector $r) {
            $routes = new Routes($r);
            $routeDefinitionCallback = $this->routeDefinitionCallback;
            $routeDefinitionCallback($routes);
        };

        if ($this->cacheFile) {
            $dispatcher = \FastRoute\cachedDispatcher($routeDefinitionCallback, [
                'cacheFile' => $this->cacheFile,
            ]);
        } else {
            $dispatcher = \FastRoute\simpleDispatcher($routeDefinitionCallback);
        }

        return $dispatcher;
    }

    /**
     * @return string
     */
    public function getHttpMethod()
    {
        return Arr::get($_SERVER, 'REQUEST_METHOD', 'GET');
    }

    /**
     * 参考 slim 实现
     * @return bool|string
     */
    public function getUri()
    {
        if (PHP_SAPI == 'cli') {
            return '/'. trim(Arr::get($_SERVER['argv'], 1, '/'), '/');
        }

        $requestUri = parse_url('http://example.com' . Arr::get($_SERVER, 'REQUEST_URI', ''), PHP_URL_PATH);

        $requestUri = empty($requestUri) ? '/' : $this->filterPath($requestUri);

        return '/' . trim($requestUri, '/');

        // nginx 这种配置不可能走下边逻辑 try_files $uri $uri/ /index.php?$query_string;

        // Path
        // $requestScriptName = parse_url(Arr::get($_SERVER, 'SCRIPT_NAME', ''), PHP_URL_PATH);
        // $requestScriptDir = dirname($requestScriptName);

        // parse_url() requires a full URL. As we don't extract the domain name or scheme,
        // we use a stand-in.
        // $requestUri = parse_url('http://example.com' . Arr::get($_SERVER, 'REQUEST_URI', ''), PHP_URL_PATH);
        //
        // $basePath = '';
        // $virtualPath = $requestUri;
        // if (stripos($requestUri, $requestScriptName) === 0) {
        //     $basePath = $requestScriptName;
        // } elseif ($requestScriptDir !== '/' && stripos($requestUri, $requestScriptDir) === 0) {
        //     $basePath = $requestScriptDir;
        // }
        //
        // if ($basePath) {
        //     $virtualPath = ltrim(substr($requestUri, strlen($basePath)), '/');
        // }
        //
        // $requestUri = empty($virtualPath) ? '/' : $this->filterPath($virtualPath);
        //
        // return '/' . ltrim($requestUri, '/');
    }

    /**
     * Filter Uri path.
     *
     * This method percent-encodes all reserved
     * characters in the provided path string. This method
     * will NOT double-encode characters that are already
     * percent-encoded.
     *
     * @param  string $path The raw uri path.
     * @return string       The RFC 3986 percent-encoded uri path.
     * @link   http://www.faqs.org/rfcs/rfc3986.html
     *
     * @see https://github.com/slimphp/Slim/blob/f9db2e68a8099a4e097921a4b04779cd99162f1d/Slim/Http/Uri.php#L644
     */
    protected function filterPath($path)
    {
        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/',
            function ($match) {
                return rawurlencode($match[0]);
            },
            $path
        );
    }

    /**
     * @var string $controller
     */
    protected $controller;

    /**
     * @var string $action
     */
    protected $action;

    /**
     * @var array $attr
     */
    protected $attr;

    public function getController()
    {
        return $this->controller;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getAttr($key = null, $default = null)
    {
        return Arr::get($this->attr, $key, $default);
    }

    const MAP_ROUTE = '/[{module}[/{controller}[/{action}[/{paths:.+}]]]]';

    const MAP_ROUTE_HANDLER = 'default';

    //路由默认 module controller action
    public static $defaultRouteVar = [
        'module'        => 'home',
        'controller'    => 'index',
        'action'        => 'index',
    ];

    public static function parseMapRouteInfo($handler, $var)
    {
        $var = array_merge(self::$defaultRouteVar, $var);
        $args = [];
        // 解析剩余的URL参数
        if (isset($var['paths'])) {
            // preg_replace e 参数 有Deprecated(不建议) 提示
            // preg_replace('@(\w+)\/([^\/]+)@e', '$var[\'\\1\']=strip_tags(\'\\2\');', implode('/',$paths));
            // 闭包函数需要 php 版本大于 5.3
            // $var = array();
            preg_replace_callback('@(\w+)\/([^\/]+)@', function ($matches) use (&$args) {
                $args[$matches[1]] = $matches[2];
            }, $var['paths']);
        }

        return [
            sprintf('%s\\%s', ucfirst($var['module']), ucfirst($var['controller'])),
            $var['action'],
            $args
        ];
    }

    /**
     * 解析映射路由和路由文件中的路由
     *
     * @param $handler
     * @param $var
     * @throws HttpException
     */
    protected function parseRouteInfo($handler, $var)
    {
        if (is_string($handler) && $handler == static::MAP_ROUTE_HANDLER) { //映射路由
            $parseMapRouteCallback = $this->parseMapRouteCallback;
            if (! is_callable($parseMapRouteCallback)) {
                throw new HttpException(404, 'Not Found.');
            }
            $routeInfo = $parseMapRouteCallback($handler, $var);
        } else {
            $routeInfo = $this->parseDefinitionRouteInfo($handler, $var);
        }

        try {
            list($controller, $action, $var) = $routeInfo;
        } catch (\Exception $e) {
            throw new HttpException(404, 'Not Found.');
        }

        $this->controller = $this->getControllerName($controller);

        if (empty($action)) {
            throw new HttpException(404, 'action not found');
        }

        $this->action = $action;

        $this->attr = $var;
    }

    /**
     * 解析路由文件中的普通路由
     *
     * @param $handler
     * @param $var
     * @return array
     */
    protected function parseDefinitionRouteInfo($handler, $var)
    {
        list($controller, $action) = array_pad(explode('@', $handler, 2), 2, '');

        return [$controller, $action, $var];
    }

    /**
     * @param string $controller
     * @return string
     * @throws HttpException
     */
    protected function getControllerName($controller)
    {
        //限制作用域，提高团队协作统一和项目可维护性
        if (PHP_SAPI == 'cli') { //命令行模式
            $controller = '\\App\\Commands\\' . $controller;
        } else {
            $controller = '\\App\\Controllers\\' . $controller;
        }

        if (! class_exists($controller)) {
            throw new HttpException(404, 'controller: ' . $controller . ' not found.');
        }

        return $controller;
    }

    /**
     *
     * 路由中间件
     *
     * @return array
     */
    public function getRouteMiddleware()
    {
        /** @var Controller $controller */
        $controller = $this->controller;
        $controllerMiddleware = $controller::getMiddleware($this->action);

        return (array)$controllerMiddleware;
    }

    /**
     * @param Application $app
     * @return Response
     * @throws \Exception
     */
    public function execute(Application $app)
    {
        /** @var Controller $controller */
        $controller = $this->controller;
        $response = (new $controller($app))->{$this->action}();

        return new Response($response);
    }
}
