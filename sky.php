<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

//////////////////////////////////////////////////////////////////////////
class SKY
{
    const ERR_DETECT = 1;
    const ERR_SHOW   = 3;

    public $tracing = '';
    public $error_prod = '';
    public $errors = '';
    public $error_no = 0;
    public $error_last = 0;
    public $was_error = 0;
    public $cnt_error = 0;
    public $cli;
    public $gpc = '';
    public $lg = DEFAULT_LG;
    public $shutdown = [];

    static $mem = [];
    static $reg = [];
    static $vars = [];
    static $databases;
    static $dd = false;

    protected $ghost = false;
    protected $except = false;

    function __construct() {
        ob_get_level() && ob_end_clean();
        $this->debug = DEBUG;
        $this->constants();
        $this->cli = CLI;
        date_default_timezone_set(PHP_TZ);
        define('NOW', date(DATE_DT));
        srand((double) microtime() * 1e6);

        spl_autoload_register(function ($name) {
            trace("autoload($name)");
            if (strpos($name, '\\')) # `vendor` folder autoloader
                return;
            if (in_array(substr($name, 0, 2), ['m_', 'q_', 't_'])) {
                is_file($file = "main/app/$name.php") ? require $file : eval("class $name extends Model_$name[0] {}");
            } elseif (is_file($file = DIR_S . '/w2/' . ($name = strtolower($name)) . '.php')) {
                require $file; # wing2 folder
            } else {
                require "main/w3/$name.php";
            }
            return true;
        }, true, true);

        set_error_handler(function ($no, $message, $file, $line, $context = null) {
            $this->error_last = $no;
            if (error_reporting() & $no && ($this->debug || !SKY::$dd || $this->s_prod_error)) {
                $this->error_title = 'PHP ' . ($err = Debug::error_name($no));
                trace("$err: $message", true, $line, $file, $context);
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
                'title' => $this->error_title = "Exception $name($mess)",
            ];
            if ('Stop' == $name && !$mess)
                $this->tailed = true;
            global $user;
            $etc = $m && 22 == $m[1] ? isset($user) && implode("\n", $user->jump_path) : $e->getTraceAsString();
            trace("$this->error_title\n$etc", $crash, $e->getLine(), $e->getFile());
            $this->error_title = "Exception $name($mess)";
        });

        register_shutdown_function([$this, 'shutdown']);
    }

    function init_h($vendor = false) {
        if ($vendor)
            require 'vendor/autoload.php';
    }

    function shutdown() {
        chdir(DIR); # restore dir!
        SQL::$dd = SKY::$dd; # set main database driver if SKY loaded
        foreach ($this->shutdown as $object)
            call_user_func([$object, 'shutdown']);

        $e = error_get_last();
        //if ($e && $e['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR)) {
        if ($e && $e['type'] != $this->error_last) {
            $err = Debug::error_name($e['type']);
            trace("$err: $e[message]", true, $e['line'], $e['file']);
            $this->error_title = "PHP $err";
        }

        $this->ghost or !SKY::$dd or $this->tail_ghost(!$this->cli && !$this->tailed);
        if ($this->error_prod) # write error log
            sqlf('update $_memory set dt=' . SKY::$dd->f_dt()
                . ', tmemo=substr(' . SKY::$dd->f_cc('%s', 'tmemo') . ', 1, 5000) where id=4', $this->error_prod);

        if (!$this->tailed) { # if script exit in advance (with exit() or throw new Err())
            $plus = $this->debug ? "x autotrace\n\n" : '';
            $this->cli ? ($this->was_error || $this->trace_cli) && $this->tracing($plus, true) : $this->tail_force($plus);
            $this->tailed = true;
        }
        SQL::close();
    }

    function load() {
        global $argv;
        SKY::$dd = SQL::open();
        if (CLI)
            $this->gpc = '$argv = ' . html(var_export($argv, true));
        if (DEV)
            Ext::init();

        $this->memory(3, 's');
        $this->debug |= (int)$this->s_trace_single;
        if ($this->s_prod_error || $this->debug)
            ini_set('error_reporting', -1);
        $this->trace_cli = $this->s_trace_cli;
    }

