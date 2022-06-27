<?php

class Plan
{
    static $tree = [];
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

    /*
        driver => 'file' by default
        path   => required!
        pref   => '' by default ??
        ttl    => -1 (infinity) by default
        dsn    => '' by default ??
    */
    public $path;
    public $pref;

    private $apn;
    private $driver;

    function __construct($pn, $ware = 'main') {
        $this->apn = [$pn, $ware];
        $cfg =& SKY::$plans[$ware][$pn];
        $this->path = $cfg['path'] . '/';
        $this->pref = $cfg['pref'] ?? '';
        $driver = 'dc_' . ($cfg['driver'] ?? 'file');
        require_once DIR_S . "/w2/$driver.php";
        $this->driver = new $driver($cfg);
        unset($cfg['dsn']);
    }

    static function __callStatic($func, $arg) {
        list ($name, $op) = explode('_', $func);
        $plan = Plan::open($name ?: 'app');
        $a0 = $arg[0];
        if ('jet' == $name)
            $a0 = Plan::$ware . '-' . $a0;
        switch ($op) {
            case 'p': return $plan->put($a0, $arg[1]);
            case 'g': return $plan->get($a0);
           case 'g2': return file_get_contents(DIR_S . "/w2/$a0.php");
            case 'tp': Plan::$parsed_fn = $plan->path . $a0;
            case 't': return $plan->test($a0);
            case 'm': return $plan->mtime($a0);
            case 'r': return $plan->get($a0, false);
            case 'rr': $recompile = $arg[1]; return require $plan->path . $a0;
            case 'gs':
                if ('_' == ($a0[1] ?? '') && '_' == $a0[0])
                    return file_get_contents(DIR_S . '/w2/' . $a0);
                return $plan->get($a0);
            case 'mf':
                $s = $plan->get($a0);
                $line = substr($s, $n = strpos($s, "\n"), strpos($s, "\n", 2 + $n) - $n);
                return [$plan->mtime($a0), explode(' ', trim($line, " \r\n#"))];
            # the app postfixes:
            case 'w2':
            case 'as':
                if (is_file($fn = DIR_S . '/w2/' . ($a0 = strtolower($a0)) . '.php'))
                    return require $fn;
                if ('w2' == $op)
                    throw new Error("Plan::_w2($arg[0]) failed");
                $a0 = "w3/$a0";
            case 'ap': return require ($plan->path . "$a0.php");
            case 'am':
                $fn = $plan->path . "app/$a0.php";
                return is_file($fn) ? require $fn : eval("class $a0 extends Model_$a0[0] {}");
            default: throw new Error("Plan::$func(..) - method not exists");
        }
    }

    static function open($name, $ware = false) {
        $ware or $ware = Plan::$ware;
        if (isset(Plan::$tree[$ware][$name])) {
            return Plan::$tree[$ware][$name];
        } elseif ('cache' == $name && 'main' == $ware) {
            SKY::$plans['main']['cache'] = SKY::$plans['cache'] ?? Plan::$defaults['cache'];
        } elseif (!Plan::$tree) {
            $cache = Plan::open('cache');
            if (!$cache->get('sky_plan.php', false, true)) {
                $wares = SKY::$plans['wares'] ?? [];
                unset(SKY::$plans['main'], SKY::$plans['wares']);
                SKY::$plans['main'] = SKY::$plans + Plan::$defaults + ['app' => ['path' => DIR_M]];
                foreach ($wares as $key) {
                    $plans = [];
                    if (is_file($fn = 'wares/' . $key . '/conf.php'))
                        require $fn;
                    SKY::$plans[$key] = ['app' => ['path' => 'wares/' . $key]] + $plans + SKY::$plans['main'];
                }
                $cache->put('sky_plan.php', '<?php SKY::$plans = ' . var_export(SKY::$plans, true) . ';');
            }
        }
        return Plan::$tree[$ware][$name] = new Plan($name, $ware);
    }

    function test($name) {
        return $this->driver->test($this->path . $name);
    }

    function get($name, $return = true, $quiet = false) {
        return $this->driver->get($this->path . $name, $return, $quiet);
    }

    function put($name, $data) {
        return $this->driver->put($this->path . $name, $data);
    }

    function mtime($name) {
        return $this->driver->mtime($this->path . $name);
    }

    function drop($name, $quiet = false) {
        if (in_array($this->apn[0], ['view', 'glob', 'app']))
            throw new Error("Cannot drop " . $this->apn[0]);
        return $this->driver->drop($this->path . $name, $quiet);
    }

    function drop_all() {
        if (in_array($this->apn[0], ['view', 'glob', 'app']))
            throw new Error("Cannot drop all " . $this->apn[0]);
        return $this->driver->drop_all($this->path);
    }
}
