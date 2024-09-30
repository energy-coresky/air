<?php

class JS // T_COMMENT
{
    const version = 0.001;

    static $js;

    public $in;
    public $array = [];
    public $pad = 4; # 0 for minified Javacsript

    function __construct(string $in = '') {
        defined('T_KEYWORD') or define('T_KEYWORD', 10001);
        defined('T_CHAR') or define('T_CHAR', 10002);
        self::$js or self::$js = yml('+ @inc(js)');
        $this->in = unl($in);
    }

    static function file($name) {
        echo new JS(file_get_contents($name));
    }

    function __toString() {
        $this->array or $this->parse();
        $this->in = $this->pad ? '' : "/* Minified with Coresky framework, https://coresky.net */\n";
        $x = [
            str_pad('', $this->pad),
            $this->pad ? "\n" : '',
            $this->pad ? ' ' : '',
        ];
        $this->walk($this->array, $fn = function ($one, $y) use (&$fn, $x) {
            if (isset($one[1])) {
                $this->in .= $y->pad . $this->define($one[0], $x[2]) . "$x[2]{" . $x[1];
                $last = array_key_last($one[1]);
                if (is_int($last)) {
                    $this->walk($one[1], $fn, 1 + $y->depth);
                } else foreach ($one[1] as $name => $value) {
                    $this->in .= "$y->pad$x[0]$name:$x[2]$value";
                    $last == $name && !$this->pad or $this->in .= ";$x[1]";
                }
                $this->in .= "$y->pad}" . $x[1];
                $y->last or $this->in .= $x[1];
            } else {
                $this->in .= $y->pad . $this->define($one[0], $x[2]) . ";$x[1]$x[1]";
            }
        });
        return $this->in;
    }

    function walk(&$ary, $fn, $depth = 0) {
        $last = array_key_last($ary);
        $y = (object)[
            'pad' => str_pad('', $this->pad * $depth),
            'depth' => $depth,
        ];
        foreach ($ary as $n => $one) {
            $y->last = $n == $last;
            $fn($one, $y);
        }
    }

    function mode(&$in, $k, $len, &$mode, $chr, $real = false) {
    }

    function tokens($y = false) {
        $y or $y = (object)['mode' => 'd', 'find' => false];
        $len = strlen($in =& $this->in);
        for ($j = 0; $j < $len; $j += strlen($t)) {
            if ($y->found = $y->find) {
                if (false === ($pos = strpos($in, $y->find, $j))) {
                    $t = substr($in, $j); # /* </style> */ is NOT comment inside <style>!
                } else {
                    $t = substr($in, $j, $pos - $j + 2);
                    $y->find = false;
                }
            } elseif ('/' == $in[$j] && '*' == $in[$j + 1]) {
                $t = '/*'; # comment
                $y->find = '*/';
            } elseif ($y->space = strspn($in, "\t \n", $j)) {
                $t = substr($in, $j, $y->space);
            } else {
                $k = $this->mode($in, $j, $len, $y->mode, false);
                $t = rtrim(substr($in, $j, $k - $j));
            }
            yield $t => $y;
        }
    }

    function parse() {
        $define = [];
        $push = function () use (&$define) {
            $v = 1 == count($define) ? $define[0] : $define;
            $define = [];
            return $v;
        };
        $this->array = [];
        $ptr = [&$this->array];
        foreach ($this->tokens() as $t => $y) {
            if ($y->found || $y->find || $y->space)
                continue;
            $p =& $ptr[array_key_last($ptr)];
            if (';' == $t) {
                'd' != $y->mode or $p[] = [$push()];
            } elseif ('{' == $t) {
                $p[] = [$push(), []];
                $ptr[] =& $p[array_key_last($p)][1];
            } elseif ('v' == $y->mode) {
                $p[$key] = $t;
            } elseif ('k' == $y->mode) {
                $p[$key = $t] = '';
            } elseif ('}' == $t) {
                array_pop($ptr);
            } elseif (',' != $t) {
                $define[] = $t;
            }
        }
    }
}
