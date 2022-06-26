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
       // 'ware' => [], // 'ware_name' (module name) => 'wares/path_to_main_dir'
    ];
    static $wares = ['main'];
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

    static function app($name, $p = false) {
        $app = Plan::open('app', end(Plan::$wares));
        if ('_' == $p) {
            $fn = "app/$name.php";
            return $app->test($fn) ? $app->get($fn, false) : eval("class $name extends Model_$name[0] {}");
        } elseif (!$p) {
            $fn = DIR_S . '/w2/' . ($name = strtolower($name)) . '.php';
            return $app->driver->test($fn) ? $app->driver->get($fn, false) : false;
        }
        $app->get($p . "$name.php", false); # w3/
    }

    static function gate($name, $p = false) {
        if ('test' == $p)
            return '';
        if ('mtime' == $p)
            return '';
        if ('put' == $p)
            return '';
    }

    static function view($name, $p = false) {
        static $view;

        if (!$name)
            return $view = 0;
        $view or $view = Plan::open('view', end(Plan::$wares));
        
        $path = '_' == ($name[1] ?? '') && '_' == $name[0] ? DIR_S . '/w2/' : $view->path;
        
        if ('test' == $p)
            return $view->driver->test($path . $name);
        if ('mtime' == $p)
            return $view->driver->mtime($path . $name);
        if ('get' == $p) {
            $s = $view->driver->get($path . $name, true);
            $view = 0;
            return $s;
        }
    }

    static function jet(&$in, $p = 0, $data = 0) { # parsed templates
        static $fn;
        static $jet;

        if (0 === $p) {
            $r = $jet->get($fn, false, false, $in);
            $jet = 0;
            return $r;
        }
        $ware = end(Plan::$wares);
        $jet or $jet = Plan::open('jet', $ware);
        $fn = $ware . "-$in";
        if ('mx' == $p) {
            $s = $jet->get($fn, true);
            $line = substr($s, $n = strpos($s, "\n"), strpos($s, "\n", 2 + $n) - $n);
            return [$jet->mtime($fn), explode(' ', trim($line, " \r\n#"))];
        }
        if ('test' == $p)
            return $jet->test($fn);
        if ('put' == $p)
            return $jet->put($fn, $data);
    }

    static function open($name, $ware = 'main') {
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

    function get($name, $return = true, $quiet = false, &$vars = false) {
        return $this->driver->get($this->path . $name, $return, $quiet, $vars);
    }

    function put($name, $data) {
        return $this->driver->put($this->path . $name, $data);
    }

    function mtime($name) {
        return $this->driver->mtime($this->path . $name);
    }

    function drop_all() {
        if (in_array($this->apn[0], ['view', 'glob', 'app']))
            throw new Error("Cannot drop all " . $this->apn[0]);
        return $this->driver->drop_all($this->path);
    }

    function drop($name, $quiet = false) {
        if (in_array($this->apn[0], ['view', 'glob', 'app']))
            throw new Error("Cannot drop " . $this->apn[0]);
        return $this->driver->drop($this->path . $name, $quiet);
    }
}
