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
        if ('_app' == $name && class_exists('App'))
            return new App($args);
        echo "Command `" . substr($name, 1) . "` not found";
    }

    function _commands($etc) {
        $m = (new ReflectionObject($this))->getMethods(ReflectionMethod::IS_PUBLIC);
        $ary = [];
        array_walk($m, function ($v) use (&$ary) {
            if ($s = $v->getDocComment())
                $ary[substr($v->name, 1)] = trim($s, "*/ \n\r");
        });
        if (is_file(DIR_M . '/w3/app.php')) {
            $m = (new ReflectionObject(new App))->getMethods(ReflectionMethod::IS_PUBLIC);
            array_walk($m, function ($v) use (&$ary) {
                if ($s = $v->getDocComment())
                    $ary['app ' . substr($v->name, 1)] = trim($s, "*/ \n\r");
            });
        }
        echo "$etc\n  " . array_join($ary, ' - ', "\n  ");
    }

    /** Push new CORESKY version to github.com */
    function _master() {
        $v = explode(' ', SKY::CORE);
        $v[0] += 0.001;
        $v[1] = date('c');
        echo SKY::CORE . " (current)\n" . implode(' ', $v) . "\nCreate new? [n] ";
        $q = trim(fgets(STDIN));
        chdir(DIR_S);
        if ('y' == strtolower($q))
            file_put_contents('sky.php', str_replace(SKY::CORE, implode(' ', $v), file_get_contents('sky.php')));
        echo "\n>git status\n";
        system('git status');
        echo "\nCommit text [tiny fix] ";
        $c = trim(fgets(STDIN)) or $c = 'tiny fix';
        echo "\n>git add *\n";
        system('git add *');
        echo "\n>git commit -a -m \"$c\"\n";
        system("git commit -a -m \"$c\"");
        echo "\n>git push origin master\n";
        system("git push origin master");
    }

    /** Read tmemo cell from $_memory */
    function _m($id = 3, $unhtml = false) {
        $s = sqlf('+select tmemo from $_memory where id=%d', $id);
        echo !$unhtml ? $s : (1 == $unhtml ? unhtml($s) : unhtml(unhtml($s)));
    }

    /** Check globals */
    function _g() {
        (new Globals)->dirs();
    }

    /** Show controllers */
    function _c() {
        echo 'Rescanned: ';
        print_r(Gate::controllers());
        echo 'From SKY::$plans: ';
        print_r(SKY::$plans['main']['ctrl']);
    }

    /** Show list of routes */
    function _a() {
       Gate::$cshow = true;
       foreach (SKY::$plans['main']['ctrl'] as $k => $_) {
           $e = new eVar(DEV::gate($mc = '*' != $k ? "c_$k" : 'default_c'));
           foreach ($e as $row)
               echo "$mc::$row->func$row->pars  --  " . strip_tags($row->url). "\n";
       }
    }

    /** Drop all cache */
    function _cache() {
        echo Admin::drop_all_cache() ? 'Drop all cache: OK' : 'Error when drop cache';
    }

    /** Warm all cache */
    function _warm() {
        echo '2do';
    }

    /** Exec SQL command */
    function _sql($sql) {
        $r = sqlf($sql);
        echo 'result: ' . print_r($r, 1);
    }

    /** Eval PHP */
    function _eval($php) {
        global $sky;
        eval($php);
    }

    /** 2do */
    function _t() {
        //Gate::test("main/app/$argv[2]");
    }
}
