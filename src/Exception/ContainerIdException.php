<?php

namespace SigaClient\Exception;

class ContainerIdException extends SigaException
{

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct("ContainerId is missing!");
    }
}
