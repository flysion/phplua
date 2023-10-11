<?php

namespace Flysion\Lua\Exceptions;

class IncludeException extends Exception
{
    private $includeFile;

    public function __construct($includeFile, \Throwable $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e);
        $this->includeFile = $includeFile;
    }

    /**
     * @return mixed
     */
    public function getIncludeFile()
    {
        return $this->includeFile;
    }
}
