<?php

class Console
{
    static $d;

    function __construct($argv = [], $found = []) {
        global $sky;

        if ('Console' != get_class($this))
            return $argv && call_user_func_array([$this, $argv], $found);

        self::$d = $found + [3 => $ns = $found[2] && 'air' != basename(getcwd())];

        if ('master' == $argv[1]) {
            if ($ns || is_dir(DIR_S . '/.git'))
                return $this->master(!$ns);
        } elseif ('s' == $argv[1]) {
            $this->s($argv[2] ?? 8000);
        } elseif ($found[0]) {
            SQL::$dd_h = 'Console::dd_h';
            '_' === ($argv[2][0] ?? '') or $sky->open();
        }
        $this->__call("c_$argv[1]", array_slice($argv, 2));
    }

    static function dd_h($dd, $name) {
        if ('' === $name) {
            require DIR_S . '/w2/mvc.php';
            Plan::app_r('mvc/common_c.php');
        }
        common_c::dd_h($dd, $name);
    }

    function __call($name, $args) {
        static $src;

        if (is_null($src) && self::$d[0]) {
            $src = ['' => new ReflectionClass('Console')];
            if (is_file(DIR_M . '/w3/app.php'))
                $src += ['app' => new ReflectionClass('App')];
            foreach (SKY::$plans as $w => $_) {
                if ('main' == $w || !Plan::_rq([$w, "w3/$w.php"]))
                    continue;
                $r = new ReflectionClass($w);
                if (($pr = $r->getParentClass()) && 'Console' == $pr->name)
                    $src[$w] = $r;
            }
        }

        $com = substr($name, 2);
        if ($com && isset($src[$com]) && 'c_' == substr($name, 0, 2))
            return new $com('a_' . array_shift($args), $args);

        $ary = [
            's' => 'Run PHP web-server',
            'd' => 'List dirs (from current dir)',
            'v' => 'Show Coresky version',
            'php' => 'Lint PHP files (from current dir)',
        ];
        $ware = self::$d[1] ? basename(self::$d[1]) : false;
        if (self::$d[3] || is_dir(DIR_S . '/.git')) {
            $repo = 'new CORESKY version';
            if (self::$d[3])
                $repo = $ware ? "ware `$ware`" : 'repository';
            $ary += ['master' => "Push $repo to remote origin master"];
        }
        foreach ($src ?? [] as $w => $rfn) {
            $list = $rfn->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($list as $v) {
                $pfx = substr($v->name, 0, 2);
                if ('c_' == $pfx && !$w || 'a_' == $pfx && $w)
                    $ary[($w ? "$w " : '') . substr($v->name, 2)] = trim($v->getDocComment(), "*/ \n\r");
            }
        }

        $cls = strtolower(get_class($this));
        $pfx = 'console' == $cls ? '' : "$cls ";
        if (isset($ary[$pfx . $com])) {
            return call_user_func_array([$this, $name], $args);
        } elseif ($pfx || $com) {
            echo "\nCommand `" . trim($pfx . $com) . "` not found\n\n";
        }
        $ary = array_filter($ary);
        ksort($ary);
        echo "Usage: sky command [param ...]\nCommands are:\n  ";
        echo implode("\n  ", array_map(function($k, $v) {
            return str_pad($k, 15, ' ') . $v;
        }, array_keys($ary), $ary));
        if (self::$d[0])
            echo "\nCoresky app: " . SKY::version()['app'][3] . ' (' . _PUBLIC . ')';
        if ($ware)
            echo "\nCoresky ware: $ware";
        if (self::$d[2]) {
            chdir(self::$d[2]);
            exec('git remote get-url origin', $output);
            echo "\nRepository: $output[0]";
        }
    }

    static function get_public() {
        if ($dir = Plan::mem_gq('www_dir')) //$sky->memory();
            return $dir;
        foreach (glob('*') as $dir) {
            if ('_' != $dir[0] && is_file($fn = "$dir/index.php") && strpos(file_get_contents($fn), 'new HEAVEN'))
                return $dir;
        }
        return false;
    }

