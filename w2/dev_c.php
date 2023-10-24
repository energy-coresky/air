<?php

class dev_c extends Controller
{
    use HOOK_D;

    private $_w = '';
    private $_c = '';
    private $_a = '';

    function head_y($action) {
        global $sky;

        $sky->eview = 'dev';
        if (in_array($action, ['a_crash', 'a_etc']))
            return;
        $sky->fly or MVC::$layout = '__dev.layout';
        $sky->open();
        if ($sky->d_dev)
            SKY::$debug = 0;
        [$this->_w, $this->_c, $this->_a] = explode('.', $this->_1, 3) + ['', '', ''];
        return ['y_1' => $this->_1, 'page' => substr($action, 2)];
    }

    function tail_y() {
        if (!MVC::$layout)
            return;
        if (1 == $this->method && '_trace' != $this->_0 && 'view' != $this->_1)
            $this->d_last_page = URI;
        $this->_static = [[], ["~/m/dev.js"], ["~/m/dev.css"]];
        return [
            'y_tx' => '_trace' == $this->_0 ? $this->_1 : 0,
            'y_ware_dir' => '',
            'y_tasks' => [
                '_dev?main=0' => 'Main',
                '_map' == $this->_0 ? '_map' : '_gate' => 'Open SkyGate',
                '_lang?list' => 'Open SkyLang',
                '_inst' => 'Open SkyProject',
                '_glob?' . ($this->d_gr_start ?: 'settings') => 'Global Reports',
                '_vend?local' => 'Browse All Vendors',
            ],
            'wares' => $this->dev->wares_menu(),
            'ware1' => substr(parse_url($this->d_ware1, PHP_URL_PATH), 1),
            'ware2' => substr(parse_url($this->d_ware2, PHP_URL_PATH), 1),
            'y_act' => function ($uri) {
                return $this->_0 == explode('?', $uri)[0] && !in_array($this->_1, ['view', 'ware']);
            },
        ];
    }

    function __call($func, $args) {
        $x = explode('_', $func, 2);
        $set = isset(SKY::$plans[$name = $x[1] ?? '']);
        $x = $x[0];
        if ($set && 'dev' == SKY::$plans[$name]['app']['type']) {
            trace($name, 'WARE');
            $this->eview = $this->last_ware = $this->d_last_ware = Plan::$ware = Plan::$view = $name;
            if (1 == $this->method) {
                $w1 = parse_url($tmp = $this->d_ware1, PHP_URL_PATH);
                $this->d_ware1 = '_venus' == parse_url(URI, PHP_URL_PATH) ? '_venus?ware' : URI;
                "_$name" == $w1 or $this->d_ware2 = $tmp;
            }
            Plan::_r('mvc/' . ($ctrl = $name . '_c') . '.php');
            MVC::$cc = MVC::$mc;
            MVC::$mc = new $ctrl;
            if (!method_exists(MVC::$mc, $action = '' == $this->_1 ? 'empty_' . $x : $x . "_$this->_1"))
                $action = 'default_' . $x;
            if (method_exists(MVC::$mc, 'head_y'))
                MVC::instance()->set(MVC::$mc->head_y($action), true);
            MVC::body("$name." . ($this->_1 ? $this->_1 : 'empty'));
            MVC::$_y += ['ware_dir' => Plan::_obj('path')];
            return MVC::$mc->$action();
        } elseif ('a' == $x) {
            return call_user_func([$this, "j_$name"], 'c');
        }
        return parent::__call($func, $args);
    }

    function j_trace() {
        SKY::$debug = 0;
        echo '<h1>Tracing</h1>';
        if ($this->_1)
            echo pre(sqlf('+select tmemo from $_memory where id=%d', $this->_1), 'class="trace"');
    }

    function j_file() {
        SKY::$debug = 0;
        [$file, $line] = explode('^', $_POST['name']);
        $txt = is_file($file) ? file_get_contents($file) : 'is_file() failed';
        echo Display::php($txt, [$line, 'true' == $_POST['c'], true]);
    }

    function j_drop() {
        echo Admin::drop_all_cache() ? 'OK' : 'Error';
    }

    function a_png() {
        SKY::$debug = 0;
        header('Cache-Control: private, max-age=3600');
        MVC::mime('image/png');
        ob_end_clean();
        $dir = $this->_2 ? "$this->_2/assets" : DIR_S . "/etc/img";
        readfile("$dir/$this->_1.png");
        throw new Stop;
    }

