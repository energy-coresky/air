<?php

interface PARADISE
{
}

//////////////////////////////////////////////////////////////////////////
class SKY implements PARADISE
{
    const CORE = '0.211 2022-12-30T19:48:13+02:00 energy';
    const ERR_DETECT = 1;
    const ERR_SHOW   = 3;

    public $tracing = '';
    public $error_prod = '';
    public $error_no = 0;
    public $error_last = 0;
    public $was_error = 0;
    public $was_warning = 0;
    public $gpc = '';
    public $langs = [];
    public $shutdown = [];

    static $mem = [];
    static $reg = [];
    static $vars = [];
    static $databases;
    static $dd = null;
    static $plans = [];
    static $cli;
    static $debug;
    static $errors = [0]; # cnt_error

    protected $ghost = false;
    protected $except = false;

    function __construct() {
        ob_get_level() && ob_end_clean();
        $this->constants();
        SKY::$debug = DEBUG;
        SKY::$cli = CLI;
        date_default_timezone_set(PHP_TZ);
        mb_internal_encoding(ENC);
        define('NOW', date(DATE_DT));
        srand((double) microtime() * 1e6);
        require DIR_S . '/w2/plan.php';
        spl_autoload_register('Plan::_autoload', true, true);

        set_error_handler(function ($no, $message, $file, $line, $context = null) {
            $this->error_last = $no;
            $this->was_error |= SKY::ERR_DETECT;
            if (error_reporting() & $no && (SKY::$debug || !SKY::$dd || $this->s_prod_error)) {
                $error = Debug::error_name($no);
                trace(["PHP $error", "$error: $message"], true, $line, $file, $context);
            }
            return true;
        });

        set_exception_handler(function ($e) {
            preg_match("/^(\d{1,3}) ?(.*)$/", $mess = $e->getMessage(), $m);
            $this->except = [
                'name' => $name = get_class($e),
                'crash' => $crash = 'Stop' != $name && ('Exception' != $name || !SKY::$dd || !$this->s_quiet_eerr),
                'code' => $m ? $m[1] : 0,
                'mess' => $m ? $m[2] : $mess,
                'title' => $title = "Thrown $name($mess)",
            ];
            if ('Stop' == $name && !$mess)
                $this->tailed = true;
            trace($crash ? [$title, $title] : $title, $crash, $e->getLine(), $e->getFile(), $e->getTraceAsString());
        });

        register_shutdown_function([$this, 'shutdown']);
    }

    function load() {
        global $argv;
        if (SKY::$dd === false)
            return false;
        try {
            SKY::$dd = SQL::open();
        } catch (Error $e) {
            SKY::$dd = false;
            throw new Error($e->getMessage());
        }
        if (CLI)
            $this->gpc = '$argv = ' . html(var_export($argv, true));
        if (DEV)
            DEV::init();

        $this->memory(3, 's');
        if ($this->s_prod_error || SKY::$debug)
            ini_set('error_reporting', -1);
        $this->trace_cli = $this->s_trace_cli;
        if (Plan::$put_cache)
            Plan::main();
        return SKY::$dd;
    }

    function memory($id = 9, $char = 'n', $dd = null) {
        if (!isset(SKY::$mem[$char])) {
            if (false === SKY::$dd)
                return '';
            $dd or $dd = SKY::$dd;
            $dd or $dd = $this->load();
            if (!$dd)
                return '';
            
            list($dt, $imemo, $tmemo) = $dd->sqlf('-select dt, imemo, tmemo from $_memory where id=' . $id);
            SKY::ghost($char, $tmemo, 'update $_memory set dt=$now, tmemo=%s where id=' . $id, 0, $dd);
            if (9 == $id && defined('WWW') && 'n' == $char)
                Schedule::setWWW($this->n_www);
        }
        return SKY::$mem[$char][3];
    }

    function __get($name) {
        $pre = substr($name, 0, 2);
        if ('k_' == $pre) {
            return array_key_exists($name, SKY::$vars) ? SKY::$vars[$name] : '';
        } elseif (2 == strlen($pre) && '_' == $pre[1]) {
            $name = substr($name, 2);
            $char = $pre[0];
            return array_key_exists($name, SKY::$mem[$char][3]) ? SKY::$mem[$char][3][$name] : '';
        }
        return array_key_exists($name, SKY::$reg) ? SKY::$reg[$name] : '';
    }

