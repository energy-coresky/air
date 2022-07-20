<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

function view($_in, $return = false, &$_vars = null) {
    if (!$_in) {
        return require Plan::$parsed_fn;
    } elseif ($_in instanceof MVC) {
        $layout = MVC::$layout;
        $mvc = $_in;
    } else {
        list($_in, $layout) = is_array($_in) ? $_in : [$_in, ''];
        $no_handle = false;
        if (!is_bool($return)) {
            $no_handle = is_array($_vars = $return);
            $return = true;
        }
        $mvc = MVC::sub($_in, $_vars, $no_handle);
        $mvc->return = $return;
    }
    if ('' !== $mvc->ob)
        $mvc->body = '';

    trace("$mvc->hnd $layout^$mvc->body", $mvc->is_sub ? 'SUB-VIEW' : 'TOP-VIEW', 1);
    if ($layout || $mvc->body)
        $mvc->ob = view(false, 0, MVC::jet($mvc, $layout));
    return MVC::tail($mvc, $layout);
}

function t(...$in) { # situational translation
    global $sky;

    $sky->trans_i or $sky->trans_i = 0;
    if ($args = $in) {
        $str = array_shift($in);
    } elseif ($sky->trans_i++ % 2) {
        $str = ob_get_clean();
    } else {
        return ob_start();
    }
    if (is_array(@$in[0]))
        $in = $in[0];

    if (isset($sky->trans_late[$str])) {
        DEFAULT_LG == LG or $str = $sky->trans_late[$str];
    } elseif (DEV && 1 == $sky->d_trans) {
        SKY::$reg['trans_coll'][$str] = $sky->trans_i;
    }
    $args or print $str;
    return $in ? vsprintf($str, $in) : $str;
}

//////////////////////////////////////////////////////////////////////////
abstract class MVC_BASE
{
    function __get($name) {
        global $sky;
        if (in_array(substr($name, 0, 2), ['m_', 'q_', 't_'])) {
            isset(SKY::$reg[$name]) && is_a(SKY::$reg[$name], $name) or SKY::$reg[$name] = new $name;
            if ('m' != $name[0]) {
                SKY::$reg[$name]->dd->onduty(SKY::$reg[$name]->table);
                SQL::$dd = SKY::$reg[$name]->dd;
            }
        }
        return $sky->$name;
    }

    function __set($name, $value) {
        global $sky;
        if (DEV && $sky->in_tpl && !$sky->in_row_c)
            throw new Error("Cannot set \$sky->$name property from the templates");
        $sky->$name = $value;
    }
}

abstract class Bolt {
    function __call($name, $args) {
    }
}

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

/** Query builder methods */
abstract class Model_q extends Model_m
{
    use QUERY_BUILDER;
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

    function cell($rule = '', String $what = 'count(1)') {
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

    function _list($ipp = 0) { # items per page
        if ($ipp) {
            list ($limit, $pages, $cnt) = pagination($ipp);
            return ['query' => $this->sql(1, 'select * from $_ limit $., $.', $limit, $ipp), 'pages' => $pages, 'cnt' => $cnt];
        }
        return ['query' => $q = $this->sql(1, 'select * from $_'), 'pages' => false, 'cnt' => $q->_dd->_rows_count($this->table)];
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
        if ($sky->debug) {
            switch ($class = get_class($this)) {
                case __CLASS__:
                case 'default_c':
                    if (DEV && Plan::_t([Gate::$ware, "app/c_$sky->_0.php"])) {
                        Plan::cache_d(['main', 'sky_plan.php']);
                        $sky->ajax or jump(URI);
                    }
                    $x = 1 == $sky->ajax ? 'j' : 'a';
                    $msg = preg_match("/^\w+$/", $sky->_0)
                        ? "Controller `c_$sky->_0.php` or method `default_c::{$x}_$sky->_0()` not exist"
                        : "Method `default_c::default_$x()` not exist";
                    trace($msg, (bool)DEV);
                    break;
                default:
                    trace("Method `{$class}::$name()` not exist", (bool)DEV);
            }
        }
        return 404; # 1 == $sky->ajax also works via sky.error() and CONTR::error_y()
    }
}

trait HOOK
{
    static function rewrite_h($cnt, &$surl) {
        if (1 == $cnt && 'robots.txt' == $surl[0] && !$_GET)
            return array_unshift($surl, '_etc');
        HOOK::re_dev($cnt, $surl);
    }

