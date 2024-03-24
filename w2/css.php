<?php

class CSS
{
    const version = 0.777;

    public $in;
    public $array = [];
    public $pad = 2; # 0 for minified CSS
    public $sort = false; # (compare 2 CSS)

    function __construct(string $in = '') {
     #$this->sort = [$this, '_sort'];
        $this->in = unl($in);
    }

    static function file($name) {
        echo new CSS(file_get_contents($name));
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

    function define($in, $x3) {
        if (is_string($in))
            return $in;
        return implode(',' . $x3, $in);
    }

    function _sort($a, $b) {
        $_a = '@' == $a[0][0];
        $_b = '@' == $b[0][0];
        return $_a != $_b ? ($_a && !$_b ? 1 : -1) : strcmp($a[0], $b[0]);
    }

    function walk(&$ary, $fn, $depth = 0) {
        if ($this->sort)
            usort($ary, $this->sort);
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

    function query($str) {
        
    }

    function mode(&$in, $k, $len, &$mode, $chr, $real = false) {
        if (strpbrk($t = $in[$k], 'd' == $mode ? '{;,}' : '{:;,}')) {
            if ('}' == $t) {
                $mode = 'd';
            } elseif (':' == $t) {
                $mode = 'v';
            } elseif (';' == $t && 'v' == $mode) {
                $mode = 'k';
            }
            return ++$k;
        }
        static $list = ['d' => '{:;,', 'k' => ':}', 'v' => ';}'];
        for ($cm = false; true;) {
            $k += strcspn($in, "\\/" . ($chr ?: $list[$mode]), $k);
            if ($k < $len) switch ($in[$k]) {
                case '\\': # escape char
                    $k += 2;
                    continue 2;
                case '/':
                    if ('*' == $in[$k += 1]) { # comment
                        $cm or $cm = $k - 1;
                        $pos = strpos($in, '*/', $k + 1);
                        $k = false === $pos ? $len : 2 + $pos;
                    }
                    continue 2;
                case ':': # key-like
                    if ('d' == $mode) {
                        $pos = $this->mode($in, $k, $len, $mode, ';}', true);
                        $pos > $this->mode($in, $k, $len, $mode, '{', true) or $mode = 'k';
                    }
                    return $cm ?: ('k' == $mode ? $k : $this->mode($in, $k, $len, $mode, '{,'));
            } # { or , or ; or }
            return $real ? $k : ($cm ?: $k);
        };
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
