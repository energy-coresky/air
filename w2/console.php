<?php

class Console
{
    function __construct($argv, $found) {
        global $sky;

        $this->found = $found + [3 => $ns = $found[1] && 'air' != basename(getcwd())];

        if ($found[0] && 'master' != $argv[1]) {
            if ('app' != $argv[1] || '_' !== ($argv[2][0] ?? ''))
                $sky->load();
            return call_user_func_array([$this, "_$argv[1]"], array_slice($argv, 2));
        } elseif ('master' == $argv[1] && ($ns || is_dir(DIR_S . '/.git'))) {
            return $this->_master(!$ns);
        }

        $this->__call("_$argv[1]", []);
    }

    function __call($name, $args) {
        if ('_app' == $name && $this->found[0] && class_exists('App'))
            return new App($args);

        if ('_' != $name)
            echo "\nCommand `" . substr($name, 1) . "` not found\n\n";

        $ary = [
            's' => 'Run PHP web-server',
            'd' => 'List dirs (from current dir)',
            'php' => 'Lint PHP files (from current dir)',
        ];
        if ($this->found[3] || is_dir(DIR_S . '/.git')) {
            $repo = 'new CORESKY version';
            if ($this->found[3])
                $repo = $this->found[2] ? "ware `" . basename(getcwd()) . "`" : 'repository';
            $ary += ['master' => "Push $repo to remote origin master"];
        }
        if ($this->found[0]) {
            $m = (new ReflectionObject($this))->getMethods(ReflectionMethod::IS_PUBLIC);
            $cnt = count($m);
            if (is_file(DIR_M . '/w3/app.php'))
                $m = array_merge($m, (new ReflectionObject(new App))->getMethods(ReflectionMethod::IS_PUBLIC));
            array_walk($m, function ($v, $i) use (&$ary, $cnt) {
                if ($s = $v->getDocComment())
                    $ary[($i > $cnt ? 'app ' : '') . substr($v->name, 1)] = trim($s, "*/ \n\r");
            });
        }
        ksort($ary);
        echo "Usage: sky command [param ...]\nCommands are:\n  ";
        echo implode("\n  ", array_map(function($k, $v) {
            return str_pad($k, 15, ' ') . $v;
        }, array_keys($ary), $ary));
    }

    function _master($air) {
        if ($air)
            chdir(DIR_S);
        echo "\n>git remote get-url origin\n";
        system('git remote get-url origin');
        if ($air) {
            if (!preg_match("/'(\d+\.\d+[^']+? energy)'/s", $php = file_get_contents('sky.php'), $m))
                throw new Error('Wrong preg_match');
            $v = explode(' ', $m[1]);
            $v[0] += 0.001;
            $v[1] = date('c');
            echo "\n$m[1] (current)\n" . implode(' ', $v) . "\nCreate new? [n] ";
            $q = trim(fgets(STDIN));
            if ('y' == strtolower($q))
                file_put_contents('sky.php', str_replace($m[1], implode(' ', $v), $php));
        }
        echo "\n>git status\n";
        $line = system('git status');
        if ('nothing to commit, working tree clean' == trim($line))
            return;
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
        echo "Rescanned:\n  " . array_join(Gate::controllers(), ' => ', "\n  ");
        echo "\nFrom SKY::\$plans:\n  " . array_join(SKY::$plans['main']['ctrl'], ' => ', "\n  ");
    }

    /** Show top-view actions (routes) */
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

    /** Execute SQL, example: sky sql "+select 1+1" */
    function _sql($sql) {
        $r = sqlf($sql);
        echo 'result: ' . print_r($r, 1);
    }

    /** Eval PHP code, example: sky eval "SKY::s('statp','1011p');" */
    function _eval($php) {
        global $sky;
        eval($php);
    }
}
