<?php

namespace Flysion\Lua;

use Flysion\Lua\Exceptions\ModuleNotExistsException;

/**
 * @link https://www.php.net/manual/zh/book.lua.php
 * @link http://www.lua.org/manual/5.4/
 *
 * @mixin \Lua
 */
class Lua
{
    /**
     * 存放用户暴露给lua的方法
     *
     * @var CallbackStore
     */
    protected $callbackStore;

    /**
     * @link https://www.php.net/manual/zh/book.lua.php
     * @var \Lua
     */
    protected $interpreter;

    /**
     * 暴露给lua的模块列表
     * lua可以通过"import"方法导入到lua中
     *
     * @var mixed[]
     */
    protected $modules = [];

    /**
     * lua包路径
     *
     * @var string[]
     */
    protected $packagePaths = [];

    /**
     * @param mixed[] $modules
     * @param string[] $packagePaths
     * @throws
     */
    public function __construct($modules = [], $packagePaths = [])
    {
        array_push($this->packagePaths, ...$packagePaths, ...$this->defaultPackagePaths());

        $this->modules = $modules;
        $this->callbackStore = new CallbackStore();

        $this->initInterpreter();
    }

    /**
     * 初始化lua解释器
     */
    protected function initInterpreter()
    {
        $this->interpreter = new \Lua;
        $this->setPackagePath();

        $this->interpreter->eval(<<<LUA
function __set(name, value)
    _G[name] = __data2lua(value)
end

function __call(name, ...)
    __data2lua(...)
    return _G[name](...)
end

function __register(name, dest)
    _G[name] = function(...)
        return __data2lua(__exception2lua(__call_func__(dest, ...)))
    end
end

function __meta_gc(dest)
    __exception2lua(__gc__(dest))
end

function __meta_call(dest, ...)
    return __data2lua(__exception2lua(__call_func__(dest, ...)))
end

function __meta_index(dest, key)
    if string.sub(key, 1, 1) == '@' then
        return __data2lua(__exception2lua(__read_property__(dest, string.sub(key, 2))))
    end

    return __function2lua(__exception2lua(__call_method__(dest, key)))
end

function __meta_newindex(dest, key, value)
    if string.sub(key, 1, 1) == '@' then
        __exception2lua(__write_property__(dest, string.sub(key, 2), value))
    else
        __exception2lua(__write_property__(dest, key, value))
    end
end

function __meta_tostring(dest)
    return dest.__toString()
end

function __data2lua(...)
    local parameters = {...}

    for k, v in pairs(parameters) do
        if type(v) == 'table' and v.__custom__ then
            parameters[k] = __custom2lua(v)
        elseif type(v) == 'table' then
            for k1, v1 in pairs(v) do
                parameters[k][k1] = __data2lua(v1)
            end
        else
            parameters[k] = v
        end
    end

    return table.unpack(parameters)
end

function __custom2lua(r)
    if r.type == 'function' then
        return __function2lua(r)
    elseif r.type == 'object' then
        return __object2lua(r)
    elseif r.type == 'resource' then
        return __resource2lua(r)
    else
        return r
    end
end

function __function2lua(r)
    return setmetatable(r, {
        __gc = __meta_gc,
        __call = __meta_call
    })
end

function __object2lua(r)
    local m = {
        __gc = __meta_gc,
        __index = __meta_index,
        __newindex = __meta_newindex,
    }

    if r.tostring then
        m.__tostring = __meta_tostring
    end

    return setmetatable(r, m)
end

function __resource2lua(r)
    return setmetatable(r, {
        __gc = __meta_gc
    })
end

function __exception2lua(r)
    if type(r) == 'table' and r.__exception__ then
        error(r)
    end

    return r
end

function import(name, ...)
    return __data2lua(__exception2lua(__import__(name, ...)))
end
LUA
        );

        $this->interpreter->registerCallback('__import__', function () {
            try {
                return $this->luaImport(...func_get_args());
            } catch (\Throwable $e) {
                return $this->exception2lua($e);
            }
        });

        $this->interpreter->registerCallback('__read_property__', function($custom, $key) {
            try {
                return $this->data2lua($this->callbackStore->get($custom['index'])->{$key});
            } catch (\Throwable $e) {
                return $this->exception2lua($e);
            }
        });

        $this->interpreter->registerCallback('__write_property__', function($custom, $key, $value) {
            try {
                $this->callbackStore->get($custom['index'])->{$key} = $value;
            } catch (\Throwable $e) {
                return $this->exception2lua($e);
            }
        });

        $this->interpreter->registerCallback('__gc__', function($custom) {
            try {
                $this->callbackStore->unRef($custom['index']);
            } catch (\Throwable $e) {
                return $this->exception2lua($e);
            }
        });

        $this->interpreter->registerCallback('__call_func__', function($custom, ...$arguments) {
            try {
                return $this->data2lua(
                    call_user_func_array($this->callbackStore->get($custom['index']), $this->data2php($arguments))
                );
            } catch (\Throwable $e) {
                return $this->exception2lua($e);
            }
        });

        $this->interpreter->registerCallback('__call_method__', function($custom, $key) {
            try {
                return $this->function2lua([$this->callbackStore->get($custom['index']), $key]);
            } catch (\Throwable $e) {
                return $this->exception2lua($e);
            }
        });
    }

