<?php

class Boot
{
    use Processor;#2do ?

    const version = 0.901;

    private static $boot = 0;
    private static $dir;
    private static $const = [];

    private $at;
    private $array;
    private $stack = [];
    private $mode = [
        'em' => '',
        'val' => '{[',
        'json' => '{[,]}',
    ];

    static $transform;
    static $eval;
    static $dev = false;

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
        self::$eval = fn($v) => $v;

        self::$transform = [
            'inc' => fn($v) => self::inc($v),
            'eval' => self::$eval,
            'bin' => fn($v) => intval($v, 2),
            'oct' => fn($v) => intval($v, 8),
            'hex' => fn($v) => intval($v, 16),
            'base64' => fn($v) => base64_decode($v),
            'split' => fn($v) => preg_split("/\s+/", $v),
            'bang' => fn($v) => strbang(trim(unl($v))),
            'self' => function ($path, &$a, $unset = false) {
                $p =& $this->array;
                foreach (explode('.', $path) as $key) {
                    $prev =& $p;
                    $p =& $p[$key];
                }
                $return = $p;
                if ($unset)
                    unset($prev[$key]);
                return $return;
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
            $ymls += Rare::wares($fn, $ctrl, SKY::$plans['main']['class']);
        $plans = SKY::$plans;
        $plans['main'] += ['ctrl' => $ctrl + Rare::controllers('main')];
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

    static function yml(string $in, $is_file = true, $try = false) {
        self::$dir = $is_file ? str_replace('\\', '/', dirname($in)) : '???';
        defined('DEV') && (self::$dev = DEV);
        $yml = new Boot;
        $yml->at = [$is_file ? $in : false, 0];
        $yml->yml_text($is_file ? file_get_contents($in) : $in);
        return $yml->array;
    }

    private function yml_text(string $in) {
        $p = ['' => &$this->array];
        $add = function ($m) use (&$p) {
            if (is_string($m->key) && 'DEV+' == substr($m->key, 0, 4)) {
                if (!self::$dev)
                    return;
                $m->key = substr($m->key, 4);
            }
            $v = $this->yml_val($m, $define);
            if (array_key_exists($m->pad, $p)) {
                array_splice($p, 1 + array_flip(array_keys($p))[$m->pad]);
                $z =& $p[$m->pad];
            } else {
                $lt = array_key_last($p);
                $z =& $p[$lt][array_key_last($p[$lt])];
            }
            true === $m->key ? ($z[] = $v) : ($z[$m->key] = $v);
            $p[$m->pad] =& $z;
            if ($define)
                self::$const =& $z[$m->key];
        };

        $n = $this->obj();
        foreach (explode("\n", unl($in)) as $key => $in) {
            $this->at[1] = 1 + $key;
            $m = clone $n;
            if ($this->yml_line($in . ' ', $n, $m->code))
                continue;
            is_null($m->key) or $add($m);
            $n->voc && $add($n->voc); # vocabulary: - key: val
        }
        is_null($n->key) or $add($n);
    }

    private function yml_val($m, &$define) {
        $define = false;
        foreach ($this->stack as $i => &$p) {
            if ($p[2] == $p[1])
                $p[2] =& $m->pad;
            if ($m->pad >= $p[2])
                continue;
            array_splice($this->stack, $i); # drop cascade code
            break;
        }
        if ($m->json) {
            $v = json_decode($m->json, true);
            if (json_last_error())
                $this->halt('JSON failed');
        } else {
            $v = $this->scalar($m->mod ? $m->val : trim($m->val), true, $m);
            if (1 == self::$boot) {
                if ('define' == $m->key) {
                    $v = ['WWW' => Rare::www()];
                    $define = true;
                } elseif ('DEV' == $m->key) {
                    self::$boot = 2;
                    self::$dev = $v;
                }
            }
        }
        return $v;
    }

    private function tokens(string &$in, $json) {
        $len = strlen($in);
        return new eVar(function ($prev) use (&$in, $json, $len) {
            static $j = 0;
            $j += strlen($pt = $prev->token ?? '');
            if ($j >= $len)
                return false;
            $x = $in[$j];
            $mode = $wt = $prev->mode ?? ($json ? 'json' : 'em');
            if ($whitespace = ' ' == $x || "\t" == $x) {
                $t = substr($in, $j, strspn($in, "\t ", $j));
                $pt && ($wt = $pt);
            } elseif ('rest' == $mode) { # return rest of line
                $t = substr($in, $j);
            } elseif ('#' == $x && ($prev->ws ?? true)) {
                return false; # cut comment
            } elseif ('"' == $x || "'" == $x) {
                $sz = self::str($in, $j, $len) or $this->halt('Incorrect string');
                $t = substr($in, $j, $sz -= $j);
            } else {
                $cl = $this->mode[$mode];
                $t = strpbrk($x, ":-$cl") ? $x : substr($in, $j, strcspn($in, "\t\"' :-$cl", $j));
            }
            return [
                'token' => $t,
                'ws' => $whitespace,
                'keyc' => $colon = ':' == $wt,
                'key2' => $colon || '-' == $wt,
                'code' => 'val' == $mode && '@' == $wt[0],
                'mode' => $mode,
            ];
        });
    }

    private function yml_line(string $in, &$n, &$code) {
        static $pad_0 = '', $pad_1 = 0;

        $pad = '';
        $len = strlen($p =& $n->val);
        $setk = true; # set key first
        $reqk = false;

        foreach ($this->tokens($in, $n->json) as $_ => $el) {
            $t = $el->token;
            if (0 == $_) { # first step
                ($has_t = !$el->ws) ? ($p .= $t) : ($pad = $this->halt(false, $t));
                $reqk = $pad <= $pad_0; # require match key
                $mult = ('|' == $n->mod || '>' == $n->mod) && (!$reqk || '' === trim($in));
                if ($mult) {
                    $el->mode = 'rest'; # multiline mode
                    if (!$len) {
                        $pad_1 = strlen($pad);
                    } elseif ('|' == $n->mod) {
                        $p .= "\n" . substr($pad, $pad_1);
                    }
                }
            } elseif ($el->key2 && $setk && ($reqk || !$n->mod)) { # key found
                if ($pad > $n->pad) { # aggregate node
                    $len && $this->halt('Mapping disabled');
                    if ($code)
                        $this->stack[] = [$code, $n->pad, $n->pad];
                    $code = 0;
                }
                $key = $el->keyc ? $this->scalar(substr($p, $len, -1)) : true;
                null !== $key or $this->halt('Key cannot be NULL');
                $n = $this->obj(['key' => $key, 'pad' => $pad_0 = $pad]);
                $p =& $n->val;
                $setk = false;
                $spaces = $t;
                $el->mode = 'val';
            } elseif ($el->keyc && true === $n->key && !$n->json) { # vocabulary key
                $n->voc = $this->obj(['key' => true, 'pad' => $n->pad, 'code' => 0]);
                $pad_0 = $n->pad .= ' ' . $this->halt(false, $spaces);
                $n->key = $this->scalar(substr($p, 0, -1));
                null !== $n->key or $this->halt('Key cannot be NULL');
                $el->mode = 'val';
                $p = '';
            } elseif ($el->code) {
                if (!$n->code = self::$transform[substr($p, 1)] ?? false)
                    $this->halt("Transformation `$p` not found");
                $p = '';
            } elseif ($n->json && 1 == strlen($t) && !$reqk && strpbrk($t, ':{},[]')) {
                $n->json .= '' === ($p = trim($p)) ? $t : $this->scalar($p, ':' != $t, $n, true) . $t;
                $p = '';
            } elseif ('' === $p && ('{' == $t || '[' == $t) && !$n->mod) {
                $n->mod = $n->json = $t;
                $reqk = false;
                $el->mode = 'json';
            } else {
                if ($xmul = $len && !$has_t && '|' != $n->mod)
                    $len++;
                if ('' !== $p && 'val' == $el->mode)
                    $el->mode = 'em';
                $p .= $xmul ? " $t" : $t;
                $has_t = true;
            }
        }

        if ($setk) {
            if ($reqk && $has_t)
                $this->halt('Cannot match key');
            if ($p && ' ' == $p[-1])
                $p = substr($p, 0, -1);
        } elseif ('|' == ($p = rtrim($p)) || '>' == $p) {
            $n->mod = $p;
            $p = '';
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
            'code' => false,
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

    private function transform(&$v, $n) {
        $ary = array_reverse($this->stack);
        if ($n->code)
            array_unshift($ary, [$n->code, 1]); # ' ' > 1 is false
        foreach ($ary as $one) {
            [$code, $pad] = $one;
            if ($n->pad > $pad || 1 === $pad)
                $v = run_transform($code, $v, $this->array);
        }
        return !is_string($v);
    }

    private function scalar(string $v, $is_val = false, $n = 0, $json = false) {
        if ($v && '$' == $v[0])
            $this->var($v);
        if ($is_val && 0 !== $n->code && $this->transform($v, $n))
            return $v;
        if ('' === $v || 'null' === $v)
            return $json ? 'null' : null;
        $true = 'true' === $v;
        if ($true || 'false' === $v)
            return $json ? $v : $true;
        if ('"' == $v[0] && '"' == $v[-1])
            return $json ? $v : substr($v, 1, -1);
        if ("'" == $v[0] && "'" == $v[-1])
            return $json ? '"' . substr($v, 1, -1) . '"' : substr($v, 1, -1);
        if ($is_val && is_numeric($x = $v)) {
            if ($json)
                return $v;
            if ('-' === $v[0] || '+' === $v[0])
                $x = substr($v, 1);
            return ctype_digit($x) ? intval($v) : floatval($v);
        }
        return $json ? '"' . str_replace('\\', '\\\\', $v) . '"' : $v;
    }

    static function inc($name, $ware = false) {
        if (!$ware) {
            strpos($name, '::') or $name = Plan::$ware . '::' . $name;
            [$ware, $name] = explode('::', $name, 2);
        }
        $addr = [$ware, $name];
        $ext = explode('.', $name);
        switch (end($ext)) {
            case 'php': return Plan::app_r($addr);
            case 'json': return json_decode(Plan::app_g($addr), true);
            case 'yml':
            case 'yaml': return self::yml(Plan::app_t($addr));
            default: return strbang(unl(Plan::app_g($addr)));
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

    static function rewrite(&$in) {
        $code = "\n";
        foreach (Plan::_rq('rewrite.php') as $rw)
            !self::$dev && $rw[2] or $code .= $rw[1] . "\n";
        $in = explode("'',", $in, 2);
        $in = "$in[0]function(\$cnt, &\$surl, \$uri, \$sky) {{$code}},$in[1]";
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

function yaml($path, $unset = false) {
    return (Boot::$transform['self'])($path, $path, $unset);
}

function run_transform($__code__, $v, &$a) {
    global $sky;
    $__ret__ = $__code__($v, $a);
    if ($__code__ !== Boot::$eval)
        return $__ret__;
    $tok = token_get_all("<? $__ret__");
    if (!array_filter($tok, fn($v) => is_array($v) && T_RETURN == $v[0]))
        $__ret__ = 'return ' . $__ret__;
    return eval("$__ret__;");
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
