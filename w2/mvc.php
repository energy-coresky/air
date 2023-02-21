<?php

function view($_in, $_return = false, &$_vars = null) {
    if (!$_in) {
        return require Plan::$parsed_fn;
    } elseif ($_in instanceof MVC) {
        $layout = MVC::$layout;
        $mvc = $_in;
    } else {
        list($_in, $layout) = is_array($_in) ? $_in : [$_in, ''];
        $no_handle = false;
        if (!is_bool($_return)) {
            $no_handle = is_array($_vars = $_return);
            $_return = true;
        }
        $mvc = MVC::sub($_in, $_vars, $no_handle);
        $mvc->return = $_return;
    }
    if ('' !== $mvc->ob) {
        $mvc->body = '';
        if (DEV && !$layout)
            Util::vars(['$' => $mvc->ob], $mvc->no);
    }
    trace("$mvc->no $mvc->hnd $layout^$mvc->body", $mvc->no ? 'SUB-VIEW' : 'TOP-VIEW', 1);

    global $sky;
    if ($layout || $mvc->body)
        $mvc->ob = view(false, 0, MVC::jet($mvc, $layout));
    if ($mvc->no)
        return $mvc->return ? $mvc->ob : null;
    if ($sky->fly || !$layout)
        $sky->tail_x(0, $mvc->ob); # tail_x ended with "exit"
}