    function a_img() {
        SKY::$debug = 0;
        list(,$s) = explode("#.$this->_1", file_get_contents(DIR_S . '/w2/__img.jet'), 3);
        [$type, $data] = explode(",", trim($s), 2);
        header('Cache-Control: private, max-age=3600');
        MVC::mime(substr($type, 5, strlen($type) - 12));
        ob_end_clean();
        echo base64_decode($data);
        throw new Stop;
    }

    function a_svg() {
        MVC::mime('image/svg+xml');
        echo new SVG($this->_w, $this->_c);
        throw new Stop;
    }

    function x_databases() {
        $list = ['main' => 0] + SKY::$databases;
        unset($list['driver'], $list['pref'], $list['dsn'], $list['']);
        return ['databases' => array_keys($list), 'is_merc' => 'mercury' == Plan::$ware];
    }

    # ---------------- j_ + a_, see self::__call(..)

    function j_dev($x = 'j') {
        MVC::body('_dev.' . ($page = $this->_1 ?: 'main'));
        $r = $this->dev->{"{$x}_$page"}($this->_2);
        return is_int($r) ? $r : (array)$r;
    }

    function j_lang() {
        MVC::body('_lng.' . $this->_w);
        return call_user_func([new Language, 'c_' . $this->_w], $this->_c);
    }

    function j_vend() {
        MVC::body('_vend.' . $this->_w);
        return call_user_func([new Vendor, 'c_' . $this->_w], $this->_c);
    }

    function j_glob() {
        MVC::body('_glob.' . $this->_w);
        return call_user_func([new Globals, 'c_' . $this->_w], $this->_4 ?: $this->_3);
    }

    function j_inst() {
        return Install::run($this->_1);
    }

    function j_gate($x = 'j') {
        if (!$this->_c && 'c' == $x)
            $this->_w = self::atime($this->_c); # open last access time controller
        $ary = !$this->_4 ? [] : [
            'y_tx' => $this->d_dev ? 1 : 2, # sample: ?_gate=main.c_company&func=j_edit&ajax
            'trace_x' => pre(sqlf('+select tmemo from $_memory where id=1'), 'id="trace"'),
        ];
        return [
            'wc' => "$this->_w.$this->_c",
            'ctrl' => Debug::controllers(),
            'cshow' => self::cshow() ? ' checked' : '',
            'e_func' => self::gate($this->_w, $this->_c),
            'act' => $this->_3 ?? '',
        ] + $ary;
    }

    function j_delete() {
        self::save($this->_w, $this->_c, $this->_a);
        $this->_a or $this->_c = '';
        return $this->j_gate();
    }

    function j_edit() {
        return self::gate($this->_w, $this->_c, $this->_a);
    }

    function j_save() {
        self::save($this->_w, $this->_c, $this->_a, true);
        return self::gate($this->_w, $this->_c, $this->_a, false);
    }

    function j_code() {
        $gate = Gate::instance();
        json([
            'code' => $gate->highlight(self::post_data(), $this->_c, $this->_a, $_POST['argc']),
            'url'  => $gate->uri,
        ]);
    }

    function x_c23_edit() {
        return self::ary_c23();
    }

    ///////////////////////////////////// GATE UTILITY /////////////////////////////////////
    static function ary_c23($is_addr = 1, $v = []) {
        return [
            'kname' => $v[0] ?? '',
            'key' => $v[1] ?? '',
            'vname' => $v[2] ?? '',
            'val' => $v[3] ?? '',
            'isaddr' => $is_addr,
            'chk' => $v[4] ?? 0,
        ];
    }

    static function post_data() {
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
        $method = $_POST['method'] ?? [];
        foreach($method as &$v)
            $v = (int)$v;
        $flag = isset($_POST['flag']) ? array_sum($_POST['flag']) : 0;
        return [$flag, $method, $addr, $pfs];
    }

    static function atime(&$c) { # search ctrl with last access time
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
        return 'main';
    }

    static function save($ware, $ctrl, $act = false, $in = false) {
        $ary = Plan::_rq([$ware, 'gate.php']);
        if (!$act) { # delete controller
            unset($ary[$ctrl]);
        } elseif (!$in) { # delete action
            unset($ary[$ctrl][$act]);
        } else { # update, add
            $ary[$ctrl][$act] = true === $in ? self::post_data() : $in;
        }
        Plan::gate_dq([$ware, "$ware-$ctrl.php"]); # drop cache file
        Plan::_p([$ware, 'gate.php'], Plan::auto($ary));
    }

