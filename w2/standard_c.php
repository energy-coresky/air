<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class standard_c extends Controller
{
    private $_c = '';
    private $_a = '';

    function head_y($action) {
        $soft = [
            'a_trace',
            'j_file',
            'a_exception',
            'j_init',
            'j_crop_code',
            'j_crop',
            'j_file_tmp',
            'j_file_delete',
        ];
        if ('a_etc' == $action || DEV && 'a_dev' == $action) # run MVC::$cc !
            return parent::head_y($action);
        if (in_array($action, $soft)) # app's ::head_y() locked !
            return $this->soft(111);
        if (!DEV)
            return 404;
        global $sky;
        $v = explode('.', $sky->_1, 2);
        $this->_c = '*' == $v[0] ? 'default_c' : "c_$v[0]";
        $this->_a = isset($v[1]) ? $v[1] : '';
        return ['y_1' => $v[0]];
    }

    private function soft($xxx) {
        global $sky, $user;
        $user = new USER;
    }

    function a_trace($id) {
        global $sky, $user;
        if (!$user->root && !DEBUG)
            return 404;
        $sky->debug = false;
        echo sqlf('+select tmemo from $_memory where id=%d', $id); # show X-trace
        throw new Stop;
    }

    function j_file() {
        global $user;
        if (!$user->root && !DEV)
            return 404;
        $sky->debug = false;
        list($file, $line) = explode('^', $_POST['name']);
        $txt = file_get_contents($file);
        echo Display::php($txt, str_pad('', $line - 1, '=') . ('true' == $_POST['c'] ? '-' : '+'));
        throw new Stop;
    }

    function a_exception() {
        global $sky;
        $no = $sky->_1 or $no = $sky->error_no;
        $sky->error_no < 10000 or $no = 11;
        $no or jump();
        $sky->k_static = [[], [], []];
        MVC::$layout = '';
        return [
            'd_ky' => $sky->error_no,
            'd_no' => $no,
            'd_tracing' => '',
        ];
    }

    function a_etc() {
        global $sky, $user;
        $s = "$sky->_1 $user->vid $user->ip";
        if (file_exists($fn = WWW . "pub/etc/$sky->_1")) { // 2do: file's cache check!
            $ext = substr($sky->_1, strrpos($sky->_1, '.') + 1);
            if ('txt' == $ext)
                header('Content-Type: text/plain; charset=' . ENC);
            if ('xml' == $ext)
                header('Content-Type: application/xml');
            MVC::last_modified(filemtime($fn), false, function() use($sky, $s) {
                $sky->log('etc', "304 $s");
            });
            header('Content-Length: ' . filesize($fn));
            $sky->log('etc', "200 $s");
            while (@ob_end_flush());
            readfile($fn);
            return true;
        }
        $sky->log('etc', "404 $s");
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

    function a_dev() {
        $form = Ext::form();
        if ($_POST) {
            foreach ($form as $k => $v)
                is_int($k) or isset($_POST[$k]) or $_POST[$k] = 0;
            Ext::save($_POST);
        }
        return ['form' => Form::A(Ext::$cfg, $form)];
    }

    function a_get_dev() {
        is_file('dev.php') or file_put_contents('dev.php', file_get_contents('http://coresky.net/download?dev.php'));
        jump(WWW ? '../dev.php' : 'dev.php');
    }

    function j_drop() {
        echo Admin::drop_all_cache() ? 'Drop all cache: OK' : 'Error when drop cache';
    }

    function j_inst() { //////////////////////////////////////////////////////////////
        return Install::run($this->_1);
    }

    function j_gate() { //////////////////////////////////////////////////////////////
        if ($this->_c == 'c_') # open last access time controller
            $this->_c = Gate::atime();
        $list = Gate::controllers($this->_c);
        return [
            'y_1' => $this->_c ? ('default_c' == $this->_c ? '*' : substr($this->_c, 2)) : '',
            'h1' => $this->_c,
            'virtuals' => $this->_c ? array_shift($list) : '',
            'list' => $list,
            'cshow' => Gate::cshow() ? ' checked' : '',
            'e_func' => $this->_c ? Gate::view($this->_c) : false,
            'error' => isset($_POST['err']) ? $_POST['err'] : false,
        ];
    }

    function j_virt() {
        Gate::save($this->_c, preg_split("/\s+/", trim($_POST['v'])));
        return $this->j_gate();
    }

    function j_delete() {
        Gate::save($this->_c, $this->_a);
        $this->_a or $this->_c = 'c_';
        return $this->j_gate();
    }

    function j_edit() {
        return Gate::view($this->_c, $this->_a);
    }

    function j_save() {
        Gate::save($this->_c, $this->_a, true);
        return Gate::view($this->_c, $this->_a, false);
    }

    function j_code() {
        Gate::compile($this->_c, $this->_a);
    }

    function x_c23_edit() {
        return Gate::ary_c23();
    }

    function j_lang() { //////////////////////////////////////////////////////////////
        MVC::body('_lng.' . substr($this->_c, 2));
        return call_user_func([new Language, $this->_c], $this->_a);
    }

    function a_api() { # lang auto translations
        # 2do
    }
}
