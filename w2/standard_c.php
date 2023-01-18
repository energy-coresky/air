<?php

class standard_c extends Controller
{
    private $_c = '';
    private $_a = '';
    private $_y = [];

    function head_y($action) {
        global $sky, $user;

        if ('a_etc' == $action)
            return;
        if ('a_' == substr($action, 0, 2)) {
            $this->_y = ['page' => substr($action, 2)];
            MVC::$layout = '__dev.layout';
        }
        if (in_array($action, ['a_trace', 'j_trace', 'j_file', 'j_init']))
            return $user = new USER;
        if (!DEV) {
            $this->_y = MVC::$layout = '';
            return 404;
        }
        if ($sky->d_dev) {
            $sky->debug = 0;
            $sky->s_prod_error = 1;
        }
        $v = explode('.', $this->_1, 2);
        $this->_c = '*' == $v[0] ? 'default_c' : "c_$v[0]";
        $this->_a = $v[1] ?? '';
        return ['y_1' => $v[0]];
    }

    function tail_y() {
        global $sky;

        if ($this->_y) {
            if (1 == $sky->method && '_trace' != $sky->_0)
                $sky->d_last_page = URI;
            $sky->k_static = [[], ["~/m/dev.js"], ["~/m/dev.css"]];
            defined('LINK') or define('LINK', PROTO . '://' . DOMAIN . PORT . PATH);
            return $this->_y + [
                'tx' => '_trace' == $sky->_0 ? $sky->_1 : 0,
                'ware_dir' => '',
                'tasks' => [
                    '_dev?main=0' => 'Main',
                    '_gate' => 'Open SkyGate',
                    '_lang?list' => 'Open SkyLang',
                    '_inst' => 'Compile Project',
                    '_glob?' . ($sky->s_gr_start ? 'report' : 'dirs') => 'Globals Report',
                    '_vend?list' => 'Browse All Vendors',
                ],
                'wares' => $this->dev->wares_menu(),
            ];
        }
    }

    function a_exception() {
        global $sky;

        $no = $sky->_1 or $no = $sky->error_no;
        $sky->error_no < 10000 or $no = 11;
        $no or jump();
        $sky->k_static = [[], [], []];
        MVC::$layout = $this->_y = '';
        return [
            'ky' => $sky->error_no,
            'no' => $no,
            'tracing' => '',
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
        $sky->debug = 0;
        if (2 != $id)
            $body = '<h1>Tracing</h1>' . tag(sqlf('+select tmemo from $_memory where id=%d', $id), 'id="trace"', 'pre');
        echo $body ?? 'err';
    }

    function j_file() {
        global $sky, $user;
        if (!$user->root && !DEV)
            return 404;
        $sky->debug = 0;
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
            is_file($fn = DIR_S . "/assets/$sky->_1")
                or $fn = Plan::_t([$this->d_last_ware, "assets/$sky->_1"]);
        } else {
            $fn = WWW . "m/etc/$sky->_1";
        }
        if (is_file($fn)) {
            switch ($ext) {
                case 'txt': header('Content-Type: text/plain; charset=' . ENC); break;
                case 'css': header('Content-Type: text/css'); break;
                case 'xml': header('Content-Type: application/xml'); break;
                case 'js': header('Content-Type: application/javascript'); break;
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
            if ($user->js_unlock || $mobi)
                echo 'main';
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
            define('LINK', PROTO . '://' . DOMAIN . PORT . PATH);
            $this->d_last_ware = Plan::$ware = Plan::$view = $name;
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

    function a_svg() {
        header("Content-Type: image/svg+xml;");
        $this->ajax = 3;
        echo new SVG(substr($this->_c, 2), $this->_a);
        throw new Stop;
    }

    function j_gate($x = 'j') {
        if ('c_' == $this->_c && 'c' == $x)
            $this->_c = DEV::atime(); # open last access time controller
        return (!$this->_4 ? [] : [
            'y_tx' => 1,
            'err_ajax' => tag(sqlf('+select tmemo from $_memory where id=1'), 'id="trace"', 'pre'),
        ]) + [
            'y_1' => $this->_c ? ('default_c' == $this->_c ? '*' : substr($this->_c, 2)) : '',
            'h1' => $this->_c,
            'list' => Gate::controllers(true) + DEV::ctrl(),
            'cshow' => DEV::cshow() ? ' checked' : '',
            'e_func' => $this->_c ? DEV::gate($this->_c) : false,
            'func' => $this->_3 ?? '',
        ];
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
