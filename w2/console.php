<?php

class Console
{
    static $d;

    function __construct($argv = [], $found = []) {
        global $sky;

        if ('Console' != get_class($this))
            return $argv && call_user_func_array([$this, $argv], $found);

        self::$d = $found + [3 => $ns = $found[1] && 'air' != basename(getcwd())];

        if ('master' == $argv[1]) {
            if ($ns || is_dir(DIR_S . '/.git'))
                return $this->master(!$ns);
        } elseif ('s' == $argv[1]) {
            return $this->s($argv[2] ?? 8000);
        } elseif ($found[0]) {
            if ('app' != $argv[1] || '_' !== ($argv[2][0] ?? ''))
                $sky->load();
            return call_user_func_array([$this, "c_$argv[1]"], array_slice($argv, 2));
        }

        $this->__call("c_$argv[1]", []);
    }

    function __call($name, $args) {
        if ('c_app' == $name && self::$d[0] && is_file(DIR_M . '/w3/app.php'))
            return new App('a_' . array_shift($args), $args);

        if ('c_' != $name) {
            echo "\nCommand `";
            echo ('Console' != get_class($this) ? "app " : '') . substr($name, 2);
            echo "` not found\n\n";
        }

        $ary = [
            's' => 'Run PHP web-server',
            'd' => 'List dirs (from current dir)',
            'php' => 'Lint PHP files (from current dir)',
        ];
        if (self::$d[3] || is_dir(DIR_S . '/.git')) {
            $repo = 'new CORESKY version';
            if (self::$d[3])
                $repo = self::$d[2] ? "ware `" . basename(getcwd()) . "`" : 'repository';
            $ary += ['master' => "Push $repo to remote origin master"];
        }
        if (self::$d[0]) {
            $m = (new ReflectionClass('Console'))->getMethods(ReflectionMethod::IS_PUBLIC);
            $cnt = count($m);
            if (is_file(DIR_M . '/w3/app.php'))
                $m = array_merge($m, (new ReflectionClass('App'))->getMethods(ReflectionMethod::IS_PUBLIC));
            array_walk($m, function ($v, $i) use (&$ary, $cnt) {
                if ($i >= $cnt && 'c_' == substr($v->name, 0, 2))
                    return;
                if ($s = $v->getDocComment())
                    $ary[($i >= $cnt ? 'app ' : '') . substr($v->name, 2)] = trim($s, "*/ \n\r");
            });
        }
        ksort($ary);
        echo "Usage: sky command [param ...]\nCommands are:\n  ";
        echo implode("\n  ", array_map(function($k, $v) {
            return str_pad($k, 15, ' ') . $v;
        }, array_keys($ary), $ary));
    }

    function s($port) {
        if (self::$d[0]) {
            if (!DEV)
                return print("Cannot run php-server on production");
            echo "\n";
            $this->c_drop();
            echo "\n";
        }
        if (function_exists('socket_create')) {
            for ($i = 0; $i < 9; $i++, $port++) {
                $sock = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
                $busy = @socket_connect($sock, '::1', $port);
                socket_close($sock);
                if (!$busy)
                    break;
            }
        }
        chdir(DIR_R);
        if (!file_exists($fn = '../s.php')) {
            echo "File `$fn` written\n\n";
            file_put_contents($fn, "<?php\n\n"
                . '$uri = urldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));'
                . "\nif ('/' !== \$uri && file_exists(getcwd() . \$uri))\n\treturn false;\n"
                . '$_SERVER["SCRIPT_NAME"] = "/index.php"; require "index.php";');
        }
        system("explorer \"http://localhost:$port\"");
        system("php -S localhost:$port $fn");
    }

    function master($air) {
        if ($air)
            chdir(DIR_S);
        echo "\n>git remote get-url origin\n";
        system('git remote get-url origin');
        if ($air) {
            if (!preg_match("/'(\d+\.\d+[^']+? energy)'/s", $php = file_get_contents('sky.php'), $m))
                throw new Error('Wrong preg_match');
            date_default_timezone_set('Europe/Kiev');
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
    function c_m($id = 3, $unhtml = false) {
        $s = sqlf('+select tmemo from $_memory where id=%d', $id);
        echo !$unhtml ? $s : (1 == $unhtml ? unhtml($s) : unhtml(unhtml($s)));
    }

    /** Check globals */
    function c_g() {
        (new Globals)->dirs();
    }

    /** Show controllers */
    function c_c() {
        echo "Rescanned:\n  " . array_join(Gate::controllers(), ' => ', "\n  ");
        echo "\nFrom SKY::\$plans:\n  " . array_join(SKY::$plans['main']['ctrl'], ' => ', "\n  ");
    }

    /** Show top-view actions (routes) */
    function c_a() {
       Gate::$cshow = true;
       foreach (SKY::$plans['main']['ctrl'] as $k => $_) {
           $e = new eVar(DEV::gate($mc = '*' != $k ? "c_$k" : 'default_c'));
           foreach ($e as $row)
               echo "$mc::$row->func$row->pars  --  " . strip_tags($row->url). "\n";
       }
    }

    /** Drop all cache */
    function c_drop() {
        echo Admin::drop_all_cache() ? 'Drop all cache: OK' : 'Error when drop cache';
    }

    /** Warm all cache */
    function c_warm() {
        echo '2do';
    }

    /** Execute SQL, example: sky sql "+select 1+1" */
    function c_sql($sql) {
        $r = sqlf($sql);
        echo 'result: ' . print_r($r, 1);
    }

    /** Eval PHP code, example: sky eval "SKY::s('statp','1011p');" */
    function c_eval($php) {
        global $sky;
        eval($php);
    }
}
