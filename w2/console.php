<?php

class Console
{
    function __construct($argv) {
        call_user_func_array([$this, "_$argv[1]"], array_slice($argv, 2));
    }

    function __call($name, $args) {
        echo "Command `$name` not found";
    }

    function _master() {
        // 2do `.. git push origin master` with question about new SKY version
    }

    function _m() {
        $sky->debug = 0;
        echo sqlf('+select tmemo from $_memory where id=%d', $argv[2] ?? 1);
    }

    function _g() {
        (new Globals)->dirs();
    }

    function _c() {
        $list = Gate::controllers();
        echo "Reparsed:\n" . implode(' ', $list);
    }

    function _cache() {
        echo Admin::drop_all_cache() ? 'Drop all cache: OK' : 'Error when drop cache';
    }

    function _sql($sql) {
        $r = sqlf($sql);
        echo 'result: ' . print_r($r, 1);
    }

    function _jet() {
        echo '2do';
    }

    function _a() {
       $list = Gate::controllers(true);
       array_shift($list);
       Gate::$cshow = true;
       foreach ($list as $k => $v) {
           if (1 != $v)
               continue;
           $e = new eVar(Gate::view($mc = '*' != $k ? "c_$k" : 'default_c'));
           foreach ($e as $row)
               echo "$mc::$row->func$row->pars  --  " . strip_tags($row->url). "\n";
       }
 
    #$gate = new Gate;
    #$gate->put_cache($argv[2]);
    #$ary = $gate->contr();
    #echo count($ary) . " $gate->i\n";print_r($ary);
    }

    function _t() {
        //Gate::test("main/app/$argv[2]");
    }

    function _xxx() {
        #Gate::test(__FILE__);
    }
}
