<?php

class Plan
{
    static $defaults = [
        'view' => ['path' => DIR_M . '/mvc/view'],
        'cache' => ['path' => 'var/cache'],
        'jet' => ['path' => 'var/jet'],
        'gate' => ['path' => 'var/gate'],
        'mem' => ['path' => 'var/mem'],
        'sql' => ['path' => 'var/sql'], //2do
    ];
    static $wares = ['main'];
    static $ware = 'main';
    static $view = 'main';
    static $gate = 'main';
    static $apps = [];
    static $ctrl = [];
    static $parsed_fn;
    private static $connections = [];
    /*
        path   => required!
        driver => 'file' by default with '' (empty name) connection
        pref   => '' by default
        ttl    => -1 (infinity) by default
        dsn    => '' by default
        use    => '' by default ( => 'plan_name') - use selected connection
    */

    static function set($ware) {
        $prev = Plan::$ware;
        Plan::$ware = $ware;
        return $prev;
    }

    static function vendor($class = false) {
        static $vendor = false;
        if ($class && $vendor) {
            call_user_func($vendor, $class);
        } elseif (!$class && !$vendor) {
            require 'vendor/autoload.php';
            if (2 != count($ary = spl_autoload_functions()))
                throw new Error('Vendor autoload');
            spl_autoload_unregister($vendor = 'Plan' === $ary[0][0] ? $ary[1] : $ary[0]);
        }
    }

    static function _g($a0, $w2 = false) {
        $std = is_array($a0) ? $a0[1] : $a0;
        return $w2 ? file_get_contents(DIR_S . "/$std") : Plan::__callStatic('_g', [$a0]);
    }

    static function view_($op, $a0) { # g or m
        list ($ware, $a0) = is_array($a0) ? $a0 : [Plan::$ware, $a0];
        if (!preg_match($re = "/^\w+\.jet$/", $a0))
            throw new Error("Jet: file `$a0` do not match $re");
        if ('_' == ($a0[1] ?? '') && '_' == $a0[0]) {
            $a0 = DIR_S . '/w2/' . $a0;
            return 'g' == $op ? file_get_contents($a0) : stat($a0)['mtime'];
        }
        if ('main' != $ware && !Plan::view_t([$ware, $a0]))
            return Plan::__callStatic('view_' . $op, [['main', $a0]]);
        return Plan::__callStatic('view_' . $op, [[$ware, $a0]]);
    }

    static function __callStatic($func, $arg) {
        static $old_ware = false;
        static $old_obj = false;
        list ($pn, $op) = explode('_', $func);
        $pn or $pn = 'app';
        list ($ware, $a0) = is_array($arg[0]) ? $arg[0] + [1 => 1] : [Plan::$ware, $arg[0]];

        if ('view' == $pn && 'main' == $ware && !is_array($arg[0]))
            $ware = Plan::$view;

        if ($old_ware == $ware && $old_obj->pn == $pn) {
            $obj = $old_obj;
        } else {
            $obj = (object)Plan::open($pn, $ware);
            $obj->pn = $pn;
            $old_ware = $ware;
            $old_obj = $obj;
        }
        $obj->quiet = 'q' === ($op[1] ?? 0) ? $a0 : false;
        if ($obj->con->setup($obj))
            return $arg[1] ?? ('rq' == $op ? [] : ('gq' == $op ? '' : 0));
        switch ($op) {
            case 'obj':
                return $obj;
            case 'b':
                return $obj->con->glob($a0); # mask
            case 'a': # append
            case 'p':
                return $obj->con->put($a0, $arg[1], 'a' == $op);
            case 'gq':
            case 'g':
                return $obj->con->get($a0);
            case 'tp': # jet for view(..) func
                Plan::$parsed_fn = $obj->path . '/' . $a0;
            case 't':
                return $obj->con->test($a0); # if OK return fullname
            case 'mq':
            case 'm':
                return $obj->con->mtime($a0);
            case 'mf': # jet
                $s = $obj->con->get($a0);
                $line = substr($s, $n = strpos($s, "\n"), strpos($s, "\n", 2 + $n) - $n);
                return [$obj->con->mtime($a0), explode(' ', trim($line, " \r\n#"))];
            case 'rq':
            case 'r':
                return $obj->con->run($a0);
            case 'rr': # gate
                $recompile = $arg[1];
                return require $obj->path . '/' . $a0;
            case 'da':
            case 'dq':
            case 'd':
                if (in_array($pn, ['view', 'mem', 'app']))
                    throw new Error("Failed when Plan::{$pn}_$op(..)");
                return 'da' == $op ? $obj->con->drop_all($a0) : $obj->con->drop($a0);
            case 'autoload':
                trace("autoload($a0)");
                if (strpos($a0, '\\'))
                    return Plan::vendor($a0);
                $low = strtolower($a0);
                $cfg =& SKY::$plans['main']['class'];
                if (in_array(substr($a0, 0, 2), ['m_', 'q_', 't_'])) {
                    if (is_file($fn = $obj->path . "/mvc/$a0.php"))
                        return require $fn;
                    if ('main' != $ware && ($fn = Plan::_t(['main', "mvc/$a0.php"])))
                        return require $fn;
                    return eval("class $a0 extends Model_$a0[0] {}");
                } elseif (isset($cfg[$a0])) {
                    return Plan::_r([$cfg[$a0], "w3/$low.php"]);
                }
                $fn = DIR_S . '/w2/' . $low . '.php';
                return is_file($fn) ? require $fn : Plan::_rq("w3/$low.php") || Plan::vendor($a0);
            default:
                throw new Error("Plan::$func(..) - method not exists");
        }
    }

