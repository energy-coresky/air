<?php

class Yaml
{
    const version = 0.945;

    static $boot = 0;
    static $custom = [];
    static $dev = false;

    public $array;
    public $tail;

    private $at;
    private $dir;
    private $php = [];
    private $stack = [];
    private $_p;
    private $marker = false;

    static function directive($name, $func = null) {
        $ary = is_array($name) ? $name : [$name => $func];
        self::$custom = $ary + self::$custom;
    }

    static function run($in) {
        [$fn, $query, $vars, $mode] = $in + [1 => false, false, 0];
        if (!$query) {
            trace("ON-FLY IS $fn", 'YAML');
            return Yaml::text($fn);
        }
        if ($vars)
            $vars = (object)['data' => $vars];
        global $sky;
        $fn = 'yml_' . ($sky->eview ?: Plan::$ware) . "_$fn.php";
        $out = Plan::cache_rq($fn = ['main', $fn], $vars, false);
        DEV && trace("$fn[1] IS $query # " . ($out ? 'used cached' : 'recompiled'), 'YAML');
        if ($out)
            return $out;
        Plan::cache_s($fn, "<?php\n\n" . Yaml::text($query) . ';');
        return Plan::cache_r($fn, $vars);
    }

    static function text($in) {
        $yml = new Yaml($in, false);
        return $yml->out();
    }

    static function file($name, $marker = '') {
        $yml = new Yaml(file_get_contents($name), $name, $marker);
        return $yml->out();
    }

    static function lint(string $in, $fn = false, $marker = '') : bool {
        try {
            new Yaml($fn ? file_get_contents($fn) : $in, $fn, $marker);
        } catch (Error $e) {
            return false;
        }
        return true;
    }

    private function out() {
        if (!$this->php)
            return $this->array;
        $this->array = 'return ' . var_export($this->array, true);
        if (isset($this->php[0])) { # preflight
            [$param, $code] = $this->php[0];
            unset($this->php[0]);
            if (isset($param)) {
                $param and $param = " use ($param)";
                $code = "\$__return = call_user_func(function()$param {\n$code\n});";
            }
            $this->array = "$code\n\n" . $this->array;
        }
        return strtr($this->array, $this->php);
    }

    static function path($path, $unset = false) {
        return (Yaml::$custom['path'])(0, $path, $path, 0, $unset);
    }

    function __construct(string $in, $fn, $marker = '') {
        defined('DEV') && (self::$dev = DEV);
        $this->dir = $fn ? str_replace('\\', '/', dirname($fn)) : '???';
        $this->at = [$fn, 1];

        if ('' !== $marker) {
            if (3 != count($ary = preg_split("/^\#[\.\w+]*?\.{$marker}\b[\.\w+]*.*$/m", $in, 3))) {
                if (3 != count($ary = preg_split("/^\#[\.\w+]*?\._\b[\.\w+]*.*$/m", $in, 3)))
                    $this->halt("Cannot find marker `$marker`");
                trace("Used magic marker from $fn", 'YAML');
            }
            $in = preg_replace("/^\r?\n?(.*?)\r?\n?$/s", '$1', $ary[1]);
            unset($ary);
            $this->marker = $marker;
        }
        if (!self::$boot) {
            MVC::$mc && MVC::handle('yml_c'); # not console!
            self::$custom = Plan::_rq('mvc/yaml.php') + self::$custom;
        }
        $this->array = $this->stack = $this->php = [];
        $this->tail = explode("\n~\n", unl($in));
        $this->_p = ['' => &$this->array];
        $this->parse();
    }

    private function value($m, &$define) {
        $define = false;
        foreach ($this->stack as $i => &$p) {
            if ($p[2] == $p[1])
                $p[2] =& $m->pad;
            if ($m->pad >= $p[2])
                continue;
            array_splice($this->stack, $i); # drop cascade code
            break;
        }
        $v = [] === $m->val ? [] : $this->scalar($m->val, true, $m);
        if (1 == self::$boot) {
            if ('define' == $m->key) {
                $v = ['WWW' => Boot::www()];
                $define = true;
            } elseif ('DEV' == $m->key) {
                self::$boot = 2;
                self::$dev = $v;
            }
        }
        return $v;
    }

