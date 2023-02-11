<?php

class standard_c extends Controller
{
    private $_c = '';
    private $_a = '';
    private $_y = [];

    function head_y($action) {
        global $sky, $user;

        if (in_array($action, ['a_crash', 'a_etc']))
            return;
       $sky->eview = 'dev';
        if ('a_' == substr($action, 0, 2)) {
            $this->_y = ['page' => substr($action, 2)];
            MVC::$layout = '__dev.layout';
        }
        if (in_array($action, ['a_trace', 'j_trace', 'j_file', 'j_init'])) {
            $sky->open();
            return $user = common_c::user_h();
        }
        if (!DEV) {
            $this->_y = MVC::$layout = '';
            return 404;
        }
        in_array($action, ['a_img']) ? (SKY::$debug = 0) : $sky->open();//, 'a_svg'
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
                    '_inst' => 'Compile Project',
                    '_glob?' . ($sky->d_gr_start ? 'report' : 'dirs') => 'Globals Report',
                    '_vend?list' => 'Browse All Vendors',
                ],
                'wares' => $this->dev->wares_menu(),
            ];
        }
    }

    function a_crash() {
        global $sky;
        $sky->open();
        SKY::$debug = $this->_static = 0;
        $tracing = '';
        http_response_code($this->_1 ?: 404);
        if (DEV) {
            $x = (int)SKY::d('tracing_toggle');
            $x or $tracing = pre(sqlf('+select tmemo from $_memory where id=1'));
            SKY::d('tracing_toggle', 1 - $x);
        }
        return [
            'redirect' => '',
            'no' => $this->_1 ?: 404,
            'tracing' => $tracing,
        ];
    }

    function a_trace() {
        return $this->j_trace();
    }

    function j_trace() {
        if (!$id = array_search($this->_1, [2 => 0, 1 => 1, 15 => 2, 16 => 3]))
            return 404;
        global $sky, $user;
        if (!$user->root && !DEBUG)
            return 404;
        SKY::$debug = 0;
        if (2 != $id)
            $body = '<h1>Tracing</h1>' . tag(sqlf('+select tmemo from $_memory where id=%d', $id), 'class="trace"', 'pre');
        echo $body ?? 'err';
    }

    function j_file() {
        global $sky, $user;
        if (!$user->root && !DEV)
            return 404;
        SKY::$debug = 0;
        list($file, $line) = explode('^', $_POST['name']);
        $txt = is_file($file) ? file_get_contents($file) : 'is_file() failed';
        echo Display::php($txt, str_pad('', $line - 1, '=') . ('true' == $_POST['c'] ? '-' : '+'));
        throw new Stop;
    }

    function a_etc() {
        global $sky;
        $ext = '';

        if ($pos = strrpos($sky->_1, '.'))
            $ext = substr($sky->_1, $pos + 1);
        if (DEV && in_array($ext, ['js', 'css'])) {
            if (SKY::d('etc'))
                $sky->open(); # save tracing on DEV only now
            is_file($fn = DIR_S . "/assets/$sky->_1")
                or $fn = Plan::_t([$this->d_last_ware, "assets/$sky->_1"]);
        } else {//2do: use Plans (to get var) to save optionally user log on Prod
            $fn = WWW . "m/etc/$sky->_1";
        }
        if (is_file($fn)) {
            switch ($ext) {
                case 'txt': MVC::mime('text/plain; charset=' . ENC); break;
                case 'css': MVC::mime('text/css'); break;
                case 'xml': MVC::mime('application/xml'); break;
                case 'js':  MVC::mime('application/javascript'); break;
                //case 'ico': MVC::mime('image/x-icon'); break;
            }
            MVC::last_modified(filemtime($fn), false, function() use ($sky) {
                $sky->log('etc', "304 $sky->_1");
            });
            header('Content-Length: ' . filesize($fn));
            $sky->log('etc', "200 $sky->_1");
            while (@ob_end_flush());
            readfile($fn);
            throw new Stop;
        }
        $sky->log('etc', "404 $sky->_1");
        return 404;
    }

    function j_init() {
        global $sky, $user;
        if (isset($_POST['unload'])) {
            if ($user->id && !$user->v_mem)
                $user->logout();
        } elseif (isset($_POST['tz']) && isset($_POST['scr'])) {
            $user->v_tz = floatval($_POST['tz']);
            preg_match("/^(\d{3,4})x\d{3,4}$/", $_POST['scr'], $m);
            $user->v_scr = $m ? $m[0] : '768x1024';
            if ($mobi = $user->v_mobi === '' && ($m && $m[1] < 1000 || $sky->orientation))
                $user->v_mobi = $user->v_mobd = $m[1] < 700 ? 1 : 2;
  //          if ($user->js_unlock || $mobi)  2do: check
   //             echo 'main';
        }
        return true;
    }

    #-----------------------------
    # functions below for DEV only
    #-----------------------------

    function j_drop() {
        echo Admin::drop_all_cache() ? 'OK' : 'Error';
    }

     /* ====================================== */


    function __call($func, $args) {
        $x = explode('_', $func, 2);
        $name = $x[1] ?? '';
        $x = $x[0];
        if (isset(SKY::$plans[$name])) {
            trace($name, 'WARE');
            $this->eview = $this->last_ware = $this->d_last_ware = Plan::$ware = Plan::$view = $name;
            //Plan::_r([$name, 'mvc/' . ($class = $name . '_c') . '.php']);
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
        } elseif (DEV && 'a' == $x) {
            return call_user_func([$this, "j_$name"], 'c');
        }
        return parent::__call($func, $args);
    }

    function a_img() {/////////////////
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

    function j_gate($x = 'j') {
        if ('c_' == $this->_c && 'c' == $x)
            $this->_c = DEV::atime(); # open last access time controller
        $ary = !$this->_4 ? [] : [
            'y_tx' => $this->d_dev ? 1 : 2, # sample: ?_gate=company&func=j_edit&ajax
            'trace_x' => tag(sqlf('+select tmemo from $_memory where id=1'), 'id="trace"', 'pre'),
        ];
        return [
            'y_1' => $this->_c ? ('default_c' == $this->_c ? '*' : substr($this->_c, 2)) : '',
            'h1' => $this->_c,
            'list' => Gate::controllers(true) + DEV::ctrl(),
            'cshow' => DEV::cshow() ? ' checked' : '',
            'e_func' => $this->_c ? DEV::gate($this->_c) : false,
            'func' => $this->_3 ?? '',
        ] + $ary;
    }

    function j_delete() {
        DEV::save($this->_c, $this->_a);
        $this->_a or $this->_c = 'c_';
        return $this->j_gate();
    }

    function j_edit() {
        return DEV::gate($this->_c, $this->_a);
    }

    function j_save() {
        DEV::save($this->_c, $this->_a, true);
        return DEV::gate($this->_c, $this->_a, false);
    }

    function j_code() {
        DEV::compile($this->_c, $this->_a);
    }

    function x_c23_edit() {
        return DEV::ary_c23();
    }

    function x_databases() {
        $list = ['main' => 0] + SKY::$databases;
        unset($list['driver'], $list['pref'], $list['dsn'], $list['']);
        return ['databases' => array_keys($list), 'is_main' => 'main' == Plan::$ware];
    }

    # ---------------- j_ + a_, see self::__call(..)

    function j_dev($x = 'j') {
        MVC::body('_dev.' . ($page = $this->_1 ?: 'main'));
        return (array)$this->dev->{"{$x}_$page"}($this->_2);
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
        return call_user_func([new Globals, $this->_c], $this->_a);
    }

    function j_inst() {
        return Install::run($this->_1);
    }
}
