<?php

function e() {
    $top = MVC::instance();
    DEV && !SKY::s('gate_404') ? DEV::e($top) : $top->set(404);
}

function sqlf(...$in) { # just more quick parsing, using printf syntax. No SQL injection!
    $sql = new SQL($in, 'parseF');
    return $sql->exec();
}

function sql(...$in) {
    $sql = $in[0] instanceof SQL ? $in[0] : new SQL($in, 'parseT');
    return $sql->exec();
}

function qp(...$in) { # Query Part, Query Parse
    $in or $in = [''];
    return new SQL($in, 'parseT');
}

function html($str, $hide_percent = false, $mode = ENT_COMPAT) {
    $str = htmlspecialchars((string)$str, $mode, ENC);
    return $hide_percent ? str_replace('%', '&#37;', $str) : $str;
}

function unhtml($str, $mode = ENT_QUOTES) {
    return html_entity_decode($str, $mode, ENC);
}

function escape($str, $reverse = false) {
    $ary = ["\\" => "\\\\", "\r" => "\\r", "\n" => "\\n", "\t" => "\\t"];
    return strtr($str, $reverse ? array_flip($ary) : $ary);
}

function unl($str) {
    return str_replace(["\r\n", "\r"], "\n", $str);
}

function strand($n = 23) {
    $str = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRSTUVWXYZ2345679'; # length == 53
    if ($n != 7) $str .= 'o0Ol1iIB8'; # skip for passwords (9 chars)
    for ($ret = '', $i = 0; $i < $n; $i++, $ret .= $str[rand(0, 7 == $n ? 52 : 61)]);
    return $ret;
}

function strcut($str, $n = 100) { #300
    $text = mb_substr($str, 0, $n);
    return mb_strlen($str) > $n
        ? trim(mb_substr($text, 0, mb_strrpos($text, ' ', 0) - mb_strlen($text)), '.,?!') . '&nbsp;...'
        : $text;
}

function strbang($str, $via1 = ' ', $via2 = "\n") {
    $out = [];
    foreach (explode($via2, $str) as $item) {
        if ($via1 instanceof Closure) {
            $via1($out, $item);
        } else {
            [$k, $v] = explode($via1, $item, 2);
            $out[$k] = $v;
        }
    }
    return $out;
}

function array_join($ary, $via1 = ' ', $via2 = "\n") {
    return implode($via2, array_map(function($k, $v) use ($via1) {
        return $via1 instanceof Closure ? $via1($k, $v) : $k . $via1 . $v;
    }, array_keys($ary), $ary));
}

function array_match($re, $ary, $re_key = false) {
    if (!is_array($ary))
        return false;
    foreach ($ary as $k => $v) {
        if (!preg_match($re, $v) || !($re_key ? preg_match($re_key, $k) : is_num($k)))
            return false;
    }
    return true;
}

function is_num($v, $zero = false, $lt = true) {
    if (is_int($v) && ($lt || $v > 0))
        return true;
    if (!is_string($v))
        return false;
    return '0' === $v || ctype_digit($v) && ('0' !== $v[0] || $zero);
}

function jump($uri = '', $code = 302, $exit = true) {
    global $sky;

    if ($sky->fly)
        throw new Error('Jump when $sky->fly');
    $sky->fly = HEAVEN::Z_FLY;
    if (strpos($uri, '://')) {
        $sky->jump = $uri;
    } else {
        $sky->is_front or '' === $uri or '?' != $uri[0] or $uri = "adm$uri";
        if (common_c::$tune && $code)
            $uri = common_c::$tune . ('' === $uri || '?' == $uri[0] ? '' : '/') . $uri;
        $sky->jump = LINK . $uri;
    }

    header("Location: $sky->jump", true, $code ?: 302);
    $sky->tailed = "JUMP: $sky->jump\n";
    if ($exit)
        exit;
}

