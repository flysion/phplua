<?php

namespace Flysion\Lua\Exceptions;

class CallException extends Exception
{
    private $name;
    private $arguments;

    public function __construct($name, $arguments, \Throwable $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e);
        $this->name = $name;
        $this->useSelf = $useSelf;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getArguments()
    {
        return $this->arguments;
    }
}
