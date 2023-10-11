<?php

namespace Flysion\Lua\Exceptions;

class EvalException extends Exception
{
    private $luaCode;

    public function __construct($luaCode, \Throwable $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e);
        $this->luaCode = $luaCode;
    }

    /**
     * @return mixed
     */
    public function getLuaCode()
    {
        return $this->luaCode;
    }
}
