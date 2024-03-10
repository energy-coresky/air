<?php

class Diff
{
    static function parse($new, $old, $mode = false) {
        $V = [];
        $N = count($new = explode("\n", str_replace(["\r\n", "\r"], "\n", $new)));
        $L = count($old = explode("\n", str_replace(["\r\n", "\r"], "\n", $old)));

        for ($n = $l = 0; $n < $N && $l < $L; ) {
            $sn = current($new);
            $sl = current($old);
            if ($sn === $sl) {
                $V[$n++] = $l++;
                array_shift($new);
                array_shift($old);
            } else {
                $fn = array_keys($old, $sn);
                $fl = array_keys($new, $sl);
                if (!$fn && !$fl) {
                    $V[$n++] = '*';
                    $l++;
                    array_shift($new);
                    array_shift($old); # optimize?
                } elseif (!$fn) {
                    $V[$n++] = '?';
                    array_shift($new);
                } elseif (!$fl) {
                    $l++;
                    array_shift($old);
                } else { # cross
                    $tn = current($fn);
                    $tl = current($fl);
                    for ($in = 1; isset($new[$in]) && isset($old[$tn + $in]) && $new[$in] === $old[$tn + $in]; $in++);
                    for ($il = 1; isset($old[$il]) && isset($new[$tl + $il]) && $old[$il] === $new[$tl + $il]; $il++);
                    if ($in / $tn < $il / $tl) {
                        $V[$n++] = '?';
                        array_shift($new);
                    } else {
                        $l++;
                        array_shift($old);
                    }
                }
            }
        }
        if ($mode)
            return $V;

        for ($n = $l = 0, $rN = '', $c = count($V); $n < $N || $l < $L; ) {
            if ($n >= $N) {
                $rN .= '.';
                $l++;
            } elseif ($l >= $L) {
                $rN .= '+';
                $n++;
            } elseif ($n >= $c || $V[$n] === '*') {
                $rN .= '*';
                $n++;
                $l++;
            } elseif ($V[$n] === $l) {
                $rN .= '=';
                $n++;
                $l++;
            } elseif ($V[$n] !== '?') do {
                    $rN .= '.';
                } while ($V[$n] > ++$l);
            else {
                $cp = false;
                for ($p = 1; $n + $p < $c; $p++)
                    if (is_int($V[$n + $p])) {
                        $cp = true;
                        break;
                    }
                if ($cp) {
                    $q = $V[$n + $p] - $l;
                    while ($q && $p) {
                        $rN .= "*";
                        $n++;
                        $l++;
                        $q--;
                        $p--;
                    }
                    while ($p--) {
                        $rN .= "+";
                        $n++;
                    }
                    while ($q--) {
                        $rN .= ".";
                        $l++;
                    }
                } else do {
                    $rN .= '*';
                    $n++;
                    $l++;
                } while ($n < $N && $l < $L);
            }
        }
        return $rN;
    }
}
