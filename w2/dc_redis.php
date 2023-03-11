<?php

class dc_redis implements DriverCache
{
    public $type = 'Redis';

    private $obj;
    private $path;

    function __construct($cfg) {
    }

    function info() {
        $ary = ['name' => $this->name, 'version' => ''];
        return $ary + ['str' => implode(', ', $ary)];
    }

    function setup($obj) {
     //?   $this->obj = $obj;
        $this->path = $obj->path . '/' . ($obj->pref ?? '');
    }

    function test($name) {
        return ;
    }

    function get($name, $return, $quiet = false) {
    }

    function put($name, $data) {
    }

    function mtime($name) {
    }

    function drop($name, $quiet) {
    }

    function glob($mask = '*') {
    }
}