    static function re_dev($cnt, &$surl) {
        global $sky;
        if (DEV && $cnt && 'm' == $surl[0]) // $sky->s_statp == $surl[0]
            return $surl[0] = '_etc';
    }

    static function re_verse_ext($cnt, &$surl, $ext = 'html') {
        if (0 == $cnt || '_' == $surl[0][0])
            return false;
        $a = explode('.', $s =& $surl[$cnt - 1]);
        return $s = 2 != count($a) || $ext != $a[1] ? "$s.$ext" : $s = $a[0];
    }

    static function head_h() {
        global $sky, $user;
        $tz = !$user->vid || '' === $user->v_tz ? "''" : (float)('' === $user->u_tz ? $user->v_tz : $user->u_tz);
        return "sky.is_debug=" . (int)$sky->debug . "; sky.tz=$tz;";// var addr='".LINK."';";
    }

    //add named limits and permission to sky-gate!!!!!!

    function mc(Array $in = null) {
        $name = get_class(MVC::$mc);
        if (!$in)
            return $name;
        if (is_int(key($in)))
            return in_array($name, $in);
    }

    function setLG_h() {
        return [];
    }

    static function getLG_h() {
        global $sky;
        if (!is_array($sky->lg))
            return DEFAULT_LG;
        $locale = Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? DEFAULT_LG);
        $lg = implode('|', $sky->lg);
        return preg_match("/^($lg)/", strtolower($locale), $match) ? $match[1] : DEFAULT_LG;
    }

    static function dd_h($name, $dd) {
        if ('MySQLi' != $dd->name) // dd_sqlite3  dd_mysqli
            return;
        mysqli_set_charset($dd->conn, 'utf8') or exit('charset');
        $dd->sqlf('set time_zone=%s', date('P'));
    }
}

class MVC extends MVC_BASE
{
    private static $stack = [];

    public $_v = []; # body vars
    public $body = '';
    public $ob;
    public $is_sub;
    public $return;

    static $vars;
    static $_y = []; # layout vars
    static $layout = '';
    static $mc; # main controller
    static $cc; # common controller
    static $ctrl;
    static $tpl = 'default';

    function __construct($is_sub = false) {
        MVC::$stack[] = $this;
        $this->is_sub = $is_sub;
    }

    static function in_tpl($bool = null) {
        global $sky;
        static $mem = [];
        if (is_bool($bool))
            $mem[] = $sky->in_tpl;
        $sky->in_tpl = null === $bool ? array_pop($mem) : $bool;
    }

    static function instance($level = 0) {
        MVC::$stack or new MVC;
        return MVC::$stack[$level];
    }

    static function fn_parsed($layout, $body) {
        global $sky;
        $p = (DESIGN ? 'd' : '') . ($sky->is_mobile ? 'm' : '') . '_';
        return "$p{$sky->style}-{$layout}-{$body}.php";
    }

