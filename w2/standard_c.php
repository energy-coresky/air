<?php

class standard_c extends Controller
{
    use HOOK_D;

    private $_c = '';
    private $_a = '';
    private $_y = [];

    function head_y($action) {
        global $sky;

        $sky->eview = 'dev';
        if (in_array($action, ['a_crash', 'a_etc']))
            return;
        if ('a_' == substr($action, 0, 2)) {
            $this->_y = ['page' => substr($action, 2)];
            MVC::$layout = '__dev.layout';
        }
        $sky->open();//, 'a_svg'
        if ($sky->d_dev)
            SKY::$debug = 0;
        $v = explode('.', $this->_1, 2);
        $this->_c = '*' == $v[0] ? 'default_c' : "c_$v[0]";
        $this->_a = $v[1] ?? '';
        return ['y_1' => $v[0]];
    }

    function tail_y() {
        global $sky;

        if ($this->_y) {
            if (1 == $sky->method && '_trace' != $sky->_0 && 'view' != $sky->_1)
                $sky->d_last_page = URI;
            $sky->_static = [[], ["~/m/dev.js"], ["~/m/dev.css"]];
            return $this->_y + [
                'tx' => '_trace' == $sky->_0 ? $sky->_1 : 0,
                'ware_dir' => '',
                'tasks' => [
                    '_dev?main=0' => 'Main',
                    '_gate' => 'Open SkyGate',
                    '_lang?list' => 'Open SkyLang',
                    '_inst' => 'Open SkyProject',
                    '_glob?' . ('settings') => 'Global Reports',//$sky->d_gr_start ?: 
                    '_vend?list' => 'Browse All Vendors',
                ],
                'wares' => $this->dev->wares_menu(),
                'ware1' => substr(parse_url($sky->d_ware1, PHP_URL_PATH), 1),
                'ware2' => substr(parse_url($sky->d_ware2, PHP_URL_PATH), 1),
            ];
        }
    }

    function __call($func, $args) {
        $x = explode('_', $func, 2);
        $name = $x[1] ?? '';
        $x = $x[0];
        if (isset(SKY::$plans[$name])) { // and type=DEV
            trace($name, 'WARE');
            $this->eview = $this->last_ware = $this->d_last_ware = Plan::$ware = Plan::$view = $name;
            if (1 == $this->method) {
                $w1 = parse_url($tmp = $this->d_ware1, PHP_URL_PATH);
                $this->d_ware1 = URI;
                if ("_$name" != $w1)
                    $this->d_ware2 = $tmp;
            }
            Plan::_r('mvc/' . ($class = $name . '_c') . '.php');
            MVC::$cc = MVC::$mc;
            MVC::$mc = new $class;
            if (!method_exists(MVC::$mc, $action = '' == $this->_1 ? 'empty_' . $x : $x . "_$this->_1"))
                $action = 'default_' . $x;
            if (method_exists(MVC::$mc, 'head_y'))
                MVC::instance()->set(MVC::$mc->head_y($action), true);
            MVC::body("$name." . ($this->_1 ? $this->_1 : 'empty'));
            if ($this->_y)
                $this->_y += ['ware_dir' => Plan::_obj(0)->path];
            return MVC::$mc->$action();
        } elseif ('a' == $x) {
            return call_user_func([$this, "j_$name"], 'c');
        }
        return parent::__call($func, $args);
    }

    function j_trace() {
        $this->a_trace();
        throw new Stop;
    }

    function a_trace() {
        SKY::$debug = 0;
        echo '<h1>Tracing</h1>';
        if ($this->_1)
            echo pre(sqlf('+select tmemo from $_memory where id=%d', $this->_1), 'class="trace"');
    }

    function j_file() {
        SKY::$debug = 0;
        list($file, $line) = explode('^', $_POST['name']);
        $txt = is_file($file) ? file_get_contents($file) : 'is_file() failed';
        echo Display::php($txt, str_pad('', $line - 1, '=') . ('true' == $_POST['c'] ? '-' : '+'));
        throw new Stop;
    }

    function j_drop() {
        echo Admin::drop_all_cache() ? 'OK' : 'Error';
    }

    function a_img() {/////////////////
        SKY::$debug = 0;
        list(,$s) = explode("#.$this->_1", file_get_contents(DIR_S . '/w2/__img.jet'), 3);
        list($type, $data) = explode(",", trim($s), 2);
        header('Cache-Control: private, max-age=3600');
        MVC::mime(substr($type, 5, strlen($type) - 12));
        ob_end_clean();
        echo base64_decode($data);
        throw new Stop;
    }

