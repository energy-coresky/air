<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

function view($in, $return = false, $param = null) {
    global $sky;

    if ($is_top = $in instanceof MVC) {
        $layout = MVC::$layout;
        $mvc = $in;
    } else {
        list($in, $layout) = is_array($in) ? $in : [$in, ''];
        $no_handle = false;
        if (!is_bool($return)) {
            $no_handle = is_array($param = $return);
            $return = true;
        }
        $mvc = MVC::sub($in, $param, $no_handle);
    }
    if ('' !== $mvc->echo)
        $mvc->body = '';
    trace("$layout^$mvc->body", 'LAYOUT^BODY', 1);
    if ($layout || $mvc->body) {
        MVC::$vars = SKY::$vars;
        !$is_top or array_walk(MVC::$_y, 'MVC::walk_c', 'y_');
        array_walk($mvc->_v, 'MVC::walk_c', 'v_');
        MVC::$vars['sky'] = $mvc;
        MVC::$vars['a_stdout'] = &$mvc->echo;
        MVC::$vars['a_parsed'] = MVC::recompile($layout, "_$mvc->body");
        ob_start();
        $sky->in_tpl = true;
        call_user_func(function() {
            extract(MVC::$vars, EXTR_REFS);
            require $a_parsed;
        });
        $sky->in_tpl = false;
        $mvc->echo = ob_get_clean();
    }
    if (!$is_top)
        return $return ? $mvc->echo : print($mvc->echo);
    if ($sky->ajax || !$layout)
        $sky->tail_x('', $mvc->echo); # tail_x ended with exit()
    if (!$sky->tailed)
        throw new Err('MVC::tail() must used in the layout');
    if (1 == $sky->extra) # save extra cache
        file_put_contents("var/extra/$sky->fn_extra", ($sky->s_extra_ttl ? time() + $sky->s_extra_ttl : 0) . "\n<!-- E -->$mvc->echo");
    echo $mvc->echo;
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
    } elseif (DEV && 1 == Ext::cfg('trans')) {
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
           'm' == $name[0] or SKY::$reg[$name]->dd->onduty(SKY::$reg[$name]->table);/////////
        }
        return $sky->$name;
    }

    function __set($name, $value) {
        global $sky;
        if ($sky->in_tpl)
            throw new Err("Cannot set \$sky->$name property from the templates");
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

    function __construct() {
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
    protected $list;
    protected $id;

    function __construct() {
        $this->list = 'select $+ from $_ '; ////////////////
        parent::__construct();
    }

    function struct() {
        $data = $this->sql(1, '@explain $_');
        array_walk($data, function(&$v, $k) {
            $v = is_null($v[3]) ? '' : $v[3]; # default value or empty string
        });
        return $data;
    }

    function where($rule) {
        if ($rule instanceof SQL) {
            $ws = 'where ' == substr($rule, 0, 6);
            return $ws ? $rule : $rule->prepend('where ');
        }
        if (null === $rule) $rule = $this->id;
        if (is_num($rule))
            return $this->qp('where id=$.', $rule);
        if (is_array($rule)) return is_int(key($rule))
            ? $this->qp('where id in ($@)', $rule)
            : $this->qp('where @@', $rule); # dangerous !
        return null;
    }

    function cnt($rule = '') {
        return $this->sql(1, '+select count(1) from $_ $$', !$rule ? '' : $this->where($rule));
    }

    function one($rule, $return_array = false) {
        if (0 === $rule)
            return $this->struct();
        $row = $this->sql(1, ($return_array ? '~' : '>') . 'select * from $_ $$', $this->where($rule));
        if (isset($row->id))
            $this->id = $row->id;
        return $row;
    }

    function all($rule = '', $what = '#select *') {
        return $this->sql(1, $what . ' from $_ $$', $this->where($rule));
    }

    function insert($ary) {
        return $this->id = $this->sql(1, 'insert into $_ @@', $ary);
    }

    function update($what, $rule = null) {
        if (null === $rule) $rule = $this->id;
        if (is_array($what))
            return $this->sql(1, 'update $_ set @@ $$', $what, $this->where($rule));
        return $this->sql(1, 'update $_ set $$ $$', $what, $this->where($rule));
    }

    function replace(&$id, $validate = true) {
        $rare = new Rare;
        $ret = $rare->replace($this->table, $id, $validate);
        if ($ret === false) return $rare->get_errors();
        if (!$ret && $id) return 'Nothing changed!';
        $id or $id = $ret;
        $this->id = $id;
        return false;
    }

    function delete($rule = null) {
        if (is_array($rule))
            $rule = $this->qp(SQL::_UPD, 'id in ($@)', $rule);
        return $this->sql(1, 'delete from $_ $$', $this->where($rule));
    }

    function _html($ary) {
        foreach ($ary as &$c) $c = html($c);
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
        return 'common_c' == get_class($this) ? null : MVC::$cc->head_y($action);
    }

    # for overload if needed
    function error_y($action) {
        return 'common_c' == get_class($this) ? null : MVC::$cc->error_y($action);
    }

    # for overload if needed
    function tail_y() {
        return 'common_c' == get_class($this) ? null : MVC::$cc->tail_y();
    }

    function __call($name, $args) {
        global $sky;
        if ($sky->debug) {
            switch ($class = get_class($this)) {
                case __CLASS__:
                case 'default_c':
                    if (DEV && is_file("main/app/c_$sky->_0.php")) {
                        $sky->s_contr = '';
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

abstract class HOOK extends Controller
{
    function mc(Array $in = null) {
        $name = get_class(MVC::$mc);
        if (!$in)
            return $name;
        if (is_int(key($in)))
            return in_array($name, $in);
    }

    //add named limits and permission to sky-gate!!!!!!

    static function rewrite_h($cnt) {
        global $sky;
        if (1 == $cnt && 'robots.txt' == $sky->surl[0] && !$_GET)
            array_unshift($sky->surl, '_etc');
    }

    static function lang_h() {
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
    const dir_j = 'var/jet/';

    private static $stack = [];

    public $_v = []; # body vars
    public $body = '';
    public $echo;

    static $vars;
    static $_y = []; # layout vars
    static $layout = '';
    static $mc; # main controller
    static $cc; # common controller
    static $tpl = 'default';
    static $cache_filename = '';

    function __construct() {
        MVC::$stack[] = $this;
    }

    static function instance($level = 0) {
        MVC::$stack or new MVC;
        return MVC::$stack[$level];
    }

    static function jet($fn) {
        if (!is_file($fn)) {
            list (, $layout, $body) = explode('-', basename($fn, '.php'));
            new Jet($layout, $body, $fn);
        }
        return $fn;
    }
    
    static function fn_parsed($layout, $body) {
        global $sky;
        $p = MVC::dir_j . (DESIGN ? 'd' : '') . ($sky->is_mobile ? 'm' : '') . '_';
        return "$p{$sky->style}-{$layout}-{$body}.php";
    }

    static function fn_tpl($name) {
        global $sky;
        $std = '__std' == $name;
        if ($std || '__lng' == $name)
            return DIR_S . '/w2/' . ($std ? 'standard_c' : 'language') . '.jet';
        $dir = $sky->style ? DIR_V . "/$sky->style" : DIR_V;
        if ($sky->is_mobile && '_' == $name[0] && is_file("$dir/b$name.jet"))
            $name = "b$name";
        return "$dir/$name.jet";
    }

    static function recompile($layout, $name) {
        global $sky;
        $fn = MVC::fn_parsed($layout, $name);
        $dev = DEV || DESIGN;
        $ok = is_file($fn) && ($sky->s_jet_cact || !$dev);
        if ($ok && ($dev || $sky->s_jet_prod)) { # this `if` can be skipped on the production by the config
            $mtime = filemtime($fn);             # to get max speed (avoid mtime checking)
            $lines = file($fn);
            $files = explode(' ', trim($lines[1], " \r\n#"));
            foreach ($files as $one) {
                $ok &= filemtime(MVC::fn_tpl($one)) < $mtime; # check for file mtime
                if (!$ok)
                    break; # recompilation required
            }
        }
        $ok or new Jet($layout, $name, $fn, true);
        trace("JET: $fn, " . ($ok ? 'used cached' : 'recompiled'));
        return $fn;
    }

    static function walk_c(&$v, $k, $_) {
        if (strlen($k) < 2 || '_' != $k[1]) {
            $k = $_ . $k;
        } else switch ($k[0]) {
            case 'h': # for escaped html
                $v = html($v);
                break;
            case 'e': # for cycles & stdObjects returned from models
                $v = new eVar($v);
                break;
            case 'd': # delete prefix
                if ('a_' != substr($k = substr($k, 2), 0, 2))
                    break;
            case 'a'://////////////////       _ !!!
                throw new Err('Cannot use variables with `a_` prefix');
            case 'k':
                SKY::$vars[$k] = $v;
        }
        MVC::$vars[$k] =& $v;
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

    static function head($plus = '') {
        global $sky, $user;

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
            $sky->k_static = [[], ["~/$fn.js"], ["~/$fn.css"]]; # default app meta_tags, js, css files
        }
        $plus = "<title>$sky->k_title</title>$plus";
        $plus .= tag($sky->k_static[0] + ['csrf-token' => $user->v_csrf, 'surl-path' => PATH]); # meta tags
        $plus .= js([-2 => '~/jquery.min.js', -1 => '~/sky.js'] + $sky->k_static[1]);
        $plus .= css([-1 => '~/sky.css'] + $sky->k_static[2]) . js($sky->k_js);
        echo $plus . '<link href="' . PATH . 'favicon.ico" rel="shortcut icon" type="image/x-icon" />' . $sky->k_head;
    }

    static function tail() {
        global $sky;
        $sky->ajax or $sky->tail();
    }

    static function doctype($mime) {
        global $sky;
        header("Content-Type: $mime;");
        $sky->ajax = 3;
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
                    echo DEV ? a('D', PATH . '_dev') : sprintf(span_r, 'D');
                    echo a('A', PATH . $link);
                }
                echo a('X', ['sky.trace(1)']) . a('T', ['sky.trace()']);
            } else {
                echo a('ADMIN', PATH . $link);
            }
            echo "$plus</span>";
        }
    }

    static function body($body) {
        end(MVC::$stack)->body = $body;
    }

    static function handle($method, $param = null) {
        if (method_exists(MVC::$mc, $method))
            return MVC::$mc->$method($param);
        if (method_exists(MVC::$cc, $method))
            return MVC::$cc->$method($param);
    }

    static function sub($action, $param = null, $no_handle = false) {
        global $sky;
        $me = new MVC;
        ob_start();
        if (is_string($action)) {
            if (DEV && $sky->s_red_label && 'r_' == substr($action, 0, 2)) {
                echo tag("view($action)", 'class="red_label"'); # show red label
            } else {
                list ($tpl, $action) = 2 == count($ary = explode('.', $action)) ? $ary : [MVC::$tpl, $ary[0]];
                '_' == $action[1] or $action = "x_$action"; # must have prefix, `x_` is default
                $me->body = "$tpl." . substr($action, 2);
                $me->set($no_handle ? $param : MVC::handle($action, $param));
            }
        } elseif ($action instanceof Closure) {
            $me->set($action($param));
        } else { // array /////////////?
            $me->body = MVC::$tpl . '.' . substr($action[1], 2);
            $me->set(call_user_func($action, $param));
        }
        $me->echo = ob_get_clean();
        array_pop(MVC::$stack);
        return $me;
    }

    private function set($in, $is_common = false) {
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

    private function gate($ex = false) {
        global $sky;

        $_0 = $sky->_0;
        $list = !$ex && $sky->s_contr ? explode(' ', $sky->s_contr) : Gate::controllers();
        $in_a = '*' !== $_0 && in_array($_0, $list, true);
        $class = $real = $in_a ? 'c_' . $_0 : 'default_c';
        $dst = is_file($fn_dst = "var/gate/$class.php");
        if (!$dst || DEV) {
            $src = is_file($fn_src = "main/app/$class.php") or !$in_a or $src = Gate::real_src($real, $fn_src);
            if (!$src)
                return $ex || !$in_a ? ['Controller', '_', false, ''] : $this->gate(true);
            if (!$dst || filemtime($fn_src) > filemtime($fn_dst))
                Gate::put_cache($class, $fn_src, $fn_dst);
        }
        $is_j = 1 == $sky->ajax;
        $action = $in_a
            ? ('' === $sky->_1 ? ($is_j ? 'empty_j' : 'empty_a') : ($is_j ? 'j_' : 'a_') . $sky->_1)
            : ($is_j ? 'j_' : 'a_') . $_0;
        require $fn_dst;
        if (!method_exists($gape = new Gape, $action))
            $action = $is_j ? 'default_j' : 'default_a';
        if (isset($gape->src))
            $real = "c_$gape->src";
        if ($in_a)
            MVC::$tpl = substr($real, 2);
        return [$class, $action, $gape, $real];
    }

    static function top() {
        global $sky, $user;

        MVC::$vars = ['sky' => $me = new MVC];
        ob_start();
        $param = [];
        if ('_' == $sky->_0[0]) {
            if ($id = array_search(URI, [1 => '_x1', 15 => '_x2', 16 => '_x3'])) {
                $param = [$id];
                $sky->surl[0] = '_trace';
            }
            $real = $class = 'standard_c';
            $action = (1 == $sky->ajax ? 'j' : 'a') . $sky->_0;
            MVC::$tpl = '_std';
            $me->body = '_std.' . substr($sky->_0, 1);
            $gape = false;
        } else {
            list ($class, $action, $gape, $real) = $me->gate();
            switch ($action[0]) {
                case 'd': $me->body = MVC::$tpl . ".default"; break; # default_X
                case 'e': $me->body = MVC::$tpl . ".empty"; break; # empty_X
                default:  $me->body = MVC::$tpl . "." . substr($action, 2); break; # a_.. or j_..
            }
        }
        trace("$class::$action()" . ($class == $real ? '' : ' (virtual)'), 'TOP-VIEW');
        MVC::$mc = new $class;
        MVC::$cc = new common_c;
        SKY::$vars['k_class'] = $class;
        $me->set(MVC::$mc->head_y($action), true);
        if ($gape) {
            $param = (array)call_user_func([$gape, $action], $sky, $user);
            if (DEV && 1 == $sky->error_no) { # gate error
                $me->body = 1 == $sky->ajax ? '' : '_std.lock';
                $sky->ca_path = ['ctrl' => $real, 'func' => $action];
            }
        }
        if (!$sky->error_no || $sky->surl && '_exception' == $sky->surl[0])
            $me->set(call_user_func_array([MVC::$mc, $action], $param));
        if ($sky->error_no > 400 && $sky->error_no < 501)
            $me->set(MVC::$mc->error_y($action));
        is_string($tail = MVC::$mc->tail_y()) ? (MVC::$layout = $tail) : $me->set($tail, true);
        $me->echo = ob_get_clean();
        view($me); # visualize
    }
}
