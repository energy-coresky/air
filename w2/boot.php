<?php

class Boot
{
    use Processor;#2do ?

    const version = 0.988;

    private static $dev = false;
    private static $boot = 0;
    private static $dir;
    private static $const = [];

    private $at;
    private $array;
    private $stack = [];

    static $transform;

    static function auto($v, $more = '', $func = false) {
        $array = var_export($v, true);
        $func && call_user_func_array($func, [&$array]);
        return "<?php\n\n# this is auto generated file, do not edit\n$more\nreturn $array;\n";
    }

    static function directive($name, $func = null) {
        $ary = is_array($name) ? $name : [$name => $func];
        self::$transform = $ary + self::$transform;
    }

    function __construct($dc = false, $nx = null) {
        $this->array = [];

        self::$transform = [
            'inc' => fn($v) => self::inc($v),
            'bin' => fn($v) => intval($v, 2),
            'oct' => fn($v) => intval($v, 8),
            'hex' => fn($v) => intval($v, 16),
            'base64' => fn($v) => base64_decode($v),
            'split' => fn($v) => explode(' ', $v),
            'bang' => fn($v) => strbang(trim(unl($v))),
            'self' => function ($v) {
                $p =& $this->array;
                foreach (explode('.', $v) as $key)
                    $p =& $p[$key];
                return $p;
            },
        ];

        if (!$dc) {
            if (self::$boot)
                return;
            if (MVC::$mc) # not console!
                MVC::handle('yml_c');
            self::$transform = Plan::_rq('mvc/yaml.php') + self::$transform;
            return;
        }

        self::$boot = 1;
        $cfg = self::cfg($ymls, DIR_M . '/config.yaml');
        self::$const = $cfg['define'];
        $plans = SKY::$plans + ($cfg['plans'] ?? []) + [
            'view' => ['path' => DIR_M . '/mvc/view'],
            'cache' => ['path' => 'var/cache'],
            'gate' => ['path' => 'var/gate'],
            'jet' => ['path' => 'var/jet'],
            'mem' => ['path' => 'var/mem'],
        ];

        $more = "\ndate_default_timezone_set('$cfg[timezone]');\n";
        if (self::$dev) {
            fseek($fp = fopen(__FILE__, 'r'), __COMPILER_HALT_OFFSET__);
            $more = stream_get_contents($fp) . $more;
            fclose($fp);
        }
        $more .= "define('NOW', date(DATE_DT));\n";
        foreach ($cfg['define'] as $key => $val)
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
        self::$boot = 0;
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
        self::$dir = $is_file ? str_replace('\\', '/', dirname($in)) : '???';
        defined('DEV') && (self::$dev = DEV);
        $yml = new Boot;
        $yml->at = [$is_file ? $in : false, 0];
        $yml->yml_text($is_file ? file_get_contents($in) : $in);
        return $yml->array;
    }

