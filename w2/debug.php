<?php

class Debug
{
    private static $vars = [];

    static function data() {
        if (!isset(self::$vars[0])) {
            $top = MVC::instance();
            trace("0 $top->hnd " . MVC::$layout . "^$top->body", 'TOP-VIEW', 1);
            self::vars([]);
        }
        ksort(self::$vars);
        $dev_data = [
            'cnt' => [
                $cnt = count($a1 = self::get_classes(get_declared_classes())[1]),
                $cnt + count($a2 = self::get_classes(get_declared_interfaces())[1]),
            ],
            'classes' => array_merge($a1, $a2, get_declared_traits()),
            'vars' => self::$vars,
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
        isset(self::$vars[$no]) or self::$vars[$no] = [];
        $p =& self::$vars[$no];

        foreach ($in as $k => $v) {
            if (in_array($k, ['_data', '_vars', '_in', '_return', '_a', '_b']))
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
                $out += self::vars(array_combine(array_map('key', $new), array_map('current', $new)), 0, 0);
            if ($new = array_diff_key(Plan::$see_also, $collect))
                $out += self::vars(array_combine(array_map('key', $new), array_map('current', $new)), 0, 0);
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

    static function not_found($class) {
        global $sky;

        $x = HEAVEN::J_FLY == $sky->fly ? 'j' : 'a';
        if ('default_c' == $class) {
            is_string($k = $sky->_0) or $k = '*';
            $msg = preg_match("/^\w+$/", $k)
                ? "Controller `c_$k.php` or method `$class::{$x}_$k()` not exist"
                : "Method " . ('' === $k ? "`$class::empty_$x()` or " : '') . "`$class::default_$x()` not exist";
        } else {
            is_string($v = $sky->_1) or $v = '*';
            $msg = preg_match("/^\w+$/", $v)
                ? "Method `$class::{$x}_$v()` or `$class::default_$x()` not exist"
                : "Method " . ('' === $v ? "`$class::empty_$x()` or " : '') . "`$class::default_$x()` not exist";
        }
        trace($msg, (bool)DEV);
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

    static function tracing(&$top) {
        global $sky;
        trace(implode(', ', array_keys(SKY::$mem)), 'CHARS of Ghost');
        if ($sky->trans_coll)
            Language::translate($sky->trans_coll);
        $rewritten = implode('/', $sky->surl) . ($_GET ? '?' . urldecode(http_build_query($_GET)) : '');
        $uri = $sky->methods[$sky->method] . ' /' . URI . " --> /$rewritten\n\$sky->lref: ";
        $ref = false === $sky->lref ? L::m('false') . ' $sky->eref: ' . html($sky->eref) : '/' . html($sky->lref);
        $sky->tracing = 'PATH: ' . PATH . html("\nADDR: $uri") . "$ref\n\$sky->fly: $sky->fly\n\n" . $sky->tracing;

        if (SKY::$debug > 1) {
            $top .= 'Request headers:'  . html(substr(print_r(self::request_headers(), true), 7, -2));
            $top .= 'Response headers:' . html(substr(print_r(self::response_headers(), true), 7, -2));
            $top .= '$sky->orig_surl: ' . html($sky->orig_surl) . "\n" . '$sky->orig_qstr: ' . html($sky->orig_qstr);
        }
    }

    static function gpc() {
        return "\$_GET: " . Plan::var($_GET) .
            "\n\$_POST: " . Plan::var($_POST) .
            "\n\$_FILES: " . Plan::var($_FILES) .
            "\n\$_COOKIE: " . Plan::var($_COOKIE) . "\n";
    }

    static function closure($fun) {
        $fun = new ReflectionFunction($fun);
        $file = file($fun->getFileName());
        return trim($file[$fun->getStartLine() - 1]);
        //return 'Plan::' != substr($line, 0, 6) ? $line : 'Extended Closure';
    }

    static function catch_error($func) {
        global $sky;
        $t = [$sky->was_error, SKY::$debug];
        SKY::$debug = $sky->was_error = 0;
        $r = [call_user_func($func), $e = $sky->was_error];
        [$sky->was_error, SKY::$debug] = $t;
        $sky->was_error |= $e;
        return $r;
    }

    static function mail($message, $ary, $subject, $to) {
        $ary += [
            'from' => 'nor@' . _PUBLIC,
            'to' => $to ?: SKY::s('email'),
            'subject' => $subject ?: 'SKY-Mail from ' . _PUBLIC,
            'vars' => [],
            'files' => [],
        ];
        extract($ary);
        $boundary = md5(uniqid(time()));
        if ($vars)
            $message = Jet::text($message, $vars);
        if ($files) {
            # 2do
        }
        $headers = "From: $from\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"";
        // SKY::s('email_cnt', SKY::s('email_cnt') + 1);
        if (!DEV)
            return mail($to, $subject, $message, $headers);

        $data = "to=$to\n\nsubject=$subject\n\nheaders=$headers\n\nmessage:\n\n$message";
        SKY::log('mail', html($data));
    }

    static function pdaxt($plus = '') {
        global $sky, $user;

        if ($sky->show_pdaxt || DEV) {
            $link = $user && $user->pid
                ? ($user->u_uri_admin ? $user->u_uri_admin : $sky->adm_first)
                : 'auth';
            echo '<span class="pdaxt">';
            if (DEV || 1 == $user->pid) {
                if (!$sky->is_mobile) {
                    if ($sky->has_public)
                        echo DEV ? a('P', PROTO . '://' . _PUBLIC) : L::r('P');
                    if (DEV) {
                        echo '_venus' == $sky->d_last_page
                            ? a('V', PATH . '_venus?page=' . rawurlencode(HOME . URI))
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

    static function out($out, $is_html = true, $c2 = '30%') { # check Debug::out() for XSS
        if (is_array($out)) {
            echo th(0 === $is_html ? ['','',''] : ['', 'NAME', 'VALUE'], 'id="table"');
            $i = 0;
            foreach ($out as $k => $v) {
                is_string($v) or is_int($v) or $v = print_r($v, true);
                if ($is_html)
                    $v = html($v);
                echo td([[1 + $i, 'style="width:5%"'], [$k, 'style="width:' . $c2 . '"'], $v], eval(zebra)); //2do delete zebra
            }
            echo '</table>';
        } else {
            echo pre($out, 'id="pre-out"');
        }
    }

    static function drop_all_cache() {
        global $sky;
        $result = 1;
        foreach (['cache', 'gate', 'jet'] as $plan)
            $result &= call_user_func(['Plan', "{$plan}_da"], '*');
        if (SKY::$dd) {
            $s = (int)substr($sky->s_statp, 0, -1) + 1;
            $sky->s_statp = $s > 9999 ? '1000p' : $s . 'p';
        }
        return $result;
    }

    static function warm_all_cache() {
        foreach (SKY::$plans['main']['ctrl'] as $ctrl => $ware) {
            $ctrl = explode('/', $ctrl);
            echo "Controller: $ware." . ($ctrl = $ctrl[1] ?? $ctrl[0]) . "\n";
            $ctrl = '*' == $ctrl ? 'default_c' : "c_$ctrl";
            Plan::gate_p("$ware-$ctrl.php", Gate::instance()->parse($ware, "mvc/$ctrl.php", false));
        }
        //2do other: jet, svg, assets
        return 1;
    }
}
