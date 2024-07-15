<?php

class ZML # universal descriptor
{
    const version = 0.001;

    static $zml;

    private $file;
    private $pos;

    function __construct() {
        self::$zml or self::$zml = yml('+ @inc(zml)');
    }

    function file($name) {
        
    }

}