function trace($var, $is_error = false, $line = 0, $file = '', $context = false) {
    global $sky;

    if ($err = true === $is_error) {
        $sky->was_error |= SKY::ERR_DETECT;
        if (null === SKY::$dd)
            $sky->open(is_array($var) ? $var[1] : $var);
        if (SKY::$debug && SKY::$errors[0]++ > 49) {
            return;
            //throw new Error("500 Internal SKY error");
        }
    }
    if (SKY::$debug || $sky->log_error && $err) {
        if ($err && is_array($var))
            list ($title, $var) = $var;

        $var = is_string($var) ? html($var) : Plan::var($var, '', false, '?');

        $is_warning = 'WARNING' === $is_error;
        if (is_string($is_error)) {
            $var = "$is_error: $var";
            $is_error = false;
        }
        if (!$file) {
            $depth = 1 + $line;
            $db = debug_backtrace();
            list ($file, $line) = array_values($db[$line]);
            if (is_array($line)) { # file-line don't supported
                list ($file, $line) = array_values($db[$depth - 2]);
                $fln = L::r("<span>$file^$line</span>");
            }
        }
        isset($fln) or $fln = "<span>$file^$line</span>";
        $mgs = "$fln\n$var";// . html($var);
        if ($err) {
            $sky->was_error |= SKY::ERR_SHOW;
            if (SKY::$cli)
                echo "\n$file^$line\n$var\n\n";//2do striptags
            if ($sky->log_error) { # collect error log
                $type = SKY::$cli ? 'console' : ($sky->is_front ? 'front' : 'admin');
                $sky->error_prod .= L::r('<b>' . NOW . ' - ' . $type . '</b>');
                if (!SKY::$cli)
                    $sky->error_prod .= ' ' . $sky->methods[$sky->method] . ' uri: ' . html(URI);
                $sky->error_prod .= "\n$mgs\n\n";
            }
        }
        if (!SKY::$debug)
            return; # else collect tracing
        if ($err) {
            $sky->tracing .= "$fln\n" . '<div class="error">' . "$var</div>\n";//html() . 
            if (!DEV)
                return;
            if (is_string($context)) {
                $backtrace = html($context);
                $context = false;
            } else {
                ob_start();
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $backtrace = html(ob_get_clean());
            }
            Plan::epush($title ?? 'User Error', "$mgs\n\n$backtrace", $context);
        } elseif ($is_warning) {
            $sky->was_warning = 1;
            $sky->tracing .= "$fln\n" . '<div class="warning">' . "$var</div>\n";//html() . 
        } else {
            $sky->tracing .= "$mgs\n\n";
        }
    }
}

function pagination(&$limit, $cnt = false, $tpl = false) {
    global $sky;
    $tpl or $tpl = $sky->pagination;

    if (!is_num($cnt)) {
        if ($cnt instanceof SQL) {
            $cnt = sql('+select count(1) $$', $cnt);
        } else {
            is_string($cnt) or $cnt = (string)(SQL::$dd);
            $cnt = SQL::$dd->_rows_count($cnt);
        }
    }
    [$su, $qs] = array_values(parse_url(URI) + ['path' => '', 'query' => '']);
    $err = false;
    $current = 1;

    if (is_array($tpl)) { # $tpl = [0, 'page-2'] for surl
        $su = explode('/', $su);
        if (false !== common_c::$page) {
            array_splice($su, $tpl[0], 1);
            if (($current = (int)common_c::$page) < 2)
                $err = $current = 1;
        }
        $url = function ($page = 1) use ($su, $qs, $tpl) {
            1 == $page or array_splice($su, $tpl[0], 0, str_replace('2', $page, $tpl[1]));
            return PATH . implode('/', $su) . ($qs ? '?' . $qs : '');
        };
    } elseif (is_num($tpl)) { # for javascript links or custom
        $current = (int)$tpl;
        $url = 1;//2do
    } else {
        parse_str($qs, $qs);
        if (isset($qs[$tpl]) && ($current = (int)$qs[$tpl]) < 2)
            $err = $current = 1;
        $url = function ($page = 1) use ($su, $qs, $tpl) {
            if (1 == $page) {
                unset($qs[$tpl]);
            } else {
                $qs[$tpl] = $page;
            }
            $fn = fn($k, $v) => '' === $v ? $k : "$k=$v";
            return PATH . $su . ($qs ? '?' . array_join($qs, $fn, '&') : '');
        };
    }
    $limit = ($ipp = $limit) * ($current - 1);
    $last = ceil($cnt / $ipp);
    common_c::$page = $err || 1 != $current && $current > $last;

    return (object)[
        'current' => $current,
        'last' => $last,
        'cnt' => $cnt,
        'ipp' => $ipp,
        'item' => [($cnt ? 1 : 0) + $limit, 1 + $limit == $cnt ? 0 : ($limit + $ipp > $cnt ? $cnt : $limit + $ipp)],
        'url' => $url,
        'ary' => function ($m = 7, $b = 1) use ($last, $current) {
            if ($last <= $m + 2 * $b)
                return range(1, $last ?: 1);
            $x = $b ? range(1, $b) : [];
            $start = $current - floor($m / 2);
            if ($start < $b + 1)
                $start = $b + 1;
            if ($b && $b + 1 < $start)
                array_push($x, 0);
            if ($start + $m + $b > $last)
                $start = $last - $m - $b + 1;
            $x = array_merge($x, range($start, $start + $m - 1));
            if ($b && end($x) + 1 < $last - $b + 1)
                array_push($x, 0);
            return array_merge($x, $b ? range($last - $b + 1, $last) : []);
        },
    ];
}