    private function yml_text(string $in) {
        $p = ['' => &$this->array];
        $n = $this->obj();
        $add = function ($m) use (&$p) {
            if (is_string($m->key) && 'DEV+' == substr($m->key, 0, 4)) {
                if (!self::$dev)
                    return;
                $m->key = substr($m->key, 4);
            }
            $v = $this->yml_val($m, $ptr);
            if (array_key_exists($m->pad, $p)) {
                array_splice($p, 1 + array_flip(array_keys($p))[$m->pad]);
                $z =& $p[$m->pad];
            } else {
                $lt = array_key_last($p);
                $z =& $p[$lt][array_key_last($p[$lt])];
            }
            true === $m->key ? ($z[] = $v) : ($z[$m->key] = $v);
            $p[$m->pad] =& $z;
            if ($ptr)
                self::$const =& $z[$m->key];
        };

        foreach (explode("\n", unl($in)) as $key => $in) {
            $this->at[1] = 1 + $key;
            $m = clone $n;
            if ($this->yml_line($in . ' ', $n, $m->pfx))
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

    private function yml_val($m, &$ptr) {
        $ptr = false;
        $this->stack && $this->code_fix($m->pad);
        if ($m->json) {
            $v = json_decode($m->json, true);
            if (json_last_error())
                $this->halt('JSON failed');
        } else {
            $v = $this->scalar($m->mod ? $m->val : trim($m->val), true, false, $m);
            if (1 == self::$boot) {
                if ('define' == $m->key) {
                    $v = ['WWW' => self::www()];
                    $ptr = true;
                } elseif ('DEV' == $m->key) {
                    self::$boot = 2;
                    self::$dev = $v;
                }
            }
        }
        return $v;
    }

    private function yml_line(string $in, &$n, &$pfx) {
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
                $x = self::str($in, $j, $szl) or $this->halt('Incorrect string');
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
                if ($pad > $n->pad) {
                    if ($pfx)
                        $this->stack[] = [$pfx, $n->pad, $n->pad];
                    $pfx = 0;
                }
                $setk = false;
                $sps = $t;
                $n = $this->obj([
                    'pad' => $pad_0 = $pad,
                    'key' => $c2 ? $this->scalar(substr($p, ($char ?? 0) + $szv, -1)) : true,
                ]);
                if (null === $n->key)
                    $this->halt('Key cannot be NULL');
                $p =& $n->val;
            } elseif ($w && true === $n->key && $c2 && !$n->voc && !$n->json) { # vocabulary key
                $n->voc = $this->obj([
                    'mod' => &$n->mod,
                    'pad' => $pad_0 = $this->halt(false, $sps) . ' ' . $n->pad,
                    'key' => $this->scalar(substr($p, 0, -1)),
                ]);
                if (null === $n->voc->key)
                    $this->halt('Key cannot be NULL');
                $p =& $n->voc->val;
            } elseif ($n->json && 1 == strlen($t) && !$reqk && strpbrk($t, ':{},[]')) {
                $n->json .= '' === ($p = trim($p)) ? $t : $this->scalar($p, ':' != $t, true, $pad_0) . $t;
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
            if (preg_match("/^@(\(|\w+)\s*(.*)$/", $p = rtrim($p), $x))
                $this->code_set($x, $n);
            if ('|' == $p || '>' == $p) {
                $n->mod = $p;
                $p = '';
            }
        }

        return $setk;
    }

    private function code_set($x, &$n) {
        [,$name, $_v] = $x;
        $n->voc ? ($v =& $n->voc->val) : ($v =& $n->val);
        if ('(' == $name[0]) { # inline code
            $br = self::bracket(substr($v, 1));
            $_v = trim(substr($v, 2 + strlen($br)));
            $code = "return $br;";
        } elseif (!$code = self::$transform[$name] ?? false) {
            $this->halt("Transformation `@$name` not found");
        }
        $n->voc ? ($n->voc->pfx = $code) : ($n->pfx = $code);
        $v = $_v;
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
            'pfx' => false,
        ];
        return (object)$in;
    }

    private function var(string &$in) {
        if (!preg_match("/^\\$([A-Z\d_]+)(.*)$/", $in, $m))
            return;
        [, $key, $rest] = $m;
        if ($rest && ($bkt = self::bracket($rest))) {
            $rest = substr($rest, strlen($bkt));
            $in = self::env($key, substr($bkt, 1, -1));
        } elseif ('SELF' == $key) {
            $in = self::$dir;
        } elseif (defined($key)) {
            $in = constant($key);
            'WWW' != $key or $in = substr($in, 0, -1);
        } elseif (isset(self::$const[$key])) {
            $in = self::$const[$key];
            'WWW' != $key or $in = substr($in, 0, -1);
        } else {
            return;
        }
        '' === $rest or $in = (string)$in . $rest;
    }

    static function num(string $in) {// 2delete
        $ary = token_get_all("<?php " . trim($in));
        array_shift($ary);
        $cnt = count($ary);
        if (!$cnt || $cnt > 2)
            return false;
        $sign = '+';
        if ($cnt == 2 && !in_array($sign = array_shift($ary), ['-', '+'], true))
            return false;
        [$token, $num] = $ary[0] + [1 => 1];
        if (T_DNUMBER === $token)
            return floatval($sign . $num);
        if (T_LNUMBER !== $token)
            return false;
        $base = ['x' => 16, 'b' => 2];
        return intval($sign . $num, $base[$num[1] ?? 0] ?? (0 == $num[0] ? 8 : 10));
    }

