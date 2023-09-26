<?php

namespace Flysion\Lua;

class CallbackStore
{
    /**
     * @var int
     */
    protected $index = 0;

    /**
     * @var array
     */
    protected $items = [];

    /**
     * @param mixed $data
     * @return int
     */
    public function add($data)
    {
        if(is_string($data)) {
            return $data;
        }

        $this->items[++$this->index] = (object)['val' => $data, 'ref' => 1];

        return $this->index;
    }

    /**
     * @param $index
     * @return mixed
     */
    public function get($index)
    {
        if(is_string($index)) {
            return $index;
        }

        $index = intval($index);

        return $this->items[$index]->val;
    }

    /**
     * @param $index
     * @return mixed
     */
    public function unRef($index)
    {
        if(is_string($index)) {
            return ;
        }

        $index = intval($index);
        $val = $this->items[$index]->val;

        if(--$this->items[$index]->ref === 0) {
            unset($this->items[$index]);
        }

        return $val;
    }

    /**
     * clean items
     */
    public function clean()
    {
        foreach($this->items as $k => $item) {
            unset($this->items[$k]);
        }
    }
}