    private function push($m) {
        $p =& $this->_p;
        $str = !is_bool($m->key);
        if ($str && 'DEV+' === substr($m->key, 0, 4)) {
            if (!self::$dev)
                return;
            $m->key = substr($m->key, 4);
        }
        $v = $this->value($m, $define);
        if (array_key_exists($m->pad, $p)) {
            array_splice($p, 1 + array_flip(array_keys($p))[$m->pad]);
            $z =& $p[$m->pad];
        } elseif (null !== $p[$lt = array_key_last($p)]) {
            $z =& $p[$lt][array_key_last($p[$lt])];
        } else {
            $z =& $p[''];
        }
        if ($str) { # key:
            $z[$m->key] = $v;
        } elseif ($m->key) { # -
            $z[] = $v;
        } elseif (is_array($v)) { # other is +
            foreach ($v as $k => $v)
                is_int($k) ? ($z[] = $v) : ($z[$k] = $v);
        } elseif (count($p) > 1) {
            $z[$v] = null;
        } elseif (null !== ($p[''] = $v)) {
            return true;
        }
        $p[$m->pad] =& $z;
        $define && Boot::set_const($z[$m->key]);
        return false;
    }

    private function json(&$m, $el, $t) {
        $list = '[' == $t;
        $open = $list || '{' == $t;
        $key = function ($list) use ($el) {
            $el->mode = $list ? "v[" : "k{";
            return $list ? true : null;
        };
        $push = function ($m) {
            foreach ($m->code as $fun)
                $this->stack[] = [$fun, $m->pad, $m->pad];
            $m->val = [];
            $m->code = 0;
            $this->push($m);
        };

        if ($el->json) { # json continue
            $x = '[' == $m->opt[-1];
            if ($return = strpbrk($t, $x ? '[,]{' : '{,:}[')) {
                if ('' !== ($m->val = trim($_v = $m->val))) {
                    if (':' == $t) {
                        $m->key = $this->scalar($m->val); # prepare {key: }
                        $m->val = '';
                        $el->mode = 'v{';
                        return true;
                    }
                    if ($open)
                        $this->halt("JSON error near `$_v$t`");
                } elseif (':' == $t) {
                    $this->halt("JSON error near `:`");
                }
                if (null !== $m->key && (!empty($m->val) || $m->code || is_string($m->key)))
                    $this->push($m);

                if ($open) { # [ or {
                    $push($m);
                    $m = $this->obj($key($list), " $m->pad", $m->opt . $t);
                } elseif (',' == $t) { # comma
                    $m = $this->obj($key('[' == $m->opt[-1]), $m->pad, $m->opt);
                } elseif ('}' == $t || ']' == $t) { # close
                    $opt = substr($m->opt, 0, -1);
                    if ('.' == $opt) { # json end
                        $el->mode = 'k';
                        $m = $this->obj();
                    } else {
                        $m = $this->obj($key('[' == $opt[-1]), substr($m->pad, 1), $opt);
                    }
                }
            }
        } elseif ($return = '' === $m->val && $open && !$m->opt) { # json init
            $push($m);
            $m = $this->obj($key($list), false === $m->key ? $m->pad : " $m->pad", '.' . $t);
        }
        return $return;
    }

    private function code(string $str, &$t) {
        if (!preg_match("/^@(\w+)(\(|)(.*)$/s", $str, $match))
            return false;
        [,$name, $q, $rest] = $match;
        if (!$q)
            return $t = '@' . $name;
        if ($br = Rare::bracket("($rest"))
            $t = "@$name$br";
        return $br ? [$name, substr($br, 1, -1), $this->at[1]] : false;
    }

