<?php

class Plan
{
    static $planets = [];
    static $defaults = [
        'app' => ['path' => 'main/app']; # must exists: ../conf.php
        'view' => ['path' => 'view'];
        'cache' => ['path' => 'var/cache'];
        'glob' => ['path' => 'var/glob'];
        'jet' => ['path' => 'var/jet'];
        'sql' => ['path' => 'var/sql'];
        'plan' => []; // 'planet_name' (module name) => 'mods/path_to_app_dir'
    ];
    /*
        driver => 'file' by default
        path   => required!
        pref   => '' by default ??
        ttl    => -1 (infinity) by default
    */
    private $name;
    private $path;
    private $driver;

    function __construct($name, $row = [], $div = true) {
        $this->name = $name;
        $cfg =& SKY::$plans[$name];
   //     isset($cfg['pref']) or $cfg['pref'] = '';
        $driver = 'dc_' . ($cfg['driver'] ?? 'file');
        $this->driver = new $driver($cfg);
 //  unset($cfg['dsn']);
    }

    static function init_modules() {
        
    }

    static function open($name = '', $p2 = false) {
        if (isset(Plan::$planets[$name])) {
            $dc = Plan::$planets[$name];
        } else {
            if (!Plan::$planets) {
                SKY::$plans += Plan::$defaults;
                if (SKY::$plans['plan'])
                    Plan::init_modules();
            }
            Plan::$planets[$name] = $dc = new Plan($name);
        }
        return $dc;
    }

    static function cache($name = false, $func = null, $ttl = 0) {
        global $sky;
        static $cache = [];
        
        if ($name) { # the begin place
            if (is_array($name)) {
                list ($name, )
            } else {
                
            }
            $file = 'var/cache/_' . (DEFAULT_LG ? '%s_' : '') . "$name.php";
            if (is_numeric($func))
                $ttl = $func;
            if (-1 == $ttl) # delete on ttl = -1
                return @unlink(DEFAULT_LG ? sprintf($file, $func) : $file);
            $fn = DEFAULT_LG ? sprintf($file, LG) : $file;
            $ttl or $ttl = SKY::$mem['s'][3]['cache_sec'];
            $recompile = true;
            if ($sky->s_cache_act && is_file($fn)) {
                $s = stat($fn);
                $s['mtime'] + $ttl < time() or $recompile = false;
            }

            trace("CACHE: $fn, " . ($recompile ? 'recompiled' : 'used cached'));

            if (is_callable($func)) {
                $recompile
                    ? file_put_contents($fn, $str = call_user_func($func, $name, true))
                    : ($str = file_get_contents($fn));
                return $str;
            } elseif ($recompile) {
                $cache[] = $fn;
                ob_start();
                return true;
            }
            require $fn;
            return false;
            
        } else { # the end place in the template
            $dc = array_pop($cache);
            echo $str = ob_get_clean();
            $dc->save($dc->fn, $str);
            //file_put_contents($fn, );
            
        }
    }
}
