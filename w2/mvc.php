<?php

function view($in, $return = false, &$param = null) {
    if ($in instanceof MVC) {
        $layout = MVC::$layout;
        $mvc = $in;
    } else {
        [$in, $layout] = is_array($in) ? $in : [$in, ''];
        $no_handle = false;
        if (!is_bool($return)) {
            $no_handle = is_array($param = $return);
            $return = true;
        }
        $mvc = MVC::sub($in, $param, $no_handle);
        $mvc->return = $return;
    }
    if ('' !== $mvc->ob) {
        $mvc->body = '';
        if (DEV && !$layout)
            Debug::vars(['$' => $mvc->ob], $mvc->no);
    }
    trace("$mvc->no $mvc->hnd $layout^$mvc->body", $mvc->no ? 'SUB-VIEW' : 'TOP-VIEW', 1);

    if ($layout || $mvc->body)
        $mvc->ob = Plan::jet_r(MVC::jet($mvc, $layout, $vars), $vars);
    if ($mvc->no)
        return $mvc->return ? $mvc->ob : null;
    global $sky;
    if ($sky->fly || !$layout)
        method_exists($sky, 'tail_x') ? $sky->tail_x(0, $mvc->ob) : Console::tail_x(0, $mvc->ob);
}

//////////////////////////////////////////////////////////////////////////
abstract class MVC_BASE
{
    function __get($name) {
        static $instances = [];
        if (in_array(substr($name, 0, 2), ['m_', 't_', 'x_'])) {
            $obj = 'x' == $name[0]
                ? Plan::xload($name)
                : $instances[$name] ?? ($instances[$name] = new $name);
            if ('m' != $name[0]) {
                SQL::$dd = $obj->dd;
                SQL::$dd->onduty($obj->table);
            }
            return $obj;
        }
        global $sky;
        return $sky->$name;
    }

    function __set($name, $value) {
        global $sky;
        $sky->$name = $value;
    }
}

abstract class Guard {
    function __call($name, $args) {
    }

    function __get($name) {
        global $sky;
        return $sky->$name;
    }
}

//////////////////////////////////////////////////////////////////////////
class Model_m extends MVC_BASE
{
    use SQL_COMMON;
    protected $dd;

    function __construct($table = null) { # set database driver & onduty table
        $this->table = $table ?? $this->table ?? substr(get_class($this), 2);
        $this->dd = $this->head_y();
    }

    function dd() {
        return $this->dd;
    }

    # for overload if needed
    function head_y() {
        return SQL::$dd;
    }
}

/** SQL parser methods */
class Model_t extends Model_m
{
    protected $id;

    function where($rule) {
        if ($rule instanceof SQL)
            return 'where ' == substr($rule, 0, 6) ? $rule : $rule->prepend('where ');
        if (null === $rule)
            $rule = $this->id;
        if (is_num($rule))
            return $this->qp('where id=' . $rule); # num already checked
        if (is_array($rule))
            return is_int(key($rule)) ? $this->qp('where id in ($@)', $rule) : $this->qp('where @@', $rule);
    }

    function cell($rule = '', String $what = 'count(1)', $use_id = false) {
        if ($use_id) {
            $row = $this->sql(1, '~select * from $_ $$', $this->where($rule));
            $this->id = $row['id'];
            return $row[$what];
        }
        return $this->sql(1, '+select ' . $what . ' from $_ $$', !$rule ? '' : $this->where($rule));
    }

    function one($rule = null, String $pref = '~') {
        if (!in_array($pref, ['-', '~', '>']))
            throw new Error("Wrowng prefix $pref");
        $row = $this->sql(1, $pref . 'select * from $_ $$', $this->where($rule));
        if ('>' == $pref && isset($row->id)) {
            $this->id = $row->id;
        } elseif ('~' == $pref && isset($row['id'])) {
            $this->id = $row['id'];
        } elseif ('-' == $pref) {
            $this->id = $row[0];
        }
        return $row;
    }

    function all($rule = 0, $what = '*', String $pref = '#') {
        $is_e = true === $rule;
        if (0 === $rule || $is_e)
            return $this->sql(1, ($is_e ? '&' : '') . 'select * from $_');
        if (!in_array($pref, ['', '@', '%', '#', '&']))
            throw new Error("Wrowng prefix $pref");
        if ($what instanceof SQL)
            $what = (string)$what;
        is_array($what) or $what = [$what];
        return $this->sql(1, $pref . 'select !! from $_ $$', $what, $this->where($rule));
    }

