<?php

class Boot
{
    use Processor;

    const version = 0.888;

    private static $dev = false;
    private $at;
    private $array = [];

    static function auto($v, $more = '', $func = false) {
        $array = var_export($v, true);
        if ($func)
            call_user_func_array($func, [&$array]);
        return "<?php\n\n# this is auto generated file, do not edit\n$more\nreturn $array;\n";
    }

    function __construct($dc = false) {
        if (!$dc)
            return;
        $cfg = Boot::cfg($ymls, DIR_M . '/config.yaml') + ['www' => Boot::www()];
        $plans = SKY::$plans + ($cfg['plans'] ?? []) + [
            'view' => ['path' => DIR_M . '/mvc/view'],
            'cache' => ['path' => 'var/cache'],
            'gate' => ['path' => 'var/gate'],
            'jet' => ['path' => 'var/jet'],
            'mem' => ['path' => 'var/mem'],
        ];
        Boot::$dev = eval('return ' . $cfg['define']['DEV'] . ';');
        $more = "\ndate_default_timezone_set('$cfg[timezone]');\n";
        foreach ($cfg['define'] as $key => $val)
            $more .= "define('$key', $val);\n";
        foreach ($cfg['ini_set'] as $key => $val)
            $more .= "ini_set('$key', $val);\n";
        unset($cfg['plans'], $cfg['define'], $cfg['ini_set'], $cfg['timezone']);
        SKY::$plans = $ctrl = [];
        $app = ['path' => DIR_M, 'cfg' => $cfg];
        SKY::$plans['main'] = ['rewrite' => '', 'class' => [], 'app' => $app] + $plans;
        $ymls = ['main' => $ymls];
        if (is_file($fn = DIR_M . '/wares.php'))
            $ymls += Boot::wares($fn, $ctrl, SKY::$plans['main']['class']);
        $plans = SKY::$plans;
        $plans['main'] += ['ctrl' => $ctrl + Debug::controllers('main')]; /////////////////////////
        SKY::$plans['main']['cache']['dc'] = $dc;
        Plan::cache_s('sky_plan.php', Boot::auto($plans, $more, ['Boot', 'rewrite']));
        SKY::$plans = Plan::cache_r('sky_plan.php');
        foreach ($ymls as $ware => $yml)
            Boot::cfg($yml, $ware);
    }

    static function lint(string $in, $nofile = true) : bool {
        try {
            Boot::yml($in, $nofile);
        } catch (Error $e) {
            return false;
        }
        return true;
    }

    static function yml(string $in, $nofile = true) : array {
        $yml = new Boot;
        $yml->at = [$nofile ? false : $in, 0];
        $yml->yml_text($nofile ? $in : file_get_contents($in));
        return $yml->array;
    }

