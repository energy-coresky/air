<?php

class Wares extends MVC_BASE # Base class for wares installation
{

    function __construct($mode = false) {
    }

    static function open($name, $char = false) {
        static $dd;
        if ($dd)
            return $dd;
        SKY::$databases += Plan::app_r('conf.php')['app']['databases'];
        $dd = SQL::open($name);
        global $sky;
        if ($char)
            $sky->memory(8, $char, $dd);
        return $dd;
    }

    function test() {
        return false;
    }

    function __toString() {
        return '---';
    }

    function create_tables() {
    }

    function install() {
        return 'tables: 1';
    }

    function uninstall() {
        // 2do
    }
}