    /**
     * @return \Lua
     */
    public function interpreter()
    {
        return $this->interpreter;
    }

    /**
     * 默认的lua包查找路径(package.path)
     *
     * @return string[]
     */
    protected function defaultPackagePaths()
    {
        return [
            sprintf('%s/?.lua', realpath(__DIR__ . '/../lua/lib')),
        ];
    }

    /**
     * 设置lua包查找路径(package.path)
     *
     * @param string ...$path
     */
    public function setPackagePath(...$path)
    {
        array_push($path, ...$this->packagePaths);

        if(count($path) === 0) {
            return;
        }

        $this->eval(sprintf('package.path="%s;;"', implode(';', $path)));
    }

    /**
     * lua的import逻辑
     *
     * @param string|string[] $name
     * @param mixed ...$arguments
     * @return array|\Closure
     * @throws Exceptions\ModuleNotExistsException
     * @throws \ReflectionException
     */
    protected function luaImport($name, ...$arguments)
    {
        if(is_array($name)) {
            $modules = [];
            foreach($name as $k => $v) {
                $modules[is_numeric($k) ? $v : $k] = $this->luaImport($v);
            }
            return $modules;
        }

        $module = array_dot_get($this->modules, $name);
        if(!is_null($module)) {
            return $this->data2lua($module);
        }

        $method = "lua_{$name}_module";
        if(method_exists($this, $method)) {
            return $this->data2lua($this->{$method}(...$arguments));
        }

        $method = "lua_{$name}_function";
        if(method_exists($this, $method)) {
            return $this->function2lua([$this, $method]); // 在 __call_func__ 进行异常捕获；在eval/call等方法throws
        }

        if(function_exists($name)) {
            return $this->function2lua($name); // 在 __call_func__ 进行异常捕获；在eval/call等方法throws
        }

        throw new Exceptions\ModuleNotExistsException($name, "Module \"{$name}\" does not exist");
    }

    /**
     * 把php数据转换成lua数据
     *
     * 基本数据类型可以直接传给lua使用，但是一些对象啊、资源啊等复杂数据类型就没办法传给lua了。
     * 这个时候需要把资源放在php数组里边，然后把数组下标传给lua。lua通过setmetatable方法把他们伪装成对象、函数
     *
     *
     * @param $value
     * @return array
     */
    public function data2lua($value)
    {
        if(is_scalar($value) || is_null($value)) {
            return $value;
        }

        if(is_callable($value)) {
            return $this->function2lua($value);
        }

        if(is_object($value)) {
            return $this->object2lua($value);
        }

        if(is_resource($value)) {
            return $this->resource2lua($value);
        }

        if(isset($value['__custom__']) && $value['__custom__']) {
            return $value;
        }

        foreach($value as $k => $v) {
            $value[$k] = $this->data2lua($v);
        }

        return $value;
    }

