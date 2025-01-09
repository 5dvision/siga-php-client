<?php

namespace SigaClient\Exception;

use Throwable;

class InvalidSigaParamException extends SigaException
{

    /**
     * @param string $message
     * @param integer $code
     * @param \Throwable|null $previous
     */
    public function __construct($message, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
