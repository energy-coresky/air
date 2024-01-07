<?php

trait Processor
{
    private function preprocessor($in) {
        $ary = [];
        $p =& $ary;
        $d = [&$p];
        $tmp = '';
        while (preg_match("/^(.*?)\#((if\(|elseif\()|(end|else)\b)(.*)$/s", $in, $m)) {
            $in = $m[5];
            $arg = ($br = '(' == $m[2][-1]) ? Rare::bracket('(' . $m[5]) : false;
            $if = 'if(' == $m[2];
            if (count($d) < 2 && !$if || '()' == $arg || $br && !$arg) {
                $tmp .= $m[1] . '#' . $m[2];
                continue;
            }
            if ('end' == $m[2]) {
                array_push($p, $tmp . $m[1], 'end');
                array_pop($d);
                $p =& $d[count($d) - 1];
            } else {
                if ($arg)
                    $in = substr($m[5], strlen($arg) - 1);
                if ($if) {
                    array_push($p, $tmp . $m[1], []);
                    $tmp = $m[1] = '';
                    $p =& $p[count($p) - 1];
                    $d[] =& $p;
                }
                array_push($p, $tmp . $m[1], $arg ?: 'else');
            }
            $tmp = '';
        }
        $p[] = $tmp . $in;

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

    private function echos($str) {
        //$echo = 'Jet' == get_class($this) ? 'echo' : 'return';
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
        }, $str);
    }
}
