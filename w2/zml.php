<?php

class ZML # universal descriptor
{
    const version = 0.001;

    static $zml;
    static $custom = [];

    private $fh;
    private $pos;
    private $bang = [];

    function __construct($fn = false) {
        self::$zml or self::$zml = yml('+ @inc(zml)');
        if ($fn)
            $this->open($fn);
    }

    function open($fn) {
        $this->fh = fopen($fn, "c+b");
        $head = fread($this->fh, 8192);
        if ($this->pos = strpos($head, "\n\n"))
            $this->bang = bang(substr($head, 0, $this->pos));
    }

    function close() {
        fclose($this->fh);
    }

    function info() {
        while (!feof($this->fh)) {
            $contents = fread($this->fh, 8192);
        }
        return $this->bang;
    }

}
