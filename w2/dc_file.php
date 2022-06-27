<?php

class dc_file implements Cache_driver
{
    public $type = 'File';
    public $pref;

    function __construct($cfg) {
    }

    function info() {
        $ary = [
            'name' => $this->name,
            'version' => '',///////
            'charset' => '',///////////
        ];
        return $ary + ['str' => implode(', ', $ary)];
    }

    function test($name) {
        return is_file($name);
    }

    function get($name, $return = true, $quiet = false) {
        if ($quiet && !is_file($name))
            return false;
        if ($return)
            return file_get_contents($name);
        return require $name;
    }

    function put($name, $data) {
        return file_put_contents($name, $data);
    }

    function mtime($name) {
        return is_file($name) ? stat($name)['mtime'] : 0;
    }

    function drop($name, $quiet) {
        if ($quiet && !is_file($name))
            return true;
        return unlink($name);
    }

    function drop_all($path) {
        $result = 1;
        foreach (glob("$path/*.php") as $fn) {
            $result &= (int)unlink($fn);
        }
        return $result;
    }
}
