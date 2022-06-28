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
       // 'wares' => [], // 'ware_name' (module name) => 'wares/path_to_main_dir'
    ];
    static $wares = ['main'];
    static $ware = 'main';
    static $parsed_fn;
    private static $connections = [];
    /*
        driver => 'file' by default
        path   => required!
        pref   => '' by default ??
        ttl    => -1 (infinity) by default
        dsn    => '' by default ??
    */

    static function _g($a0, $w2 = false) {
        return $w2 ? file_get_contents(DIR_S . "/$a0") : Plan::__callStatic('_g', [$a0]);
    }

    static function view_g($a0) {
        $w2 = '_' == ($a0[1] ?? '') && '_' == $a0[0];
        return $w2 ? file_get_contents(DIR_S . '/w2/' . $a0) : Plan::__callStatic('view_g', [$a0]);
    }

    static function __callStatic($func, $arg) {
        static $old_ware = false;
        static $old_obj = false;
        list ($pn, $op) = explode('_', $func);
        list ($ware, $a0) = is_array($arg[0]) ? $arg[0] : [Plan::$ware, $arg[0]];
        if ($old_ware != $ware || $old_obj->pn != $pn) {
            $obj = (object)Plan::open($pn ?: 'app', $ware);
            $old_ware = $ware;
            $old_obj = $obj;
            $old_obj->pn = $pn;
        } else {
            $obj = $old_obj;
        }
        $con = $obj->con;
        $con->setup($obj);
        if ('jet' == $pn)
            $a0 = $ware . '-' . $a0;
        switch ($op) {
            case 'tp': # jet for view(..) func
                Plan::$parsed_fn = $obj->path . '/' . $a0;
            case 't':
                return $con->test($a0);
            case 'm':
                return $con->mtime($a0);
            case 'p':
                return $con->put($a0, $arg[1]);
            case 'g':
            case 'gq':
                return $con->get($a0, 'gq' == $op);
            case 'r':
            case 'rq':
                return $con->run($a0, 'rq' == $op);
            case 'rr': # gate
                $recompile = $arg[1];
                return require $obj->path . '/' . $a0;
            case 'mf': # jet
                $s = $con->get($a0);
                $line = substr($s, $n = strpos($s, "\n"), strpos($s, "\n", 2 + $n) - $n);
                return [$con->mtime($a0), explode(' ', trim($line, " \r\n#"))];
            case 'ra': # autoloader
                if (in_array(substr($a0, 0, 2), ['m_', 'q_', 't_'])) {
                    $fn = $obj->path . "/app/$a0.php";
                    return is_file($fn) ? require $fn : eval("class $a0 extends Model_$a0[0] {}");
                }
                $fn = DIR_S . '/w2/' . ($a0 = strtolower($a0) . '.php');
                return is_file($fn) ? require $fn : Plan::_r("w3/$a0");
            case 'd':
            case 'da': return; # 2do
#        if (in_array($this->apn[0], ['view', 'glob', 'app']))
 #           throw new Error("Cannot drop " . $this->apn[0]);
  #      if (in_array($this->apn[0], ['view', 'glob', 'app']))
   #         throw new Error("Cannot drop all " . $this->apn[0]);
            default: throw new Error("Plan::$func(..) - method not exists");
        }
    }

    static function &open($pn, $ware = false) {
        $ware or $ware = Plan::$ware;

        if (!Plan::$connections) {
            require DIR_S . "/w2/dc_file.php";
            Plan::$connections[''] = new dc_file;
            $cfg = SKY::$plans['cache'] ?? Plan::$defaults['cache'];
            if (is_file($fn = $cfg['path'] . '/' . 'sky_plan.php')) {
                require $fn;
            } else {
                $plans = SKY::$plans;
                SKY::$plans = [];
                $wares = $plans['wares'] ?? [];
                unset($plans['wares']);
                SKY::$plans['main'] = ['app' => ['path' => DIR_M]] + $plans + Plan::$defaults;
                foreach ($wares as $key) {
                    $plans = [];
                    require 'wares/' . $key . '/conf.php';
                    SKY::$plans[$key] = ['app' => ['path' => 'wares/' . $key]] + $plans;
                }
                file_put_contents($fn, '<?php SKY::$plans = ' . var_export(SKY::$plans, true) . ';');
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
