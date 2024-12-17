<?php

class JS
{
    const version = '0.002';

    static $js;

    public $in;
    public $array = [];
    public $pad = 4; # 0 for minified Javacsript

    function __construct(string $in = '') {
        defined('T_KEYWORD') or define('T_KEYWORD', 10001);
        self::$js or self::$js = yml('+ @object @inc(js)');
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

    private function halt(string $error, $space = false, $line = false) {
        #if (false !== $space && !strpbrk($space, "\t"))
        #    return $space;
        #$line or $line = $this->at[1];
        #$at = (false === $this->at[0] ? 'Line ' : $this->at[0] . ', Line ') . $line;
        throw new Error("JS: $error");
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
                $y->tok = T_COMMENT;
            } elseif ('/' == $in[$j] && in_array($t2 = $in[$j + 1], ['*', '/'])) {
                $t = '/' . $t2; # comment
                $y->find = $t2 == "*" ? '*/' : "\n";
                $y->tok = T_COMMENT;
            } elseif ($y->space = strspn($in, "\t \n", $j)) {
                $t = substr($in, $j, $y->space);
                $y->tok = T_WHITESPACE;
            } elseif (strpbrk($t = $in[$j], self::$js->chars)) {
                $y->tok = 0;
            } elseif ('"' == $t || "'" == $t) {
                $sz = Rare::str($in, $j, $len) or $this->halt('Incorrect string');
                $t = substr($in, $j, $sz - $j);
            } elseif ($y->word = preg_match("/^[a-z\n_\$]+/i", substr($in, $j), $m)) {
                $y->tok = in_array($t = $m[0], self::$js->keywords) ? T_KEYWORD : T_STRING;
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