    function insert($ary) {
        return $this->id = $this->sql(1, 'insert into $_ @@', $ary);
    }

    function update($what, $rule = null) {
        return $this->sql(1, 'update $_ set ' . (is_array($what) ? '@@' : '$$') . ' $$', $what, $this->where($rule));
    }

    function delete($rule = null) {
        return $this->sql(1, 'delete from $_ $$', $this->where($rule));
    }
}

abstract class Controller extends MVC_BASE
{
    # for overload if needed
    function head_y($action) {
        return $this === MVC::$cc ? null : MVC::$cc->head_y($action);
    }

    # for overload if needed
    function error_y($action) {
        return $this === MVC::$cc ? null : MVC::$cc->error_y($action);
    }

    # for overload if needed
    function tail_y() {
        return $this === MVC::$cc ? null : MVC::$cc->tail_y();
    }

    function __call($name, $args) {
        if (SKY::$debug)
            Debug::not_found(get_class($this), $name);
        return 404;
    }
}

trait HOOK_C
{
    static $lg; # (not set)
    static $page = false; # for pagination (page not set)
    static $tune = false; # for rewritten wares (not set)

    static function langs_h() {
    }

    static function rewrite_h($cnt, &$surl, $uri, $sky) {
        SKY::$plans['main']['rewrite']($cnt, $surl, $uri, $sky);
    }

    static function head_h() {
        global $user;
        $tz = !$user->vid || '' === $user->v_tz ? "''" : (float)('' === $user->u_tz ? $user->v_tz : $user->u_tz);
        return "sky.is_debug=" . (int)SKY::$debug . "; sky.tz=$tz;";
    }

    static function user_h(&$lg = null) {
        return new USER;
    }

    static function dd_h($dd, $name = '') {
        $dd->init();
    }

    static function make_h($forward) {
        return Install::make($forward);
    }
}

trait HOOK_D
{
    protected $hook = false;

    function head_y($action) {
        if (!$this->hook = in_array($action, ['a_crash', 'a_etc', 'j_init']))
            return parent::head_y($action);
    }

    function tail_y() {
        if (!$this->hook)
            return parent::tail_y();
    }

    function a_crash() {
        global $sky;
        $sky->open();
        SKY::$debug = $this->_static = 0;
        $tracing = '';
        if (DEV) {
            $x = (int)('_' != $sky->_0[0] && SKY::d('tracing_toggle'));
            $x or $tracing = pre(sqlf('+select tmemo from $_memory where id=1'));
            SKY::d('tracing_toggle', 1 - $x);
        }
        http_response_code($no = (int)($this->_1 ?: 404));
        MVC::body('_std.crash');
        return [
            'redirect' => '',
            'no' => $no,
            'tracing' => $tracing,
        ];
    }

    function a_test_crash() {
        if ($this->_1)
            return [];
        throw new Error('Crash pretty');
    }

    function a_etc($fn, $ware) {
        global $sky;
        $ext = '';
        if ($pos = strrpos($fn, '.'))
            $ext = substr($fn, $pos + 1);
        if (DEV && in_array($ext, ['js', 'css'])) {
            if (SKY::d('etc'))
                $sky->open(); # save tracing on DEV only now
            if (count($sky->surl) < 3) // 2do: fix skygate
                $ware = false;
            if (!$ware && !in_array($w = substr($fn, 0, $pos), ['sky', 'dev']))
                $ware = $w;
            $file = $ware && Plan::has($ware, false) ? Plan::_t([$ware, "assets/$fn"]) : DIR_S . "/assets/$fn";
        } else {//2do: use Plans (to get var) to save optionally user log on Prod
            $file = WWW . "m/etc/$fn";
        }
        if (is_file($file)) {
            switch ($ext) {
                case 'txt': MVC::mime('text/plain; charset=' . ENC); break;
                case 'css': MVC::mime('text/css'); break;
                case 'xml': MVC::mime('application/xml'); break;
                case 'js':  MVC::mime('application/javascript'); break;
                //case 'ico': MVC::mime('image/x-icon'); break;
            }
            MVC::last_modified(filemtime($file), false, function() use ($sky, $fn) {
                $sky->log('etc', "304 $fn");
            });
            header('Content-Length: ' . filesize($file));
            $sky->log('etc', "200 $fn");
            while (@ob_end_flush());
            readfile($file);
            throw new Stop;
        }
        $sky->open();
        $sky->log('etc', "404 $fn");
        http_response_code($sky->error_no = 404);
        throw new Hacker('etc');//return true;
    }