    /**
     * 把php数据转换成lua的table
     *
     * @param string $type
     * @param array $data
     * @return array
     */
    protected function customize2lua(string $type, array $data)
    {
        return array_merge($data, [ '__custom__' => true, 'type' => $type ]);
    }

    /**
     * 把php对象转换成lua的table
     * lua内部使用setmetatable方法使table包装成像对象一样调用
     *
     * @param object $object
     * @return array
     * @throws \ReflectionException
     */
    protected function object2lua(object $object)
    {
        return $this->customize2lua('object', [
            'index' => $this->callbackStore->add($object),
            'tostring' => method_exists($object, '__toString'),
        ]);
    }

    /**
     * 把php函数转换成lua的table
     * lua内部使用setmetatable方法使table包装成可调用的
     *
     * @param callable $func
     * @return array
     */
    protected function function2lua(callable $func)
    {
        return $this->customize2lua('function', [
            'index' => $this->callbackStore->add($func)
        ]);
    }

    /**
     * 把php资源转换成lua的table
     * table有一个属性"index"，当lua把该table传给php的时候，php从callbackStroe[index]获得该资源
     *
     * @param resource $resource
     * @return array
     */
    protected function resource2lua($resource)
    {
        return $this->customize2lua('resource', [
            'index' => $this->callbackStore->add($resource)
        ]);
    }

    /**
     * （当lua调用php的方法，php抛出异常时）把php异常转换成lua异常
     * 1.lua收到该异常，会通过error方法抛出
     * 2.error方法会触发php侧的\LuaException异常
     * 3.php捕获该异常并通过该异常的err属性找到原异常，并抛出
     *
     * 凡是注册(register,registerCallback)给lua的php方法内部都要捕获异常，并通过该方法转换成lua异常
     *
     * @param resource $resource
     * @return array
     */
    protected function exception2lua(\Throwable $e)
    {
        return [
            '__exception__' => true,
            'index' => $this->callbackStore->add($e)
        ];
    }

    /**
     * 把lua传给php的参数转成php数据
     * 1.php调用lua方法时，lua返给php的参数
     * 2.lua调用php方法时，lua传给php的参数
     *
     * @param $value
     * @return mixed
     */
    protected function data2php($value)
    {
        if(is_scalar($value) || is_null($value)) {
            return $value;
        }

        if ($value instanceof \LuaClosure) {
            return $this->closure2php($value);
        }

        if(isset($value['__custom__'])) {
            return $this->custom2php($value);
        }

        foreach($value as $k => $v) {
            $value[$k] = $this->data2php($v);
        }

        return $value;
    }

    /**
     *
     *（当php调用lua的方法，lua抛出error）把lua的error转换成php异常
     * 1.lua收到该异常，会通过error方法抛出
     * 2.error方法会触发php侧的\LuaException异常
     * 3.php捕获该异常并通过该异常的err属性找到原异常，并抛出
     *
     * 凡是调用(call,eval等)lua方法的地方，php都要捕获\LuaException异常，并通过该方法获得原异常并抛出
     *
     * @param \LuaException $e
     * @return \Exception|\LuaException
     */
    protected function exception2php(\LuaException $e)
    {
        if(isset($e->err['__exception__'])) {
            return $this->callbackStore->unRef($e->err['index']);
        }

        if(method_exists($this, 'exception')) {
            return $this->exception($e);
        }

        return $e;
    }

