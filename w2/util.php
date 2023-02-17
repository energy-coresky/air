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
                $out[$k] = $new ? $v : sprintf(span_y, $is_obj ? "Object $cls" : 'Array') . ' - ' . sprintf(span_m, 'see prev. View');
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

    ///////////////////////////////////// GATE UTILITY /////////////////////////////////////
    static function post_data($me) {
         isset($_POST['args']) && $me->argc($_POST['args']);//////////
        SKY::d('sg_prod', (int)isset($_POST['production']));
        $addr = $pfs = [];
        $to =& $addr;
        if (isset($_POST['key'])) {
            foreach ($_POST['key'] as $i => $key) {
                if ($i == $_POST['cnt-addr'])
                    $to =& $pfs;
                if ('' === ($val = trim($_POST['val'][$i])) && '' === trim($key))
                    continue;
                $to[] = [$_POST['kname'][$i], trim($key), $_POST['vname'][$i], $val, (int)$_POST['chk'][$i]];
            }
        }
        $method = isset($_POST['method']) ? $_POST['method'] : [];
        foreach($method as &$v)
            $v = (int)$v;
        $flag = isset($_POST['flag']) ? array_sum($_POST['flag']) : 0;
        return [$flag, $method, $addr, $pfs];
    }

    static function compile($class, $func) {
        $me = Gate::instance();
        json([
            'code' => $me->view_code(Util::post_data($me), $class, $func),
            'url'  => PROTO . '://' . $me->url,
        ]);
    }

    static function atime() { # search ctrl with last access time
        $glob = Plan::_b('mvc/c_*.php');
        if ($fn = Plan::_t('mvc/default_c.php'))
            array_unshift($glob, $fn);
        $max = $c = 0;
        foreach ($glob as $fn) {
            $stat = stat($fn);
            if ($max < $stat['atime']) {
                $max = $stat['atime'];
                $c = basename($fn, '.php');
            }
        }
        return $c;
    }

    static function save($class, $func = false, $ary = false) {
        $cfg =& SKY::$plans['main']['ctrl'];
        if ('main' != ($cfg[substr($class, 2)] ?? 'main'))
            Plan::$gate = $cfg[substr($class, 2)];
        $sky_gate = Gate::load_array();
        if (!$func) { # delete controller
            unset($sky_gate[$class]);
        } elseif (!$ary) { # delete action
            unset($sky_gate[$class][$func]);
        } else { # update, add
            $sky_gate[$class][$func] = true === $ary ? Util::post_data(Gate::instance()) : $ary;
        }
        Plan::gate_dq($class . '.php'); # clear cache
        Plan::_p([Plan::$gate, 'gate.php'], Plan::auto($sky_gate));
    }

    static function cshow() {
        Gate::$cshow = SKY::d('sg_cshow');
        if (isset($_POST['s']))
            SKY::d('sg_cshow', Gate::$cshow = $_POST['s']);
        return Gate::$cshow;
    }

    static function gate($class, $func = null, $is_edit = true) {
        $cfg =& SKY::$plans['main']['ctrl'];
        Plan::$gate = $cfg[substr($class, 2)] ?? 'main';
        $ary = Gate::load_array($class);
        $me = Gate::instance();
        $src = Plan::_t([Plan::$gate, $fn = "mvc/$class.php"]) ? $me->parse($fn) : [];
        if ($diff = array_diff_key($ary, $src))
            $src = $diff + $src;
        if ($has_func = is_string($func)) {
            $me->argc(is_array($src[$func]) ? '' : $src[$func]);
            $src = [$func => $src[$func]];
        }
        $edit = $has_func && $is_edit;
        $return = [
            'row_c' => function($row = false) use (&$src, $ary, $me, $class, $edit) {
                if ($row && $row->__i && !next($src) || !$src)
                    return false;
                $name = key($src);
                $delete = is_array($pars = current($src));
                if (Gate::$cshow && !$delete)
                    $me->argc($pars);
                $is_j = in_array($name, ['empty_j', 'default_j']) || 'j_' == substr($name, 0, 2);
                $ary = isset($ary[$name]) ? $ary[$name] : [];
                list ($flag, $meth, $addr, $pfs) = $ary + [0, [], [], []];
                $vars = [
                    'func' => $name,
                    'delete' => $delete,
                    'pars' => $delete ? '' : $pars,
                    'code' => $edit || Gate::$cshow ? $me->view_code($ary, $class, $name) : false,
                    'error' => $delete ? 'Function not found' : '',
                    'url' => $me->url,
                ];
                if (Gate::$cshow)
                    return $vars;
                return $vars + [
                    'c1' => Util::view_c1($flag, $edit, $meth, $is_j),
                    'c2' => Util::view_c23($flag, $edit, $addr, 1),
                    'c3' => Util::view_c23($flag, $edit, $pfs, 0),
                    'prod' => SKY::d('sg_prod') ? ' checked' : '',
                ];
            },
        ];
        return $has_func ? ['row' => (object)($return['row_c']())] : $return;
    }

    static function view_c1($flag, $edit, $meth, $is_j) {
        global $sky;

        $flags = [
            Gate::HAS_T3 => 'Address has semantic part',
            Gate::RAW_INPUT => 'Use raw body input',
            Gate::AUTHORIZED => 'User must be authorized',
            Gate::OBJ_ADDR => 'Return address as object',
            Gate::OBJ_PFS => 'Return postfields as object',
        ];
        $out = '';
        if ($is_j)
            $meth = [0];
        $skip = !$edit || $is_j;
        foreach ($sky->methods as $k => $v) {
            $ok = in_array($k, $meth);
            if ($skip && !$ok)
                continue;
            $input = sprintf('<input type="checkbox" name="method[]" value="%d"%s/>', $k, $ok ? ' checked' : '');
            $col = $k ? (1 == $k ? '#0f0' : '#aaf') : 'pink';
            $attr = sprintf('cx="%s" style="background:%s"', $col, $ok ? $col : '#ddd');
            $out .= ($out ? ' ' : '') . tag($skip ? $v : "$input$v", $attr, 'label');
        }
        $out .= sprintf('<div style="width:100%%%s">', $edit ? '' : ';min-height:50px');
        foreach ($flags as $k => $v) {
            if ($is_j && $k & Gate::HAS_T3)
                continue;
            $ok = (bool)($flag & $k);
            $input = sprintf('<input type="checkbox" name="flag[]" value="%d"%s%s/>', $k, $ok ? ' checked' : '', $edit ? '' : ' disabled');
            $attr = sprintf('style="color:%s%s"', $ok ? '#111' : '#777', $ok ? ';font-weight:bold' : '');
            $chk = tag("$input$v", $attr, 'label');
            if ($ok || $edit)
                $out .= "$chk<br>";
        }
        $out .= '</div>';
        return $out;
    }

    static function ary_c23($is_addr = 1, $v = []) {
        $v += ['', '', '', '', 0];
        return [
            'kname' => $v[0],
            'key' => $v[1],
            'vname' => $v[2],
            'val' => $v[3],
            'isaddr' => $is_addr,
            'chk' => $v[4],
        ];
    }

    static function view_c23($flag, $edit, $ary, $is_addr) {
        $out = '';
        if ($edit) {
            foreach ($ary as $v) {
                trace($v, 'x0');
                $out .= view('c23_2edit', Util::ary_c23($is_addr, $v));
            }
            if ($is_addr)
                $out .= hidden('cnt-addr', count($ary));
            $out .= a('add parameter', 'javascript:;', 'onclick="sky.g.tpl(this,' . $is_addr . ')"');
        } else {
            foreach ($ary as $v) {
                trace($v, 'x1');
                $v += ['', '', '', '', 0];
                $re_val = !preg_match("/^\w*$/", $v[3]);
                $val = $re_val ? "/^$v[3]$/" . ($v[2] ? " ($v[2])" : '') : $v[3];
                $re_key = !preg_match("/^\w*$/", $v[1]);
                $key = $re_key ? "/^$v[1]$/" . ($v[0] ? " ($v[0])" : '') : $v[1];
                $out .= view('c23_view', [
                    'data' => "$key => $val",
                    'isaddr' => $is_addr,
                    'ns' => $v[4] ? 'ns&nbsp;' : '',
                ]);
            }
        }
        return $out;
    }

    static function ctrl() {
        return array_filter(SKY::$plans['main']['ctrl'], function ($v) {
            return $v != 'main';
        });
    }

}