function menu($act, $ary, $tpl = '', $by = '</li><li>', $class = 'menu') {
    global $sky;

    if (!$tpl) {
        $tpl = defined('TPL_MENU') ? TPL_MENU : '?%s';
        $by = ' &nbsp; ';
    }
    if (count($sky->surl) > 1)
        $tpl = PATH . $tpl;
    array_walk($ary, function(&$v, $k) use ($tpl, $act) {
        $ok = $act == explode('?', $k)[0];
        $v = sprintf('<a%s href="' . $tpl . '">%s</a>', $ok ? ' class="active"' : '', $k, $v);
    });
    return '</li><li>' == $by ? sprintf('<ul class="%s"><li>%s</li></ul>', $class, implode($by, $ary)) : implode($by, $ary);
}

function json($in, $return = false, $off_layout = true) {
    if ($off_layout)
        MVC::$layout = '';
    header('Content-Type: application/json; charset=' . ENC);
    $out = json_encode($in, $return ? JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR : 0); # second const from PHP 5.5
    if ($err = json_last_error())
        trace('json error: ' . $err, true, 1);
    if (DEV && !$return)
        Debug::vars(['$$' => $in], MVC::instance(-1)->no);
    if ($return)
        return $out;
    echo $out;
}

function unjson($in, $assoc = false) {
    $out = json_decode($in, $assoc);
    if ($err = json_last_error()) {
        strlen($in) < 1000 or $in = substr($in, 0, 1000) . ' ...';
        trace("unjson() error=$err, in=$in", true, 1);
    }
    return $out;
}

function get($addr, $headers = '', $unjson = true) {
    $ts = microtime(true);
    $response = file_get_contents($addr, false, stream_context_create(['http' => [
        'method' => "GET",
        'header' => "User-Agent: Coresky\r\n$headers",
    ]]));
    if (DEBUG)
        trace(sprintf("%01.3f sec - $addr", microtime(true) - $ts), 'GET', 1);
    return $response ? ($unjson ? unjson($response, true) : $response) : false;
}

function api($addr, $in) {
    $response = file_get_contents($addr, false, stream_context_create(['http' => [
        'method' => "POST",
        'header' => "Content-Type: application/json; charset=UTF-8\r\n",
        'content' => json_encode($in),
    ]]));
    return $response ? unjson($response, true) : false;
}

function option($selected, $ary, $attr = '') {
    $out = '';
    $attr .= ($attr ? ' ' : '') . 'value="%s"%s';
    foreach ($ary as $k => $v)
        $out .= tag(html($v), sprintf($attr, $k, $selected == $k ? ' selected' : ''), 'option');
    return $out;
}

function radio($name, $checked, $ary, $by = ' ') {
    $tpl = '<label><input type="radio" name="%s" value="%s"%s /> %s</label>%s';
    $out = '';
    foreach ($ary as $k => $v)
        $out .= sprintf($tpl, $name, $k, (string)$checked == $k ? ' checked' : '', $v, $by);
    return $out;
}