    private function tokens(&$m) {
        $in = $this->tail[0] . "\n";
        $len = strlen($in);
        $j = 0;
        return new eVar(function ($prev) use (&$m, &$j, &$in, $len) {
            $j += strlen($pt = $prev->token ?? '');
            if ($j >= $len)
                return false;
            static $list = [
                'k' => '-:+', 'v' => ':{[', 'e' => ':',
                /* list: */   'v[' => '{[,]', 'e[' => '{[,]',
                'k{' => ':}', 'v{' => '[{,}', 'e{' => ',}',
            ];
            $mode = $prev->mode ?? 'k';
            $ws = fn($_) => in_array($_, [' ', "\t", "\n"], true);
            $wt = $code = false;
            if ($whitespace = $ws($t = $in[$j])) {
                "\n" == $t or $t = substr($in, $j, strspn($in, "\t ", $j));
                $prev->token = $prev->ws = $prev->_ws = $t;
                if ("\n" != $t && ($prev->ss ?? false))
                    return true; # SkipSpace
            } elseif ('rest' == $mode || '#' == $t && ($prev->ws ?? true)) { # return rest of line
                $t = $prev->token = substr($in, $j, strcspn($in, "\n", $j));
                if ('rest' !== $mode)
                    return true; # cut comment
                $prev->mode = 'k';
            } elseif ('"' == $t || "'" == $t) {
                $sz = Rare::str($in, $j, $len) or $this->halt('Incorrect string');
                $t = substr($in, $j, $sz -= $j);
            } elseif (strpbrk($t, "\n" . ($cx = $list[$mode]))) {
                if (in_array($t, [':', '-', '+']) && $ws($in[$j + 1])) # next is space
                    $wt = $t;
            } else {
                $t = substr($in, $j, strcspn($in, "\n'\"\t $cx", $j));
                if ('@' == $t[0] && 'v' == $mode[0])
                    $code = $this->code(substr($in, $j), $t);
            }
            $json = 2 == strlen($mode);
            if ($json && !$code && 'v' == $mode[0])
                $mode[0] = 'e';
            return [
                'token' => $t,
                'ws' => $whitespace,
                '_ws' => $prev->_ws ?? '',
                'nl' => "\n" == $pt || '' === $pt,
                'k1' => ':' == $wt,
                'k3' => $wt && 'k' == $mode ? $wt : false,
                'code' => $code,
                'mode' => $mode,
                'json' => $json,
                'mult' => in_array($m->opt, ['|', '>']),
            ];
        }, -1);
    }

    private function parse() {
        $m = $this->obj();
        $p =& $m->val;
        $pad_0 = $reqk = '';
        foreach ($this->tokens($m) as $el) {
            if ($el->nl) {
                $len = strlen($p);
                $pad = $el->ws ? $this->halt(false, $el->token) : '';
                $reqk = $lock = $pad <= $pad_0; # require match key
            }
            if ("\n" == ($t = $el->token)) {
                if ('k' == $el->mode) {
                    if ($reqk && $has_t)
                        $this->halt('Cannot match key');
                } elseif (!$m->opt && in_array($p = trim($p), ['|', '>'])) {
                    $m->opt = $p;
                    $p = '';
                }
                $this->at[1]++; # next line
                $el->json or $el->mode = 'k';
                '|' != $m->opt or !$el->nl or $p .= "\n";
            } elseif ($el->code) {
                $m->code[] = is_array($el->code) ? $el->code : [substr($t, 1), null, $this->at[1]];
                $el->ss = true;
            } elseif (1 == strlen($t) && $this->json($m, $el, $t)) {
                $p =& $m->val;
                $el->ss = true;
            } elseif ($el->k3 && ($reqk || !$m->opt)) { # yaml key
                $k1 = $el->k1 ? $this->scalar(substr($p, $len)) : '-' == $el->k3;
                if (null !== $m->key) {
                    if ($pad > $m->pad) { # aggregate node
                        $len && $this->halt('Mapping disabled');
                        foreach ($m->code as $fun)
                            $this->stack[] = [$fun, $m->pad, $m->pad];
                        $m->code = 0;
                    }
                    $p = substr($p, 0, $len);
                    if ($this->push($m))
                        return; # + scalar - stop parsing
                }
                $m = $this->obj($k1, $pad_0 = $pad);
                $p =& $m->val;
                $el->mode = $el->ss = 'v';
                $has_t = false;
            } elseif ($el->k1 && is_bool($m->key) && !$m->opt) { # vocabulary key
                if ($m->key) {
                    $this->push($this->obj(true, $m->pad, '', 0));
                    $pad_0 = $m->pad .= ' ' . $this->halt(false, $el->_ws);
                }
                $m->key = $this->scalar($p);
                $p = '';
                $el->mode = $el->ss = 'v';
            } elseif ($el->nl) { # new line start
                if ($has_t = !$el->ws)
                    $p .= $t;
                if ($el->mult && !$reqk) {
                    $el->mode = 'rest'; # multiline mode
                    if (!$len) {
                        $pad_1 = strlen($pad);
                    } elseif ('|' == $m->opt) {
                        $p .= "\n" . substr($pad, $pad_1);
                    }
                }
            } else {
                $p .= !$lock && $len && '|' != $m->opt && ($lock = $len++) ? ' ' . $t : $t;
                'v' != $el->mode or $el->mode = 'e';
                'k' != $el->mode or $has_t = true;
            }
        }
        is_null($m->key) or $this->push($m); # the last
    }

