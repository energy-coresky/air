<?php

class Boot
{
    use Processor;#2do ?

    const version = 0.888;

    private static $dev = false;
    private static $boot = false;
    private static $dir;

    private $at;
    private $array = [];

    static function auto($v, $more = '', $func = false) {
        $array = var_export($v, true);
        $func && call_user_func_array($func, [&$array]);
        return "<?php\n\n# this is auto generated file, do not edit\n$more\nreturn $array;\n";
    }

    function __construct($dc = false) {
        if (!$dc)
            return;
        self::$boot = true;
        $cfg = self::cfg($ymls, DIR_M . '/config.yaml');
        $plans = SKY::$plans + ($cfg['plans'] ?? []) + [
            'view' => ['path' => DIR_M . '/mvc/view'],
            'cache' => ['path' => 'var/cache'],
            'gate' => ['path' => 'var/gate'],
            'jet' => ['path' => 'var/jet'],
            'mem' => ['path' => 'var/mem'],
        ];

        $more = "\ndate_default_timezone_set('$cfg[timezone]');\n";
        $more .= "define('NOW', date(DATE_DT));\n";
        foreach ($cfg['define'] + ['WWW' => self::www()] as $key => $val)
            $more .= "define('$key', " . var_export($val, true) . ");\n";
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
        SKY::$plans = Plan::cache_r('sky_plan.php');
    }

    static function lint(string $in, $is_file = true) : bool {
        try {
            self::yml($in, $is_file);
        } catch (Error $e) {
            return false;
        }
        return true;
    }

    static function yml(string $in, $is_file = true) {
        self::$dir = $is_file ? realpath(dirname($in)) : '???';
        if (defined('DEV'))
            self::$dev = DEV;
        $yml = new Boot;
        $yml->at = [$is_file ? $in : false, 0];
        $yml->yml_text($is_file ? file_get_contents($in) : $in);
        return $yml->array;
    }

    private function yml_text(string $in) {
        $p = ['' => &$this->array];
        $n = $this->obj();
        $add = function ($m) use (&$p) {
            if (is_string($m->key) && 'DEV.' == substr($m->key, 0, 4)) {
                if (!self::$dev)
                    return;
                $m->key = substr($m->key, 4);
            }
            $v = $this->yml_val($m);
            if (array_key_exists($m->pad, $p)) {
                array_splice($p, 1 + array_flip(array_keys($p))[$m->pad]);
                $z =& $p[$m->pad];
            } else {
                $lt = array_key_last($p);
                $z =& $p[$lt][array_key_last($p[$lt])];
            }
            true === $m->key ? ($z[] = $v) : ($z[$m->key] = $v);
            $p[$m->pad] =& $z;
        };

        foreach (explode("\n", unl($in)) as $key => $in) {
            $this->at[1] = 1 + $key;
            $m = clone $n;
            if ($this->yml_line($in . ' ', $n))
                continue;

            is_null($m->key) or $add($m);
            if ($n->voc) {
                $n->val = null;
                $add($n); # vocabulary: - key: val
                $n = $n->voc;
            }
        }
        is_null($n->key) or $add($n);
    }

    private function yml_val($m) {
        $v = $m->json ? json_decode($m->json, true) : self::scalar($m->mod ? $m->val : trim($m->val));
        if ($m->json && json_last_error())
            $this->halt('JSON failed');
        if (self::$boot && 'DEV' == $m->key) {
            self::$boot = false;
            self::$dev = $v;
        }
        return $v;
    }