    static function cshow() {
        Gate::$cshow = SKY::d('sg_cshow');
        if (isset($_POST['s']))
            SKY::d('sg_cshow', Gate::$cshow = $_POST['s']);
        return Gate::$cshow;
    }

    static function gate($ware, $ctrl, $act = null, $is_edit = true) {
        $ary = Plan::_rq([$ware, 'gate.php'])[$ctrl] ?? [];
        $gate = Gate::instance();
        $src = Plan::_t([$ware, $fn = "mvc/$ctrl.php"]) ? $gate->parse($ware, $fn) : [];
        if ($diff = array_diff_key($ary, $src))
            $src = array_map('is_array', $diff) + $src;
        if ($has_act = is_string($act))
            $src = [$act => $src[$act]];
        $edit = $has_act && $is_edit;
        $row_c = function($row = false) use (&$src, $ary, $gate, $ctrl, $edit) {
            if ($row && $row->__i && false === next($src) || !$src)
                return false;
            $ary = $ary[$act = key($src)] ?? [];
            [$flag, $meth, $addr, $pfs] = $ary + [0, [], [], []];
            if ($is_j = in_array($act, ['empty_j', 'default_j']) || 'j_' == substr($act, 0, 2))
                $meth = [0];//2del
            $vars = [
                'act' => $act,
                'args' => $args = $src[$act],
                'delete' => $delete = true === $args,
                'code' => $edit || Gate::$cshow ? $gate->highlight($ary, $ctrl, $act, $delete ? 0 : count($args)) : false,
                'gerr' => $gate->gerr,
                'uri' => $gate->uri,
                'var' => $gate->var,
                'meth' => $meth,
            ];
            return Gate::$cshow ? $vars : $vars + [
                'c1' => self::view_c1($flag, $edit, $meth, $is_j),
                'c2' => self::view_c23($flag, $edit, $addr, 1),
                'c3' => self::view_c23($flag, $edit, $pfs, 0),
            ];
        };
        return !$has_act ? $row_c : [
            'row' => (object)$row_c(),
            'wc' => "$ware.$ctrl",
        ];
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

    static function view_c23($flag, $edit, $ary, $is_addr) {
        $out = '';
        if ($edit) {
            foreach ($ary as $v) {
                trace($v, 'x0');
                $out .= view('c23_2edit', self::ary_c23($is_addr, $v));
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

    ///////////////////////////////////// THE MAP /////////////////////////////////////
    function j_test() {
        Rewrite::get($lib, $map, $keys);
        $code = -1 == $_POST['lib'] ? false : $lib[$_POST['lib']][1];
        echo '/' . Rewrite::test($_POST['uri'], $code);
        Rewrite::vars();
    }

    function j_map() {
        $y_1 = (int)$this->_1;
        Rewrite::get($lib, $map, $keys);
        Rewrite::vars();
        $rshow = SKY::d('sg_rshow');
        if (isset($_POST['s'])) {
            SKY::d('sg_rshow', $rshow = $_POST['s']);
        } elseif ($_POST) {
            $err = !Rewrite::put($y_1);
        }
        $data = array_combine(['n', '_0', 'x', 'u'], $map[$y_1]);
        array_walk($lib, 'Rewrite::highlight');
        array_walk($map, 'Rewrite::highlight');

        $vars = [
            'err' => $err ?? false,
            'rshow' => $rshow,
            'map' => $map,
            'y_1' => $y_1,
            'ctrl' => Debug::controllers(),
            'opt' => option(-2, array_reverse($keys, true)),
            'json' => tag(html(json_encode($lib)), 'id="json" style="display:none"'),
            'form' => Form::A($data, [
                'mode' => "save $y_1 ",
                'php' => '',
                'n' => ['Name', '', 'size="25"'],
                'x' => ['DEV only', 'chk'],
                'u' => ['Test URI', '', 'size="25"'],
                ["Save R$y_1", 'button', 'onclick="sky.g.rw()" style="margin-top:5px"'],
            ]),
        ];
        return $rshow ? $vars : $vars + [
            'e_func' => [
                'row_c' => function ($in, $e = false) {
                    static $ary;
                    if ($e) {
                        Gate::$cshow = true;
                        $ary = (new eVar(self::gate($in[0], $in[1])))->all();
                        Rewrite::external($ary, $in[1]);
                        return false;
                    }
                    return $ary ? array_shift($ary) : false;
                },
                'str_c' => function ($e) {
                    Rewrite::vars();
                    return 1 + $e->key();
                },
            ],
        ];
    }
}
