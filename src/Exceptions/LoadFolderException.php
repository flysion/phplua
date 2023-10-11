<?php

namespace Flysion\Lua\Exceptions;

class LoadFolderException extends Exception
{
    private $folder;

    public function __construct($folder, \Throwable $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e);
        $this->folder = $folder;
    }

    /**
     * @return mixed
     */
    public function getFolder()
    {
        return $this->folder;
    }
}