    private function yml_line(string $in, &$n) {
        static $pad_0 = '', $pad_1 = 0;

        $pad = '';
        $szv = strlen($n->voc ? ($p =& $n->voc->val) : ($p =& $n->val));
        $cont = '' !== $p;
        $k2 = $reqk = $ne = false;
        $w2 = $setk = true; # set key first

        for ($j = 0, $szl = strlen($in); $j < $szl; $j += $x) {
            if ($w = ' ' == $in[$j] || "\t" == $in[$j]) {
                # whitespaces
                $t = substr($in, $j, $x = strspn($in, "\t ", $j));
            } elseif ($pad && !$reqk && ('|' == $n->mod || '>' == $n->mod)) {
                $t = substr($in, $j); # set rest of line
                $x = $szl;
            } elseif ('"' == $in[$j] || "'" == $in[$j]) {
                $x = self::str($in, $j) or $this->halt('Incorrect string');
                $t = substr($in, $j, $x -= $j);
            } elseif ('#' == $in[$j] && $w2 && ('|' !== $n->mod || strlen($pad) < $pad_1)) {
                # cut comment
                break;
            } elseif (strpbrk($in[$j], '#:-{},[]')) {
                $x = 1;
                $t = $in[$j];
            } else {
                # get token anyway
                $t = substr($in, $j, $x = strcspn($in, "\t\"' #:-{},[]", $j));
            }
            $w2 = $w;

            if (!$j) { # first step
                $w ? ($pad = $this->halt(false, $t)) : ($ne = $p .= $t);
                $reqk = $pad <= $pad_0; # require match key
                if (!$reqk && '|' == $n->mod)
                    '' === $p ? ($pad_1 = strlen($pad)) : ($p .= "\n" . substr($pad, $pad_1));
            } elseif ($w && $setk && $k2 && ($reqk || !$n->mod)) { # key found
                if (!$reqk && $cont)
                    $this->halt('Mapping disabled');
                $setk = false;
                $sps = $t;
                $n = $this->obj([
                    'pad' => $pad_0 = $pad,
                    'key' => $c2 ? self::scalar(substr($p, ($char ?? 0) + $szv, -1)) : true,
                ]);
                if (null === $n->key)
                    $this->halt('Key cannot be NULL');
                $p =& $n->val;
            } elseif ($w && true === $n->key && $c2 && !$n->voc && !$n->json) { # vocabulary key
                $n->voc = $this->obj([
                    'mod' => &$n->mod,
                    'pad' => $pad_0 = $this->halt(false, $sps) . ' ' . $n->pad,
                    'key' => self::scalar(substr($p, 0, -1)),
                ]);
                if (null === $n->voc->key)
                    $this->halt('Key cannot be NULL');
                $p =& $n->voc->val;
            } elseif ($n->json && 1 == strlen($t) && !$reqk && strpbrk($t, ':{},[]')) {
                $n->json .= '' === ($p = trim($p)) ? $t : self::scalar($p, true, ':' != $t) . $t;
                $p = '';
            } elseif ('' === $p && ('{' == $t || '[' == $t) && !$n->mod) {
                $n->mod = $n->json = $t;
                $reqk = false;
            } else {
                if ($rule = !$reqk && '' !== $p && !$ne && '|' != $n->mod)
                    $char = 1;
                $p .= $rule ? " $t" : $t;
                $ne = true;
            }
            $k2 = ($c2 = ':' == $t) || '-' == $t;
        }

        if ($setk) {
            if ($reqk && $ne)
                $this->halt('Cannot match key');
            if ($p && ' ' == $p[-1])
                $p = substr($p, 0, -1);
        } else {
            $p = rtrim($p);
            if ('|' == $p || '>' == $p) {
                $n->mod = $p;
                $p = '';
            }
        }

        return $setk;
    }

    private function halt(string $error, $space = false) {
        if ($space && !strpbrk($space, "\t"))
            return $space;

        $at = (false === $this->at[0] ? 'Line ' : $this->at[0] . ', Line ') . $this->at[1];
        throw new Error("Yaml, $at: " . ($error ?: 'Tabs disabled for indent'));
    }

    private function obj(array $in = []) : stdClass {
        $in += [
            'mod' => '',
            'pad' => '',
            'key' => null,
            'val' => '',
            'voc' => false,
            'json' => false,
        ];
        return (object)$in;
    }

    static function str(string &$in, $p) {
        $quot = $in[$p++] . '\\';
        for ($len = strlen($in); true; $p += $bs % 2) {
            $p += strcspn($in, $quot, $p);
            if ($p >= $len)
                return false;
            if ('\\' != $in[$p])
                return ++$p;
            $p += ($bs = strspn($in, '\\', $p));
        }
    }

