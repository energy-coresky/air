<?php

trait SAW
{
    static $saws = [];

    protected $wn_input = '';
    protected $wn_tokens = [
        'Marker',
        '#if..#end',
        '#use',
        'Operator',
        7 => 'Data',
        9 => 'Comment',
    ];

    private function _saw(...$in) {
        $saw =& self::$saws[array_shift($in)];
        if (isset($saw['array']))
            return $saw['array'];
        $saw['bind'] or $saw['bind'] = Closure::bind($saw['closure'], $this, $this);
        return call_user_func_array($saw['bind'], $in);
    }

    function unbind($list = []) {
        foreach (self::$saws as $name => &$saw)
            if (in_array($name, $list))
                $saw['bind'] = false;
    }

    function load($src, $dst) {
        if ('~' == $src[0]) {
            $ware = 'main';
        } else {
            $ware = Plan::$ware;
            $src = "$ware::$src";
        }
        $dst = ['main', $name = "saw_$ware" . "_$dst.php"];
        //Plan::_m([$ware, $fn]) < Plan::cache_mq($dst)
        if (1) { //!$saw = Plan::cache_rq($dst)) {
            $init = '';
            $php = '$array = [';
            foreach (yml("+ @inc(saw) $src") as $k => $saw) {
                if (isset($saw['array'])) {
                    $php .= "'$k' => ['array' => " . var_export($saw['array'], true) . "],\n\n";
                    if ($saw['init'])
                        $init .= $saw['init'];
                    continue;
                }
                $php .= "'$k' => ['bind' => false, 'closure' => function ($saw[param]) {\n";
                foreach (['head', 'body', 'tail'] as $section) {
                    if (!$section = $saw['closure'][$section] ?? false)
                        continue;
                    foreach ($section as $k => $v) {
                        if ('vars' === $k) {
                            foreach ($v as $var => $is)
                                $php .= "\$$var = " . var_export($is, true) . ";\n";
                        } elseif ('if' === $k) {
                            foreach ($v as $n => $rule) {
                                if ('default' === $n) {
                                    $php .= " else {\n$rule\n}\n";
                                } elseif ($n > 0) {
                                    $php .= " elseif ($rule[on]) {\n$rule[do]\n}";
                                } else {
                                    $php .= "if ($rule[on]) {\n$rule[do]\n}";
                                }
                            }
                        } else {
                            $php .= "$v\n\n";
                        }
                    }
                }
                $php .= "}],\n\n";
            }
            $php .= "];\n\n";
            Plan::cache_s($dst, "<?php\n\n$php\n\n$init\n\nreturn \$array;");
            $saw = Plan::cache_r($dst);
        }
        self::$saws += $saw;
    }

    function tokens($y = false) {
        //2do: #.markers, #if(..) .. #end, {{ }} echo's like
        // @verb, disable PHP tags, #use
        $re = [
            "/^(#\.\w+(\.\w+)*)/", # markers 0
            "/^((#if|#elseif|#use)\(|(#end\b|#else\b))/", # pre-proc 1
            "/^([~@]\w+)/", # ~word or @word
        ];
        $y or $y = (object)['typ' => 9, 'pt' => "\n"];
        $len = strlen($in =& $this->wn_input);
        $cspn = fn($s, $j) => substr($in, $j, strcspn($in, $s, $j));
        $match = fn($s, &$m, $x) => preg_match($re[$x], $s, $m);
        for ($j = 0; $j < $len; $j += strlen($t . $y->bracket)) {
            $y->bracket = '';
            if (!$y->typ) {
                $y->typ = 9; # commment (right from marker)
                $t = $cspn("\n", $j) . "\n";
            } elseif("#" == ($t = $in[$j])) {
                if ($match($line = $cspn(" \t\n", $j), $m, $y->typ = 1)) { # pre-proc
                    if ($m[2]) {
                        $br = Rare::bracket($in, '(', $j + strlen($m[2]));
                        if (!$br || '' == trim(substr($br, 1, -1)))
                            goto typ7;
                        if ('#use' == ($t = $m[2]))
                            $y->typ = 2;
                        $y->bracket = $br;
                    } else {
                        $t = $m[3];
                    }
                } elseif ("\n" == $y->pt[-1] && $match($line, $m, $y->typ = 0)) { # markers
                    $t = $m[1];
                } else {
                    goto typ7;
                }
            } elseif(in_array($t, ['@', '~']) && $match($cspn(" \t\n", $j), $m, 2)) {
                $y->bracket = Rare::bracket($in, '(', $j + strlen($t = $m[1]));
                $y->typ = 3;
            } else {
                typ7:
                $t .= $cspn("#~@", $j + 1); # <? @verb 
                $y->typ = 7;
            }
            yield $t => $y;
            $y->pt = $t;
        }
    }

