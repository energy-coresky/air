<?php

class Transform
{
    function __construct() { # compile image
    }

    private function transformers() {
        self::$transform = [
            'inc' => fn($v, $x, &$a, $has_var) => self::inc($v, $has_var),
            'eval' => self::$eval,
            'ip' => fn($v) => ip2long($v),
         //   'str' => fn($v) => strval($v),
            'dec' => fn($v) => str_replace([' ', '_', '-'], '', $v),
            'bin' => fn($v) => intval($v, 2),
            'oct' => fn($v) => intval($v, 8),
            'hex' => fn($v) => intval($v, 16),
            'hex2bin' => fn($v) => hex2bin(str_replace(' ', '', $v)),
            'rot13' => fn($v) => str_rot13($v),
            'base64' => fn($v) => base64_decode($v),
            'ini_get' => fn($v) => ini_get($v),
            'space' => fn($v) => preg_split("/\s+/", $v),
            'semi' => fn($v) => explode(';', $v),
            'csv' => fn($v, $x) => explode('' === $x ? ',' : $x, $v),
            'join' => fn($v, $x) => implode('' === $x ? ',' : $x, $v),
            'bang' => fn($v) => strbang(trim(unl($v))),
            'url' => fn($v) => parse_url($v),
            'time' => fn($v) => strtotime($v),
            'scan' => fn($v, $x) => sscanf($v, $x),
            'left' => function ($v, $x) {
                if (!is_array($v))
                    return $x . $v;
                array_walk_recursive($v, fn(&$_) => $_ = $x . $_);
                return $v;
            },
            'self' => function& ($v, $path, &$a = null, $_ = 0, $unset = false) {
                '' === $v ? ($p =& $this->array) : ($p =& $v);
                if (!is_string($path))
                    return is_int($path) ? $this->tail[$path] : $this->tail;
                foreach (explode('.', $path) as $key) {
                    $prev =& $p;
                    $p =& $p[$key];
                }
                $return =& $p;
                if ($unset)
                    unset($prev[$key]);
                return $return;
            },
        ];
    }
}


function __run_transform($__code__, $v, &$a, $has_var) {
    global $sky;
    $__ret__ = ($__code__[0])($v, $__code__[1], $a, $has_var);
    if ($__code__[0] !== Boot::$eval)
        return $__ret__;
    $tok = token_get_all("<? $__ret__");
    if (!array_filter($tok, fn($v) => is_array($v) && T_RETURN == $v[0]))
        $__ret__ = 'return ' . $__ret__;
    return eval("$__ret__;");
}
