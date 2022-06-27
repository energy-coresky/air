<?php

class dc_memcached implements Cache_driver
{
    public $type = 'Memcached';
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

    function drop_all($path) {
    }
}