    static function scalar(string $in, $json = false, $notkey = true) {
        if ($in && '$' == $in[0] && preg_match("/^\\$([A-Z\d_]+)(\(.+)$/", $in, $m)) {
            [, $key, $in] = $m;
            $in = self::env($key, substr($in, 1, -1));
        }
        if ('' === $in || 'null' === $in || '~' === $in)
            return $json ? 'null' : null;
        $true = 'true' === $in;
        if ($true || 'false' === $in)
            return $json ? $in : $true;
        if ('"' == $in[0] && '"' == $in[-1])
            return $json ? $in : substr($in, 1, -1);
        if ("'" == $in[0] && "'" == $in[-1])
            return $json ? '"' . substr($in, 1, -1) . '"' : substr($in, 1, -1);
        if ($notkey && is_numeric($in))
            return $json ? $in : (is_num($in) ? (int)$in : (float)$in);
        if ('__DIR__' == substr($in, 0, 7))
            $in = self::$dir . substr($in, 7);
        if (!$json)
            return $in;
        return '"' . str_replace('\\', '\\\\', $in) . '"';
    }

    static function cfg(&$name, $ware = 'main') {
        if (null === $name) {
            $name = is_file($ware) ? self::yml($ware) : [];
            return $name['core'] ?? [];
        } elseif (is_array($name)) {
            foreach ($name as $key => $val) {
                if ('core' != $key && is_array($val))
                    Plan::cache_s(['main', "cfg_{$ware}_$key.php"], self::auto($val));
            }
        } else {
            $yml = self::yml(Plan::_t([$ware, 'config.yaml']))[$name];
            if (is_string($yml)) {
                $ext = explode('.', $yml);
                switch (end($ext)) {
                    case 'php': return Plan::_r([$ware, $yml]);
                    case 'yml':
                    case 'yaml': return self::yml(Plan::_t([$ware, $yml]));
                    case 'json': return json_decode(Plan::_g([$ware, $yml]), true);
                    default: return strbang(unl(Plan::_g([$ware, $yml])));
                }
            }
            return $yml;
        }
    }

    static function wares($fn, &$ctrl, &$class) {
        $ymls = [];
        foreach (require $fn as $ware => $ary) {
            unset($yml);
            $cfg = self::cfg($yml, ($path = $ary['path']) . "/config.yaml");
            $plan = $cfg['plans'];
            if ($ary['type'] ?? false)
                $plan['app']['type'] = 'pr-dev';
            if ($ary['options'] ?? false)
                $plan['app']['options'] = $ary['options'];
            if (!self::$dev && in_array($plan['app']['type'], ['dev', 'pr-dev']))
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
            if ($ary['tune'])
                $ctrl["$ary[tune]/*"] = $ware;
            $app =& $plan['app'];
            unset($cfg['plans'], $app['require'], $app['class']);
            $app['cfg'] = $cfg;
            if ($yml)
                $ymls[$ware] = $yml;
            SKY::$plans[$ware] = ['app' => ['path' => realpath($path)] + $plan['app']] + $plan;
        }
        return $ymls;
    }

    static function rewrite(&$in) {
        $code = "\n";
        foreach (Plan::_rq('rewrite.php') as $rw)
            !self::$dev && $rw[2] or $code .= $rw[1] . "\n";
        $in = explode("'',", $in, 2);
        $in = "$in[0]function(\$cnt, &\$surl, \$uri, \$sky) {{$code}},$in[1]";
    }

    static function www() {
        foreach (['public', 'public_html', 'www', 'web'] as $dir) {
            if (is_file($fn = "$dir/index.php") && strpos(file_get_contents($fn), 'new HEAVEN'))
                return "$dir/";
        }
        foreach (glob('*') as $dir) {
            if ('_' != $dir[0] && is_file($fn = "$dir/index.php") && strpos(file_get_contents($fn), 'new HEAVEN'))
                return "$dir/";
        }
        return false;
    }

    static function env($key, $default = 0) {
        if (false === ($val = getenv($key))) {
            $val = $default;
            if (is_file('.env')) {
                $ary = strbang(unl(trim(file_get_contents('.env'))));
                $val = $ary[$key] ?? $default;
            }
        }
        return $val;
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
