<?php

# 1. draw images from PHP array ?
# 2. use ready images from underscore link: <img src="_svg?.." ..>

class SVG {
    static $size = [16, 16];
    static $fill = "currentColor";

    function __construct($ary = []) { # new image from array

    }

    function __toString() { # compile image

    }

    function _std() { # output image from _svg?.. link


        echo $this;
    }
}