    function __set($name, $value) {
        $pre = substr($name, 0, 2);
        if ('k_' == $pre) {
            SKY::$vars[$name] = $value;
        } elseif (2 == strlen($pre) && '_' == $pre[1]) {
            SKY::$mem[$char = $pre[0]][0] |= 1; # set flag
            SKY::$mem[$char][3][substr($name, 2)] = $value;
        } else {
            SKY::$reg[$name] = $value;
        }
    }

    function __call($char, $args) {
        SKY::__callStatic($char, $args);
    }

    static function __callStatic($char, $args) {
        if (1 != strlen($char))
            return trace("Method SKY::$char not found", true, 1);
        $exists = isset(SKY::$mem[$char]);
        if (!$args)
            return $exists;
        $exists or SKY::$mem[$char] = [0, null, $args[1] ?? '', [], SKY::$dd];
        $x =& SKY::$mem[$char];
        if (is_array($k = $args[0])) {  # s - system conf
            $x[3] = $k + $x[3];         # a - conf for root-admin section
            return $x[0] |= 1;          # n - cron conf
        }                               # u - user
        if (1 == count($args))          # i,j - used in Language class
            return $x[3][$k] ?? '';     # d - development conf
        $v = $args[1];
        if (is_null($k)) {
            if ($exists)
                if (is_array($v)) $x[2] = $v + $x[2]; else unset($x[2][$v]);
            return $x[0] |= 2;
        }
        if (is_null($v)) {
            unset($x[3][$k]);
        } else {
            $x[3][$k] = $v;
        }
        return $x[0] |= 1;
    }

    static function &ghost($char, $packed, $tpl = '', $flag = 0, $dd = null) {
        SKY::$mem[$char] = [$flag, $flag & 4 ? null : $packed, $tpl, [], $dd ?? SKY::$dd];
        if (SKY::$debug && $tpl)
            trace(is_array($tpl) ? end($tpl) : (DEV && $tpl instanceof Closure ? Debug::closure($tpl) : $tpl), 'GHOST', 1);
        if ($packed) foreach (explode("\n", unl($packed)) as $v) {
            list($k, $v) = explode(' ', $v, 2);
            SKY::$mem[$char][3][$k] = escape($v, true);
        }
        return SKY::$mem[$char][3];
    }

    static function sql($char, $return = true) {
        $x =& SKY::$mem[$char];
        if ('s' == $char && !$x[1]) # protect if SQL select failed
            return;
        $flag = $x[0];
        $x[0] = 0; # reset flags
        if ($f1 = $flag & 1) { # sky-memory
            $new = array_join($x[3], function($k, $v) {
                return $k . ' ' . escape($v);
            });
            $new === $x[1] ? ($f1 = 0) : ($x[1] = $new);
            if ($x[2] instanceof Closure)
                return $f1 ? $x[2]($new) : null;
        }
        if (is_array($x[2])) { # type 2
            if ($flag & 2 | $f1) {
                $query = end($x[2]);
                $key = key($x[2]);
                if ($f1) {
                    $x[2][$key] = $new;
                } else {
                    unset($x[2][$key]);
                }
                if ($query && $x[2])
                    return $return ? $x[4]->qp($query, $x[2]) : $x[4]->sql($query, $x[2]);
                return $x[2];
            }
        } elseif ($f1) {
            return $return ? $new : $x[4]->sqlf($x[2], $new);
        }
    }

    static function lang($lg, $page = false) {
        define('LG', $lg);
        SKY::$reg['trans_late'] = Plan::_r("lng/$lg.php");
        if (SKY::$reg['lg_page'] = $page)
            SKY::$reg['trans_late'] += Plan::_r("lng/{$lg}_$page.php");
        if (DEV)
            SKY::$reg['trans_coll'] = [];
    }

    static function date($in, $hm = true) {
        if (!$in)
            return '';
        if (is_string($in))
            $in = strtotime($in);
        return date(SKY::s('date_format') ?: 'd.m.Y' . ($hm ? ' H:i' : ''), $in);
    }