    function j_init($tz, $scr) {
        global $sky, $user;

        $sky->open();
        $user = common_c::user_h();
        if (isset($_POST['unload'])) {
            #if ($user->id && !$user->v_mem) ???
             #   $user->logout();
        } else {
            $user->v_tz = floatval($tz);
            preg_match("/^(\d{3,4})x\d{3,4}$/", $scr, $m);
            $user->v_scr = $m ? $m[0] : '768x1024';
            if ($user->v_mobi === '' && ($m && $m[1] < 1000 || $sky->orientation))
                $user->v_mobi = $user->v_mobd = $m[1] < 700 ? 1 : 2;
        }
        return true;
    }
}

class MVC extends MVC_BASE
{
    private static $stack = [];

    static $_y = []; # layout vars
    static $layout = '';
    static $mc; # master controller
    static $cc; # common controller
    static $ctrl;
    static $tpl = '_std';

    public $_v = []; # body vars
    public $body = '';
    public $ob; # output buffer
    public $return = false;
    public $hnd;
    public $no;

    function __construct() {
        static $no = 0;
        $this->no = $no++;
        MVC::$stack[] = $this;
    }

    static function instance($level = 0) {
        MVC::$stack or new MVC;
        return MVC::$stack[$level < 0 ? count(MVC::$stack) + $level : $level];
    }

    static function mime($mime) {
        global $sky;
        header("Content-Type: $mime");
        $sky->fly = HEAVEN::Z_FLY;
    }

    static function body($body) {
        end(MVC::$stack)->body = $body;
    }

    static function fn_parsed($layout, $body) {
        global $sky;
        return ($sky->eview ?: Plan::$view) . '-' . ($sky->is_mobile ? 'm' : 'p') . "-{$layout}-{$body}.php";
    }

    static function vars(&$all, &$new, $pref = false) {
        array_walk($new, function (&$v, string $k) use (&$all, $pref) {
            if ('' === $k)
                throw new Error("Cannot use empty varname");
            $p = strlen($k) > 1 && '_' == $k[1] ? $k[0] : false;
            if ('e' === $p) {
                $all[$k] = new eVar($v);
            } elseif ('_' == $k[0]) {
                SKY::$reg[$k] = $v;
            } elseif (!$p && $pref) {
                $all[$pref . $k] =& $v;
            } else {
                $all[$k] =& $v;
            }
        });
    }

    static function jet($mvc, $layout, &$vars) {
        global $sky;
        $vars = new stdClass;
        $vars->data['_vars'] = SKY::$vars;
        $_vars =& $vars->data['_vars'];
        if (is_string($mvc)) { # for __std.crash
            $name = $mvc;
            $_vars['sky'] = $sky;
        } else {
            $name = "_$mvc->body";
            $mvc->no or MVC::vars($_vars, MVC::$_y, 'y_');
            MVC::vars($_vars, $mvc->_v);
            $_vars['sky'] = $mvc;
        }
        $fn = MVC::fn_parsed($layout, $name);
        $ok = Plan::jet_t($fn) && (!DEV || $sky->d_jet_cache);
        if ($ok && DEV) {
            $mtime = Plan::jet_m($fn);
            $jet = Plan::jet_g($fn);
            $line = substr($jet, $n = strpos($jet, "\n"), strpos($jet, "\n", 2 + $n) - $n);
            $files = explode(' ', trim($line, " \r\n#"));
            foreach ($files as $one) {
                if (!$ok = Plan::view_('m', "$one.jet") < $mtime)
                    break; # recompilation required
            }
        }
        $ok or new Jet($name, $layout, $fn, is_string($mvc) ? false : $mvc->return);
        trace("JET: $fn, " . ($ok ? 'used cached' : 'recompiled'));
        return $fn;
    }

