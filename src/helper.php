<?php

namespace Flysion\Lua;

function array_dot_get($array, $key)
{
    foreach (explode('.', $key) as $segment) {
        if(is_array($array) && array_key_exists($segment, $array)) {
            $array = $array[$segment];
        } else {
            return null;
        }
    }

    return $array;
}

function array_create() {
    return func_get_args();
}
