<?php

class Boot
{
    const version = 1.0;

    private static $const = [];

    static function auto($v, $more = '', $func = false) {
        $array = var_export($v, true);
        $func && call_user_func_array($func, [&$array]);
        return "<?php\n\n# this is auto generated file, do not edit\n$more\nreturn $array;\n";
    }

    function __construct($dc = false, $nx = null) {
        require DIR_S . "/w2/processor.php";
        require DIR_S . "/w2/rare.php";
        require DIR_S . "/w2/yaml.php";
        Yaml::$boot = 1;
        $cfg = self::cfg($ymls, DIR_M . '/config.yaml');
        self::$const = $cfg['define'];
        $plans = SKY::$plans + ($cfg['plans'] ?? []) + [
            'view' => ['path' => DIR_M . '/mvc/view'],
            'cache' => ['path' => 'var/cache'],
            'gate' => ['path' => 'var/gate'],
            'jet' => ['path' => 'var/jet'],
            'mem' => ['path' => 'var/mem'],
        ];

        $more = "\n\ndate_default_timezone_set('$cfg[timezone]');\n";
        if (Yaml::$dev) {
            fseek($fp = fopen(__FILE__, 'r'), __COMPILER_HALT_OFFSET__);
            $more = "\n" . trim(stream_get_contents($fp)) . $more;
            fclose($fp);
        }
        foreach (yml('+ @inc(define)') + $cfg['define'] as $key => $val)
            $more .= "define('$key', " . var_export($val, true) . ");\n";
        $more .= "define('NOW', date(DATE_DT));\n";
        $more .= "define('CLI', 'cli' == PHP_SAPI);\n";
        foreach ($cfg['ini_set'] as $key => $val)
            $more .= "ini_set('$key', " . var_export($val, true) . ");\n";

        unset($cfg['plans'], $cfg['define'], $cfg['ini_set'], $cfg['timezone']);
        SKY::$plans = $ctrl = [];
        $app = ['path' => DIR_M, 'cfg' => $cfg];
        SKY::$plans['main'] = ['rewrite' => '', 'class' => [], 'app' => $app] + $plans;
        $ymls = ['main' => $ymls];
        if (is_file($fn = DIR_M . '/wares.php'))
            $ymls += self::wares($fn, $ctrl, SKY::$plans['main']['class']);
        $plans = SKY::$plans;
        $plans['main'] += ['ctrl' => $ctrl + self::controllers('main')];
        SKY::$plans['main']['cache']['dc'] = $dc;
        Plan::cache_s('sky_plan.php', self::auto($plans, $more, ['Boot', 'rewrite']));
        foreach ($ymls as $ware => $yml)
            self::cfg($yml, $ware);
        SKY::$plans = Plan::cache_r('sky_plan.php', (object)['data' => ['dc' => $nx]]);

        $wares = array_map(fn($v) => $v['app']['path'], SKY::$plans) + ['sky' => DIR_S];
        foreach ($wares as $ware => $path) {
            if (!is_dir($dir = "$path/assets"))
                continue;
            foreach (glob("$dir/*") as $src) {
                [$name, $ext] = explode('.', $fn = basename($src), 2) + [1 => ''];
                if ('dev' == $name) // $ext ?
                    continue;
                $dst = $ware == $name ? WWW . "m/$fn" : WWW . "w/$ware/$fn";
                if (DEV && is_file($dst)) {
                    unlink($dst);
                } elseif (!DEV && !is_file($dst)) {
                    is_dir($dir = dirname($dst)) or mkdir($dir, 0777, true);
                    copy($src, $dst);
                }
            }
        }
        Yaml::$boot = 0;
    }

    static function get_const($key, &$v) {
        if (!isset(self::$const[$key]))
            return false;
        $v = self::$const[$key];
        'WWW' != $key or $v = substr($v, 0, -1);
        return true;
    }

    static function set_const(&$p) {
        self::$const =& $p;
    }