function a($anchor, $href = null, $x = '') {
    if (is_array($href)) {
        $x .= ' onclick="' . $href[0] . '"';
        $href = 'javascript:;';
    }
    $href or $href = $href === null ? "http://$anchor/" : LINK;
    return sprintf('<a href="%s"%s>%s</a>', $href, $x ? ' ' . trim($x) : '', $anchor);
}

function js($x = '') {
    if (is_string($x))
        return "<script>$x</script>";
    $pf = '?' . (SKY::s('statp') ?: '1000p');
    $js = '';
    foreach ($x as $src)
        $js .= '<script src="' . ('~' == $src[0] ? PATH . substr($src, 2) . $pf : $src) . '"></script>';
    return $js;
}

function css($x = '') {
    if (is_string($x))
        return '<style>' . $x . '</style>';
    $pf = '?' . (SKY::s('statp') ?: '1000p');
    $css = '';
    foreach ($x as $src)
        $css .= '<link rel="stylesheet" href="' . ('~' == $src[0] ? PATH . substr($src, 2) . $pf : $src) . '" />';
    return $css;
}

function pre($in, $x = 'class="code"') {
    return tag($in, $x, 'pre');
}

function tag($in, $x = 'class="fr"', $tag = 'div') {
    if (is_scalar($in))
        return "<$tag" . ($x ? ' ' : '') . trim($x) . ">$in</$tag>";
    $tag = '';
    foreach ($in as $k => $v)
        $tag .= sprintf(TPL_META, $k, $v);
    return $tag;
}

function th($ary, $x = '') {
    $tr = $x ? "<table $x><tr>\n" : '<tr>';
    foreach ($ary as $k => $v)
        $tr .= is_int($k) ? "<th>$v</th>\n" : "<th $v>$k</th>\n";
    return "\n$tr</tr>\n";
}

function td($ary, $x = '') {
    $tr = $x ? "<tr $x>\n" : "<tr>\n";
    foreach ($ary as $v)
        $tr .= is_array($v) ? "<td $v[1]>$v[0]</td>\n" : "<td>$v</td>\n";
    return "\n$tr</tr>\n";
}

function hidden($set = '_csrf', $r = 0) {
    is_array($set) or $set = [$set => $r];
    $out = '';
    foreach ($set as $name => $val) {
        if (!is_string($name)) {
            $name = $val;
            $val = isset($r[$name]) ? $r[$name] : '';
        } elseif ('_csrf' == $name && 0 === $r) {
            global $sky;
            $val = $sky->csrf;
        }
        $out .= sprintf(TPL_HIDDEN, $name, html($val));
    }
    return $out;
}

define('ENC', 'UTF-8');
define('CLI', 'cli' == PHP_SAPI);
define('zebra', 'return @$i++ % 2 ? \'bgcolor="#eee"\' : "";');
define('DATE_DT', 'Y-m-d H:i:s');
define('RE_LOGIN', '/^[a-z][a-z_\d]{1,19}$/i');
define('RE_PASSW', '/^\S{3,15}$/');
define('RE_EMAIL', '/^([\w\-]+\.)*[\w\-]+@([\w\-]+\.)+[a-z]{2,7}$/i');
define('RE_PHONE', '/^\+?\d{10,12}$/');
define('TPL_FORM',   '<dl><dt>%s</dt><dd>%s</dd></dl>');
define('TPL_CHECKBOX', '<input type="checkbox" name="id[]" value="%s"%s />');
define('TPL_HIDDEN', '<input type="hidden" name="%s" value="%s" />');
define('TPL_META',   '<meta name="%s" content="%s" />');

//////////////////////////////////////////////////////////////////////////
if (!class_exists('Error', false)) {
    class Error extends Exception {} # Assume as crash and error, `throw new Error` should never works!
}
class Stop extends Exception {} # Assume as just "stop", NOT crash, NOT error
class Hacker extends Exception {} # Assume as crash but NOT error on the web-scripts

