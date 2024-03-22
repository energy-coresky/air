<?php

class CSS
{
    const version = 0.003;

    private $in;
    private $array;

    function __construct(string $in = '') {
        $this->in = unl($in);
        $this->array = [];
    }

    static function file($name) {
        $css = new CSS(file_get_contents($name));
        return var_export($css->parse());
    }

    function mode(&$in, $k, $len, &$mode, $chr, &$z = null) {
        for ($cm = false; true;) {
            $k += strcspn($in, "\\/$chr", $k);
            if ($k >= $len)
                return $cm ?: $k;
            switch ($in[$k]) {
                case '\\': # escape char
                    $k += 2;
                    break;
                case '/':
                    if ('*' != $in[$k += 1])
                        break;
                    false !== $cm or $cm = $k - 1; # comment
                    $pos = strpos($in, '*/', $k + 1);
                    $k = false === $pos ? $len : 2 + $pos;
                    break;
                case ':': # key-like
                    $_cm = false;
                    $pos = $this->mode($in, $k, $len, $mode, ',{', $_cm);
                    if ($is_key = $this->mode($in, $k, $len, $mode, ';}', $_cm) < $pos)
                        $mode = 'k';
                    return $cm ?: ($is_key ? $k : ($_cm ?: $pos));
                case ',': case '{':
                    $x = 't';
                case ';': case '}':
                    $mode = $x ?? 'v';
                    if (null === $z)
                        return $cm ?: $k;
                    $z = $cm;
                    return $k;
            }
        };
    }

    function tokens($y = false) {
        $list = ['t' => "{,;", 'k' => ":}", 'v' => ';}'];
        $y or $y = (object)['mode' => 't', 'depth' => 0, 'find' => false];
        $len = strlen($in =& $this->in);
        for ($j = 0; $j < $len; $j += strlen($t)) {
            if ($y->found = $y->find) {
                if (false === ($sz = strpos($in, $y->find, $j))) {
                    $t = substr($in, $j); # /* </style> */ is NOT comment inside <style>!
                } else {
                    $t = substr($in, $j, $sz - $j) . $y->find;
                    $y->find = false;
                }
            } elseif ('/' == $in[$j] && '*' == $in[$j + 1]) {
                $t = '/*'; # comment
                $y->find = '*/';
            } elseif ($y->space = strspn($in, "\t \n", $j)) {
                $t = substr($in, $j, $y->space);
            } elseif (!strpbrk($t = $in[$j], $list[$y->mode])) {
                $k = $this->mode($in, $j, $len, $y->mode, 'v' == $y->mode ? ';}' : ':,{');
                $t = rtrim(substr($in, $j, $k - $j));
            }
            yield $t => $y;
        }
    }

    function parse(&$split = null, $plus = 0) {
        $sum = '';
        $ary = [];
        $ptr = [&$ary];
        foreach ($this->tokens() as $t => $y) {
            $p =& $ptr[$last = array_key_last($ptr)];
            if ($y->found || $y->find) {
                ;
            } elseif (';' == $t) {
                $p[] = $sum;//echo $sum;
                $sum = '';
                $y->mode = 'k';
            } elseif ('{' == $t) {
                $y->depth++;
                $p[] = [$sum, [], 0];
                $ptr[] =& $p[array_key_last($p)][1];
                $sum = '';
            } elseif ('}' == $t) {
                $y->depth--;
                !$sum or $p[] = $sum;
                array_pop($ptr);
            } elseif (':' == $t) {
                $sum .= ':';
                $y->mode = 'v';
            } elseif (',' == $t) {
                $sum .= ',';
            } elseif (!$y->space) {
                $sum .= $t;
            }
        }
        return $ary;
    }

    function &buildCSS(&$ary, $sort = false, $plus = 0) {
        if (is_string($ary))
            $ary =& $this->parse($ary);
        if ($sort) usort($ary, function ($a, $b) use ($plus) {
            $ma = $plus < $a[2];
            $mb = $plus < $b[2];
            if (!$ma && !$mb)
                return strcmp($a[0], $b[0]);
            return $ma && $mb ? 0 : ($ma && !$mb ? 1 : -1);
        });
        $pad = str_pad('', $this->tab * $plus);
        $end = 'rich' == $this->opt['format'] ? "\n" : '';
        $out = '';
        foreach ($ary as $one) {
            if ($one[1]) {
                $out .= $pad . $this->name($one[0]) . " {\n";
                if ($one[2] > $plus) {
                    $out .= $this->buildCSS($one[1], $sort, 1 + $plus);
                } else foreach ($one[1] as $prop) {
                   if ($this->add_space)
                       $prop = preg_replace("/^([\w\-]+):/", '$1: ', $prop);
                    $out .= "$pad$this->pad$prop;\n";
                }
                $out .= "$pad}\n$end";
            } else {
                $out .= "$pad$one[0];\n";
            }
        }
        if ($end)
            $out = substr($out, 0, -1);
        return $out;
    }

    function name($str) {
        $ary = [];
        $hl = $this->opt['highlight'];
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