    private function halt(string $error, $space = false, $line = false) {
        if (false !== $space && !strpbrk($space, "\t"))
            return $space;
        $line or $line = $this->at[1];
        $at = (false === $this->at[0] ? 'Line ' : $this->at[0] . ', Line ') . $line;
        throw new Error("Yaml, $at: " . ($error ?: 'Tabs disabled for indent'));
    }

    private function obj($key = null, $pad = '', $opt = '', $code = []) : stdClass {
        return (object)[
            'key' => $key,
            'val' => '',
            'pad' => $pad,
            'opt' => $opt,
            'code' => $code,
        ];
    }

    private function var(string &$in) {
        if (!preg_match("/^\\$([A-Z\d_]+)(.*)$/", $in, $match))
            return;
        [, $key, $rest] = $match;
        if ($rest && ($bkt = Rare::bracket($rest))) {
            $rest = substr($rest, strlen($bkt));
            $in = Rare::env($key, substr($bkt, 1, -1));
        } elseif ('SELF' == $key) {
            $in = $this->dir;
        } elseif ('TAIL' == substr($key, 0, 4)) {
            $in = 'TAILS' == $key
                ? array_slice($this->tail, 1)
                : $this->tail[$key[4] ?? 1] ?? null;
        } elseif (defined($key)) {
            $in = constant($key);
            'WWW' != $key or $in = substr($in, 0, -1);
        } elseif (!self::$boot || !Boot::get_const($key, $in)) {
            return;
        }
        '' === $rest or $in = (string)$in . $rest;
    }

    private function transform(&$v, $m, $has_var) {
        $ary = array_reverse($this->stack);
        foreach ($m->code as $fun)
            array_unshift($ary, [$fun, 1]); # ' ' > 1 is false
        foreach ($ary as $one) {
            [$code, $pad] = $one;
            if ($m->pad > $pad || 1 === $pad) {
                $noeval = 'eval' == $code[0] && '!' == $code[1] && !$this->marker;
                if ('deny' == $code[0] || $noeval)
                    break;
                $code += [3 => $this->statements($code[0], $code[2])];
                $v = run_transform($code, $v, $this->array, $has_var);
            }
        }
        return !is_string($v);
    }

    private function scalar(string $v, $is_val = false, $m = 0) {
        if ($has_var = $v && '$' == $v[0])
            $this->var($v);
        if ($is_val && 0 !== $m->code && $this->transform($v, $m, $has_var))
            return $v;
        if ('' === $v || 'null' === $v)
            return $is_val ? null : $this->halt('Key cannot be NULL');
        $true = 'true' === $v;
        if ($true || 'false' === $v)
            return $true;
        if ('"' == $v[0] && '"' == $v[-1])
            return substr($v, 1, -1);
        if ("'" == $v[0] && "'" == $v[-1])
            return substr($v, 1, -1);
        if ($is_val && is_numeric($x = $v)) {
            if ('-' === $v[0] || '+' === $v[0])
                $x = substr($v, 1);
            return ctype_digit($x) ? intval($v) : floatval($v);
        }
        return $v;
    }

    static function inc($name, $ware = false, $marker = '', $yml = null) {
        $default = '$DIR_S/w2/__data.yaml';
        if (false !== strpos($marker, '.')) {
            [$fn, $marker] = explode('.', $marker, 2);
            if (!$fn)
                $default = $yml->at[0];
        }
        if ('' === $name) {
            $name = $default;
            $yml->var($name);
            $ware = true;
        }
        $inc = function ($dir, $name) use ($marker) {
            $ext = explode('.', $name);
            switch (end($ext)) {
                case 'php': return $dir ? (require $name) : Plan::_r($name);
                case 'json': return json_decode($dir ? file_get_contents($name) : Plan::_g($name), true);
                case 'yml':
                case 'yaml': return Yaml::file($dir ? $name : Plan::_t($name), $marker);
                default: return bang(unl($dir ? file_get_contents($name) : Plan::_g($name)));
            }
        };
        if (true === $ware)
            return $inc(true, $name);
        if (!$ware) {
            strpos($name, '::') or $name = Plan::$ware . '::' . $name;
            [$ware, $name] = explode('::', $name, 2);
        }
        return Plan::set($ware, fn() => $inc(false, $name));
    }