    private function yml_text(string $in) {
        $p = ['' => &$this->array];
        $n = $this->obj();

        $add = function ($m) use (&$p) {
            $v = $m->json ? json_decode($m->json, true) : Boot::scalar($m->mod ? $m->val : trim($m->val));
            if ($m->json && json_last_error())
                $this->halt('JSON failed');

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

            '' === $m->key or $add($m);
            if ($n->voc) {
                $n->val = null;
                $add($n); # vocabulary: - key: val
                $n = $n->voc;
            }
        }
        '' === $n->key or $add($n);
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
                $x = Boot::str($in, $j) or $this->halt('Incorrect string');
                $t = substr($in, $j, $x -= $j);
            } elseif ('#' == $in[$j] && $w2 && ('|' !== $n->mod || strlen($pad) < $pad_1)) {
                # cut comment
                break;
            } elseif (strpbrk($in[$j], '#:-{},[]')) {
                $t = $in[$j];
                $x = 1;
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
                    'key' => $c2 ? Boot::scalar(substr($p, ($char ?? 0) + $szv, -1)) : true,
                ]);
                $p =& $n->val;
            } elseif ($w && true === $n->key && $c2 && !$n->voc) { # vocabulary key
                $n->voc = $this->obj([
                    'mod' => &$n->mod,
                    'pad' => $pad_0 = $this->halt(false, $sps) . ' ' . $n->pad,
                    'key' => substr($p, 0, -1),
                ]);
                $p =& $n->voc->val;
            } elseif ($n->json && 1 == strlen($t) && !$reqk && strpbrk($t, ':{},[]')) {
                $n->json .= '' === ($p = trim($p)) ? $t : Boot::scalar($p, true, ':' != $t) . $t;
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

        $pos = ('' === $this->at[0] ? 'Line ' : $this->at[0] . ', Line ') . $this->at[1];
        throw new Error("Yaml, $pos: " . ($error ?: 'Tabs disabled for indent'));
    }

    private function obj(array $in = []) : stdClass {
        $in += [
            'mod' => '',
            'pad' => '',
            'key' => '',
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
        return $json ? '"' . $in . '"' : $in;
    }

    static function cfg(&$name, $ware = 'main') {
        if (null === $name) {
            $name = is_file($ware) ? Boot::yml($ware, false) : [];
            return $name['plan'] ?? [];
        } elseif (is_array($name)) {
            foreach ($name as $key => $val) {
                if ('plan' != $key && is_array($val))
                    Plan::cache_s(['main', "cfg_{$ware}_$key.php"], Boot::auto($val));
            }
        } else {
            $yml = Boot::yml(Plan::_t([$ware, 'config.yaml']), false)[$name];
            if (is_string($yml)) {
                $ext = explode('.', $yml);
                switch (end($ext)) {
                    case 'php': return Plan::_r([$ware, $yml]);
                    case 'yml':
                    case 'yaml': return Boot::yml(Plan::_t([$ware, $yml]), false);
                    case 'json': return json_decode(Plan::_g([$ware, $yml]), true);
                    default: return strbang(unl(Plan::_g([$ware, $yml])));
                }
            }
            return $yml;
        }
    }

    static function wares($fn, &$ctrl, &$class) {
        $ymls = [];
        foreach (require $fn as $ware => $plan) {
            $conf = require ($path = $plan['path']) . "/conf.php";
            if ($plan['type'] ?? false)
                $conf['app']['type'] = 'pr-dev';
            if ($plan['options'] ?? false)
                $conf['app']['options'] = $plan['options'];
            if (!Boot::$dev && in_array($conf['app']['type'], ['dev', 'pr-dev']))
                continue;
            foreach ($plan['class'] as $cls) {
                $df = 'default_c' == $cls;
                if ($df || 'c_' == substr($cls, 0, 2)) {
                    $x = $df ? '*' : substr($cls, 2);
                    $ctrl[$plan['tune'] ? "$plan[tune]/$x" : $x] = $ware;
                } else {
                    $class[$cls] = $ware;
                }
            }
            if ($plan['tune'])
                $ctrl["$plan[tune]/*"] = $ware;
            $app =& $conf['app'];
            unset($yml, $app['require'], $app['class'], $app['databases']);
            if ($cfg = Boot::cfg($yml, "$path/config.yaml"))
                $app['cfg'] = $cfg;
            if ($yml)
                $ymls[$ware] = $yml;
            SKY::$plans[$ware] = ['app' => ['path' => $path] + $conf['app']] + $conf;
        }
        return $ymls;
    }

    static function rewrite(&$in) {
        $code = "\n";
        foreach (Plan::_rq('rewrite.php') as $rw)
            !Boot::$dev && $rw[2] or $code .= $rw[1] . "\n";
        $in = explode("'',", $in, 2);
        $in = "$in[0]function(\$cnt, &\$surl, \$uri, \$sky) {{$code}},$in[1]";
        return '';
    }

    static function www() {
        foreach (['public', 'public_html', 'www', 'web'] as $dir) {
            if (is_file($fn = "$dir/index.php") && strpos(file_get_contents($fn), 'new HEAVEN'))
                return $dir;
        }
        foreach (glob('*') as $dir) {
            if ('_' != $dir[0] && is_file($fn = "$dir/index.php") && strpos(file_get_contents($fn), 'new HEAVEN'))
                return $dir;
        }
        return false;
    }
}