    function log($mode, $data) {
        if (!in_array(SKY::s('test_mode'), [$mode, 'all']))
            return;
        $new = date(DATE_DT) . " $mode $data\n";
        SKY::$dd->sqlf('update $_memory set dt=' . SKY::$dd->f_dt() . ', tmemo=substr(' . SKY::$dd->f_cc('%s', 'tmemo') . ',1,15000) where id=10', $new);
    }

    static function version() : array {
        global $sky;
        $core = explode(' ', SKY::CORE);    # timestamp, CS-ver, APP-ver, APP-name
        $app = explode(' ', $sky->s_version) + [time(), $core[0], '0.0001', 'APP'];
        $len = strlen(substr($app[2], 1 + strpos($app[2], '.')));
        $app[3] = ($len < 3 ? '' : ($len < 4 ? 'βῆτα.' : 'ἄλφα.')) . "$app[2].$app[3].SKY.";
        return [
            'core' => $core,
            'app' => $app,
        ];
    }

    function check_other($class_debug = true) {
        $error = '';
 #       if ('utf8' != mysqli_character_set_name($this->conn))
  #          $error = '<h1>wrong database character set</h1>'; # cannot use trace() to SQL insert..
     #   if ($class_debug && SKY::$debug)
      #      Debug::check_other($error);
        return $error;
    }

    function constants() {
        define('ENC', 'UTF-8');
        define('CLI', 'cli' == PHP_SAPI);
        define('zebra', 'return @$i++ % 2 ? \'bgcolor="#eee"\' : "";');
        define('DATE_DT', 'Y-m-d H:i:s');
        define('I_YEAR', 365 * 24 * 3600);
        define('span_r', '<span style="color:red">%s</span>');
        define('span_g', '<span style="color:#2b3">%s</span>');
        define('span_b', '<span style="color:blue">%s</span>');
        define('RE_LOGIN', '/^[a-z][a-z_\d]{1,19}$/i');
        define('RE_PASSW', '/^\S{3,15}$/');
        define('RE_EMAIL', '/^([\w\-]+\.)*[\w\-]+@([\w\-]+\.)+[a-z]{2,7}$/i');
        define('RE_PHONE', '/^\+?\d{10,12}$/');
        define('TPL_FORM',   '<dl><dt>%s</dt><dd>%s</dd></dl>');
        define('TPL_CHECKBOX', '<input type="checkbox" name="id[]" value="%s"%s />');
        define('TPL_HIDDEN', '<input type="hidden" name="%s" value="%s" />');
        define('TPL_META',   '<meta name="%s" content="%s" />');
    }

    function tail_ghost() {
        $this->ghost = true;
        foreach (SKY::$mem as $char => &$v)
            $v[0] && $v[2] && SKY::sql($char, false);
    }

    function tracing($plus = '', $trace_x = false) {
        $plus .= "\nDIR: " . DIR . "\n$this->tracing$this->gpc";
        $plus .= sprintf("\n---\n%s: script execution time: %01.3f sec, SQL queries: " . SQL::$query_num, NOW, microtime(true) - START_TS);
        if (DEV)
            $plus .= DEV::trace(); // move to HEAVEN::tracing(..) ?
        if ($trace_x && SKY::$dd) {
            if (DEV)
                SKY::$dd->_xtrace();
            SKY::$dd->sqlf('update $_memory set tmemo=%s where id=1', $plus);
        }
        return $plus;
    }

