<?php

namespace Jetea\Framework\Exceptions;

/**
 * Jetea http exception
 */
class HttpException extends Exception
{
    protected $statusCode;

    /**
     * HttpException constructor.
     * @param int $statusCode
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct($statusCode = 200, $message = "", $code = 0, \Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取http状态码
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