    private function statements($tag, $line) {
        switch ($tag) {
            case 'inc':
                return fn($v, $marker, &$a, $has_var) => self::inc($v, $has_var, $marker, $this);
            case 'preflight': $tag = false;
            case 'php':
                return function ($v, $x) use ($tag) {
                    if ($tag) {
                        $key = md5(mt_rand());
                        $this->php["'$key'"] = $x ?? $v;
                        return $key;
                    }
                    $this->php[0] = [$x, $v];
                    return '';
                };
            case 'eval':
                return fn($v, $x) => isset($x) && '!' != $x ? $x : $v;
            case 'json':
                return fn($v) => json_encode($v);
            case 'ip':
                return fn($v) => ip2long($v);
            case 'str':
                return fn($v) => strval($v);
            case 'dec':
                return fn($v) => str_replace([' ', '_', '-'], '', $v);
            case 'bin':
                return fn($v) => intval($v, 2);
            case 'oct':
                return fn($v) => intval($v, 8);
            case 'hex':
                return fn($v) => intval($v, 16);
            case 'hex2bin':
                return fn($v) => hex2bin(str_replace(' ', '', $v));
            case 'rot13':
                return fn($v) => str_rot13($v);
            case 'base64':
                return fn($v) => base64_decode($v);
            case 'ini_get':
                return fn($v) => ini_get($v);
            case 'csv':
                return fn($v, $x) => explode($x ?: ';', $v);
            case 'join':
                return fn($v, $x) => implode($x ?? ';', $v);
            case 'space':
                return fn($v, $x) => preg_split("/\s+/", $v, $x ?: null);
            case 'bang':
                return fn($v, $x) => bang(trim(unl($v)), $x[0] ?? ' ', $x[1] ?? "\n");
            case 'url':
                return fn($v) => parse_url($v);
            case 'time':
                return fn($v) => strtotime($v);
            case 'scan':
                return fn($v, $x) => sscanf($v, $x);
            case 'match':
                return function ($v, $x) {
                    return preg_match($x, $v, $match) ? array_slice($match, 1) : false;
                };
            case 'object':
                return fn($v) => (object)$v;
            case 'range':
                return fn($v, $x) => range($v, $x);
            case 'each':
                return fn($v, $x) => null;//array_walk_recursive 
            case 'sql':
                return fn($v, $x) => $v ? sql($x, ...$v) : sql($x);
            case 'left':
                return function ($v, $x) {
                    if (!is_array($v))
                        return $x . $v;
                    array_walk_recursive($v, fn(&$_) => $_ = $x . $_);
                    return $v;
                };
            case 'path':
                return function& ($v, $path, &$a = null, $_ = 0, $unset = false) {
                    '' === $v ? ($p =& $this->array) : ($p =& $v);
                    if (!is_string($path))
                        return is_int($path) ? $this->tail[$path] : $this->tail;
                    foreach (explode('.', $path) as $key) {
                        $prev =& $p;
                        $p =& $p[$key];
                    }
                    $return =& $p;
                    if ($unset)
                        unset($prev[$key]);
                    return $return;
                };
            default:
                if (isset(self::$custom[$tag]))
                    return self::$custom[$tag];
                $this->halt("Transformation `@$tag` not found", false, $line);
        }
    }
}

//https://nodeca.github.io/js-yaml/
//https://yaml-online-parser.appspot.com/
function run_transform($_cod, $v, &$a, $has_var) {
    global $sky;
    $_ret = ($_cod[3])($v, $_cod[1], $a, $has_var);
    if ('eval' !== $_cod[0])
        return $_ret;
    $tok = token_get_all("<? $_ret");
    if (!array_filter($tok, fn($v) => is_array($v) && T_RETURN == $v[0]))
        $_ret = 'return ' . $_ret;
    return eval("$_ret;");
}