    private function preprocessor() {
        $ary = [];
        $p =& $ary;
        $d = [&$p];
        $tmp = '';
        foreach ($this->tokens() as $t => $y) {
            if (1 != $y->typ || !($if = '#if' == $t) && count($d) < 2) {
                $tmp .= $t . $y->bracket;
                continue;
            }
            if ('#end' == $t) {
                array_push($p, $tmp, 'end');
                array_pop($d);
                $p =& $d[count($d) - 1];
            } else {
                if ($if) {
                    array_push($p, $tmp, []);
                    $tmp = '';
                    $p =& $p[count($p) - 1];
                    $d[] =& $p;
                }
                array_push($p, $tmp, $y->bracket ?: 'else');
            }
            $tmp = '';
        }
        $p[] = $tmp;

        $eval = function ($arg, $out) {
            static $ary;
            if (null === $ary) {
                $ary = [':0' => '$sky->_0', ':1' => '$sky->_1', ':2' => '$sky->_2', ':-' => '""===trim($out)'];
                $lines = ($txt = Plan::_gq('mvc/jet.let')) ? explode("\n", $txt) : [];
                foreach ($lines as $one) {
                    if (preg_match("/^(:\w+)\s+(.+)/", $one, $m))
                        $ary[$m[1]] = $m[2];
                }
            }
            for ($i = 0; $i < 22; $arg = $new, $i++) {
                $new = preg_replace_callback("/(:\-|:\w+)/", function ($m) use (&$ary) {
                    return isset($ary[$m[1]]) ? '(' . $ary[$m[1]] . ')' : $m[1];
                }, $arg);
                if ($arg === $new)
                    break;
            }
            if ($i > 21)
                throw new Error("Preprocessor: cycled pseudo-variables");
            global $sky;
            return eval("return $arg;");
        };

        $crop = function ($ary) use(&$crop, &$eval) {
            $out = '';
            foreach ($ary as $v) {
                if (is_string($v)) {
                    $out .= $v;
                    continue;
                }
                $i = $ce = $ok = $len = 0;
                for ($cnt = count($v); $i < $cnt; $i++) {
                    if (++$i == $cnt)
                        break;
                    if (is_array($el = $v[$i]))
                        continue;
                    !$ce or $ce++;
                    if ('else' === $el) {
                        $ce++;
                        $ok or $ok = $i;
                    }
                    if ($ok && !$len)
                        $len = $i - $ok - 1;
                    if (!$ok && '(' === $el[0] && $eval($el, $out))
                        $ok = $i;
                }
                if ($ce > 2 || 'end' !== end($v)) { # reassemble on wrong syntax
                    foreach ($v as $i => $ok) {
                        if (is_array($ok)) {
                            $out .= $crop([$ok]);
                        } elseif (0 == $i % 2) {
                            $out .= $ok;
                        } else {
                            $out .= 1 != $i ? ('e' == $ok[0] ? "#$ok" : "#elseif$ok") : "#if$ok";
                        }
                    };
                } elseif ($ok) {
                    $out .= $crop(array_slice($v, $ok + 1, $len));
                }
            }
            return $out;
        };

        return $crop($ary);
    }

    private function echos($in) {
        return preg_replace_callback('/[~@]?{[{!\-](.*?)[\-!}]}/s', function ($m) {
            if ('@' == $m[0][0])
                return substr($m[0], 1); # verbatim
            $tilda = '~' == $m[0][0];
            $esc = '%s';
            switch ($m[0][1 + (int)$tilda]) {
            case '-':
                return $tilda ? '' : '<?php /*' . $m[1] . '*/ ?>'; # Jet comment
            case '{':
                $esc = 'html(%s)'; # echo escaped
            case '!':
                $or = $and = false;
                $left = $right = '';
                foreach (token_get_all("<?php $m[1]") as $t) { # a ?: b 
                    if (is_array($t)) {
                        if (T_OPEN_TAG == $t[0])
                            continue;
                        if (in_array($t[0], [T_LOGICAL_OR, T_LOGICAL_AND])) {
                            T_LOGICAL_OR == $t[0] ? ($or = true) : ($and = true);
                            continue;
                        }
                        $t = $t[1];
                    }
                    $or || $and ? ($right .= $t) : ($left .= $t);
                }
                if (!$or && !$and) {
                    $val = trim($m[1]);
                    $op = $tilda ? "isset($val) ? $val : ''" : $val;
                    return sprintf("<?php echo $esc ?>", $op);
                }
                $left = trim($left);
                $right = trim($right);
                if ($and) {
                    $op = $tilda ? "isset($left) && $left" : $left;
                    return sprintf("<?php echo %s ? $esc : '' ?>", $op, $right);
                }
                # else `or`
                $op = $tilda
                    ? "isset($left) && '' !== trim($left) ? $left : $right"
                    : "isset($left) ? $left : $right";
                return sprintf("<?php echo $esc ?>", $op);
            }
        }, $in);
    }
}
