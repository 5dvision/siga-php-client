<?php

namespace SigaClient\Exception;

abstract class SigaException extends \RuntimeException
{
    /**
     * @param string $message
     * @param integer $code
     * @param \Throwable $previous
     */
    public function __construct($message, $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
