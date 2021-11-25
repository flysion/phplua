# flysion/phplua
基于PHP扩展（[http://pecl.php.net/package/lua](http://pecl.php.net/package/lua)）进行二次封装。特性:
+ 解决内存持续增加的问题
+ 在LUA中很方便的调用PHP的函数和类方法
+ 及时释放PHP内存（利用 lua 的 metatable 特性）
+ 解决lua调用php方法抛出异常时，lua代码继续往下执行的问题

## 使用示例
### LUA调用PHP方法
基本用法，需要通过lua的`import`方法导入php方法
```php
$lua = new \Flysion\Lua\Lua();
$lua->eval(<<<LUA
local md5 = import('md5')
print(md5('123456'))
LUA
);
```

允许通过table的形式同时导入多个php方法
```php
$lua = new \Flysion\Lua\Lua()
$lua->eval(<<<LUA
local fileSystem = import({open = "fopen", read = "fread", close = "fclose"})
local fd = fileSystem.open("1.txt")
fileSystem.read(fd, 1024)
fileSystem.close(fd)
LUA
);
```

也可以通过php的`register`方法导入php方法（不推荐使用这种方法，这种方法无法触发lua的gc机制，无法及时释放共享内存）
```php
$lua = new \Flysion\Lua\Lua();
$lua->register('md5', 'md5');
$lua->eval("print(md5('123456'))");
```

*总之，一旦一个PHP变量导入到LUA里边之后你可以像写PHP一样的自由调用这个变量*

## 错误处理
重写`exception`方法
```php
$lua = new class() extends \Flysion\Lua\Lua {
    protected function exception(\LuaException $e)
    {
        if(!isset($e->err['__throw__']))
        {
            return $e;
        }

        switch ($e->err['__throw__']) {
            case 2:
                return new Exception2($e->err['message'] ?? "", $e->err['code'] ?? null, $e->err['data'] ?? null);
            default:
                return new Exception($e->err['message'] ?? "", $e->err['code'] ?? null);
        }
    }
};
```
定义lua方法
```lua
$lua->eval(<<<LUA
throw = {__throw__ = true}

function throw:exception(message, code)
    error({__throw__ = 0, message = message, code = code})
end

function throw:exception2(message, code, data)
    error({__throw__ = 2, message = message, code = code, data = data})
end
LUA
);
```
Throw exception example：
```lua
$lua->eval(<<<LUA
throw:exception2("test exception", 123) -- Exception2
error({1,2,3}) -- \LuaException has "err" property
LUA
);

```

## 通过PHP定义LUA模块:
See `\Flysion\Lua\Lua::luaImport`

## 关于内存问题
`\Flysion\Lua\Lua`对象在生命周期结束之后并不会被释放掉，原因是在LUA里边还有对他的引用，这个时候需要手动释放该对象，方法是：

```php
$lua = new \Flysion\Lua\Lua();
$lua->eval('collectgarbage("collect")');
$lua->destroy();
```

or

```php
$lua = new \Flysion\Lua\Destructor(
    new \Flysion\Lua\Lua()
);
```

**注意：来自[http://pecl.php.net/package/lua](http://pecl.php.net/package/lua)的lua扩展并没有`destroy`方法，需要使用我魔改过的扩展：[https://github.com/flysion/php-lua](https://github.com/flysion/php-lua)**