    /**
     * 把lua函数转换成php函数（lua函数经常以回调函数的形式传到php这边来）
     * php调用这个函数的时候会：
     * 1.把传递给lua函数的参数处理成lua数据
     * 2.把lua函数返回值处理成php数据
     * 3.lua方法会抛出\LuaException异常，把它转换成php异常
     *
     * @param \LuaClosure $closure
     */
    protected function closure2php(\LuaClosure $closure)
    {
        return \Closure::fromCallable(function() use($closure) {
            try {
                return $this->data2php(
                    $this->interpreter->call($closure, $this->data2lua(func_get_args()))
                );
            } catch (\LuaException $e) {
                throw $this->exception2php($e);
            }
        })->bindTo($this);
    }

    /**
     * 把lua的table重新还原成php数据（通过talbe中的index字段）
     *
     * @param array $value
     * @return mixed
     */
    protected function custom2php($value)
    {
        return $this->callbackStore->get($value['index']);
    }

    /**
     * 把php变量设置lua变量
     *
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function set($name, $value)
    {
        try {
            $this->interpreter->call('__set', [$name, $this->data2lua($value)]);
        } catch (\LuaException $e) {
            throw $this->exception2php($e);
        }

        return $this;
    }

    /**
     * 获取lua变量
     *
     * @param $name
     * @return mixed
     */
    public function get($name)
    {
        try {
            return $this->data2php($this->interpreter->{$name});
        } catch (\LuaException $e) {
            throw $this->exception2php($e);
        }
    }

    /**
     * 调用lua方法
     * 1.lua方法是原生lua方法
     * 2.lua方法是php注册的
     *
     * @param string $name FIXME 不支持闭包
     * @param mixed[] $arguments
     * @param bool $useSelf
     * @return mxied
     */
    public function call($name, $arguments = [], $useSelf = false)
    {
        try {
            return $this->data2php($this->interpreter->call('__call', array_create($name, ...$this->data2lua($arguments)), $useSelf));
        } catch (\LuaException $e) {
            throw $this->exception2php($e);
        }
    }

    /**
     * 执行lua代码
     *
     * @param string $statements
     * @return mixed
     */
    public function eval(string $statements)
    {
        try {
            return $this->data2php($this->interpreter->eval($statements));
        } catch (\LuaException $e) {
            throw $this->exception2php($e);
        }
    }

    /**
     * 运行lua目录（如果要设置lua的包路径应当使用"setPackagePath"）
     *
     * @param string $folder
     * @param string $main 入口文件
     * @return static
     */
    public function loadFolder($folder, $main = null)
    {
        $this->setPackagePath("{$folder}/?.lua");
        try {
            $this->interpreter->include($main ?? "{$folder}/__main__.lua");
        } catch (\LuaException $e) {
            throw $this->exception2php($e);
        }
        return $this;
    }

    /**
     * 加载lua文件并运行
     *
     * @return mixed lua运行结果
     */
    public function include($file)
    {
        try {
            return $this->data2php($this->interpreter->include($file));
        } catch (\LuaException $e) {
            throw $this->exception2php($e);
        }
    }

    /**
     * 注入一个php方法到lua内部（我们应当避免使用该方式注入php方法，而是通过在lua内部调用“import”方法）
     * 被注入的方法会通过“__call_func__”来调用，而该方法内部会有捕获异常并转成lua-error的过程
     *
     * @param string $name LUA方法名称
     * @param callable $callback 可调用的PHP方法
     * @return $this
     */
    public function register($name, $callback)
    {
        $this->interpreter->call('__register', [ $name, $this->function2lua($callback) ]);

        return $this;
    }

    /**
     * 销毁lua解释器
     * destroy内部只是把通过registerCallback注册的函数的引用计数减1，当计数到0后会销毁这些注册函数，从而导致：
     * 1.在lua内这些函数仍然存在，但是却无法调用（所以不要在destroy方法之后执行lua）
     * 2.如果destroy方法还解除了对当前对象的引用，当前对象（\Flysion\Lua\Lua）如果生命周期结束了，那么就会被析构和销毁
     */
    public function destroy()
    {
        $this->interpreter->destroy();
        $this->callbackStore->clean();
        return $this;
    }

    /**
     * @param string $name
     * @param mixed[] $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->call($name, $arguments);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }
}
