<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class standard_c extends Controller
{
    private $_c = '';
    private $_a = '';
    private $_y = [];

    function head_y($action) {
        $head2 = [
            'a_trace',
            'j_file',
//            'a_exception',
            'j_init',
            'j_crop_code',
            'j_crop',
            'j_file_tmp',
            'j_file_delete',
        ];
        if ('a_etc' == $action)
            return;
        if ('a_' == substr($action, 0, 2)) {
            $this->_y = ['page' => substr($action, 2)];
            MVC::$layout = '__dev.layout';
        }
        if (in_array($action, $head2))
            return $this->head_y2(3);
        if (!DEV)
            return 404;
        $this->load();
        $v = explode('.', $this->_1, 2);
        $this->_c = '*' == $v[0] ? 'default_c' : "c_$v[0]";
        $this->_a = isset($v[1]) ? $v[1] : '';
        return ['y_1' => $v[0]];
    }

    function load() {
        global $sky;
        if ($sky->d_dev) {
            $sky->debug = 0;
            $sky->s_prod_error = 1;
        }
    }

    function tail_y() {
        global $sky;
        if ($this->_y) {
            if (!$_POST && '_trace' != $sky->_0)
                $sky->d_last_page = URI;
            #if ('WINNT' == PHP_OS)
            #    $ary += ['adm?get_dev' => 'Open DEV.SKY.'];
            $sky->k_static = [[], ["~/dev.js"], ["~/dev.css"]];
            defined('LINK') or define('LINK', PROTO . '://' . DOMAIN . PATH);
            return $this->_y + [
                'tx' => '_trace' == $sky->_0 ? URI : '_x0',
                'tasks' => [
                    '_dev' => 'Development',
                    '_gate' => 'Open SkyGate',
                    '_lang?list' => 'Open SkyLang',
                    '_inst' => 'Compile Project',
                    '_glob?' . ($sky->s_gr_start ? 'report' : 'dirs') => 'Globals report',
                    '_php' => 'Visual PHP',
                    '_visual' => 'Visual HTML',
                    '_sandbox' => 'Sandbox',
                ],
            ];
        }
    }

    private function head_y2($x) {
        global $sky, $user;
        $user = new USER;
    }

    function a_trace($id) {
        global $sky, $user;
        if (!$user->root && !DEBUG)
            return 404;
        $this->load();
        $sky->debug = false;
        $this->_y = ['page' => 2 == $id ? 'trace-t' : 'trace-x'];
        if (2 != $id)
            $body = '<h1>Tracing</h1>' . tag(sqlf('+select tmemo from $_memory where id=%d', $id), 'id="trace"', 'pre');
        echo $body ?? 'err';
    }

    function j_file() {
        global $sky, $user;
        if (!$user->root && !DEV)
            return 404;
        $sky->debug = false;
        list($file, $line) = explode('^', $_POST['name']);
        $txt = is_file($file) ? file_get_contents($file) : 'is_file() failed';
        echo Display::php($txt, str_pad('', $line - 1, '=') . ('true' == $_POST['c'] ? '-' : '+'));
        throw new Stop;
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

    function a_etc() {
        global $sky;
        $_1 = $sky->_1;
        $ext = '';
        if ($pos = strrpos($_1, '.'))
            $ext = substr($_1, $pos + 1);
        $fn = DEV && in_array($ext, ['js', 'css']) ? DIR_S . "/assets/$_1" : WWW . "pub/etc/$_1";
        if (is_file($fn)) {
            switch ($ext) {
                case 'txt': header('Content-Type: text/plain; charset=' . ENC); break;
                case 'css': header('Content-Type: text/css'); break;
                case 'xml': header('Content-Type: application/xml'); break;
                case 'js': header('Content-Type: application/javascript'); break;
            }
            MVC::last_modified(filemtime($fn), false, function() use($sky, $_1) {
                $sky->log('etc', "304 $_1");
            });
            header('Content-Length: ' . filesize($fn));
            $sky->log('etc', "200 $_1");
            while (@ob_end_flush());
            readfile($fn);
            throw new Stop;
        }
        $sky->log('etc', "404 $_1");
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

    function j_crop_code() {
        $sizes = is_file('main/app/t_file.php') ? $this->t_file->img_sizes() : ['100 x 100'];
        return ['opt' => option(0, array_combine($sizes, $sizes))];
    }

    function j_crop() {
        File_t::crop($_POST['id'], $_POST['x0'], $_POST['y0'], $_POST['x1'], $_POST['y1'], $_POST['szx'], $_POST['szy']);
        return true;
    }

    function j_file_tmp() {
        File_t::tmp();
    }

    function j_file_delete() {
        File_t::delete_one();
    }

    # functions below for DEV only

    #function a_get_dev() {
    #    is_file('dev.php') or file_put_contents('dev.php', file_get_contents('http://coresky.net/download?dev.php'));
    #    jump(WWW ? '../dev.php' : 'dev.php');
    #}

    function j_drop() {
        echo Admin::drop_all_cache() ? 'Drop all cache: OK' : 'Error when drop cache';
    }

    function a_gate() { /* ====================================== */
        if ('c_' == $this->_c)
            $this->_c = DEV::atime(); # open last access time controller
        return $this->j_gate();
    }

    function j_gate() {
        $list = Gate::controllers($this->_c);
        return (!$this->_4 ? [] : [
            'y_tx' => '_x1',
            'err_ajax' => tag(sqlf('+select tmemo from $_memory where id=1'), 'id="trace"', 'pre'),
        ]) + [
            'y_1' => $this->_c ? ('default_c' == $this->_c ? '*' : substr($this->_c, 2)) : '',
            'h1' => $this->_c,
            'virtuals' => $this->_c ? array_shift($list) : '',
            'list' => $list,
            'cshow' => DEV::cshow() ? ' checked' : '',
            'e_func' => $this->_c ? DEV::gate($this->_c) : false,
            'func' => $this->_3 ?? '',
            'vaults' => [strtoupper(DIR_M) . '.SKY.', 'Earth', 'Mercury', 'Venus'],
        ];
    }

    function j_virt() {
        DEV::save($this->_c, preg_split("/\s+/", trim($_POST['v'])));
        return $this->j_gate();
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

    function a_lang() { /* ====================================== */
        return $this->j_lang();
    }

    function j_lang() {
        MVC::body('_lng.' . substr($this->_c, 2));
        return call_user_func([new Language, $this->_c], $this->_a);
    }

    function a_glob() { /* ====================================== */
        return $this->j_glob();
    }

    function j_glob() {
        MVC::body('_glb.' . substr($this->_c, 2));
        return call_user_func([new Globals, $this->_c], $this->_a);
    }

    function a_inst() { /* ====================================== */
        return $this->j_inst();
    }

    function j_inst() {
        return Install::run($this->_1);
    }

    function a_visual() { /* ====================================== */
        $this->_y = [];
        return Venus::layout();
    }

    function j_visual() {
        MVC::body('_ven.' . substr($this->_c, 2));
        return call_user_func([new Venus, $this->_c], $this->_a);
    }

    function a_dev() {
        MVC::body('_dev.' . ($page = $this->_1 ? $this->_1 : 'overview'));
        return (array)$this->dev->{"c_$page"}($this->_2);
    }

    function a_api() { # lang auto translations
        $this->_y = [];
        # 2do
    }
}
