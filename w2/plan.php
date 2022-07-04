<?php

class Plan
{
    static $defaults = [
        'view' => ['path' => DIR_M . '/app/view'],
        'cache' => ['path' => 'var/cache'],
        'jet' => ['path' => 'var/jet'],
        'gate' => ['path' => 'var/gate'],
        'glob' => ['path' => 'var/glob'],
        'sql' => ['path' => 'var/sql'],
    ];
    static $wares = ['main'];
    static $ware = 'main';
    static $view = 'main';
    static $apps = [];
    static $ctrl = [];
    static $parsed_fn;
    static $put_cache = false;
    private static $connections = [];
    /*
        path   => required!
        driver => 'file' by default with '' (empty name) connection
        pref   => '' by default
        ttl    => -1 (infinity) by default
        dsn    => '' by default
        use    => '' by default ( => 'plan_name') - use selected connection
    */

    static function main() {
        do {
            $c = Gate::controllers();
        } while (0);
    #    SKY::$plans['main'] += ['class' => Plan::$class];
   #     Plan::$put_cache['main'] += ['class' => Plan::$class];
        SKY::$plans['main'] += ['ctrl' => $c + Plan::$ctrl];
        Plan::$put_cache['main'] += ['ctrl' => $c + Plan::$ctrl];
        Plan::cache_p(['main', 'sky_plan.php'], '<?php SKY::$plans = ' . var_export(Plan::$put_cache, true) . ';');
        Plan::$put_cache = false;
    }

    static function _g($a0, $w2 = false) {
        return $w2 ? file_get_contents(DIR_S . "/$a0") : Plan::__callStatic('_g', [$a0]);
    }

    static function view_g($a0) {
        $w2 = '_' == ($a0[1] ?? '') && '_' == $a0[0];
        if ($w2)
            return file_get_contents(DIR_S . '/w2/' . $a0);
        if ('main' != Plan::$ware && !Plan::view_t($a0))
            return Plan::__callStatic('view_g', [['main', $a0]]);
        return Plan::__callStatic('view_g', [$a0]);
    }

    static function __callStatic($func, $arg) {
        static $old_ware = false;
        static $old_obj = false;
        global $user;

        list ($pn, $op) = explode('_', $func);
        $pn or $pn = 'app';
        list ($ware, $a0) = is_array($arg[0]) ? $arg[0] : [Plan::$ware, $arg[0]];
        if ('jet' == $pn) {
            $a0 = Plan::$view . '-' . $a0;
        } elseif ('view' == $pn && 'main' == $ware) {
            $ware = Plan::$view;
        }
        if ($old_ware != $ware || $old_obj->pn != $pn) {
            $obj = (object)Plan::open($pn, $ware);
            $old_ware = $ware;
            $old_obj = $obj;
            $old_obj->pn = $pn;
        } else {
            $obj = $old_obj;
        }
        $conn = $obj->con;
        $conn->setup($obj);
        switch ($op) {
            case 'obj':
                return $obj;
            case 'tp': # jet for view(..) func
                Plan::$parsed_fn = $obj->path . '/' . $a0;
            case 't':
                return $conn->test($a0); # if OK return fullname
            case 'm':
                return $conn->mtime($a0);
            case 'p':
                return $conn->put($a0, $arg[1]);
            case 'b':
                return $conn->glob($a0); # mask
            case 'g':
            case 'gq':
                return $conn->get($a0, 'gq' == $op);
            case 'r':
            case 'rq':
                return $conn->run($a0, 'rq' == $op);
            case 'rr': # gate
                $recompile = $arg[1];
                return require $obj->path . '/' . $a0;
            case 'mf': # jet
                $s = $conn->get($a0);
                $line = substr($s, $n = strpos($s, "\n"), strpos($s, "\n", 2 + $n) - $n);
                return [$conn->mtime($a0), explode(' ', trim($line, " \r\n#"))];
            case 'ra': # autoloader
                $low = strtolower($a0);
                $cfg =& SKY::$plans['main']['class'];
                if (in_array(substr($a0, 0, 2), ['m_', 'q_', 't_'])) {
                    if (is_file($fn = $obj->path . "/app/$a0.php"))
                        return require $fn;
                    if ('main' != Plan::$ware && ($fn = Plan::_t(['main', "app/$a0.php"])))
                        return require $fn;
                    return eval("class $a0 extends Model_$a0[0] {}");
                } elseif (isset($cfg[$a0])) {
                    return Plan::_r([$cfg[$a0], "w3/$low.php"]);
                }
                $fn = DIR_S . '/w2/' . $low . '.php';
                return is_file($fn) ? require $fn : Plan::_r("w3/$low.php");
            case 'd':
            case 'dq':
                if (in_array($pn, ['view', 'glob', 'app']))
                    throw new Error("Failed when Plan::{$pn}_$op(..)");
                return $conn->drop($a0, 'dq' == $op);
            case 'da':
                if (in_array($pn, ['view', 'glob', 'app']))
                    throw new Error("Failed when Plan::{$pn}_da(..)");
                return $conn->drop_all($arg[1] ?? '.php');
            default: throw new Error("Plan::$func(..) - method not exists");
        }
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
                Plan::$put_cache = SKY::$plans;
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
