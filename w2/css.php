<?php

class CSS
{
    const version = 0.223;

    public $in;
    public $array;
    public $tab = 2;

    function __construct(string $in = '') {
        $this->in = unl($in);
        $this->array = [];
    }

    static function file($name) {
        $css = new CSS(file_get_contents($name));
        echo $css;
        //return var_export($css->array);
    }

    function __toString() {
        if (!$this->array)
            $this->parse();
        return $this->toString($this->array);
    }

    function toString(&$ary, $sort = false, $plus = 0) {
        if ($sort) usort($ary, function ($a, $b) use ($plus) {
            $ma = $plus < $a[2];
            $mb = $plus < $b[2];
            if (!$ma && !$mb)
                return strcmp($a[0], $b[0]);
            return $ma && $mb ? 0 : ($ma && !$mb ? 1 : -1);
        });
        $pad = str_pad('', $this->tab * $plus);
        $pad_1 = str_pad('', $this->tab);
        $end = "\n";//'rich' == $this->opt['format'] ? "\n" : '';
        $out = '';
        foreach ($ary as $one) {
            if (is_array($one[1]) && $one[1]) {
                $out .= $pad . $this->name($one[0]) . " {\n";
                if (is_int(key($one[1]))) {
                    $out .= $this->toString($one[1], $sort, 1 + $plus);
                } else foreach ($one[1] as $name => $value) {
                   #if ($this->add_space)
                   #    $prop = preg_replace("/^([\w\-]+):/", '$1: ', $prop);
                    $out .= "$pad$pad_1$name: $value;\n";
                }
                $out .= "$pad}\n$end";
            } else {
                $out .= "$pad$one;\n";
            }
        }
        if ($end)
            $out = substr($out, 0, -1);
        return $out;
    }

    function name($str) {
        $ary = [];
        $hl = 0;//$this->opt['highlight'];
        foreach (preg_split("/\s*,\s*/", $str) as $v) {
            if (!$hl || in_array($v[0], ['@', ':', '['])) {
                $ary[] = $v;
            } elseif ('.' == $v[0]) {
                $ary[] = '.<m>' . substr($v, 1) . '</m>';
            } elseif ('#' == $v[0]) {
                $ary[] = '#<g>' . substr($v, 1) . '</g>';
            } else {
                $ary[] = '<span class="vs-tag">' . "$v</span>";
            }
        }
        return implode(', ', $ary);
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
        $ptr = [&$this->array];
        foreach ($this->tokens() as $t => $y) {
            if ($y->found || $y->find || $y->space)
                continue;
            $p =& $ptr[$last = array_key_last($ptr)];
            if (';' == $t) {
                if ('t' == $y->mode) {
                    $p[] = $sum;
                    $sum = '';
                }
            } elseif ('{' == $t) {
                $y->depth++;
                $p[] = [$sum, []];
                $ptr[] =& $p[array_key_last($p)][1];
                $sum = '';
            } elseif ('}' == $t) {
                $y->depth--;
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