    function a_svg() {
        MVC::mime('image/svg+xml');
        echo new SVG(substr($this->_c, 2), $this->_a);
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
        MVC::body('_lng.' . substr($this->_c, 2));
        return call_user_func([new Language, $this->_c], $this->_a);
    }

    function j_vend() {
        MVC::body('_vend.' . substr($this->_c, 2));
        return call_user_func([new Vendor, $this->_c], $this->_a);
    }

    function j_glob() {
        MVC::body('_glob.' . substr($this->_c, 2));
        return call_user_func([new Globals, $this->_c], $this->_3 ?: $this->_4);
    }

    function j_inst() {
        return Install::run($this->_1);
    }

    function j_gate($x = 'j') {
        if ('c_' == $this->_c && 'c' == $x)
            $this->_c = self::atime(); # open last access time controller
        $ary = !$this->_4 ? [] : [
            'y_tx' => $this->d_dev ? 1 : 2, # sample: ?_gate=company&func=j_edit&ajax
            'trace_x' => tag(sqlf('+select tmemo from $_memory where id=1'), 'id="trace"', 'pre'),
        ];
        return [
            'y_1' => $this->_c ? ('default_c' == $this->_c ? '*' : substr($this->_c, 2)) : '',
            'h1' => $this->_c,
            'list' => Gate::controllers(true) + self::ctrl(),
            'cshow' => self::cshow() ? ' checked' : '',
            'e_func' => $this->_c ? self::gate($this->_c) : false,
            'func' => $this->_3 ?? '',
        ] + $ary;
    }

    function j_delete() {
        self::save($this->_c, $this->_a);
        $this->_a or $this->_c = 'c_';
        return $this->j_gate();
    }

    function j_edit() {
        return self::gate($this->_c, $this->_a);
    }

    function j_save() {
        self::save($this->_c, $this->_a, true);
        return self::gate($this->_c, $this->_a, false);
    }

    function j_code() {
        self::compile($this->_c, $this->_a);
    }

    function x_c23_edit() {
        return self::ary_c23();
    }

    ///////////////////////////////////// GATE UTILITY /////////////////////////////////////
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

    static function post_data($gate) {
         isset($_POST['args']) && $gate->argc($_POST['args']);//////////
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
        $gate = Gate::instance();
        json([
            'code' => $gate->view_code(self::post_data($gate), $class, $func),
            'url'  => PROTO . '://' . $gate->url,
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
            $sky_gate[$class][$func] = true === $ary ? self::post_data(Gate::instance()) : $ary;
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
        $gate = Gate::instance();
        $src = Plan::_t([Plan::$gate, $fn = "mvc/$class.php"]) ? $gate->parse($fn) : [];
        if ($diff = array_diff_key($ary, $src))
            $src = $diff + $src;
        if ($has_func = is_string($func)) {
            $gate->argc(is_array($src[$func]) ? '' : $src[$func]);
            $src = [$func => $src[$func]];
        }
        $edit = $has_func && $is_edit;
        $return = [
            'row_c' => function($row = false) use (&$src, $ary, $gate, $class, $edit) {
                if ($row && $row->__i && !next($src) || !$src)
                    return false;
                $name = key($src);
                $delete = is_array($pars = current($src));
                if (Gate::$cshow && !$delete)
                    $gate->argc($pars);
                $is_j = in_array($name, ['empty_j', 'default_j']) || 'j_' == substr($name, 0, 2);
                $ary = isset($ary[$name]) ? $ary[$name] : [];
                list ($flag, $meth, $addr, $pfs) = $ary + [0, [], [], []];
                $vars = [
                    'func' => $name,
                    'delete' => $delete,
                    'pars' => $delete ? '' : $pars,
                    'code' => $edit || Gate::$cshow ? $gate->view_code($ary, $class, $name) : false,
                    'error' => $delete ? 'Function not found' : '',
                    'url' => $gate->url,
                ];
                if (Gate::$cshow)
                    return $vars;
                return $vars + [
                    'c1' => self::view_c1($flag, $edit, $meth, $is_j),
                    'c2' => self::view_c23($flag, $edit, $addr, 1),
                    'c3' => self::view_c23($flag, $edit, $pfs, 0),
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

    static function ctrl() {
        return array_filter(SKY::$plans['main']['ctrl'], function ($v) {
            return $v != 'main';
        });
    }
}
