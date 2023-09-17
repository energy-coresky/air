<?php

class dc_file implements DriverCache
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
        return $obj->quiet && !is_file($this->path . $obj->quiet);
    }

    function test($name) {
        return is_file($this->path . $name) ? $this->path . $name : false;
    }

    function get($name) {
        return file_get_contents($this->path . $name);
    }

    function run($name, $vars) {
        return require $this->path . $name;
    }

    function mtime($name) {
        return stat($this->path . $name)['mtime'];
    }

    function put($name, $data, $is_append = false) {
        global $sky;
        if (!is_dir($this->obj->path))
            mkdir($this->obj->path, (int)($sky->s_mkdir_mode ?: 0777), true);
        return file_put_contents($this->path . $name, $data, $is_append ? FILE_APPEND : 0);
    }

    function glob($mask = '*') {
        return glob($this->path . $mask);
    }

    function drop($name) {
        return (int)unlink($this->path . $name);
    }

    function drop_all($mask = '*') {
        $result = 1;
        foreach (glob($this->path . $mask) as $fn)
            $result &= (int)unlink($fn);
        return $result;
    }
}
