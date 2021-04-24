<?php

class Func_parse
{
    static function one($dd, $match) {
        switch($match[1]) {
            case 'now':
                return $dd->f_dt();
            case 'cc':
                $ary = [];
                $s = '';
                $p = 0;
                foreach (token_get_all("<?php $match[3]") as $i => $x) {
                    list ($lex, $x) = is_array($x) ? $x : [0, $x];
                    if (!$i || 1 == $i || T_WHITESPACE == $lex)
                        continue;
                    '(' != $x or $p++;
                    ')' != $x or $p--;
                    if (',' == $x && !$p) {
                        $ary[] = $s;
                        $s = '';
                    } else {
                        $s .= $x;
                    }
                }
                $ary[] = $s;
                $i = count($ary) - 1;
                $ary[$i] = substr($ary[$i], 0, -1);
                return call_user_func_array([$dd, 'f_cc'], $ary);
            default:
                return $match[0]; # as is////////////////////////////////////////////////////////
        }
    }
}