//////////////////////////////////////////////////////////////////////////
abstract class MVC_BASE
{
    function __get($name) {
        static $instances = [];
        if (in_array(substr($name, 0, 2), ['m_', 't_'])) {
            $obj = isset($instances[$name]) ? $instances[$name] : ($instances[$name] = new $name);
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

abstract class Bolt {
    function __call($name, $args) {
    }

    function __get($name) {
        global $sky;
        return $sky->$name;
    }
}

//////////////////////////////////////////////////////////////////////////
abstract class Model_m extends MVC_BASE
{
    use SQL_COMMON;
    protected $dd;

    function __construct() { # set database driver & onduty table
        isset($this->table) or $this->table = substr(get_class($this), 2);
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
abstract class Model_t extends Model_m
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
        if (0 === $rule)
            return $this->sql(1, 'select * from $_');
        if (!in_array($pref, ['', '@', '%', '#']))
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

class Controller extends MVC_BASE
{
    # for overload if needed
    function head_y($action) {
        return $this->is_common() ? null : MVC::$cc->head_y($action);
    }

    # for overload if needed
    function error_y($action) {
        return $this->is_common() ? null : MVC::$cc->error_y($action);
    }

    # for overload if needed
    function tail_y() {
        return $this->is_common() ? null : MVC::$cc->tail_y();
    }

    function is_common() {
        return get_class($this) == get_class(MVC::$cc);
    }

    function __call($name, $args) {
        global $sky;
        if (SKY::$debug) {
            switch ($class = get_class($this)) {
                case __CLASS__:
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
        return 404; # 1 == $sky->fly also works via sky.error() and CONTR::error_y()
    }
}

trait HOOK
{
    static function rewrite_h($cnt, &$surl) {
        if (1 == $cnt && 'robots.txt' == $surl[0] && !$_GET)
            return array_unshift($surl, '_etc');
        return HOOK::re_dev($cnt, $surl);
    }

    static function re_dev($cnt, &$surl) {
        if (DEV && $cnt && 'm' == $surl[0])
            return $surl[0] = '_etc';
    }

    static function re_verse_ext($cnt, &$surl, $ext = 'html') {
        if (0 == $cnt || '_' == $surl[0][0])
            return false;
        $a = explode('.', $s =& $surl[$cnt - 1]);
        return $s = 2 != count($a) || $ext != $a[1] ? "$s.$ext" : $s = $a[0];
    }

    static function head_h() {
        global $user;
        $tz = !$user->vid || '' === $user->v_tz ? "''" : (float)('' === $user->u_tz ? $user->v_tz : $user->u_tz);
        return "sky.is_debug=" . (int)SKY::$debug . "; sky.tz=$tz;";
    }

    //add named limits and permission to sky-gate!!!!!!

    function mc(Array $in = null) {
        $name = get_class(MVC::$mc);
        if (!$in)
            return $name;
        if (is_int(key($in)))
            return in_array($name, $in);
    }

    static function langs_h() {
    }

    static function user_h(&$lg = null) {
        return new USER;
    }

    static function dd_h($dd, $name = '') {
        if ('MySQLi' != $dd->name)
            return;
        if (!mysqli_set_charset($dd->conn, 'utf8'))
            throw new Error('mysqli_set_charset');
        $dd->sqlf('set time_zone=%s', date('P'));
    }
}

class MVC extends MVC_BASE
{
    private static $stack = [];

    public $_v = []; # body vars
    public $body = '';
    public $ob;
    public $return = false;
    public $hnd;
    public $no;

    static $vars;
    static $_y = []; # layout vars
    static $layout = '';
    static $mc; # main controller
    static $cc; # common controller
    static $ctrl;
    static $tpl = '_std';

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
        header("Content-Type: $mime;");
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

    static function &jet($mvc, $layout = '') {
        global $sky;
        $vars = SKY::$vars;
        if (is_string($mvc)) { # for __std.crash
            $name = $mvc;
            $vars['sky'] = $sky;
        } else {
            $name = "_$mvc->body";
            $mvc->no or MVC::vars($vars, MVC::$_y, 'y_');
            MVC::vars($vars, $mvc->_v);
            $vars['sky'] = $mvc;
        }
        $fn = MVC::fn_parsed($layout, $name);
        $ok = Plan::jet_tp($fn) && (!DEV || $sky->d_jet_cache);
        if ($ok && DEV) {
            list ($mtime, $files) = Plan::jet_mf($fn); # to get max speed (avoid mtime checking)
            foreach ($files as $one) {
                $ok &= Plan::view_('m', "$one.jet") < $mtime; # check for file mtime
                if (!$ok)
                    break; # recompilation required
            }
        }
        $ok or new Jet($name, $layout, $fn, is_string($mvc) ? false : $mvc->return);
        trace("JET: $fn, " . ($ok ? 'used cached' : 'recompiled'));
        return $vars;
    }

    static function last_modified($time, $use_site_ts = true, $func = null) {
        global $sky;

        is_numeric($time) or $time = strtotime($time);
        if (@$_SERVER['HTTP_IF_MODIFIED_SINCE'] && ($if_time = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))) {
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
                        echo DEV ? a('P', PROTO . '://' . _PUBLIC) : sprintf(span_r, 'P');
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
$js = '_' == $sky->_0[0] ? '' : common_c::head_h();
        echo css($sky->_static[2] + [-1 => '~/m/sky.css']) . js($js);
        echo '<link href="' . PATH . 'm/etc/favicon.ico" rel="shortcut icon" type="image/x-icon" />' . $sky->_head;
    }

    static function tail() {
        global $sky;
        $sky->tail_t();
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

    private function gate($recalculate) {
        global $sky;

        if ($recalculate) {
            SKY::$plans['main']['ctrl'] = Gate::controllers();
            Plan::cache_d(['main', 'sky_plan.php']);
        }
        if ($match = '*' !== $sky->_0 && isset(SKY::$plans['main']['ctrl'][$sky->_0]))
            Plan::$gate = Plan::$ware = SKY::$plans['main']['ctrl'][$sky->_0];

        $class = $match ? 'c_' . $sky->_0 : 'default_c';
        $dst = Plan::gate_t($fn_dst = "$class.php");
        $recompile = false;
        if (!$dst || DEV) {
            if ('main' != Plan::$ware)
                trace(Plan::$ware, 'WARE');
            if (!Plan::_t([Plan::$gate, $fn_src = "mvc/$class.php"]))
                return $recalculate || !$match ? ['Controller', '_', false] : $this->gate(true);
            if ($recompile = !$dst || Plan::_m([Plan::$gate, $fn_src]) > Plan::gate_m($fn_dst))
                Gate::put_cache($class, $fn_src, $fn_dst);
        }
        $action = $match
            ? ('' === $sky->_1 ? ($this->return ? 'empty_j' : 'empty_a') : ($this->return ? 'j_' : 'a_') . $sky->_1)
            : ($this->return ? 'j_' : 'a_') . $sky->_0;
        Plan::gate_rr($fn_dst, $recompile);
        $cls_g = $class . '_G';
        if (!method_exists($gate = new $cls_g, $action))
            $action = $this->return ? 'default_j' : 'default_a';
        MVC::$tpl = $match ? substr($class, 2) : 'default';
        return [$class, $action, $gate];
    }

    function top() {
        global $sky;

        ob_start();
        if ('_' == $sky->_0[0]) {
            $action = ($this->return ? 'j' : 'a') . $sky->_0;
            $this->hnd = "standard_c::$action()";
            $this->body = '_std.' . substr($sky->_0, 1);
            MVC::$mc = new standard_c;
            $this->set(MVC::$mc->head_y($action), true); # call head_y
        } else {
            list ($class, $action, $gate) = $this->gate(false);
            switch ($action[0]) {
                case 'd': $this->body = MVC::$tpl . ".default"; break; # default_X
                case 'e': $this->body = MVC::$tpl . ".empty"; break; # empty_X
                default:  $this->body = MVC::$tpl . "." . substr($action, 2); break; # a_.. or j_..
            }
            $this->hnd = "$class::$action()";
            $class .= $gate ? '_R' : '';
            MVC::$mc = new $class;
            $this->set(MVC::$mc->head_y($action), true); # call head_y
            if ($gate)
                $param = call_user_func([$gate, $action]); # call gate
        }
        if (DEV)
            trace($sky->error_no ? 'not-called' : "->$action(..)", 'MASTER ACTION');
        if (!$sky->error_no)
            $this->set(call_user_func_array([MVC::$mc, $action], $param ?? [])); # call master action

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
            throw new Error('MVC::tail() must used in the layout');
    }
}
