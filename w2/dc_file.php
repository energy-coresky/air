<?php

class dc_file implements Cache_driver
{
    public $type = 'File'; # 2do add opcache control

    private $obj;
    private $path;

    function info() {
        $ary = ['name' => $this->name, 'version' => ''];
        return $ary + ['str' => implode(', ', $ary)];
    }

    function setup($obj) {
        $this->obj = $obj;
        $this->path = $obj->path . '/' . ($obj->pref ?? '');
    }

    function test($name) {
        return is_file($this->path . $name) ? $this->path . $name : false;
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
            return [];
        return require $this->path . $name;
    }

    function put($name, $data) {
        global $sky;
        if (!is_dir($this->obj->path))
            mkdir($this->obj->path, (int)($sky->s_mkdir_mode ?: 0777), true);
        return file_put_contents($this->path . $name, $data);
    }

    function drop($name, $quiet = false) {
        if ($quiet && !is_file($this->path . $name))
            return true;
        return unlink($this->path . $name);
    }

    function drop_all($mask = '*') {
        $result = 1;
        foreach (glob($this->path . $mask) as $fn)
            $result &= (int)unlink($fn);
        return $result;
    }

    function glob($mask = '*') {
        return glob($this->path . $mask);
    }
}