    function s($port) {
        global $dir_run;

        $public = false;
        if (self::$d[0]) {
            if (!DEV)
                return print("Cannot run php-server on production");
            echo "\n";
            $this->c_drop();
            echo "\n";
            $public = self::get_public();
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
        chdir($public && is_dir($public) ? $public : $dir_run());
        if (!file_exists($fn = '../s.php')) {
            echo "File `$fn` written\n\n";
            file_put_contents($fn, "<?php\n\n"
                . '$uri = urldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));'
                . "\nif ('/' !== \$uri && file_exists(getcwd() . \$uri))\n\treturn false;\n"
                . '$_SERVER["SCRIPT_NAME"] = "/index.php"; require "index.php";');
        }
        if ('WINNT' == PHP_OS) {
            system("explorer \"http://localhost:$port\"");
        } else {
            echo "Now open browser at http://localhost:$port\n\n";
        }
        system("php -S localhost:$port $fn");
    }

    function master($air) {
        chdir($air ? DIR_S : self::$d[2]);
        echo "\n>git remote get-url origin\n";
        system('git remote get-url origin');

        echo "\n>git status\n";
        $line = system('git status');
        if ('nothing to commit, working tree clean' == trim($line))
            return;
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
        echo "\nCommit text [tiny fix] ";
        $c = trim(fgets(STDIN)) or $c = 'tiny fix';
        if (self::$d[0] && !self::$d[1]) {
            SQL::$dd_h = 'Console::dd_h';
            global $sky;
            $sky->open();
            common_c::make_h(true);
        }
        echo "\n>git add *\n";
        system('git add *');
        echo "\n>git commit -a -m \"$c\"\n";
        system("git commit -a -m \"$c\"");
        echo "\n>git push origin master\n";
        system("git push origin master");
        if (self::$d[0] && !self::$d[1])
            common_c::make_h(false);
    }

    /** Show Coresky version */
    function c_v() {
        class_exists('SKY', false) or require DIR_S . '/sky.php';
        echo SKY::CORE;
    }

    static function tail_x($exit, $stdout = '') {
        //
    }

    static function test($m1 = 5, $m2 = 100) {
        echo rand(0, $m2);
        sleep(rand(1, $m1));
        echo rand(0, $m2);
        sleep(rand(1, $m1));
        echo 'finished';
    }

    static function thread($param, ?int $id = null) {
        static $read = [], $m = 0;
        $ok = function_exists('popen');
        if (is_string($param))
            return $read[$id ?? $m++] = $ok ? popen($param, 'r') : null;
        if (!$ok)
            return call_user_func($param, "function 'popen' not exists", -1, true);
        while ($read) {
            if ($cnt = stream_select($read, $write, $except, null)) {
                foreach ($read as $id => $x)
                    empty($str = fread($x, 2096)) or call_user_func($param, $str, $id, false);
            } elseif (false === $cnt) {
                foreach ($read as $x)
                    pclose($x);
                return call_user_func($param, "Error stream_select()", -1, true);
            }
            foreach ($read as $id => $x) {
                if (feof($x)) {
                    pclose($read[$id]);
                    unset($read[$id]);
                    call_user_func($param, false, $id, false);
                }
            }
        }
    }

    /** Write default rewrite.php */
    function c_rewrite() {
        if (!DEV)
            return print "Cannot use this command on PROD";
        if ($dat = Plan::_gq('rewrite.php')) {
            Plan::mem_p('rewrite.php', $dat);
            echo 'Old file moved to `' . Plan::mem_t('rewrite.php') . "`\n";
        }
        Rewrite::lib($map);
        Plan::_p('rewrite.php', Plan::auto($map));
        $this->c_drop();
    }

    /** Write default gate.php */
    function c_gate() {
        if (!DEV)
            return print "Cannot use this command on PROD";
        if ($dat = Plan::_gq('gate.php')) {
            Plan::mem_p('gate.php', $dat);
            echo 'Old file moved to `' . Plan::mem_t('gate.php') . "`\n";
        }
        Plan::_p('gate.php', Plan::auto(Gate::default()));
        $this->c_drop();
    }

    /** Write "first run" into index.php */
    function c_fr() {
        common_c::make_h(true);
    }

    /** Read tmemo cell from $_memory */
    function c_m($id = 8, $unhtml = false) {
        $s = sqlf('+select tmemo from $_memory where id=%d', $id);
        echo !$unhtml ? $s : (1 == $unhtml ? unhtml($s) : unhtml(unhtml($s)));
    }

    /** Check globals */
    function c_g() {
        define('WWW', self::get_public() . '/');
        DEV::init();
        (new Globals)->c_run();
    }

    /** Show controllers */
    function c_c() {
        echo "Rescanned:\n  " . array_join(Debug::controllers(), function($k, $v) {
            return "$k: " . ($v[0] ? '' : 'not ') . 'exist'; # 2do: red
        }, "\n  ");
        echo "\nFrom SKY::\$plans:\n  " . array_join(SKY::$plans['main']['ctrl'], ' => ', "\n  ");
    }

    /** Show top-view actions (routes) */
    function c_a() {
        Gate::$cshow = true;
        Rewrite::get($lib, $map, $keys);
        $max = 0;
        $out = [];
        foreach (Debug::controllers() as $x) {
            if (!$x[0]) {
                $max > ($len = strlen($a = "$x[1]::")) or $max = $len;
                $out[$a] = (object)['gerr' => 'Controller not found'];
                continue;
            }
            $ary = (new eVar(dev_c::gate($x[2] ?: 'main', $x[1])))->all();
            Rewrite::external($ary, $x[1]);
            foreach ($ary as $row) {
                $max > ($len = strlen($a = "$x[1]::$row->act$row->params")) or $max = $len;
                $out[$a] = $row;
            }
        }
        foreach ($out as $a => $row) {
            echo str_pad($a, $max, ' '), ' | ';
            if ($row->gerr = trim($row->gerr)) {
                echo "\033[91m$row->gerr\033[0m\n";
                continue;
            }
            foreach (explode('<br>', $row->ext) as $i => $url) {
                if ($i)
                    echo str_pad('', $max, ' '), ' | ';
                echo strip_tags($url);
                if ($row->re && !$i)
                    echo "\033[93m" . strip_tags($row->re) . "\033[0m";
                echo "\n";
            }
        }
    }

    /** Drop all cache */
    function c_drop() {
        echo Admin::drop_all_cache() ? 'Drop all cache: OK' : 'Error when drop cache';
    }

    /** List installed wares */
    function c_w() {
        $list = [];
        foreach (SKY::$plans as $ware => $cfg)
            $list[$ware] = ($cfg['app']['type'] ?? 'prod') . '::' . $cfg['app']['path'];
        print_r($list);
    }

    /** Search for errors using all possible methods */
    function c_e() {
        echo '2do';
    }

    /** Warm all cache */
    function c_warm() {
        echo '2do';
    }

    /** Show tables [con-name] [ware] */
    function c_t($name = '', $ware = '') {
        if ($ware)
            SKY::$databases += Plan::_r([$ware, 'conf.php'])['app']['databases'];
        echo 'result: ' . print_r(SQL::open($name)->_tables(), 1);
    }

    /** Execute SQL, example: sky sql "+select 1+1" [con-name] [ware] */
    function c_sql($sql, $name = '', $ware = '') {
        if ($ware)
            SKY::$databases += Plan::_r([$ware, 'conf.php'])['app']['databases'];
        echo 'result: ' . print_r(SQL::open($name)->sqlf($sql), 1);
    }

    /** Eval PHP code, example: sky eval "echo $sky->s_online;" */
    function c_eval($php) {
        global $sky;
        eval($php);
    }
}
