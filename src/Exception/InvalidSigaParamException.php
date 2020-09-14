<?php

namespace SigaClient\Exception;

class InvalidSigaParamException extends SigaException
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