    static function vars(&$all, &$new, $pref = false) {
        array_walk($new, function (&$v, string $k) use (&$all, $pref) {
            if ('' === $k || '_' == $k[0])
                throw new Error("Cannot use `$k` var");

            $p = strlen($k) > 1 && '_' == $k[1] ? $k[0] : false;
            if ('e' === $p) {
                $all[$k] = new eVar($v);
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
        if (is_string($mvc)) { # for __std.exception
            $name = $mvc;
            $vars['sky'] = $sky;
        } else {
            $name = "_$mvc->body";
            $mvc->is_sub or MVC::vars($vars, MVC::$_y, 'y_');
            MVC::vars($vars, $mvc->_v);
            $vars['sky'] = $mvc;
        }
        $fn = MVC::fn_parsed($layout, $name);
        $dev = DEV || DESIGN;
        $ok = Plan::jet_tp($fn) && ($sky->s_jet_cact || !$dev);
        if ($ok && ($dev || $sky->s_jet_prod)) { # this `if` can be skipped on the production by the config
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
        global $sky, $user;

$js = '_' == $sky->_0[0] ? '' : common_c::head_h();
        if (!$sky->k_title) {
            $v =& MVC::instance()->_v;
            if (isset($v['y_h1']))
                $sky->k_title = $v['y_h1'];
        }
        if (!$sky->k_tkd)
            $sky->k_tkd = [$sky->s_title, $sky->s_keywords, $sky->s_description];
        $sky->k_title = html($sky->k_title ? "$sky->k_title - {$sky->k_tkd[0]}" : $sky->k_tkd[0]);
        if ($sky->k_refresh) {
            list($secs, $link) = explode('!', $sky->k_refresh);
            $sky->k_head = $sky->k_head . sprintf('<meta http-equiv="refresh" content="%d%s">', $secs, $link ? ";url=$link" : '');
        }
        if (!$sky->k_static) {
            $fn = ($sky->style ? "$sky->style/" : '') . ($sky->is_mobile ? 'mobile' : 'desktop');
            $sky->k_static = [[], ["~/m/$fn.js"], ["~/m/$fn.css"]]; # default app meta_tags, js, css files
        }
        $plus = "<title>$sky->k_title</title>$plus";
        $csrf = isset($user) ? $user->v_csrf : '';
        $plus .= tag($sky->k_static[0] + ['csrf-token' => $csrf, 'sky.home' => LINK]); # meta tags
        $plus .= js([-2 => '~/m/jquery.min.js', -1 => '~/m/sky.js'] + $sky->k_static[1]);
        $plus .= css($sky->k_static[2] + [-1 => '~/m/sky.css']) . js($js);
        echo $plus . '<link href="' . PATH . 'favicon.ico" rel="shortcut icon" type="image/x-icon" />' . $sky->k_head;
    }

    static function tail($mvc = false, $layout = false) {
        global $sky;
        if ($mvc) {
            if ($mvc->is_sub)
                return $mvc->return ? $mvc->ob : print($mvc->ob);
            if ($sky->ajax || !$layout)
                $sky->tail_x('', $mvc->ob); # tail_x ended with "throw new Stop"
            return $mvc->ob;
        }
        $sky->ajax or $sky->tail();
    }

    static function doctype($mime) {
        global $sky;
        header("Content-Type: $mime;");
        $sky->ajax = 3;
    }

    static function body($body) {
        end(MVC::$stack)->body = $body;
    }

    static function handle($method, &$param = null) {
        if (MVC::$ctrl = method_exists(MVC::$mc, $method) ? substr(get_class(MVC::$mc), 0, -7) : false)
            return MVC::$mc->$method($param);
        if (MVC::$ctrl = method_exists(MVC::$cc, $method) ? 'common_c' : false)
            return MVC::$cc->$method($param);
        MVC::$ctrl = 'not-found';
    }

    static function sub(&$action, $param = null, $no_handle = false) {
        global $sky;
        $me = new MVC(true);
        ob_start();
        if (is_string($action)) {
            if (DEV && $sky->s_red_label && 'r_' == substr($action, 0, 2)) {
                echo tag("view($action)", 'class="red_label"'); # show red label
                $me->hnd = 'red-label';
            } else {
                list ($tpl, $action) = 2 == count($ary = explode('.', $action)) ? $ary : [MVC::$tpl, $ary[0]];
                '_' == $action[1] or $action = "x_$action"; # must have prefix, `x_` is default
                $me->body = "$tpl." . substr($action, 2);
                $me->set($no_handle ? $param : MVC::handle($action, $param));
                $me->hnd = $no_handle ? "no-handle" : MVC::$ctrl . "::$action()";
            }
        } elseif ($action instanceof Closure) {
            $me->set($action($param));
            $me->hnd = "Closure";
        } else {
            $me->body = MVC::$tpl . '.' . substr($action[1], 2);
            $me->set(call_user_func($action, $param));
            $me->hnd = "$action[0]::$action[1]()";
        }
        $me->ob = ob_get_clean();
        array_pop(MVC::$stack);
        return $me;
    }

    function set($in, $is_common = false) {
        global $sky, $user;

        if (is_array($in)) {
            $is_common ? (MVC::$_y = $in + MVC::$_y) : ($this->_v = $in + $this->_v);
        } elseif (is_string($in)) {
            $this->body = $in;
        } elseif (is_bool($in)) {
            MVC::$layout = ''; # if false set to empty layout only
            !$in or $this->body = '';
        } elseif (is_int($in)) { # soft error
            $sky->error_no = $in;
            if ($sky->s_error_404)// || $user->jump_alt)
                throw new Stop($in); # terminate quick
            $sky->ajax or http_response_code($in);
            $this->body = '_std.404';
        }
    }

    private function gate($recalculate) {
        global $sky;

        if ($recalculate) {
            SKY::$plans['main']['ctrl'] = Gate::controllers();
            Plan::cache_d(['main', 'sky_plan.php']);
        }
        if ($match = '*' !== $sky->_0 && isset(SKY::$plans['main']['ctrl'][$sky->_0]))
            Gate::$ware = Plan::$ware = SKY::$plans['main']['ctrl'][$sky->_0];

        $class = $match ? 'c_' . $sky->_0 : 'default_c';//////if no * - 404 !!!
        $dst = Plan::gate_t($fn_dst = "$class.php");
        $recompile = false;
        if (!$dst || DEV) {
            if ('main' != Plan::$ware)
                trace(Plan::$ware, 'WARE');
            if (!$src = Plan::_t([Gate::$ware, $fn_src = "app/$class.php"]))
                return $recalculate || !$match ? ['Controller', '_', false] : $this->gate(true);
            if ($recompile = !$dst || Plan::_m([Gate::$ware, $fn_src]) > Plan::gate_m($fn_dst))
                Gate::put_cache($class, $fn_src, $fn_dst);
        }
        $action = $match
            ? ('' === $sky->_1 ? ($this->return ? 'empty_j' : 'empty_a') : ($this->return ? 'j_' : 'a_') . $sky->_1)
            : ($this->return ? 'j_' : 'a_') . $sky->_0;
        Plan::gate_rr($fn_dst, $recompile);
        if (!method_exists($gape = new Gape, $action))
            $action = $this->return ? 'default_j' : 'default_a';
        if ($match)
            MVC::$tpl = substr($class, 2);
        return [$class, $action, $gape];
    }

    static function top() {
        global $sky, $user;

        $me = new MVC;
        ob_start();
        $param = [];
        $me->return = 1 == $sky->ajax;
        if ('_' == $sky->_0[0]) {
            if ($id = array_search(URI, [2 => '_x0', 1 => '_x1', 15 => '_x2', 16 => '_x3'])) {
                $param = [$id];
                $sky->surl[0] = '_trace';
            }
            $class = 'standard_c';
            $action = ($me->return ? 'j' : 'a') . $sky->_0;
            MVC::$tpl = '_std';
            $me->body = '_std.' . substr($sky->_0, 1);
            $gape = false;
        } else {
            list ($class, $action, $gape) = $me->gate(false);
            switch ($action[0]) {
                case 'd': $me->body = MVC::$tpl . ".default"; break; # default_X
                case 'e': $me->body = MVC::$tpl . ".empty"; break; # empty_X
                default:  $me->body = MVC::$tpl . "." . substr($action, 2); break; # a_.. or j_..
            }
            $cached = $class . '_cached';
        }
        $me->hnd = "$class::$action()";
        MVC::$mc = $gape ? new $cached : new $class;
        MVC::$cc = new common_c;
        SKY::$vars['k_class'] = $class;
        $me->set(MVC::$mc->head_y($action), true);
        if ($gape) {
            $param = (array)call_user_func([$gape, $action], $sky, $user);
            if (DEV && 1 == $sky->error_no) { # gate error
                $me->body = $me->return ? '' : '_std.lock';
                $sky->ca_path = ['ctrl' => $class, 'func' => $action];
            }
        }
        if (!$sky->error_no || $sky->surl && '_exception' == $sky->surl[0]) {
            $me->set(call_user_func_array([MVC::$mc, $action], $param));
        } else {
            $me->hnd = "error";
        }
        if ($sky->error_no > 400 && $sky->error_no < 501)
            $me->set(MVC::$mc->error_y($action));
        is_string($tail = MVC::$mc->tail_y()) ? (MVC::$layout = $tail) : $me->set($tail, true);
        $me->ob = ob_get_clean();
        $me->ob = view($me, $extra = 1 == $sky->extra); # visualize
        if (!$sky->tailed)
            throw new Error('MVC::tail() must used in the layout');
        if ($extra) { # save extra cache
  #          file_put_contents("var/extra/$sky->fn_extra", ($sky->s_extra_ttl ? time() + $sky->s_extra_ttl : 0) . "\n<!-- E -->$me->ob");
   #         echo $me->ob;
        }
    }
}