    static function last_modified($time, $use_site_ts = true, $func = null) {
        global $sky;

        is_numeric($time) or $time = strtotime($time);
        $if_time = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? false;
        if ($if_time && ($if_time = strtotime($if_time))) {
            !$use_site_ts or $sky->s_site_ts < $time or $time = $sky->s_site_ts; # overall site design timestamp
            if ($if_time >= $time) {
                http_response_code(304); # Not Modified
                $sky->tailed = true;
                !is_callable($func) or $func();
                exit;
            }
        }
        header('Last-Modified: ' . substr(gmdate('r', $time), 0, -5) . 'GMT');
    }

    static function head($plus = '') {
        global $sky;

        if (!$sky->_title) {
            $v =& MVC::instance()->_v;
            if (isset($v['y_h1']))
                $sky->_title = $v['y_h1'];
        }
        if (!$sky->_tkd)
            $sky->_tkd = [$sky->s_title, $sky->s_keywords, $sky->s_description];
        $sky->_title = html($sky->_title ? "$sky->_title - {$sky->_tkd[0]}" : $sky->_tkd[0]);
        if ($sky->_refresh) {
            list($secs, $link) = explode('!', $sky->_refresh);
            $sky->_head = $sky->_head . sprintf('<meta http-equiv="refresh" content="%d%s">', $secs, $link ? ";url=$link" : '');
        }
        if ('' === $sky->_static) {
            $fn = $sky->is_mobile ? 'mobile' : 'desktop';
            $sky->_static = [[], ["~/m/$fn.js"], ["~/m/$fn.css"]]; # default app meta_tags, js, css files
        } elseif (!$sky->_static) {
            $sky->_static = [[], [], []];
        }
        echo "<title>$sky->_title</title>$plus";
        echo tag($sky->_static[0] + ['csrf-token' => $sky->csrf, 'sky.home' => LINK]); # meta tags
        echo js([-2 => '~/m/jquery.min.js', -1 => '~/m/sky.js'] + $sky->_static[1]);
        echo css($sky->_static[2] + [-1 => '~/m/sky.css']);
        if (!$sky->eview && 'crash' != $sky->_0)
            echo js(common_c::head_h());
        echo '<link href="' . PATH . 'm/etc/favicon.ico" rel="shortcut icon" type="image/x-icon" />' . $sky->_head;
    }

    static function handle($method, &$param = null, $mandatory = false) {
        if (MVC::$ctrl = method_exists(MVC::$mc, $method) ? get_class(MVC::$mc) : false)
            return MVC::$mc->$method($param);
        if (MVC::$ctrl = method_exists(MVC::$cc, $method) ? 'common_c' : false)
            return MVC::$cc->$method($param);
        if ($mandatory) {
            MVC::$ctrl = 'not-found';
            trace("Method `$method` not found", true, 1);
        }
    }

    static function sub($action, $param = null, $no_handle = false) {
        MVC::$stack or new MVC; # for Console
        $mvc = new MVC;
        ob_start();
        if (is_string($action)) {
            if (DEV && SKY::d('red_label') && 'r_' == substr($action, 0, 2)) {
                echo tag("view($action)", 'class="red_label"'); # show red label
                $mvc->hnd = 'red-label';
            } else {
                list ($tpl, $action) = 2 == count($ary = explode('.', $action)) ? $ary : [MVC::$tpl, $ary[0]];
                '_' == $action[1] or $action = "x_$action"; # must have prefix, `x_` is default
                $mvc->body = "$tpl." . substr($action, 2);
                $mvc->set($no_handle ? $param : MVC::handle($action, $param, true));
                $mvc->hnd = $no_handle ? 'no-handle' : ('_R' == substr(MVC::$ctrl, -2) ? substr(MVC::$ctrl, 0, -2) : MVC::$ctrl) . "::$action()";
            }
        } elseif ($action instanceof Closure) {
            $mvc->set($action($param));
            $mvc->hnd = "Closure";
        } else {
            $mvc->body = MVC::$tpl . '.' . substr($action[1], 2);
            $mvc->set(call_user_func($action, $param));
            $mvc->hnd = "$action[0]::$action[1]()";
        }
        $mvc->ob = ob_get_clean();
        array_pop(MVC::$stack);
        return $mvc;
    }

