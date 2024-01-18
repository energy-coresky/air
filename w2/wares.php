<?php

class Wares extends MVC_BASE # Base class for wares installation
{
    function __construct() {
        [$this->k_ware] = explode('\\', get_class($this));
    }

    function form() {
        return ["No options"];
    }

    function __toString() {
        return Form::A([], $this->form());
    }

    function off_ware() {
        $wares = Plan::_rq($plan = ['main', 'wares.php']);
        unset($wares[$this->k_ware]);
        Plan::app_p($plan, Boot::auto($wares));
        Plan::cache_d(['main', 'sky_plan.php']);
        return 'OK';
    }

    function create_tables() {
    }
}
