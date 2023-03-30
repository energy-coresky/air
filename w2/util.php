<?php

class Util
{
    private static $vars = [];

    static function data() {
        if (!isset(Util::$vars[0])) {
            $top = MVC::instance();
            trace("0 $top->hnd " . MVC::$layout . "^$top->body", 'TOP-VIEW', 1);
            Util::vars([]);
        }
        ksort(Util::$vars);
        $dev_data = [
            'cnt' => [
                $cnt = count($a1 = Util::get_classes(get_declared_classes())[1]),
                $cnt + count($a2 = Util::get_classes(get_declared_interfaces())[1]),
            ],
            'classes' => array_merge($a1, $a2, get_declared_traits()),
            'vars' => Util::$vars,
            'errors' => SKY::$errors,
        ];
        return tag(html(json_encode($dev_data)), 'class="dev-data" style="display:none"');
    }

    static function vars($in, $no = 0, $is_blk = false) {
        global $sky;
        static $collect = ['Closure' => 1], $cache = [];

        if (!$no && false === $is_blk && (!isset($in['sky']) || $sky != $in['sky'])) {
            $in += [isset($in['sky']) ? 'sky$' : 'sky' => $sky];
        }
        $out = Plan::$see_also = [];
        isset(Util::$vars[$no]) or Util::$vars[$no] = [];
        $p =& Util::$vars[$no];

        foreach ($in as $k => $v) {
            if (in_array($k, ['_vars', '_in', '_return', '_a', '_b']))
                continue;
            if ('$' == $k && isset($p['$$']))
                return;
            $type = gettype($v);
            if ($is_obj = 'object' == $type) {
                $cls = get_class($v);
                $no or $is_blk or $collect[$cls] = 1;
            }
            if (in_array($type, ['unknown type', 'resource', 'string', 'array', 'object'])) # else: 'NULL', 'boolean', 'integer', 'double'
                $v = Plan::var($v, '', false, 'sky$' == $k ? 'sky' : $k);//$is_obj ? $k : false

            if ($is_obj || 'array' == $type) {
                if ($new = !isset($cache[$type .= $k]) || $cache[$type] != $v)
                    $cache[$type] = $v;
                $out[$k] = $new ? $v : L::y($is_obj ? "Object $cls" : 'Array') . ' - ' . L::m('see prev. View');
            } else {
                $out[$k] = $v;
            }
        }

        if (!$no && !$is_blk) {
            if (0 === $is_blk)
                return $out;
            if ($new = array_diff_key(Plan::$see_also, $collect))
                $out += Util::vars(array_combine(array_map('key', $new), array_map('current', $new)), 0, 0);
            if ($new = array_diff_key(Plan::$see_also, $collect))
                $out += Util::vars(array_combine(array_map('key', $new), array_map('current', $new)), 0, 0);
        }
        uksort($out, function ($a, $b) {
            $a_ = (bool)strpos($a, ':');
            if ($a_ != ($b_ = (bool)strpos($b, ':')))
                return $a_ ? 1 : -1;
            return strcasecmp($a, $b);
        });
        if ($is_blk) {
            isset($p['@']) or $p += ['@' => []];
            $p['@'][] = $out;
        } else {
            $p += $out;
        }
    }

    static function get_classes($all = [], $ext = [], $t = -2) {
        $all or $all = get_declared_classes();
        $ext or $ext = get_loaded_extensions();
        $ary = [];
        $types = array_filter($ext, function ($v) use (&$ary, $t) {
            if (!$cls = (new ReflectionExtension($v))->getClassNames())
                return false;
            $t < 0 ? ($ary += array_combine($cls, array_pad([], count($cls), $v))) : ($ary[$v] = $cls);
            return true;
        });
        $types = [-1 => 'all', -2 => 'user'] + $types;
        if ($t > -2)
            return [$types, -1 == $t ? $all : array_intersect($all, $ary[$types[$t]]), $ary];
        return [$types, array_diff($all, array_keys($ary)), []];
    }

    static function not_found($class, $name) {
        global $sky;
        switch ($class) {
            case 'Controller':
            case 'default_c_R':
                if (DEV && Plan::_t([Plan::$gate, "mvc/c_$sky->_0.php"])) {
                    Plan::cache_d(['main', 'sky_plan.php']);
                    $sky->fly or jump(URI);
                }
                $x = HEAVEN::J_FLY == $sky->fly ? 'j' : 'a';
                $msg = preg_match("/^\w+$/", $sky->_0)
                    ? "Controller `c_$sky->_0.php` or method `default_c::{$x}_$sky->_0()` not exist"
                    : "Method `default_c::default_$x()` not exist";
                trace($msg, (bool)DEV);
                break;
            default:
                trace("Method `{$class}::$name()` not exist", (bool)DEV);
        }
    }

    static function request_headers() {
        if (function_exists('apache_request_headers'))
            return apache_request_headers();
        $out = [];
        $re = '/\AHTTP_/';
        foreach ($_SERVER as $key => $val) {
            if (preg_match($re, $key)) {
                $name = preg_replace($re, '', $key);
                $rx_matches = explode('_', strtolower($name));
                if (count($rx_matches) > 0 && strlen($name) > 2) {
                    foreach($rx_matches as $ak_key => &$ak_val)
                        $ak_val = ucfirst($ak_val);
                    $name = implode('-', $rx_matches);
                }
                $out[$name] = $val;
            }
        }
        return $out;
    }

    static function response_headers($flush = true) {
        if ($flush)
            flush();
        if (function_exists('apache_response_headers'))
            return apache_response_headers();
        $out = [];
        $headers = headers_list();
        foreach ($headers as $header) {
            $header = explode(":", $header);
            $out[array_shift($header)] = trim(implode(":", $header));
        }
        return $out;
    }

    static function pdaxt($plus = '') {
        global $sky, $user;

        if ($sky->show_pdaxt || DEV) {
            $link = $user->pid
                ? ($user->u_uri_admin ? $user->u_uri_admin : Admin::$adm['first_page'])
                : 'auth';
            echo '<span class="pdaxt">';
            if (DEV || 1 == $user->pid) {
                if (!$sky->is_mobile) {
                    if ($sky->has_public)
                        echo DEV ? a('P', PROTO . '://' . _PUBLIC) : L::r('P');
                    if (DEV) {
                        echo '_venus' == $sky->d_last_page
                            ? a('V', PATH . '_venus?ware=' . rawurlencode(URI))
                            : a('D', ["dev('" . ($sky->d_last_page ?: '_dev') . "')"]);
                    }
                    echo a('A', PATH . $link);
                }
                $warning = 'style="background:red;color:#fff"';
                echo a('X', ['sky.trace(1)'], Plan::cache_t(['main', 'sky_xw']) ? $warning : '');
                echo a('T', ['sky.trace(0)'], $sky->was_warning ? $warning : '');
            } else {
                echo a('ADMIN', PATH . $link);
            }
            echo "$plus</span>";
        }
    }
}
