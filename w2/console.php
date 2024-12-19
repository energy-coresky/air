<?php

class Console
{
    static $d;

    function __construct($argv = [], $found = []) {
        global $sky;

        if ('Console' != get_class($this))
            return $argv && call_user_func_array([$this, $argv], $found);

        self::$d = $found + [3 => $ns = $found[2] && 'air' != basename($found[2])];

        if ('master' == $argv[1]) {
            if ($ns || is_dir(DIR_S . '/.git'))
                return $this->master(!$ns);
        } elseif ('s' == $argv[1]) {
            return $this->s($argv[2] ?? 8000);
        } elseif ($found[0]) {
            SQL::$dd_h = 'Console::dd_h';
            if ('_' !== ($argv[2][0] ?? '') && SKY::$plans['main']['app']['cfg']['databases'])
                $sky->open();
        }
        $this->__call("c_$argv[1]", array_slice($argv, 2));
    }

    static function dd_h($dd, $name, $ware) {
        if ('core' == $name && 'main' == $ware) {
            require DIR_S . '/w2/mvc.php';
            Plan::app_r('mvc/common_c.php');
        }
        common_c::dd_h($dd, $name, $ware);
    }

    function __call($name, $args) {
        static $src;

        if (is_null($src) && self::$d[0]) {
            $src = ['' => new ReflectionClass('Console')];
            if (is_file(DIR_M . '/w3/app.php'))
                $src += ['app' => new ReflectionClass('App')];
            foreach (SKY::$plans as $w => $_) {
                if ('main' == $w || !Plan::_rq([$w, "w3/app.php"]))
                    continue;
                $r = new ReflectionClass("$w\\app");
                if (($pr = $r->getParentClass()) && 'Console' == $pr->name)
                    $src[$w] = $r;
            }
        }

        $com = substr($name, 2);
        if ($com && isset($src[$com]) && 'c_' == substr($name, 0, 2)) {
            $class = $src[$com]->getName();
            return new $class('a_' . array_shift($args), $args);
        }

        $cls = explode('\\', strtolower($_cls = get_class($this)))[0];
        $ext = 'Console' == $_cls ? '' : "$cls ";
        $ary = $ext ? [] : [
            's' => 'Run PHP web-server',
            'v' => 'Show Coresky version',
            'dir' => 'List dirs (from current dir)',
            'php' => 'Lint PHP files (from current dir)',
        ];
        $ware = self::$d[1] ? basename(self::$d[1]) : false;
        if (!$ext && (self::$d[3] || is_dir(DIR_S . '/.git'))) {
            $repo = 'new CORESKY version';
            if (self::$d[3])
                $repo = $ware ? "ware `$ware`" : 'repository';
            $ary += ['master' => "Push $repo to remote origin master"];
        }
        foreach ($src ?? [] as $w => $rfn) {
            if ($ext && $cls != $w || !$ext && $w) {
                $ext or $ary += [$w => "\033[93mList `$w` commands\033[0m"];
                continue;
            }
            $list = $rfn->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($list as $v) {
                $pfx = substr($v->name, 0, 2);
                if ('c_' == $pfx && !$w || 'a_' == $pfx && $w)
                    $ary[($w ? "$w " : '') . substr($v->name, 2)] = trim($v->getDocComment(), "*/ \n\r");
            }
        }
        if (isset($ary[$ext . $com])) {
            return call_user_func_array([$this, $name], $args);
        } elseif ($com) {
            echo "\nCommand `" . trim($ext . $com) . "` not found\n\n";
        }
        $ary = array_filter($ary);
        ksort($ary);
        echo "Usage: sky command [param ...]\n";
        echo $ext ? ucfirst($cls) . " commands are:\n  " : "Commands are:\n  ";
        echo implode("\n  ", array_map(fn($k, $v) => str_pad($k, 15, ' ') . $v, array_keys($ary), $ary));
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

    function s($port) {
        global $dir_run;

        if (function_exists('socket_create')) {
            for ($i = 0; $i < 9; $i++, $port++) {
                $sock = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
                $busy = @socket_connect($sock, '::1', $port);
                socket_close($sock);
                if (!$busy)
                    break;
            }
        }
        require DIR_S . '/w2/boot.php';
        chdir(Boot::www() ?: $dir_run());
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

        $main = self::$d[0] && realpath(DIR) === realpath(self::$d[2]);
        if ($app = self::$d[0] && !$air && (self::$d[1] || $main)) {
            chdir(DIR);
            SQL::$dd_h = 'Console::dd_h';
            if ($main) {
                global $sky;
                $sky->open();
                common_c::make_h(true);
            }
        }
        chdir($air ? DIR_S : self::$d[2]);
        echo "\n>git add *\n";
        system('git add *');
        echo "\n>git commit -a -m \"$c\"\n";
        system("git commit -a -m \"$c\"");
        echo "\n>git push origin master\n";
        system("git push origin master");
        if ($app) {
            $main && common_c::make_h(false);
            chdir(DIR);
            if (self::$d[1] && Plan::_t('w3/master.php'))
                new Master(self::$d[1]);
        }
    }

    /** Show markers [1] (from current dir) */
    function c_markers($i = false) {
        global $dir_run;
        $list = [];
        foreach (Rare::list_path($dir_run(), 'is_file') as $fn) {
            $data = file_get_contents($fn = str_replace('\\', '/', $fn));
            if (!preg_match_all("/^#\.(\w+(\.\w+)*)/m", $data, $m, 0))
                continue;
            $p =& $list[basename($fn)];
            foreach ($m[1] as $line) {
                foreach (explode('.', $line) as $marker)
                    isset($p[$marker]) ? $p[$marker]++ : ($p[$marker] = 1);
            }
            ksort($p);
        }
        if ($i) {
            echo " \033[91m Markers intersection\033[0m:\n";
            foreach ($list as $fn => $ary) {
                $all = [];
                array_walk($list, function ($v, $k) use (&$all, $fn) {
                    $fn == $k or $all = array_merge($all, array_keys($v));
                });
                if ($ary = array_intersect(array_keys($ary), $all))
                    echo "  FILE: $fn, MARKERS: " . implode(' ', $ary) . "\n";
            }
        } else {
            foreach ($list as $fn => $ary) {
                echo "  FILE: $fn, MARKERS:";
                foreach ($ary as $marker => $n)
                    echo 1 == $n ? "\033[93m $marker\033[0m"
                        : (2 == $n ? " $marker" : "\033[91m $marker\033[0m");
                echo "\n";
            }
            echo " \033[93m yellow\033[0m - no right boundary\n";
            echo " \033[91m red\033[0m - more then twice boundary\n";
        }
    }

    /** Show Coresky versions */
    function c_v() {
        $main = ['core', 'sql', 'mvc', 'processor'];
        if (!$exist = class_exists('SKY', false)) {
            require DIR_S . '/sky.php';
            foreach ($main as $bn)
                require DIR_S . "/w2/$bn.php";
        }
        echo '  Coresky: ' . SKY::CORE . ' path:' . realpath(DIR_S);
        foreach (glob(DIR_S . '/w2/*.php') as $fn) {
            if ('core' == ($bn = basename($fn, '.php')))
                continue;
            $exist or in_array($bn, [-1 => 'console'] + $main) or require $fn;
            $reflection = new ReflectionClass($bn);
            if (isset($reflection->getConstants()['version']))
                echo "\n  {$reflection->getName()}::version - " . $bn::version;
        }
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
        Plan::_p('rewrite.php', Boot::auto($map));
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
        Plan::_p('gate.php', Boot::auto(yml('default_c: @inc(yml.default_c) ~/w2/gate.php')));
        $this->c_drop();
    }

    /** Write "first run" into index.php */
    function c_fr() {
        common_c::make_h(true);
    }

    /** Read tmemo cell from $_memory */
    function c_m($id = 8, $unhtml = false) {
        global $sky;
        $sky->trace_cli = false;
        $s = sqlf('+select tmemo from $_memory where id=%d', $id);
        //$id > 3 or $s = strip_tags($s);
        echo !$unhtml ? $s : (1 == $unhtml ? unhtml($s) : unhtml(unhtml($s)));
    }

    /** Diff text files, example: sky d oldfile newfile */
    function c_d($fno, $fnn) {
        [$out, $add, $sub, $z] = Display::diffx(file_get_contents($fnn), file_get_contents($fno));
        echo '' === $out ? 'Files identical' : "@@ -$sub +$add @@ $z\n$out";
    }

    /** Check globals */
    function c_g() {
        DEV::init();
        (new Globals)->c_run();
    }

    /** Show controllers */
    function c_c() {
        # 2do: red
        echo "Rescanned:\n  " . unbang(Boot::controllers(), fn($k, $v) => "$k: " . ($v[0] ? '' : 'not ') . 'exist', "\n  ");
        echo "\nFrom SKY::\$plans:\n  " . unbang(SKY::$plans['main']['ctrl'], ' => ', "\n  ");
    }

    /** Show top-view actions (routes) */
    function c_a() {
        Gate::$cshow = true;
        Rewrite::get($lib, $map, $keys);
        $max = 0;
        $out = [];
        foreach (Boot::controllers() as $x) {
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

    /** List installed wares */
    function c_w() {
        $list = [];
        foreach (SKY::$plans as $ware => $cfg)
            $list[$ware] = ($cfg['app']['type'] ?? 'prod') . '::' . $cfg['app']['path'];
        print_r($list);
    }

    /** Parse Yaml [ware=main] [fn=config.yaml] [one of 0|1|2|3] or Inline Yaml > sky y "+ @csv(;) $PATH()" */
    function c_y($ware = 'main', $fn = 'config.yaml', $func = 0) {
        $list = ['var_export', 'print_r', 'var_dump', 'PHP::ary'];
        $out = fn($v, $func) => is_string($v) ? print($v) : call_user_func($list[$func], $v);
        if (strpos($ware, ' ')) { # inline yaml
            !is_num($fn) or $func = $fn;
            '+' == $ware[0] or print "Inline Yaml: ";
            $out(YML::text($ware), $func);
        } elseif (!$fn = Plan::_t([$ware, $fn])) {
            echo isset(SKY::$plans[$ware])
                ? "File not found in ware `$ware`"
                : "Ware `$ware` not installed";
        } else {
            echo "File `$fn`, ware=$ware is: ";
            Plan::set($ware, fn() => $out(YML::file($fn), $func));
        }
    }

    /** Parse CSS file */
    function c_css($fn, $ware = 'main') {
        echo CSS::file(Plan::_t([$ware, $fn]));
    }

    /** Parse XML file */
    function c_x($fn, $pad = '') {
        echo XML::file(Plan::_t(['main', $fn]), $pad);
    }

    /** Show ZML file info (.zml or .sky file extension) */
    function c_z($fn = '', $pad = '') {
        echo '2do';
    }

    /** Drop all cache */
    function c_drop() {
        echo Debug::drop_all_cache() ? 'Drop all cache: OK' : 'Error when drop cache';
    }

    /** Warm all cache */
    function c_warm() {
        echo Debug::warm_all_cache() ? 'WARM all cache: OK' : 'Error when WARM cache';
    }

    /** Search for errors using all possible methods */
    function c_e() {
        echo '2do';
    }

    /** Show table structure [tbl-name] [ware-main] [con-name] */
    function c_ts($tbl = '', $ware = 'main', $name = 'core') {
        if (!$tbl)
            return print 'Error: write a table name';
        if ($struct = SQL::open($name, $ware)->_struct($tbl))
            $struct = array_map(function ($ary) {
                return $ary[2];
            }, $struct);
        echo "$ware::$name.$tbl " . print_r($struct, 1);
    }

    /** Show tables [ware=main] [con-name] */
    function c_t($ware = 'main', $name = 'core') {
        print("$ware::$name " . print_r(SQL::open($name, $ware)->_tables(), 1));
    }

    /** Execute SQL, example: sky sql "+select 1+1" [con-name] [ware] */
    function c_sql($sql, $name = 'core', $ware = 'main') {
        $list = Rare::split($sql);
        foreach ($list as $sql)
            $out = SQL::open($name, $ware)->sqlf(trim($sql));
        echo !$list || $out instanceof SQL ? 'queries executed: ' . count($list) : 'result: ' . print_r($out, 1);
    }

    /** Eval PHP code, example: sky eval "echo $sky->s_online;" */
    function c_eval($php) {
        global $sky;
        eval($php);
    }
}
