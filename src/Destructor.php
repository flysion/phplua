<?php

namespace Flysion\Lua;

use Flysion\Lua\Exceptions\ModuleNotExistsException;

/**
 * 利用该类的析构函数释放 Lua 类
 * 由于 Lua 类内部保存了很多闭包 ref 了 Lua 类本身，导致 Lua 类无法被内存回收
 * 使用该类代理 Lua 类时由于该类没有任何 ref，所以该类很容易 destruct
 * 在该类 destruct 时引发 Lua 类的释放
 *
 * @mixin Lua
 */
class Destructor
{
    /**
     * @var Lua
     */
    protected $lua;

    /**
     * @param Lua|\Closure $lua
     */
    public function __construct($lua)
    {
        $this->lua = is_callable($lua) ? call_user_func($lua) : $lua;
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->lua->{$name} = $value;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->lua->{$name};
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->lua->{$name}(...$arguments);
    }

    /**
     *
     */
    public function __destruct()
    {
        // 使lua开始gc
        $this->lua->eval('collectgarbage("collect")');

        $this->lua->destroy();
    }
}
