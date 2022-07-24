<?php

class SVG {
    static $size = [16, 16];
    static $fill = "currentColor";
    private $name;
    private $pack;

    function __construct($c, $a = false) { # new image from array
        $this->name = $a ? $a : $c;
        $this->pack = 'svg_list_' . ($a ? $c : 0) . '.pack';
    }

    function __toString() { # compile image
        global $sky;
        $tpl = unl(Plan::mem_g([$sky->d_last_ware ?: 'main', $this->pack]));
        preg_match("/\n:$this->name(| [^\n]+)\n(.+?)\n:/s", $tpl, $m);
        return sprintf('<svg %s xmlns="http://www.w3.org/2000/svg">%s</svg>', $m[1], $m[2]);
    }

    function _2do() {
    }
}
