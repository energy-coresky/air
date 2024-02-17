<?php

class Yaml
{
    use Processor;#2do ?

    const version = 0.912;

    static $boot = 0;
    static $transform;
    static $eval;
    static $dev = false;

    public $array;
    public $tail;

    private $at;
    private $dir;
    private $cell;
    private $stack = [];
    private $mode = [
        'em' => '',
        'val' => '{[',
        'json' => '{[,]}',
    ];

    static function directive($name, $func = null) {
        $ary = is_array($name) ? $name : [$name => $func];
        self::$transform = $ary + self::$transform;
    }

    static function text($in) {
        $yml = new Yaml($in, false);
        return $yml->cell ? $yml->array[0] : $yml->array;
    }

    static function file($name) {
        $yml = new Yaml($name);
        return $yml->cell ? $yml->array[0] : $yml->array;
    }

    static function path($path, $unset = false) {
        return (Yaml::$transform['path'])(0, $path, $path, 0, $unset);
    }

    static function lint(string $in, $is_file = true) : bool {
        try {
//            self::yml($in, $is_file);
        } catch (Error $e) {
            return false;
        }
        return true;
    }

    function __construct(string $in, $is_file = true, $try = false) {
        self::$eval = fn($v) => $v;
        defined('DEV') && (self::$dev = DEV);
        $this->dir = $is_file ? str_replace('\\', '/', dirname($in)) : '???';
        $this->at = [$is_file ? $in : false, 0];
        $in = trim($is_file ? file_get_contents($in) : $in) . "\n";
        if ($this->cell = '+' == $in[0])
            $in[0] = '-';

        $this->array = [];
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
            $define && Boot::set_const($z[$m->key]);
        };

        $n = $this->obj();
        $this->tail = explode("\n~\n", unl($in));
        foreach (explode("\n", $this->tail[0]) as $_ => $in) {
            $this->at[1] = 1 + $_;
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
            if (json_last_error()) {
echo $m->json; // json_last_error_msg()
                $this->halt('JSON failed');
            }
        } else {
            $v = $this->scalar($m->mod ? $m->val : trim($m->val), true, $m);
            if (1 == self::$boot) {
                if ('define' == $m->key) {
                    $v = ['WWW' => Boot::www()];
                    $define = true;
                } elseif ('DEV' == $m->key) {
                    self::$boot = 2;
                    self::$dev = $v;
                }
            }
        }
        return $v;
    }

    private function code(string $str, &$t) {
        if (!preg_match("/^@(\w+)(\(|)(.+)$/", $str, $match))
            return false;
        [,$name, $q, $rest] = $match;
        $ws = fn($i) => '' === trim($rest[$i]);
        if (!$q && $ws(0))
            return true;
        $j = strlen($br = Rare::bracket("($rest"));
        if ($q = $j && $ws($j - 1))
            $t = "@$name$br";
        return $q ? [$name, substr($br, 1, -1)] : false;
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
                $t = $prev->token = substr($in, $j, strspn($in, "\t ", $j));
                $pt && ($wt = $pt);
            } elseif ('rest' == $mode) { # return rest of line
                $t = substr($in, $j);
            } elseif ('#' == $x && ($prev->ws ?? true)) {
                return false; # cut comment
            } elseif ('"' == $x || "'" == $x) {
                $sz = Rare::str($in, $j, $len) or $this->halt('Incorrect string');
                $t = substr($in, $j, $sz -= $j);
            } else {
                $cl = $this->mode[$mode];
                $t = strpbrk($x, ":-$cl") ? $x : substr($in, $j, strcspn($in, "\t\"' :-$cl", $j));
                if ($code = '@' == $x && 'val' == $mode)
                    $code = $this->code(substr($in, $j), $t);
            }
            return 'skip' == $mode ? (bool)($prev->mode = 'val') : [
                'token' => $t,
                'keyc' => $colon = ':' == $wt,
                'key2' => $colon || '-' == $wt,
                'code' => $code = ($code ?? false),
                'mode' => $code ? 'skip' : $mode,
                'ws' => $whitespace || $code,
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
                    foreach ($code as $fun)
                        $this->stack[] = [$fun, $n->pad, $n->pad];
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
                [$name, $param] = is_array($el->code) ? $el->code : [substr($t, 1), ''];
                $n->code[] = [$this->statements($name), $param];
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
            'code' => [],
        ];
        return (object)$in;
    }

    private function var(string &$in) {
        if (!preg_match("/^\\$([A-Z\d_]+)(.*)$/", $in, $m))
            return;
        [, $key, $rest] = $m;
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

    private function transform(&$v, $n, $has_var) {
        $ary = array_reverse($this->stack);
        foreach ($n->code as $fun)
            array_unshift($ary, [$fun, 1]); # ' ' > 1 is false
        foreach ($ary as $one) {
            [$code, $pad] = $one;
            if ($n->pad > $pad || 1 === $pad)
                $v = run_transform($code, $v, $this->array, $has_var);
        }
        return !is_string($v);
    }

    private function scalar(string $v, $is_val = false, $n = 0, $json = false) {
        if ($has_var = $v && '$' == $v[0])
            $this->var($v);
        if ($is_val && 0 !== $n->code && $this->transform($v, $n, $has_var))
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
        return $json ? '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"' : $v;
    }

    static function inc($name, $ware = false) {
        $inc = function ($dir, $name) {
            $ext = explode('.', $name);
            switch (end($ext)) {
                case 'php': return $dir ? (require $name) : Plan::_r($name);
                case 'json': return json_decode($dir ? file_get_contents($name) : Plan::_g($name), true);
                case 'yml':
                case 'yaml': return Yaml::file($dir ? $name : Plan::_t($name));
                default: return strbang(unl($dir ? file_get_contents($name) : Plan::_g($name)));
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

    private function statements($tag) {
        switch ($tag) {
            case 'inc':
                return fn($v, $x, &$a, $has_var) => self::inc($v, $has_var);
            //case 'php':
            case 'eval':
                return self::$eval;
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
            case 'space':
                return fn($v) => preg_split("/\s+/", $v);
            case 'csv':
                return fn($v, $x) => explode('' === $x ? ',' : $x, $v);
            case 'join':
                return fn($v, $x) => implode('' === $x ? ',' : $x, $v);
            case 'bang':
                return fn($v, $x) => strbang(trim(unl($v)), $x[0] ?? ' ', $x[1] ?? "\n");
            case 'url':
                return fn($v) => parse_url($v);
            case 'time':
                return fn($v) => strtotime($v);
            case 'scan':
                return fn($v, $x) => sscanf($v, $x);
            case 'object':
                return fn($v) => (object)$v;
            case 'range':
                return fn($v, $x) => range($v, $x);
            case '':
                return ;
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
                $this->halt("Transformation `@$tag` not found");
        }
    }
}

function run_transform($_cod, $v, &$a, $has_var) {
    global $sky;
    $_ret = ($_cod[0])($v, $_cod[1], $a, $has_var);
    if ($_cod[0] !== Yaml::$eval)
        return $_ret;
    $tok = token_get_all("<? $_ret");
    if (!array_filter($tok, fn($v) => is_array($v) && T_RETURN == $v[0]))
        $_ret = 'return ' . $_ret;
    return eval("$_ret;");
}
