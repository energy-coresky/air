<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class Vendor
{
    function __construct() {
    }

    function c_list() {
        global $sky;
        return [
            'obj' => $this,
            'e_list' => [],
        ];
    }
}
