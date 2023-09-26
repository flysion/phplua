<?php

namespace Flysion\Lua\Exceptions;

class CallException extends Exception
{
    private $name;
    private $arguments;
    private $useSelf;

    public function __construct($name, $arguments, $useSelf, \Throwable $e)
    {
        parent::__construct($e->getMessage(), $e->getCode());
        $this->name = $name;
        $this->arguments = $arguments;
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

    /**
     * @return mixed
     */
    public function getUseSelf()
    {
        return $this->useSelf;
    }
}
