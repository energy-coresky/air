<?php

class Debug
{
    static function cron() {
        if (!Plan::_t(['main', 'cron.php']))
            return trace('cron.php not found', true);
        $ts = SKY::d('cron_dev_ts') ?: 0;
        if (START_TS - $ts > 60) {
            SKY::d('cron_dev_ts', START_TS);
            exec('php ' . DIR . '/' . DIR_M . '/cron.php @ 2>&1');
        }
    }

    static function trace() {
        isset(DEV::$vars[0]) or self::vars(['sky' => $GLOBALS['sky']], 0, 0);
        ksort(DEV::$vars);
        $dev_data = [
            'cnt' => [
                $c = count($a1 = Debug::get_classes(get_declared_classes())[1]),
                $c + count($a2 = Debug::get_classes(get_declared_interfaces())[1]),
            ],
            'classes' => array_merge($a1, $a2, get_declared_traits()),
            'vars' => DEV::$vars,
            'errors' => SKY::$errors,
        ];
        return tag(html(json_encode($dev_data)), 'class="dev-data" style="display:none"');
    }

    static function vars($in, $no, $is_blk = false) {
        $ary = [];
        isset(DEV::$vars[$no]) or DEV::$vars[$no] = [];
        $p =& DEV::$vars[$no];
        if (!$no && false === $is_blk)
            $in += ['sky via __get($name)' => $GLOBALS['sky']];
        Plan::$see_also = $obs = [];
        foreach ($in as $k => $v) {
            if (in_array($k, ['_vars', '_in', '_return', '_a', '_b']))
                continue;
            if ('$' == $k && isset($p['$$']))
                return;
            //$types = ['unknown type', , 5 => 'resource', 'string', 'array', 'object']; // k u r o  str100 : arr+obj 200
            $type = gettype($v);
            if ($is_obj = 'object' == $type)
                $obs[get_class($v)] = 1;
            if (!in_array($type, ['NULL', 'boolean', 'integer', 'double']))
                $v = Plan::var($v, '', false, $is_obj ? $k : false);
            $ary[$k] = $v;
        }

        if (!$no && !$is_blk) {
            if (0 === $is_blk)
                return $ary;
            $obs = array_diff_key(Plan::$see_also, $obs);
            $ary += self::vars(array_combine(array_map('key', $obs), array_map('current', $obs)), 0, 0);
        }
        ksort($ary);
        if ($is_blk) {
            isset($p['@']) or $p += ['@' => []];
            $p['@'][] = $ary;
        } else {
            $p += $ary;
        }
    }

    static function get_classes($all = [], $ext = [], $t = -2) {
        $all or $all = get_declared_classes();
        $ext or $ext = get_loaded_extensions();
        $ary = [];
        $types = array_filter($ext, function ($v) use (&$ary, $t) {
            if (!$cls = (new ReflectionExtension($v))->getClassNames())
                return false;
            $t < 0 ? ($ary = array_merge($ary, $cls)) : $ary[$v] = $cls;
            return true;
        });
        $types = [-1 => 'all', -2 => 'user'] + $types;
        return [$types, -2 == $t ? array_diff($all, $ary) : (-1 == $t ? $all : array_intersect($all, $ary[$types[$t]]))];
    }

    static function closure($fun) {
        $fun = new ReflectionFunction($fun);
        $file = file($fun->getFileName());
        $line = trim($file[$fun->getStartLine()]);
        return 'Plan::' == substr($line, 0, 6) ? $line : 'Extended Closure';
    }

    static function z_err($z_fly, $is_error = false, $drop = true) {
        static $msg = null;

        if (null !== $msg)
            return $msg; # empty string or msg
        $msg = Plan::cache_gq($addr = ['main', 'dev_z_err']);
        if (!SKY::$debug) # j_trace don't erase file
            return $msg;
        $z_fly = HEAVEN::Z_FLY == $z_fly;
        if ($msg) {
            if ($drop) {
                $z_fly or Plan::cache_dq($addr); # erase flash file
                SKY::$debug = false; # skip self tracing to show z-error's one
            }
        } elseif ($z_fly && $is_error) {
            $msg = tag("Z-error at " . NOW, 'class="z-err"', 'h1');
            $p =& SKY::$errors;
            if (isset($p[1]))
                $msg .= '<h1>' . $p[1][0] . '</h1><pre>' . $p[1][1] . '</pre>';
            if (isset($p[2]))
                $msg .= '<h1>' . $p[2][0] . '</h1><pre>' . $p[2][1] . '</pre>';
            Plan::cache_p($addr, $msg);
            Plan::$z_error = true;
        }
        return $msg;
    }

    // 2do suppressed: fix for php 8 https://www.php.net/manual/en/language.operators.errorcontrol.php
    static function epush($title, $desc, $context) {
        if ($context) {
            $desc .= "\n";
            foreach ($context as $k => $v)
                if (is_scalar($v) || is_null($v))
                    $desc .= "\$$k = " . html(var_export($v, 1)) . ";\n";
        }
        $p =& SKY::$errors;
        $p[isset($p[1]) ? 2 : 1] = ["#$p[0] $title", mb_substr($desc, 0, 1000)];
    }

    static function _check_other(&$plus) {
        $c = array_intersect(array_keys(sqlf('@explain $_users')), array_keys(sqlf('@explain $_visitors')));
        if (count($c) == 1 && 'id' == current($c))
            return $plus;
        return $plus .= "<h1>Other Errors</h1><pre>users vs visitors columns:\n\n" . html(print_r($c, true)) . '</pre>';
    }

    static function check_other($class_debug = true) {
        $error = '';
 #       if ('utf8' != mysqli_character_set_name($this->conn))
  #          $error = '<h1>wrong database character set</h1>'; # cannot use trace() to SQL insert..
     #   if ($class_debug && SKY::$debug)
      #      Debug::_check_other($error);
        return $error;
    }

    function short($str) { # 2do
        $n = substr_count($str, "\n");
        if ($n > 10) {
            
        } elseif (strlen($str) > 500) $x = 300;
        else return $str;
        $back = '<br>' . a('back', ($href = 'href="javascript:;" onclick=') . '"sky.short()"') . '<br>';
        return substr($str, 0, $x) . a('more..', $href . '"sky.short(this)"') . tag($back . substr($str, $x) . $back, 'style="display:none"');
    }
}