    function shutdown($plus = false, $z_fly = false) {
        chdir(DIR);
        Plan::$ware = 'main';
        $dd = SQL::$dd = SKY::$dd; # set main database driver if loaded
        foreach ($this->shutdown as $object)
            call_user_func([$object, 'shutdown']);

        $e = error_get_last();
        if ($e && $e['type'] != $this->error_last) {
            $error = Debug::error_name($e['type']);
            trace(["PHP $error", "$error: $e[message]"], true, $e['line'], $e['file']);
        }
        # run tail_ghost() if !$this->tailed
        $this->ghost or $this->tail_ghost();

        if ($z_fly && SKY::$errors[0])
            Plan::cache_p('dev_z_err', NOW); # popup error for file downloads on next click

        if ($dd && $this->error_prod) # write error log
            sqlf('update $_memory set dt=' . $dd->f_dt() . ', tmemo=substr(' . $dd->f_cc('%s', 'tmemo') . ', 1, 5000) where id=4', $this->error_prod);

        if (!$this->tailed) { # if script exit in advance (with exit() or throw new Error())
            $plus = SKY::$debug ? "x autotrace\n\n" : '';
            SKY::$cli ? ($this->was_error || $this->trace_cli) && $this->tracing($plus, true) : $this->tail_force($plus);
            $this->tailed = true;
        } elseif ($plus) {
            $this->tracing($plus, true); # for jumps
        }
        SQL::close();
    }
}


//////////////////////////////////////////////////////////////////////////
if (!class_exists('Error', false)) {
    # Use when exception is caused by programmer actions. Assume like crash, `throw new Error` should never works!
    class Error extends Exception {}
}
# Assume like stop and not a crash
class Stop extends Exception {}

# use exception `Exception`, `die` when exceptional situation is caused by events of the outside world. Configure as crash or not.

interface Cache_driver
{
    function info();
    function setup($obj);
    //function close();
    function test($name);
    function get($name);
    function run($name);
    function mtime($name);
    function put($name, $data, $is_append = false);
    function glob($mask = '*');
    function drop($name);
    function drop_all($mask = '*');
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

    function __construct(Array $e) {
        $this->e = $e;
    }

    function __get($name) {
        return $this->e[$name] ?? null;
    }

    function __call($name, $args) {
        return isset($this->e[$name]) ? call_user_func_array($this->e[$name], $args) : null;
    }

    function __invoke($in) {
        call_user_func($this->e['row_c'], $in, $this);
        $this->state = 0;
        return $this;
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

    function rewind() {
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
            if (!($sql instanceof SQL))
                return $this->state++;
            $this->dd = $sql->_dd;
        }
        $this->max_i = $this->e['max_i'] ?? 500; # -1 is infinite
        $this->next();
    }

    function valid() {
        if ($this->state > 1)
            return false;
        if ($this->row)
            return true;
        $this->state++;
        if (isset($this->e['after_c']))
            call_user_func($this->e['after_c'], $this->i);
        return false;
    }

    function current() {
        return $this->row;
    }

    function key() {
        return $this->i;
    }

    function next() {
        global $sky;
        $exit = function ($fail) {
            $this->row = false;
            $this->i--;
            if ($this->dd)
                $this->dd->free($this->e['query']->stmt);
            if ($fail)
                throw new Error("eVar cycle error");
        };
        do {
            if ($this->i++ >= $this->max_i && -1 != $this->max_i)
                return $exit(!isset($this->e['max_i']));
            if ($this->dd)
                $this->row = $this->dd->one($this->e['query']->stmt, 'O');
            $x = false;
            if (isset($this->e['row_c']) && $this->row) {
                $sky->in_row_c = true;
                $this->row->__i = $this->i;
                $x = call_user_func_array($this->e['row_c'], [&$this->row]);
                $sky->in_row_c = false;
                if (false === $x)
                    return $exit(false);
            }
        } while (true === $x);
        if (!$this->dd)
            $this->row = $x ? (object)$x : false;
    }
}

