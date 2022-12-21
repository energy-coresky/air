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

    static function error_name($no) {
        $list = [
            E_NOTICE => 'Notice',
            E_WARNING => 'Warning',
            E_ERROR => 'Fatal error',
            E_PARSE => 'Parse error',
            E_CORE_ERROR => 'Core fatal error',
            E_COMPILE_ERROR => 'Compile fatal error',
            E_RECOVERABLE_ERROR => 'Recoverable error',
        ];
        return $list[$no] ?? "ErrorNo_$no";
    }

    static function context($ary = -1, $depth = 1) {
        if (-1 === $ary)
            return function_exists('get_context') ? get_context(1 + $depth) : '';
        if (is_null($ary))
            return ''; # else array
        if (function_exists('filter_context'))
            return filter_context($ary);
        $out = ''; # $ary as reference!
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
