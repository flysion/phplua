<?php

namespace Flysion\Lua\Exceptions;

class ModuleNotExistsException extends \Exception
{
    /**
     * @var string
     */
    public $method;

    /**
     * NotAllowImportException constructor.
     * @param string $method
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($method, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }
}