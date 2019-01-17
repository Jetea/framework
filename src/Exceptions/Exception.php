<?php

namespace Jetea\Framework\Exceptions;

/**
 * Jetea exception
 *
 * @copyright sh7ning 2016.1
 * @author    sh7ning
 */
class Exception extends \Exception
{
    /**
     * Exception constructor.
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