    static function main($plans) {
        Plan::$ctrl += Gate::controllers();
        SKY::$plans['main'] += ['ctrl' => Plan::$ctrl];
        $plans['main'] += ['ctrl' => Plan::$ctrl];
        Plan::cache_p('sky_plan.php', '<?php SKY::$plans = ' . var_export($plans, true) . ';');
    }

    static function &open($pn, $ware = false) {
        $ware or $ware = Plan::$ware;

        if (!Plan::$connections) {
            require DIR_S . "/w2/dc_file.php";
            Plan::$connections[''] = new dc_file;
            $plans = SKY::$plans + Plan::$defaults;
            if (is_file($fn = $plans['cache']['path'] . '/' . 'sky_plan.php')) {
                require $fn;
            } else {
                SKY::$plans = [];
                $wares = is_file($fn = DIR_M . '/wares.php') ? (require $fn) : [];
                SKY::$plans['main'] = ['app' => ['path' => DIR_M], 'class' => []] + $plans;
                $cfg =& SKY::$plans['main']['class'];
                foreach ($wares as $key => $val) {
                    $plans = [];
                    require ($path = $val['path']) . "/conf.php";
                    if ($val['type'] ?? 0)
                        $plans['app']['type'] = 'pr-dev';
                    if (!DEV && in_array($plans['app']['type'], ['dev', 'pr-dev']))
                        continue;
                    foreach ($val['class'] as $cls)
                        'c_' == substr($cls, 0, 2) ? (Plan::$ctrl[substr($cls, 2)] = $key) : ($cfg[$cls] = $key);
                    unset($plans['app']['require'], $plans['app']['class']);
                    SKY::$plans[$key] = ['app' => ['path' => $path] + $plans['app']] + $plans;
                }
                Plan::main(SKY::$plans);
            }
            $cfg =& SKY::$plans['main'][$pn];
        } elseif (isset(SKY::$plans[$ware][$pn])) {
            $cfg =& SKY::$plans[$ware][$pn];
            if ($cfg['con'] ?? false)
                return $cfg;
        } else {
            $cfg =& SKY::$plans['main'][$pn];
            SKY::$plans[$ware][$pn] =& $cfg;
            if ($cfg['con'] ?? false)
                return $cfg;
        }
        if ($cfg['driver'] ?? false) {
            $class = 'dc_' . $cfg['driver'];
            Plan::$connections[$pn] = $cfg['con'] = new $class($cfg);
            unset($cfg['dsn']);
        } else {
            $cfg['con'] = Plan::$connections[$cfg['use'] ?? ''];
        }
        return $cfg;
    }
}

