<?php

class CSS
{
    const version = 0.223;

    public $in;
    public $array = [];
    public $pad = 2; # 0 for minified CSS
    public $sort = false; # (compare 2 CSS)

    private $end = "\n";

    function __construct(string $in = '') {
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
        $this->pad or $this->end = '';
        $this->walk($this->array, $fn = function ($one, $pad, $depth) use (&$fn, $x) {
            if (isset($one[1])) {
                $this->in .= "$pad$one[0]$x[2]{" . $x[1];
                if (is_int(key($one[1]))) {
                    $this->walk($one[1], $fn, 1 + $depth);
                } else foreach ($one[1] as $name => $value) {
                    $this->in .= "$pad$x[0]$name:$x[2]$value;$x[1]";
                }
                $this->in .= "$pad}$this->end";
            } else {
                $this->in .= "$pad$one[0];$this->end";
            }
        });
        return $this->in;
    }

    function walk(&$ary, $fn, $depth = 0) {
        if ($this->sort) usort($ary, function ($a, $b) {
            $_a = '@' == $a[0][0];
            $_b = '@' == $b[0][0];
            return $_a != $_b ? ($_a && !$_b ? 1 : -1) : strcmp($a[0], $b[0]);
        });
        foreach ($ary as $one)
            $fn($one, str_pad('', $this->pad * $depth), $depth);
    }

    function mode(&$in, $k, $len, &$mode, $chr, $real = false) {
        if (strpbrk($t = $in[$k], 't' == $mode ? '{;,}' : '{:;,}')) {
            if ('}' == $t) {
                $mode = 't';
            } elseif (':' == $t) {
                $mode = 'v';
            } elseif (';' == $t && 'v' == $mode) {
                $mode = 'k';
            }
            return ++$k;
        }
        static $list = ['t' => '{:;,', 'k' => ':}', 'v' => ';}'];
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
                    if ('t' == $mode) {
                        $pos = $this->mode($in, $k, $len, $mode, ';}', true);
                        $pos > $this->mode($in, $k, $len, $mode, '{', true) or $mode = 'k';
                    }
                    return $cm ?: ('k' == $mode ? $k : $this->mode($in, $k, $len, $mode, '{,'));
            } # { or , or ; or }
            return $real ? $k : ($cm ?: $k);
        };
    }

    function tokens($y = false) {
        $y or $y = (object)['mode' => 't', 'depth' => 0, 'find' => false];
        $len = strlen($in =& $this->in);
        for ($j = 0; $j < $len; $j += strlen($t)) {
            if ($y->found = $y->find) {
                if (false === ($sz = strpos($in, $y->find, $j))) {
                    $t = substr($in, $j); # /* </style> */ is NOT comment inside <style>!
                } else {
                    $t = substr($in, $j, $sz - $j + 2);
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
        $sum = '';
        $this->array = [];
        $ptr = [&$this->array];
        foreach ($this->tokens() as $t => $y) {
            if ($y->found || $y->find || $y->space)
                continue;
            $p =& $ptr[$last = array_key_last($ptr)];
            if (';' == $t) {
                if ('t' == $y->mode) {
                    $p[] = [$sum];
                    $sum = '';
                }
            } elseif ('{' == $t) {
                $p[] = [$sum, []];
                $ptr[] =& $p[array_key_last($p)][1];
                $sum = '';
            } elseif ('}' == $t) {
                array_pop($ptr);
            } elseif ('v' == $y->mode) {
                $p[$prev] = $t;
            } elseif ('k' == $y->mode) {
                $p[$prev = $t] = '';
            } else {
                $sum .= $t;
            }
        }
    }

    function test(&$css, &$s2) { # for lost chars
        $s1 = preg_replace("/\s+/", '', $css); # may have comments
        $s2 = preg_replace("/\s+/", '', $s2); # comments cropped
        $diff = [];
        for ($i = $cx = 0, $cnt = strlen($s1); $i < $cnt; $i++) {
            if (!isset($s2[$i - $cx]))
                exit('Failed length'); # for console
            if ($s1[$i] === $s2[$i - $cx])
                continue;
            if ('}' === $s1[$i] && ';' === $s2[$i - $cx]) { # semicolon added
                $cx--;
                continue;
            }
            $pos = strpos($s1, '*/', $i);
            if (false === $pos) {
                $diff[] = substr($s1, $i);
                break;
            }
            $diff[] = substr($s1, $i, 2 + $pos - $i);
            $cx += 2 + $pos - $i;
            $i = 1 + $pos;
        }
        $cnt = count($diff);
        echo $i - $cx == strlen($s2)
            ? "Test passed, found $cnt comments, first 10:\n"
            : 'Test failed';
        print_r(array_slice($diff, 0, 10));
    }

}

