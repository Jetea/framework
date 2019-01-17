<?php

namespace Jetea\Framework\Pipeline;

use Closure;

/**
 * Class Pipeline
 * @package Jetea\Framework\Pipeline
 *
 * 参考 Laravel 实现
 * @see https://github.com/illuminate/pipeline/blob/5.3/Pipeline.php
 */
class Pipeline
{
    /**
     * The object being passed through the pipeline.
     *
     * @var mixed
     */
    protected $passable;

    /**
     * The array of class pipes.
     *
     * @var array
     */
    protected $pipes = [];

    /**
     * The method to call on each pipe.
     *
     * @var string
     */
    protected $method = 'handle';

    public function __construct()
    {
    }

    /**
     * Set the object being sent through the pipeline.
     *
     * @param  mixed  $passable
     * @return $this
     */
    public function send($passable)
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * Set the array of pipes.
     *
     * @param  array|mixed  $pipes
     * @return $this
     */
    public function through($pipes)
    {
        $this->pipes = is_array($pipes) ? $pipes : func_get_args();

        return $this;
    }

    /**
     * Run the pipeline with a final destination callback.
     *
     * @param  \Closure  $destination
     * @return mixed
     */
    public function then(Closure $destination)
    {
        $firstSlice = $this->getInitialSlice($destination);

        $callable = array_reduce(
            array_reverse($this->pipes), $this->getSlice(), $firstSlice
        );

        return $callable($this->passable);
    }

    /**
     * Get the initial slice to begin the stack call.
     *
     * @param  \Closure  $destination
     * @return \Closure
     */
    protected function getInitialSlice(Closure $destination)
    {
        return function ($passable) use ($destination) {
            return $destination($passable);
        };
    }

    /**
     * Get a Closure that represents a slice of the application onion.
     *
     * @return \Closure
     */
    protected function getSlice()
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                //字符串: 类名:参数1,参数2
                list($name, $parameters) = $this->parsePipeString($pipe);

                // If the pipe is a string we will parse the string and resolve the class out
                // of the dependency injection container. We can then build a callable and
                // execute the pipe function giving in the parameters that are required.
                $pipe = $this->getMiddleware($name);

                $parameters = array_merge([$passable, $stack], $parameters);

                // return call_user_func_array([$pipe, $this->method], $parameters);
                return $pipe->{$this->method}(...$parameters);
            };
        };
    }

    public function getMiddleware($middleware)
    {
        return new $middleware();
    }

    /**
     * Parse full pipe string to get name and parameters.
     *
     * @param  string $pipe 如 类名:参数1,参数2
     * @return array
     */
    protected function parsePipeString($pipe)
    {
        list($name, $parameters) = array_pad(explode(':', $pipe, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }
}