    function memory($id = 9, $char = 'n', $table = 'memory') {
        if (!isset(SKY::$mem[$char])) {
            SKY::$dd or $this->load();
            list($dt, $imemo, $tmemo) = sqlf('-select dt, imemo, tmemo from $_memory where id=' . $id);
            SKY::ghost($char, $tmemo, 'update $_memory set dt=$now, tmemo=%s where id=' . $id);
            if (9 == $id && defined('WWW') && 'n' == $char && 'memory' == $table)
                Schedule::setWWW($this->n_www);
        }
    }

    function tail_ghost($alt = false) {
        if ($this->lock_table)
            sql('unlock tables');
        if ($this->s_trace_single)
            $this->s_trace_single = 0; # single click done
        foreach (SKY::$mem as $char => &$v)
            $v[0] && $v[2] && SKY::sql($char);
        $this->ghost = true;
    }

    function __get($name) {
        $pre = substr($name, 0, 2);
        if ('k_' == $pre) {
            return array_key_exists($name, SKY::$vars) ? SKY::$vars[$name] : '';
        } elseif (2 == strlen($pre) && '_' == $pre[1] && isset(SKY::$mem[$char = $pre[0]])) {
            $name = substr($name, 2);
            return array_key_exists($name, SKY::$mem[$char][3]) ? SKY::$mem[$char][3][$name] : '';
        }
        return array_key_exists($name, SKY::$reg) ? SKY::$reg[$name] : '';
    }

    function __set($name, $value) {
        $pre = substr($name, 0, 2);
        if ('k_' == $pre) {
            SKY::$vars[$name] = $value;
        } elseif (2 == strlen($pre) && '_' == $pre[1] && isset(SKY::$mem[$char = $pre[0]])) {
            SKY::$mem[$char][0] = 1; # sqlf only, flag cannot be =2
            SKY::$mem[$char][3][substr($name, 2)] = $value;
        } else {
            SKY::$reg[$name] = $value;
        }
    }

    function __call($name, $args) {
        is_object($this->extend) or trace("Method \$sky->$name not found", true, 1);
        return call_user_func_array([$this->extend, $name], $args);
    }

    static function __callStatic($char, $args) {
        if (1 != strlen($char))
            return trace("Method SKY::$char not found", true, 1);
        if (!$args)
            return SKY::sql($char, true);
        $k = $args[0];
        $v = isset($args[1]) ? $args[1] : null;
        $old = isset(SKY::$mem[$char]) or SKY::$mem[$char] = [0, null, $v, []];
        $ary =& SKY::$mem[$char];    # s - system conf (hight load) for `read` usage mainly
        $flag = 1;                   # a - admin conf
        if (is_array($k)) {          # n - cron conf or low load usage (used in w2/gate.php also)
            $ary[3] = $k + $ary[3];  # v - visitor
        } elseif (is_null($v)) {     # u - user
            unset($ary[3][$k]);      # i,j,k - Language data
        } elseif (is_null($k)) {
            if ($old) {
                if (is_array($v)) $ary[2] = $v + $ary[2]; else unset(SKY::$mem[$char][2][$v]);
            }
            $flag = 2;
        } else {
            $ary[3][$k] = $v;
        }
        return $ary[0] |= $flag;
    }

    static function &ghost($char, $original, $tpl = '', $flag = 0) {
        SKY::$mem[$char] = [$flag, $flag & 4 ? null : $original, $tpl, []];
        if ($tpl)
            trace('GHOST SQL: ' . (is_array($tpl) ? end($tpl) : $tpl), false, 1);
        if ($original) foreach (explode("\n", unl($original)) as $v) {
            list($k, $v) = explode(' ', $v, 2);
            SKY::$mem[$char][3][$k] = escape($v, true);
        }
        return SKY::$mem[$char][3];
    }