    function set($in, $is_common = false) {
        global $sky;

        if (is_array($in)) {
            $is_common ? (MVC::$_y = $in + MVC::$_y) : ($this->_v = $in + $this->_v);
        } elseif (is_string($in)) {
            $this->body = $in;
        } elseif (is_bool($in)) {
            MVC::$layout = ''; # if false set to empty layout only
            !$in or $this->body = '';
        } elseif (is_int($in)) { # errors
            switch ($in) {
                case 0: $sky->error_no = 200; break;
                case 1: throw new Stop;
                case 11: throw new Hacker;
                case 12: die;
                default:
                    if ($in < 100)
                        throw new Error("Returned $in");
                    $sky->error_no = $in;
                    HEAVEN::J_FLY == $sky->fly or http_response_code($in);
            }
            $this->body = '_std.' . (MVC::instance()->return ? '_' . $in : $in);
        }
    }

    private function gate(&$class, &$action, &$gate) {
        $ctrl =& SKY::$plans['main']['ctrl'];
        is_string($i0 = $this->_0) or $i0 = '*';
        $x = false;
        if (common_c::$tune) {
            $set = isset($ctrl[$w0 = common_c::$tune . "/$i0"])
                or $set = isset($ctrl[$w0 = $x = common_c::$tune . "/*"]); # default_c in ware
        } else {
            $set = isset($ctrl[$w0 = $i0]);
        }
        if ($set) {
            Plan::$ware = Plan::$view = $ctrl[$w0];
            if ($set = '*' !== $i0 && !$x) {
                $class = 'c_' . (MVC::$tpl = $i0);
                is_string($i0 = $this->_1) or $i0 = '*';
            }
        }
        if (!$set)
            $class = (MVC::$tpl = 'default') . '_c';
        $dst = Plan::gate_t($fn_dst = Plan::$ware . "-$class.php");
        $recompile = false;
        if (!$dst || DEV) {
            if ('main' != Plan::$ware)
                trace(Plan::$ware, 'WARE');
            if ($recompile = !$dst || Plan::_mq("mvc/$class.php") > Plan::gate_m($fn_dst))
                Plan::gate_p($fn_dst, Gate::instance()->parse(Plan::$ware, "mvc/$class.php", false));
        }
        $x = $this->return ? 'j' : 'a';
        $action = '' === $i0 ? "empty_$x" : $x . '_' . $i0;
        Plan::gate_r($fn_dst, (object)['data' => ['recompile' => $recompile]]);
        $cls_g = $class . '_G';
        if (!method_exists($gate = new $cls_g, $action))
            $action = "default_$x";
    }

    function top() {
        global $sky;

        ob_start();
        if (DEV && $sky->_0 && '_' == $sky->_0[0]) {
            $action = ($this->return ? 'j' : 'a') . $sky->_0;
            $this->hnd = "dev_c::$action()";
            $this->body = '_std.' . substr($sky->_0, 1);
            MVC::$mc = new dev_c;
            $this->set(MVC::$mc->head_y($action), true); # call head_y
        } else {
            $this->gate($class, $action, $gate);
            switch ($action[0]) {
                case 'd': $this->body = MVC::$tpl . ".default"; break; # default_X
                case 'e': $this->body = MVC::$tpl . ".empty"; break; # empty_X
                default:  $this->body = MVC::$tpl . "." . substr($action, 2); break; # a_.. or j_..
            }
            $this->hnd = "$class::$action()";
            $class .= '_R';
            MVC::$mc = new $class;
            trace("$class::head_y(\$action)", 'CALL');
            $this->set(MVC::$mc->head_y($action), true); # call head_y
            $param = call_user_func([$gate, $action]); # call gate
        }
        if (DEV)
            trace($sky->error_no ? 'not-called' : "->$action(..)", 'MASTER ACTION');
        if (!$sky->error_no)
            $this->set(call_user_func_array([MVC::$mc, $action], $param ?? [])); # call master action
        if (false !== common_c::$page)
            $this->set(404);

        is_string($tail = MVC::$mc->tail_y()) ? (MVC::$layout = $tail) : $this->set($tail, true); # call tail_y

        $this->ob = ob_get_clean();
        if ($sky->error_no > 99) {
            $vars = ['err_no' => $sky->error_no, 'exit' => 0, 'stdout' => $this->ob];
            $this->ob = '';
            is_array($ary = MVC::$mc->error_y($action)) ? ($ary += $vars) : ($ary = $vars); # call error_y
            $this->set($ary);
        }
        view($this); # visualize
        if (!$sky->tailed)
            throw new Error('HEAVEN::tail_t() must used in the layout');
    }
}