    private function code_fix($pad) {
        if (!$p =& $this->stack)
            return;
        $last =& $p[array_key_last($p)];
        $last[1] != $last[2] or $last[2] = $pad;
        foreach ($p as $i => $ary) {
            if ($pad < $ary[2])
                return array_splice($p, $i);
        }
    }

    private function code_run(&$v, $pad, $code) {
        $ary = array_reverse($this->stack);
        if ($code)
            array_unshift($ary, [$code, 1]);
        $a = $this->array;
        foreach ($ary as $p) {
            if ($pad > $p[1] || 1 === $p[1])
                $v = is_string($p[0]) ? eval($p[0]) : ($p[0])($v, $a);
        }
        return !is_string($v);
    }

    private function scalar(string $v, $is_val = false, $json = false, $pad = '') {
        $code = false;
        if ($pad instanceof stdClass) {
            $code = $pad->pfx;
            $pad = $pad->pad;
        }
        $v && '$' == $v[0] && $this->var($v);
        if ($is_val && 0 !== $code && $this->code_run($v, $pad, $code))
            return $v;
        if ('' === $v || 'null' === $v || '~' === $v)
            return $json ? 'null' : null;
        $true = 'true' === $v;
        if ($true || 'false' === $v)
            return $json ? $v : $true;
        if ('"' == $v[0] && '"' == $v[-1])
            return $json ? $v : substr($v, 1, -1);
        if ("'" == $v[0] && "'" == $v[-1])
            return $json ? '"' . substr($v, 1, -1) . '"' : substr($v, 1, -1);
        if ($is_val && is_numeric($v))
            return $json ? $v : (is_num($v) ? (int)$v : (float)$v);
        if (!$json)
            return $v;
        return '"' . str_replace('\\', '\\\\', $v) . '"';
    }

    static function inc($name, $ware = false) {
        if (!$ware) {
            strpos($name, '::') or $name = Plan::$ware . '::' . $name;
            [$ware, $name] = explode('::', $name, 2);
        }
        $ext = explode('.', $name);
        switch (end($ext)) {
            case 'php': return Plan::_r([$ware, $name]);
            case 'json': return json_decode(Plan::_g([$ware, $name]), true);
            case 'yml':
            case 'yaml': return self::yml(Plan::_t([$ware, $name]));
            default: return strbang(unl(Plan::_g([$ware, $name])));
        }
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
            return is_string($yml) ? self::inc($yml, $ware) : $yml;
        }
    }

    static function wares($fn, &$ctrl, &$class) {
        $ymls = [];
        foreach (require $fn as $ware => $ary) {
            unset($yml);
            $path = str_replace('\\', '/', $ary['path']);
            if (!$cfg = self::cfg($yml, "$path/config.yaml"))
                continue; ////?
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
            $app =& $plan['app'];
            unset($cfg['plans'], $app['require'], $app['class']);
            $app['cfg'] = $cfg;
            if ($yml)
                $ymls[$ware] = $yml;
            SKY::$plans[$ware] = ['app' => ['path' => $path] + $plan['app']] + $plan;
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
        for ($i = 4, $a = ['public', 'public_html', 'www', 'web']; $a; --$i or $a = glob('*')) {
            $dir = array_shift($a);
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

    static function bracket(string $in, $b = '(') {
        if ('' === $in || $b != $in[0])
            return '';
        $close = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];
        $quot = $b . $close[$b] . '\'"';
        for ($p = $z = 1, $len = strlen($in); true; ) {
            $p += strcspn($in, $quot, $p);
            if ($p >= $len) {
                return '';
            } elseif ("'" == $in[$p] || '"' == $in[$p]) {
                if (!$p = self::str($in, $p, $len))
                    return '';
            } else {
                $b == $in[$p++] ? $z++ : $z--;
                if (!$z)
                    return substr($in, 0, $p);
            }
        }
    }

    static function str(string &$in, $p, $len) {
        for ($quot = $in[$p++] . '\\'; true; $p += $bs % 2) {
            $p += strcspn($in, $quot, $p);
            if ($p >= $len)
                return false;
            if ('\\' != $in[$p])
                return ++$p;
            $p += ($bs = strspn($in, '\\', $p));
        }
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