    static function sql($char, $return = false) {
        $x =& SKY::$mem[$char];
        $flags =& $x[0];
        if ($f1 = $flags & 1) {
            $new = array_join($x[3], function($k, $v) {
                return "$k " . escape($v);
            });
            if ($new === $x[1]) $f1 = 0; else $x[1] = $new;
        }
        if ($f2 = is_array($x[2])) {
            if (!$f2 = $flags & 2 | $f1)
                return $flags = 0;
            $query = end($x[2]);
            $key = key($x[2]);
            if ($f1) {
                $x[2][$key] = $new;
            } else {
                unset($x[2][$key]);
            }
            $new = $query && $x[2] ? qp($query, $x[2]) : false;
        }
        $flags = 0; # reset flags
        if ($return)
            return $new;
        if ($f2)
            return $new && sql($new);
        if ($f1)
            sqlf($x[2], $new);
    }

    static function date($in = 0, $hm = true) {
        global $sky;
        if (!$in)
            return '';
        if (is_string($in))
            $in = strtotime($in);
        return date($sky->s_date_format ? $sky->s_date_format : 'd.m.Y' . ($hm ? ' H:i' : ''), $in); # :s
    }

    function tracing($plus = '', $trace_x = false) {
        $plus .= "\nDIR: " . DIR . "\n$this->tracing$this->gpc";
        $plus .= sprintf("\n---\n%s: script execution time: %01.3f sec, SQL queries: " . SQL::$query_num, NOW, microtime(true) - START_TS);
        if ($trace_x) {
            if (DEV) {
                $plus .= Ext::trace();
                SKY::$dd->_xtrace();
            }
            sqlf('update $_memory set tmemo=%s where id=1', $plus);
        }
        return $plus;
    }

    function check_other($class_debug = true) {
        $error = '';
 #       if ('utf8' != mysqli_character_set_name($this->conn))
  #          $error = '<h1>wrong database character set</h1>'; # cannot use trace() to SQL insert..
     #   if ($class_debug && $this->debug)
      #      Debug::check_other($error);
        return $error;
    }

    function constants() {
        define('zebra', 'return @$i++ % 2 ? \'bgcolor="#eee"\' : "";');
        define('DATE_DT', 'Y-m-d H:i:s');
        define('I_YEAR', 365 * 24 * 3600);
        define('span_r', '<span style="color:red">%s</span>');
        define('span_g', '<span style="color:green">%s</span>');
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

    const CORE = '0.115 2021-05-24T07:01:11+03:00 energy';

    static function version() {
        global $sky;
        $core = explode(' ', SKY::CORE);    # timestamp CS-ver   APP-ver
        $sky->s_version or $sky->s_version = time() . " $core[0] 0.0001";
        $app = explode(' ', $sky->s_version, 3);
        $len = strlen(substr($app[2], 1 + strpos($app[2], '.')));
        $app[3] = $app[2] . ($len < 3 ? '' : ($len < 4 ? '-beta' : '-alfa'));
        return [
            'core' => $core,
            'app' => $app,
        ];
    }
}


//////////////////////////////////////////////////////////////////////////
class Err extends Exception {} # Use when exception is caused by programmer actions. Assume like crash, `throw new Err` should never works!
class Stop extends Exception {} # Assume like stop and not a crash
# use exception `Exception`, `die` when exceptional situation is caused by events of the outside world. Configure as crash or not.

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

