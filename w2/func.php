<?php

class Func
{
    static function replace($match, $dd) {
        switch($match[1]) {
            case 'now':
                return $dd->f_dt();
            case 'cc':
                return call_user_func_array([$dd, 'f_cc'], Func::parse($match[3]));
            default:
                return $match[0]; # as is////////////////////////////////////////////////////////
        }
    }

    static function parse($m3) {
        $ary = [];
        $s = '';
        $p = 0;
        foreach (token_get_all("<?php $m3") as $i => $x) {
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
        $ary[] = substr($s, 0, -1);

        return $ary;
    }
}