function trace($var, $is_error = false, $line = 0, $file = '', $context = null) {
    global $sky;

    if ($err = true === $is_error) {
        $sky->was_error |= SKY::ERR_DETECT;
        if (null === SKY::$dd)
            $sky->load();
        if (SKY::$errors[0]++ > 99) {
            $sky->tracing("Error 500", true);
            throw new Error("500 Internal SKY error");
        }
    }
    if (SKY::$debug || $sky->s_prod_error && $err) {
        if ($err && is_array($var)) {
            list ($title, $var) = $var;
        }
        is_string($var) or $var = var_export($var, true);
        $is_warning = 'WARNING' === $is_error;
        if (is_string($is_error)) {
            $var = "$is_error: $var";
            $is_error = false;
        }
        if ($has_depth = !$file) {
            $depth = 1 + $line;
            $db = debug_backtrace();
            list ($file, $line) = array_values($db[$line]);
            if (is_array($line)) { # file-line don't supported
                list ($file, $line) = array_values($db[$depth - 2]);
                $depth--;
                $fln = sprintf(span_r, "<span>$file^$line</span>");
            }
        }
        isset($fln) or $fln = "<span>$file^$line</span>";
        $mgs = "$fln\n" . html($var);
        if ($err) {
            $sky->was_error |= SKY::ERR_SHOW;
            if (SKY::$cli)
                echo "\n$file^$line\n$var\n\n";
            if ($sky->s_prod_error) { # collect error log
                $type = SKY::$cli ? 'console' : ($sky->is_front ? 'front' : 'admin');
                $sky->error_prod .= sprintf(span_r, '<b>' . NOW . ' - ' . $type . '</b>');
                if (!SKY::$cli)
                    $sky->error_prod .= ' uri: ' . html(URI);
                $sky->error_prod .= "\n$mgs\n\n";
            }
        }
        if (!SKY::$debug)
            return; # else collect tracing
        if ($err) {
            $sky->tracing .= "$fln\n" . '<div class="error">' . html($var) . "</div>\n";
            if (!DEV)
                return;
            if (is_string($context)) {
                $backtrace = html($context);
                $context = null;
            } else {
                ob_start();
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $backtrace = html(ob_get_clean());
            }
            Debug::epush($title ?? 'User Error', "$mgs\n\n$backtrace", $context, $has_depth ? $depth : 2);
        } elseif ($is_warning) {
            $sky->was_warning = 1;
            $sky->tracing .= "$fln\n" . '<div class="warning">' . html($var) . "</div>\n";
        } else {
            $sky->tracing .= "$mgs\n\n";
        }
    }
}

function sql(...$in) {
    $sql = $in[0] instanceof SQL ? $in[0] : new SQL($in, 'parseT');
    return $sql->exec();
}

function qp(...$in) { # Query Part, Query Parse
    $in or $in = [''];
    return new SQL($in, 'parseT');
}

function sqlf(...$in) { # just more quick parsing, using printf syntax. No SQL injection!
    $sql = new SQL($in, 'parseF');
    return $sql->exec();
}

/* function table(...$in) {
    $sql = new SQL($in, 'init_qb');
   $sql->table($sql->qstr);
   $sql->qstr = '';
    return $sql;
}*/

function html($str, $hide_percent = false, $mode = ENT_COMPAT) {
    $str = htmlspecialchars($str, $mode, ENC);
    return $hide_percent ? str_replace('%', '&#37;', $str) : $str;
}

function unhtml($str, $mode = ENT_QUOTES) {
    return html_entity_decode($str, $mode, ENC);
} # list($month, $day, $year) = sscanf('Январь 01 2000', "%s %d %d");

function escape($in, $reverse = false) {
    $ary = ["\\" => "\\\\", "\r" => "\\r", "\n" => "\\n"];
    return strtr($in, $reverse ? array_flip($ary) : $ary);
}

function strand($n = 23) {
    $str = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRSTUVWXYZ2345679'; # length == 53
    if ($n != 7) $str .= 'o0Ol1iIB8'; # skip for passwords (9 chars)
    for ($ret = '', $i = 0; $i < $n; $i++, $ret .= $str[rand(0, 7 == $n ? 52 : 61)]);
    return $ret;
}

function unl($str) {
    return str_replace(["\r\n", "\r"], "\n", $str);
}

function strcut($str, $n = 100) { #300
    $text = mb_substr($str, 0, $n);
    return mb_strlen($str) > $n
        ? trim(mb_substr($text, 0, mb_strrpos($text, ' ', 0) - mb_strlen($text)), '.,?!') . '&nbsp;...'
        : $text;
}

function array_explode($str, $via1 = ' ', $via2 = "\n") {
    $ary = explode($via2, $str);
    $out = [];
    array_walk($ary, function($item) use (&$out, $via1) {
        list ($k, $v) = explode($via1, $item, 2);
        $out[$k] = $v;
    });
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
    return '0' === $v || ctype_digit($v) && ('0' !== $v[0] || $zero);
}
