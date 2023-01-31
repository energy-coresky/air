<?php

class Debug
{
    static function start() {
        global $sky;
        if (DEV && $sky->d_cron) {
            $cron = new Schedule;
            $ts = strtotime(substr($cron->n_cron_dt, 0, 19));
            if (START_TS - $ts > 60)
                exec('php ' . DIR . '/' . DIR_M . '/cron.php');
        }
    }

    static function table($in, $no_h = true) {
        $hash = !is_num(key($in));
        
        $out = th($hash ? ['', 'NAME', 'VALUE'] : array_shift($in), 'class="debug-table"');
        $i = 1;
        foreach ($in as $k => $v) {
            if ($hash) {
                is_string($v) or is_int($v) or $v = print_r($v, true);
                $no_h or $v = html($v);
                $v = [$i, [$k, 'style="min-width:100px"'], "<pre>$v</pre>"];
            }
            $out .= td($v, eval(zebra));
        }
        return "$out</table>";
    }

    function short($str) { # 2do
        $n = substr_count($str, "\n");
        if ($n > 10) {
            
        } elseif (strlen($str) > 500) $x = 300;
        else return $str;
        $back = '<br>' . a('back', ($href = 'href="javascript:;" onclick=') . '"sky.short()"') . '<br>';
        return substr($str, 0, $x) . a('more..', $href . '"sky.short(this)"') . tag($back . substr($str, $x) . $back, 'style="display:none"');
    }

    static function gpc() {
        return "\$_GET: " . html(var_export($_GET, true)) .
            "\n\$_POST: " . html(var_export($_POST, true)) .
            "\n\$_FILES: " . html(var_export($_FILES, true)) .
            "\n\$_COOKIE: " . html(var_export($_COOKIE, true)) . "\n";
    }

    static function get_classes($all, $ext = [], $t = -2) {
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

    static function z_err($was_error = null) {
        if (!DEV)
            return '';
        $msg = Plan::cache_gq($err = ['main', 'dev_z_err']);
        if (null === $was_error) {
            $msg && Plan::cache_dq($err); # erase flash file
            return $msg;
        }
        if ($msg) {
            SKY::$debug = false; # skip tracing to show first error
        } elseif ($was_error) {
            $msg = tag("Z-error at " . NOW, 'class="z-err"', 'h1');
            $ary = SKY::$errors;
            array_shift($ary);
         #   foreach ($ary as $one)
            #    $msg .= "<h1>$one[0]</h1><pre>$one[1]</pre>";
            Plan::cache_p($err, $msg);
        }
    }

    static function error_name($no) {
        $list = [
            E_ERROR => 'Fatal error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core fatal error',
            E_CORE_WARNING => 'Core warning',
            E_COMPILE_ERROR => 'Compile fatal error',
            E_COMPILE_WARNING => 'Compile warning',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable error',
            E_DEPRECATED => 'Deprecated',
        ];
        return $list[$no] ?? "ErrorNo_$no";
    }

    // 2do: fix for php 8 https://www.php.net/manual/en/language.operators.errorcontrol.php
    static function show_suppressed() {
        //return 
    }

    static function epush($title, $err, $ary, $depth) {
        $i = !isset(SKY::$errors[1]) ? 1 : 2;
        $n = SKY::$errors[0];
        SKY::$errors[$i] = ["#$n $title", $err . Debug::context($ary, $depth)];
    }

    static function context($ary, $depth) {
        if (-1 === $ary)
            return function_exists('get_context') ? get_context(1 + $depth) : '';
        if (is_null($ary))
            return ''; # else array
        $out = $ary ? "\n" : ''; # $ary as reference!
        foreach ($ary as $k => $v) {
            if (is_scalar($v) && 's_' != substr($k, 0, 2))
                $out .= "\$$k = " . html(var_export($v, 1)) . ";\n";
        }
        return substr($out, 0, 1000);
    }

    static function check_other(&$plus) {
        $c = array_intersect(array_keys(sqlf('@explain $_users')), array_keys(sqlf('@explain $_visitors')));
        if (count($c) == 1 && 'id' == current($c))
            return $plus;
        return $plus .= "<h1>Other Errors</h1><pre>users vs visitors columns:\n\n" . html(print_r($c, true)) . '</pre>';
    }
}
