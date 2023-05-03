<?php

class Wares # Base class for wares installation
{

    function __construct($mode = false) {
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
