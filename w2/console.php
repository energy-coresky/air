<?php

class Console
{
    function __construct($argv) {
        global $sky;
        if ('_' !== $argv[1][0])
            $sky->load();
        call_user_func_array([$this, "_$argv[1]"], array_slice($argv, 2));
    }

    function __call($name, $args) {
        echo "Command `" . substr($name, 1) . "` not found";
    }

    function _master() {
        $v = explode(' ', SKY::CORE);
        $v[0] += 0.001;
        $v[1] = date('c');
        echo SKY::CORE . " (current)\n" . implode(' ', $v) . "\nCreate new? [n] ";
        $q = trim(fgets(STDIN));
        chdir(DIR_S);
        echo "\n>git status\n";
        system('git status');
        echo 'Commit text [tiny fix] ';
        $c = trim(fgets(STDIN)) or $c = 'tiny fix';
        echo "\n>git add *\n";
        system('git add *');
        echo "\n>git commit -a -m \"$c\"\n";
        system("git commit -a -m \"$c\"");
        echo "\n>git push origin master\n";
        system("git push origin master");
    }

    function _m($id = 3, $unhtml = false) {
        $s = sqlf('+select tmemo from $_memory where id=%d', $id);
        echo !$unhtml ? $s : (1 == $unhtml ? unhtml($s) : unhtml(unhtml($s)));
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

    function _eval($php) {
    }

    function _sql($sql) {
        $r = sqlf($sql);
        echo 'result: ' . print_r($r, 1);
    }

    function _jet() {
        echo '2do';
    }

    function _a() {
       Gate::$cshow = true;
       foreach (Gate::controllers() as $k => $_) {
           $e = new eVar(DEV::gate($mc = '*' != $k ? "c_$k" : 'default_c'));
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