    function row() {
        if ($this->state > 1)
            return false;
        $this->state ? $this->next() : $this->rewind();
        return $this->valid() ? $this->row : false;
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
        $exit = function ($fail) {
            if ($this->dd)
                $this->dd->free($this->e['query']->stmt);
            $this->row = false;
            if ($fail)
                throw new Err("eVar cycle error");
        };
        do {
            if ($this->i++ >= $this->max_i && -1 != $this->max_i)
                return $exit(!isset($this->e['max_i']));
            if ($this->dd)
                $this->row = $this->dd->one($this->e['query']->stmt, 'O');
            $x = false;
            if (isset($this->e['row_c']) && $this->row) {
                $this->row->__i = $this->i;
                $x = call_user_func_array($this->e['row_c'], [&$this->row]);
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

    //if (!isset($sky))        return;
    if (true === $is_error) {
        $sky->was_error |= SKY::ERR_DETECT;
        SKY::$dd or $sky->load();
        if (++$sky->cnt_error > 99) {
            $sky->tracing("Error 500", true);
            throw new Err("500 Internal SKY error");
        }
    }
    if ($sky->debug || $sky->s_prod_error && true === $is_error) {
        is_string($var) or $var = var_export($var, true);
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
        $error = "$fln\n" . html($var);
        if ($is_error) {
            $sky->was_error |= SKY::ERR_SHOW;
            if ($sky->cli)
                echo "\n$file^$line\n$var\n\n";
            if ($sky->s_prod_error) { # collect error log
                $type = $sky->cli ? 'console' : ($sky->is_front ? 'front' : 'admin');
                $sky->error_prod .= sprintf(span_r, '<b>' . NOW . ' - ' . $type . '</b>');
                if (!$sky->cli)
                    $sky->error_prod .= ' uri: ' . html(URI);
                $sky->error_prod .= "\n$error\n\n";
            }
        }
        if (!$sky->debug)
            return; # else collect tracing
        if ($is_error) {
            $sky->tracing .= "$fln\n" . '<div class="error">' . html($var) . "</div>";
            ob_start();
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $backtrace = html(ob_get_clean());
            if ($sky->is_front || $sky->ajax) {
                $sky->error_title or $sky->error_title = 'User Error';
                $sky->errors .= "<h1>$sky->error_title</h1><pre>$error\n\n$backtrace</pre>";
                $sky->error_title = '';
                $str = Debug::context($context, $has_depth ? $depth : 2) and $sky->errors .= "<pre>$str</pre>";
            } else {
                $sky->tracing .= "BACKTRACE:\n$backtrace";
                if (!$sky->cli && !$sky->ajax && !$sky->s_trace_single) {
                    printf(span_r, "<br /><b>SKY:</b> " . html($var) . " at <b>$file</b> on line <b>$line</b>");
                }
            }
            $sky->tracing .= "\n";
        } else {
            $sky->tracing .= "$error\n\n";
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

function table(...$in) {
    $sql = new SQL($in, 'init_qb');
   $sql->table($sql->qstr);
   $sql->qstr = '';
    return $sql;
}

function sqlf(...$in) { # just more quick parsing, using printf syntax. No SQL injection!
    //$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);//new Exception();  |DEBUG_BACKTRACE_PROVIDE_OBJECT
    //$trace = $e->getTrace();
    //trace($trace[1]);

    $sql = new SQL($in, 'parseF');
    return $sql->exec();
}

function html($str, $hide_percent = false, $mode = ENT_COMPAT) {
    $str = htmlspecialchars($str, $mode, ENC);
    return $hide_percent ? str_replace('%', '&#37;', $str) : $str;
}

function unhtml($str, $mode = ENT_QUOTES) {
    return html_entity_decode($str, $mode, ENC);
} # list($month, $day, $year) = sscanf('Январь 01 2000', "%s %d %d");

function escape($in, $reverse = false, $chars = "\\\n") {
    $src = str_split($chars);
    $dst = array_map(function($v) use ($src) {
        return $src[0] . ("\n" == $v ? 'n' : $v);
    }, $src);
    return strtr($in, $reverse ? array_combine($dst, $src) : array_combine($src, $dst));
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

function strcut($str, $n = 300) {
    $text = mb_substr($str, 0, $n, ENC);
    return mb_strlen($str, ENC) > $n
        ? trim(mb_substr($text, 0, mb_strrpos($text, ' ', 0, ENC) - mb_strlen($text, ENC), ENC), '.,?!') . '&nbsp;...'
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
