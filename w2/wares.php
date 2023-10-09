<?php

class Wares extends MVC_BASE # Base class for wares installation
{
    function __construct() {
        [$this->k_ware] = explode('\\', get_class($this));
    }

    function form() {
        return ["No options"];
    }

    function databases() {
        $list = SKY::$databases;
        unset($list['driver'], $list['pref'], $list['dsn'], $list['']);
        $k = array_keys($list);
        return array_combine([-1 => ''] + $k, [-1 => 'main'] + $k);
    }

    function __toString() {
        return Form::A([], $this->form());
    }

    function test() {
        return false;
    }

    function create_tables() {
    }
}
