<?php

# 1. draw images from PHP array ?
# 2. use ready images from underscore link: <img src="_svg?.." ..>

class SVG {
    static $size = [16, 16];
    static $fill = "currentColor";
    private $ca;

    function __construct($c, $a = false) { # new image from array
        $this->ca = [$c, $a ? 'svglist_' . $a : 'svglist_'];
    }

    function __toString() { # compile image
        global $sky;
        list ($name, $pack) = $this->ca;
        $tpl = unl(Plan::_g([$sky->d_last_ware ?: 'main', "glob/$pack.pack"]));
        preg_match("/\n:$name(| [^\n]+)\n(.+?)\n:/s", $tpl, $m);
        return sprintf('<svg %s xmlns="http://www.w3.org/2000/svg">%s</svg>', $m[1], $m[2]);
    }

    function _2do() {
    }
}