    static function cfg(&$name, $ware = 'main') {
        if (null === $name) {
            $name = is_file($ware) ? Yaml::file($ware) : [];
            return $name['core'] ?? [];
        } elseif (is_array($name)) {
            foreach ($name as $key => $val) {
                if ('core' != $key && is_array($val))
                    Plan::cache_s(['main', "cfg_{$ware}_$key.php"], self::auto($val));
            }
        } else {
            $yml = Yaml::file(Plan::_t([$ware, 'config.yaml']))[$name];
            return is_string($yml) ? Yaml::inc($yml, $ware) : $yml;
        }
    }

    static function www() {
        for ($i = 4, $a = ['public', 'public_html', 'www', 'web']; $a; --$i or $a = glob('*')) {
            $dir = array_shift($a);
            if ('_' != $dir[0] && is_file($fn = "$dir/index.php") && strpos(file_get_contents($fn), 'new HEAVEN'))
                return "$dir/";
        }
        return false;
    }

    static function rewrite(&$in) {
        $code = "\n";
        foreach (Plan::_rq('rewrite.php') as $rw)
            !Yaml::$dev && $rw[2] or $code .= $rw[1] . "\n";
        $in = explode("'',", $in, 2);
        $in = "$in[0]function(\$cnt, &\$surl, \$uri, \$sky) {{$code}},$in[1]";
    }

    static function wares($fn, &$ctrl, &$class) {
        $ymls = [];
        foreach (require $fn as $ware => $ary) {
            unset($yml);
            $path = str_replace('\\', '/', $ary['path']);
            if (!$cfg = Boot::cfg($yml, "$path/config.yaml"))
                continue; ////?
            $plan = $cfg['plans'];
            if ($ary['type'] ?? false)
                $plan['app']['type'] = 'pr-dev';
            if ($ary['options'] ?? false)
                $plan['app']['options'] = $ary['options'];
            if (!Yaml::$dev && in_array($plan['app']['type'], ['dev', 'pr-dev']))
                continue;
            foreach ($ary['class'] as $cls) {
                $df = 'default_c' == $cls;
                if ($df || 'c_' == substr($cls, 0, 2)) {
                    $x = $df ? '*' : substr($cls, 2);
                    $ctrl[$ary['tune'] ? "$ary[tune]/$x" : $x] = $ware;
                } else {
                    $class[$cls] = $ware;
                }
            }
            $app =& $plan['app'];
            unset($cfg['plans'], $app['require'], $app['class']);
            $app['cfg'] = $cfg;
            if ($yml)
                $ymls[$ware] = $yml;
            SKY::$plans[$ware] = ['app' => ['path' => $path] + $plan['app']] + $plan;
        }
        return $ymls;
    }

    static function controllers($ware = false, $plus = false) {
        $list = [];
        if (!$ware) {
            foreach (SKY::$plans as $ware => &$cfg) {
                if ('main' == $ware || 'prod' == $cfg['app']['type'])
                    $list += self::controllers($ware, true);
            }
            return $list;
        }
        $glob = Plan::_b([$ware, 'mvc/c_*.php']);
        if ($fn = Plan::_t([$ware, 'mvc/default_c.php']))
            array_unshift($glob, $fn);
        $z = 'main' == $ware ? false : $ware;
        foreach ($glob as $v) {
            $k = basename($v, '.php');
            $v = 'default_c' == $k ? '*' : substr($k, 2);
            $list[$plus ? "$ware.$k" : $v] = $plus ? [1, $k, $z] : $ware;
        };
        if ($plus) {
            foreach (Plan::_rq([$ware, 'gate.php']) as $k => $v) {
                $v = 'default_c' == $k ? '*' : substr($k, 2);
                isset($list["$ware.$k"]) or $list["$ware.$k"] = [0, $k, $z]; # deleted
            }
        }
        return $list;
    }
}

__halt_compiler();

global $sky;
if (true === $dc) {
    $sky->tracing = "sky_plan.php: created\n\n";
} elseif ($dc) {
    $recompile = stat(DIR_M . '/config.yaml')['mtime'] > $dc->mtime('sky_plan.php');
    $sky->tracing = 'sky_plan.php: ' . ($recompile ? 'recompiled' : 'used cached') . "\n\n";
    if ($recompile)
        return false;
}
