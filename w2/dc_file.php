<?php

function run_file($_fn, $_data) {
    $_data && extract($_data->data, EXTR_REFS);
    return require $_fn;
}

class dc_file implements DriverCache
{
    public $type = 'File'; # 2do add opcache control

    private $obj;
    private $path;

    function info() {
        $ary = ['type' => $this->type, 'version' => ''];
        return $ary + ['str' => implode(', ', $ary)];
    }

    function setup($obj, $quiet = false) {
        $this->obj = $obj;
        $this->path = $obj->path . '/' . ($obj->pref ?? '');
        return $quiet && !is_file($this->path . $quiet);
    }

    function test($key) {
        return is_file($this->path . $key) ? $this->path . $key : false;
    }

    function get($key) {
        return file_get_contents($this->path . $key);
    }

    function run($key, $vars = false) {
        return run_file($this->path . $key, $vars);
    }

    function mtime($key) {
        return stat($this->path . $key)['mtime'];
    }

    function append($key, $data) {
        return file_put_contents($this->path . $key, $data, FILE_APPEND);
    }

    function put($key, $data, $ttl = false) {
        global $sky;
        if (!is_dir($this->obj->path))
            mkdir($this->obj->path, (int)($sky->s_mkdir_mode ?: 0777), true);
        return file_put_contents($this->path . $key, $data);
    }

    function set($key, $data) {
        return $this->put($key, $data);
    }

    function glob($mask = '*') {
        return glob($this->path . $mask);
    }

    function drop($key) {
        return (int)unlink($this->path . $key);
    }

    function drop_all($mask = '*') {
        $result = 1;
        foreach (glob($this->path . $mask) as $fn)
            $result &= (int)unlink($fn);
        return $result;
    }
}
