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
        return new CSS(file_get_contents($name));
    }

    function tokens($y = false) {
        $y or $y = (object)['mode' => 'txt', 'depth' => 0];
        $len = strlen($in =& $this->in);
        for ($j = 0; $j < $len; $j += strlen($t)) {
            if ($y->space = strspn($in, "\t \n", $j)) {
                $t = substr($in, $j, $y->space);
            } elseif (!strpbrk($t = $in[$j], "{:;}")) {
                $t = substr($in, $j, strcspn($in, "\n \t{:;}", $j));
            }
            yield $t => $y;
        }
    }

    function &parse_css(&$in, &$split = null, $plus = 0) {
        $in = preg_replace("~(#+|//+)~", "$1\n", '<?php ' . unl($in));
        $depth = $has_child = 0;
        $sum = '';
        $ary = [];
        foreach (token_get_all($in) as $k => $token) {
            $id = $str = $token;
            if (is_array($token)) {
                list($id, $str) = $token;
                if (!$k || T_DOC_COMMENT == $id || $space) {
                    $space = false;
                    continue;
                }
                if (T_WHITESPACE == $id) {
                    $str = ' '; # 2do: comment between spaces
                } elseif (T_COMMENT == $id) {
                    if ('#' != $str && '*' == $str[1]) # //
                        continue;
                    if ("\n" != $str[-1] && " " != $str[-1])
                        $space = true;
                    $str = trim($str);
                }
            }
            switch ($id) {
                case '{':
                    if (1 == ++$depth) {
                        $key = trim($sum);
                        $prop = [];
                        $sum = '';
                        continue 2;
                    } elseif (2  == $depth) {
                        $has_child = 1;
                    }
                    break;
                case ';':
                    if (1 == $depth) {
                        $prop[] = trim($sum);
                        $sum = '';
                        continue 2;
                    } elseif (0 == $depth) {
                        $ary[] = [trim($sum), [], $plus];
                        $sum = '';
                        continue 2;
                    }
                    break;
                case '}':
                    if (0 == --$depth) {
                        if ($has_child) {
                            $null = null;
                            $prop =& $this->parse_css($sum, $null, 1 + $plus);
                        } else {
                            '' === trim($sum) or $prop[] = trim($sum);
                        }
                        $ary[] = [$key, $prop, $plus + $has_child];
                        if ($split === $key) {
                            $split = $ary;
                            $ary = [];
                        }
                        $sum = '';
                        $has_child = 0;
                        continue 2;
                    }
                    break;
            }
            $sum .= preg_replace("~(#+|//+)\n~", "$1", $str);
        }
        return $ary;
    }

    function &buildCSS(&$ary, $sort = false, $plus = 0) {
        if (is_string($ary))
            $ary =& $this->parse_css($ary);
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

