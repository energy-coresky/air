<?php

class dc_file implements Cache_driver
{
    public $type = 'File';
    private $obj;
    private $path;

    function __construct($cfg = null) {
    }

    function info() {
        $ary = [
            'name' => $this->name,
            'version' => '',///////
            'charset' => '',///////////
        ];
        return $ary + ['str' => implode(', ', $ary)];
    }

    function setup($obj) {
     //?   $this->obj = $obj;
        $this->path = $obj->path . '/' . ($obj->pref ?? '');
    }

    function test($name) {
        return is_file($this->path . $name);
    }

    function mtime($name) {
        return is_file($this->path . $name) ? stat($this->path . $name)['mtime'] : 0;
    }

    function get($name, $quiet = false) {
        if ($quiet && !is_file($this->path . $name))
            return false;
        return file_get_contents($this->path . $name);
    }

    function run($name, $quiet = false) {
        if ($quiet && !is_file($this->path . $name))
            return false;
        return require $this->path . $name;
    }

    function put($name, $data) {
        return file_put_contents($this->path . $name, $data);
    }

    function drop($name, $quiet = false) {
        if ($quiet && !is_file($this->path . $name))
            return true;
        return unlink($this->path . $name);
    }

    function drop_all($ext = '') {
        $result = 1;
        foreach (glob("$this->path/*$ext") as $fn)
            $result &= (int)unlink($fn);
        return $result;
    }
}