interface DriverCache
{
    function info();
    function setup($obj, $quiet = false);
    //function close();
    function test($name);
    function get($name);
    function run($name, $vars = false);
    function mtime($name);
    function append($name, $data);
    function put($name, $data, $ttl = false);
    function set($name, $data);
    function glob($mask = '*');
    function drop($name);
    function drop_all($mask = '*');
}

//////////////////////////////////////////////////////////////////////////
trait SQL_COMMON
{
    protected $table; # for overload in models if needed

    function __call($name, $args) {
        if (!in_array($name, ['sql', 'sqlf', 'qp', 'table']))
            throw new Error('Method ' . get_class($this) . "::$name(..) not exists");
        $mode = $args && is_int($args[0]) ? array_shift($args) : 0;
        return call_user_func_array($name, [-2 => $this, -1 => 1 + $mode] + $args);
    }

    function __toString() {
        return $this->table;
    }

    function onduty($table) {
        $this->table = $table;
    }
}

//////////////////////////////////////////////////////////////////////////
class eVar implements Iterator
{
    private $state = 0;
    private $i = -1;
    private $max_i;
    private $row;
    private $e;
    private $dd = false;

    function __construct($e) {
        if ($e instanceof Closure)
            $e = ['row_c' => $e];
        $this->e = $e;
    }

    function __toString() {
        if (isset($this->e['str_c']))
            return (string)call_user_func($this->e['str_c'], $this);
        return (string)(1 + $this->i);
    }

    function __invoke($in) {
        call_user_func($this->e['row_c'], $in, $this);
        $this->state = 0;
        return $this;
    }

    function __call($name, $args) {
        return isset($this->e[$name]) ? call_user_func_array($this->e[$name], $args) : null;
    }

    function __get($name) {
        return $this->e[$name] ?? null;
    }

    function __set($name, $value) {
        $this->e[$name] = $value;
    }

    function one() {
        if ($this->state > 1)
            return false;
        $this->state ? $this->next() : $this->rewind();
        return $this->valid() ? $this->row : false;
    }

    function all() {
        return iterator_to_array($this, false);
    }

    function rewind(): void {
        if (!$this->e)
            $this->state = 2;
        if ($this->state)
            return;
        $this->row = new stdClass;
        $this->state++;
        if (isset($this->e['query'])) {
            isset($this->e['max_i']) or $this->e['max_i'] = -1;
            $sql =& $this->e['query'];
            if (is_string($sql)) {
                $sql = sql(2, $sql);
            } elseif (true === $sql->stmt) {     # mean instanceof SQL
                $sql->mode |= 2 + SQL::NO_PARSE; # already parsed or query builder used
                $sql = sql($sql); # perform query exec with error's detection
            }
            if (!($sql instanceof SQL)) {
                $this->state++;
                return;
            }
            $this->dd = $sql->_dd;
        }
        $this->max_i = $this->e['max_i'] ?? 500; # -1 is infinite
        $this->next();
    }

    function valid(): bool {
        if ($this->state > 1)
            return false;
        if ($this->row)
            return true;
        $this->state++;
        if (isset($this->e['after_c']))
            call_user_func($this->e['after_c'], $this->i);
        return false;
    }
    #[\ReturnTypeWillChange]
    function current() {
        return $this->row;
    }
    #[\ReturnTypeWillChange]
    function key() {
        return $this->i;
    }

    function next(): void {
        $exit = function ($fail) {
            $this->row = false;
            $this->i--;
            if ($this->dd)
                $this->dd->free($this->e['query']->stmt);
            if ($fail)
                throw new Error("eVar cycle error");
        };
        do {
            if ($this->i++ >= $this->max_i && -1 != $this->max_i) {
                $exit(!isset($this->e['max_i']));
                return;
            }
            if ($this->dd)
                $this->row = $this->dd->one($this->e['query']->stmt, 'O');
            $x = false;
            if (isset($this->e['row_c']) && $this->row) {
                $this->row->__i = $this->i;
                $x = call_user_func_array($this->e['row_c'], [&$this->row]);
                if (false === $x) {
                    $exit(false);
                    return;
                }
            }
        } while (true === $x);
        if (!$this->dd)
            $this->row = $x ? (object)$x : false;
    }
}
