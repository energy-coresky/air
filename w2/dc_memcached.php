<?php

class dc_memcached implements Cache_driver
{
    public $type = 'Memcached';

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

    function drop_all($mask = '*') {
    }

    function glob($mask = '*') {
    }
}
