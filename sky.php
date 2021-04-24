<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license
# Filename: unique

function trace($var, $is_error = false, $line = 0, $file = '', $context = null) {
    global $sky;

    if (!isset($sky))
        return;
    if (true === $is_error) {
        $sky->was_error |= SKY::ERR_DETECT;
        if (!$sky->loaded)
            $sky->load();
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
            if ($sky->loaded && $sky->s_prod_error) { # collect error log
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

function sql() {
    $in = func_get_args();
    $sql = $in[0] instanceof SQL ? $in[0] : new SQL($in);
    $qstr = (string)$sql; # build sql string
    if (SQL::NO_EXEC & $sql->mode)
        return $qstr;
    if ($sql->error)
        return false;
    if (false !== strpos('+-~>^@%#', $char = $qstr[0])) {
        $qstr = substr($qstr, 1);
    } else {
        $char = '';
    }
    if ('^' == $char)
        return '$q = sql(' . ++$sql->mode . ',' . var_export($qstr, true) . ');' . $sql->_dd->one(null, 'E');

    global $sky;
    $ts = microtime(true);
    if ($no = $sql->_dd->query($qstr, $sql->stmt))
        $sky->was_error |= SKY::ERR_DETECT;
    if ($sky->debug || $no && $sky->s_prod_error) {
        $ts = microtime(true) - $ts;
        SQL::$query_num++;
        if ($is_error = (bool)$no)
            $sky->error_title = 'SQL Error';
        $depth = SQL::NO_TRACE & $sql->mode ? (int)$is_error : 1 + $sql->mode & 7;
        if ($depth)
            trace(($is_error ? "ERROR in {$sql->_dd->name} - $no: " . $sql->_dd->error() . "\n" : '') . "SQL: $char$qstr", $is_error, $depth);
        if (DEV && Ext::cfg('sql'))
            Ext::ed_sql($qstr, $ts, $depth, $no);
    }

    if ($no)
        return false;
    switch (strtolower(substr($qstr, 0, 6))) {
        case 'delete': case 'update': case 'replac': return $sql->_dd->affected();
        case 'insert': return $sql->_dd->insert_id();
        default: switch ($char) {
            case '+': return $sql->_dd->one($sql->stmt, 'C', true);
            case '-': return $sql->_dd->one($sql->stmt, 'R', true);
            case '~': return $sql->_dd->one($sql->stmt, 'A', true);
            case '>': return $sql->_dd->one($sql->stmt, 'O', true);
            case '@': return $sql->all('R', 1);
            case '%': return $sql->all('A', 1);
            case '#': return $sql->all('O', 1);
            case '&': return new eVar(['query' => $sql]);
        }
    }
    return $sql;
}

function sqlf() { # just more quick parsing, using printf syntax. No SQL injection!
    //$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);//new Exception();  |DEBUG_BACKTRACE_PROVIDE_OBJECT
    //$trace = $e->getTrace();
    //trace($trace[1]);

    $sql = new SQL;
    $in = func_get_args();
    if (is_int($sql->qstr = array_shift($in))) {
        $sql->mode = $sql->qstr;
        $sql->qstr = array_shift($in);
    }
    $sql->mode++; # add 1 depth
    if (SQL::NO_PARSE & $sql->mode)
        return sql($sql);
    if (false !== strpos($sql->qstr, '$'))
        $sql->qstr = $sql->replace_nop(false, $sql->qstr);

    if ($in) $sql->qstr = vsprintf($sql->qstr, array_map(function ($val) use ($sql) {
        $sql->i++;
        return is_array($val)
            ? $sql->array_join($val)
            : ($val instanceof Closure
                ? $val($sql)
                : (is_num($val) ? $val : $sql->_dd->escape($val)));
    }, $in));

    return sql($sql);
}

function qp() { # Query Part, Query Parse
    return new SQL(func_get_args());
}

//////////////////////////////////////////////////////////////////////////
final class SQL # avoid SQL injection !
{
    const _NC  =  8; # no comma in implode arrays
    const _OR  = 16; # implode via "OR"
    const _UPD = 32; # insert in update's style
    const _EQ  = 64;
    const NO_TRACE = 128;
    const NO_PARSE = 256;
    const NO_EXEC  = 512;
    const NO_FUNC = 1024;

    public $mode = 0;
    public $error = false;
    public $stmt = true;
    public $i = 0;
    public $qstr = false;
    public $_dd; # database driver for this object

    static $databases;
    static $query_num = 0;
    static $dd;  # selected database driver

    private static $onduty = 'memory'; # table name without prefix
    private static $re_func = '\B\$([a-z]+)([ \t]*)(\(((?>[^()]+)|(?3))*\))?'; // revision later
    private static $method = ['table', 'columns', 'where', 'join', 'group', 'having', 'order', 'limit'];
    private static $connections = [];

    private $in;
    private $depth = 3; # for constructor (if parse error to show)
    private $qbuild = false;

    function __construct($in = false) {
        if (is_string($in)) {
            SQL::dd_set($in, $this); # single query to different connection
        } else {
            $this->_dd = SQL::$dd;
            if ($in)
                $this->parse($in);
        }
    }

    function __toString() {
        return $this->qstr;
    }

    function __call($name, $args) { # query builder methods
        if (!in_array($name, SQL::$method))
            throw new Err("SQL::$name() - unknown method");
        if (!$this->qbuild)
            $this->qbuild = array_combine(SQL::$method, array_fill(0, count(SQL::$method), []));
        $this->qbuild[$name] = array_merge($this->qbuild[$name], $args[0]);
        return $this;
    }

    static function onduty($table = false) {
        $t = SQL::$onduty;
        false === $table or SQL::$onduty = $table;
        return $t;
    }

    static function close($name = false) {
        if ($name) {
            SQL::$connections[$name]->close();
            unset(SQL::$connections[$name]);
            return;
        }
        foreach (SQL::$connections as $dd)
            $dd->close();
    }

    static function dd_set($in = '', $obj = false) {
        if (isset(SQL::$connections[$in])) {
            $dd = SQL::$connections[$in];
        } else {
            '' === $in ? ($cfg =& SQL::$databases) : ($cfg =& SQL::$databases[$in]);
            $driver = "dd_$cfg[driver]";
            $dd = SQL::$connections[$in] = new $driver($cfg['dsn'], $cfg['pref']);
            unset($cfg['dsn']);
        }
        return $obj ? ($obj->_dd = $dd) : (SQL::$dd = $dd); # set selected database driver
    }

    function one($meth = 'A', $free = false) {
        return $this->_dd->one($this->stmt, $meth, $free);
    }

    function all($meth = 'A', $mode = 0) { # 0-usual 1-c0_is_key
        if ('I' == $meth)
            return new eVar(['query' => $this]);
        $m2 = $meth;
        if ($mode)
            'R' == $m2 or $m2 = 'A';
        $ary = [];
        for ($cnt = 0; $row = $this->_dd->one($this->stmt, $m2); ) {
            if (!$mode) {
                $ary[] = $row;
                continue;
            }
            $cnt or $cnt = count($row);
            $key = current(array_splice($row, 0, 1));
            if (1 == $cnt) {
                $ary[] = $key;
            } else {
                $ary[$key] = 2 == $cnt ? current($row) : ('O' == $meth ? (object)$row : $row);
            }
        }
        return $ary;
    }

    function append() {
        $this->depth = 2;
        $tmp = $this->qstr;
        $this->qstr = $tmp . $this->parse(func_get_args());
        return $this;
    }

    function prepend() {
        $this->depth = 2;
        $tmp = $this->qstr;
        $this->qstr = $this->parse(func_get_args()) . $tmp;
        return $this;
    }

    private function parse($in) {
        $this->error = false;
        if (is_int($this->qstr = array_shift($in))) {
            $this->mode |= $this->qstr;
            $this->qstr = array_shift($in);
        }
        if ($this->qstr instanceof SQL)
            $this->qstr = (string)$this->qstr;
        if (!$in)
            return (SQL::NO_PARSE & $this->mode) || false === strpos($this->qstr, '$')
                ? $this->qstr
                : ($this->qstr = $this->replace_nop(false, $this->qstr));
        $this->in =& $in;
        $this->i = 0;
        $re = '\$_`|\$[@\$\.\+`]|\\\\[1-9]|@@|!!|' . $this->replace_nop();

        if ($this->qstr = preg_replace_callback("~$re~u", [$this, 'replace_par'], $this->qstr))
            count($in) == $this->i or $this->error = 'Placeholder\'s count don\'t match parameters count';
    
        if ($this->error) {
            global $sky;
            $sky->error_title = 'SQL parse Error';
            trace("$this->error\nSQL-x: $this->qstr", true, $this->depth + ($this->mode & 7));
        }
        return $this->qstr;
    }

    function replace_nop($m = false, $str = false) {
        if (!$m) {
            $re = '\$_[a-z_\d]*';
            if (SQL::$re_func && !(SQL::NO_FUNC & $this->mode))
                $re .= '|' . SQL::$re_func;
            return $str ? preg_replace_callback("~$re~u", [$this, __FUNCTION__], $str) : $re;
        } elseif ('_' == $m[0][1]) { # $_ (on-duty table) or $_sometable
            return '`' . $this->_dd->pref . (2 == strlen($m[0]) ? SQL::onduty() : substr($m[0], 2)) . '`';
        }
        if (isset($m[3]) && strpos($m[3], '$'))
            $m[3] = preg_replace_callback('~' . SQL::$re_func . '~u', [$this, __FUNCTION__], $m[3]);
        return Func_parse::one($this->_dd, $m);
    }

    private function replace_par($m) {
        global $sky;

        $c2 = $m[0][1];
        if ('\\' == $m[0][0]) # \1..\9 - backlinks
            return $this->in[$c2 - 1];
        if ('$_`' == $m[0])  # variable table
            return $val = '`' . $this->_dd->pref . str_replace('`', '``', trim($val =& $this->in[$this->i++], '`')) . '`';
        if ('_' == $c2 || ord($c2) > 0x60)
            return $this->replace_nop($m);

        $val =& $this->in[$this->i++];
        if ($this->error)
            return $val = '??'; # run faster
        if (is_bool($val))
            return $val = (int)$val;

        $param = 'Parameter N ' . (1 + $this->i) . ' - ';
        switch ($m[0]) {
            case '$.': # numbers
                if (is_num($val))
                    return $val;
                $sky->debug ? ($this->error = "$param not numeric") : die;
            break;
            case '$+': # string (scalar)
                if (null === $val)
                    return $val = 'NULL';
                if (is_scalar($val))
                    return $val = is_num($val) ? $val : $this->_dd->escape($val);
                $sky->debug ? ($this->error = "$param not a scalar") : die;
            break;
            case '$`': # column of a table, free to use
                if (is_string($val))
                    return $val = '`' . str_replace('`', '``', trim($val, '`')) . '`';
                $this->error = "$param not a string";
            break;
            case '$$': # free to use, parsed sql part
                # ? $this->mode |= $val->mode;
                if ($val instanceof SQL)
                    return $val = (string)$val;
                if (null === $val)
                    return $val = 'NULL';
                if ('' === $val || is_num($val))
                    return $val;
                $this->error = "$param is not instanceof SQL";
            break;
            case '$@':
                if (is_array($val) && is_num(key($val)))
                    return $val = $this->array_join(array_values($val));
                $sky->debug ? ($this->error = "$param not array or key0 not numeric") : die;
            break;
            case '!!': # scalar or array, dangerous, NOT escaped! use it as least as can
                if (is_scalar($val))
                    return $val;
                if ($val instanceof Closure)
                    return $val($this);
                # continue, no break
            case '@@': # array created by programmers, escaped
                if (null === $val)
                    return $val = 'NULL';
                if (is_array($val))
                    return $val = $this->array_join($val, '@@' == $m[0]);
                $this->error = "$param not an array or scalar";
            break;
        }
        return $val = '>>' . gettype($val) . '<<';
    }

    function array_join($ary, $esc = true) {
        $keys = $vals = [];
        array_walk($ary, function ($v, $k) use (&$keys, &$vals, $esc) {
            $param = sprintf('Parameter N %d`%s` - ', 1 + $this->i, $k);
            $char = (string)@$k[0];
            $pref = false;
            if ($char and $pref = (false !== strpos('.+$!`', $char)))
                $k = substr($k, 1);
            if (is_bool($v))
                $v = (int)$v;
            if ('`' == $char)
                $pref = false; # do not change $esc

            if (null === $v) {
                $esc = false;
                $v = 'NULL';
            } elseif (!(is_scalar($v) || $v instanceof SQL)) {
                $this->error = "$param not a scalar";
            } elseif ('+' == $char) {
                $esc = true;
            } elseif ($pref) {
                $esc = false;
                if ('.' == $char && !is_num($v))
                    $this->error = "$param not numeric";
                if ('$' == $char) {
                    if ($v instanceof SQL) $v = (string)$v;
                    elseif ('' !== $v && !ctype_digit((string)$v)) $this->error = "$param not instance of SQL";
                }
            }
            if ($this->error)
                $v = '>>' . gettype($v) . '<<';
            $keys[] = '`' == $char ? '`' . str_replace('`', '``', trim($k, '`')) . '`' : $k;
            if (!$esc && '$now' == $v)
                $v = $this->_dd->f_dt();
            $vals[] = !$esc || is_num($v) ? $v : $this->_dd->escape($v);
        });

        $uir = in_array($char = strtolower($this->qstr[0]), ['i', 'r']); # insert, replace
        if ($uir && !(self::_UPD & $this->mode))
            return '(' . implode(', ', $keys) . ') VALUES (' . implode(', ', $vals) . ')';
        $uir or $uir = 'u' == $char; # update
        $num_key = is_num($keys[0]) && !(self::_NC & $this->mode);

        $str = implode(
            $num_key || $uir ? ', ' : (self::_OR & $this->mode ? ' OR ' : ' AND '),
            array_map(function ($k, $v) use ($uir) {
                return is_num($k) ? $v : ($uir ? "$k = $v" : $k . $v);
            }, $keys, $vals)
        );
        if ($num_key && false !== strpos($str, '$'))
            $str = $this->replace_nop(false, $str);
        
        return $str;
    }
}

function cnt($q, $rows = true) {
    return SQL::$dd->num($q, $rows);
}

function html($str, $hide_percent = false, $mode = ENT_COMPAT) {
    $str = htmlspecialchars($str, $mode, ENC);
    return $hide_percent ? str_replace('%', '&#37;', $str) : $str;
}

function unhtml($str, $mode = ENT_QUOTES) {
    return html_entity_decode($str, $mode, ENC);
} # list($month, $day, $year) = sscanf('Январь 01 2000', "%s %d %d");

function escape($in, $char = false) {
    global $sky;
    if (is_array($in)) return array_map(function($v) use ($sky) {
        return SQL::$dd->escape($v);
    }, $in);
    else $in = (string)$in;
    if (is_string($char)) {
        $dst = array_map(function($v) {
            return "\\\\$v";
        }, $src = str_split($char));
        return strtr($in, array_combine($src, $dst));
    }
    return $char ? SQL::$dd->escape($in, false) : SQL::$dd->escape($in);
}

function unescape($in, $char = false) {
    if (is_string($char)) {
        $src = array_map(create_function('$v', 'return "\\\\$v";'), $dst = str_split($char));
        return strtr($in, array_combine($src, $dst));
    }
    $char or $in = substr($in, 1, -1);
    return strtr($in, ['\0' => "\x00", '\n' => "\n", '\r' => "\r", '\\\\' => "\\", '\\\'' => "'", '\"' => '"', '\Z' => "\x1a"]);
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
        ? trim(mb_substr($text, 0, mb_strrpos($text, ' ', ENC) - mb_strlen($text, ENC), ENC), '.,?!') . '&nbsp;...'
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

//////////////////////////////////////////////////////////////////////////
class Err extends Exception {} # Use when exception is caused by programmer actions. Assume like crash, `throw new Err` should never works!
class Stop extends Exception {} # Assume like stop and not a crash
# use exception `Exception`, `die` when exceptional situation is caused by events of the outside world. Configure as crash or not.

//////////////////////////////////////////////////////////////////////////
class Hook_base { # use it in app/hook.php
    function __call($name, $args) {
        global $sky;

        if ('h_' == substr($name, 0, 2) && method_exists($sky, $name))
            return call_user_func_array([$sky, $name], $args); # call the default processing for the method
        throw new Err("Hook `$name` not exists");
    }
}

//////////////////////////////////////////////////////////////////////////
class SKY
{
    const ERR_DETECT = 1;
    const ERR_SHOW   = 3;

    public $tracing = '';
    public $error_prod = '';
    public $errors = '';
    public $error_no = 0;
    public $was_error = 0;
    public $cnt_error = 0;
    public $loaded = false;
    public $cli;
    public $gpc = '';
    public $lg = DEFAULT_LG;
    public $shutdown = [];

    static $mem = [];
    static $reg = [];
    static $vars = [];

    protected $ghost = false;
    protected $except = false;

    function __construct() {
        if (ob_get_level())
            ob_end_clean();
        $this->debug = DEBUG;
        $this->constants();
        $this->cli = CLI;
        date_default_timezone_set(PHP_TZ);
        define('NOW', date(DATE_DT));
        srand((double) microtime() * 1e6);
        spl_autoload_register([$this, 'autoload']);
        set_error_handler([$this, 'error']);
        set_exception_handler([$this, 'exception']);
        register_shutdown_function([$this, 'shutdown']);
    }

    function load() {
        global $argv;
        if (CLI)
            $this->gpc = '$argv = ' . html(var_export($argv, true));
        $dd = SQL::dd_set();
        require 'main/app/hook.php';
        call_user_func(['hook', get_class($dd)], $dd);
        if (DEV)
            Ext::init();
        list($this->imemo, $tmemo) = sqlf('-select imemo, tmemo from $_ where id=3');
        SKY::ghost('s', $tmemo, 'update $_memory set dt=$now, tmemo=%s where id=3');
        $this->debug |= (int)$this->s_trace_single;
        if ($this->s_prod_error || $this->debug)
            ini_set('error_reporting', -1);
        $this->trace_cli = $this->s_trace_cli;
        $this->loaded = true;
    }

    function autoload($name) {
        trace("autoload($name)");
        if ('_' == $name[1] && in_array($name[0], ['t', 'm'])) {
            is_file($file = "main/app/$name.php") ? require $file : eval("class $name extends Model_$name[0] {}");
        } else {
            is_file($file = DIR_S . '/w2/' . ($name = strtolower($name)) . '.php') ? require $file : require "main/w3/$name.php";
        }
    }

    function error($no, $message, $file, $line, $context = null) { # user_error() not useful due to absent $depth parameter
        if (error_reporting() & $no && ($this->debug || $this->s_prod_error)) {
            $this->error_title = 'PHP ' . ($err = Debug::error_name($no));
            trace("$err: $message", true, $line, $file, $context);
        }
        return true;
    }

    function exception($e) {
        preg_match("/^(\d{1,3}) ?(.*)$/", $mess = $e->getMessage(), $m);
        $this->except = [
            'name' => $name = get_class($e),
            'crash' => 'Stop' != $name && ('Exception' != $name || !$this->s_quiet_eerr),
            'code' => $m ? $m[1] : 0,
            'mess' => $m ? $m[2] : $mess,
            'title' => $this->error_title = "Exception $name($mess)",
        ];
        if ('Stop' == $name && !$mess)
            $this->tailed = true;
        global $user;
        $etc = $m && 22 == $m[1] ? implode("\n", $user->jump_path) : $e->getTraceAsString();
        trace("$this->error_title\n$etc", 'Err' == $name, $e->getLine(), $e->getFile());
        $this->error_title = "Exception $name($mess)";
    }

    function shutdown() {
        chdir(DIR); # restore dir!
        if ($this->loaded)
            SQL::dd_set(); # main database driver
        foreach ($this->shutdown as $object)
            call_user_func([$object, 'shutdown']);
        $e = error_get_last();
        if ($e && $e['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR)) {
            $err = Debug::error_name($e['type']);
            trace("$err: $e[message]", true, $e['line'], $e['file']);
            $this->error_title = "PHP $err";
        }
        $this->ghost or !$this->loaded or $this->tail_ghost(!$this->cli && !$this->tailed);
        if ($this->error_prod) # write error log
            sqlf('update $_memory set dt=$now, tmemo=substr($cc(%s, tmemo),1,5000) where id=4', $this->error_prod);

        if (!$this->tailed) { # if script exit in advance (with exit() or throw new Err())
            $plus = $this->debug ? "x autotrace\n\n" : '';
            $this->cli ? ($this->was_error || $this->trace_cli) && $this->tracing($plus, true) : $this->tail_force($plus);
            $this->tailed = true;
        }
        SQL::close();
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
        if ('s_' == $pre)
            return array_key_exists($name = substr($name, 2), SKY::$mem['s'][3]) ? SKY::$mem['s'][3][$name] : '';
        if ('k_' == $pre)
            return array_key_exists($name, SKY::$vars) ? SKY::$vars[$name] : '';
        return array_key_exists($name, SKY::$reg) ? SKY::$reg[$name] : '';
    }

    function __set($name, $value) {
        $pre = substr($name, 0, 2);
        if ('s_' == $pre) {
            SKY::$mem['s'][0] = 1; # sqlf only, flag cannot be =2
            SKY::$mem['s'][3][substr($name, 2)] = $value;
        } elseif ('k_' == $pre) {
            SKY::$vars[$name] = $value;
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
        $ary =& SKY::$mem[$char];    # s - system conf
        $flag = 1;                   # a - admin conf
        if (is_array($k)) {          # n - cron conf
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
            SKY::$mem[$char][3][$k] = unescape($v, true);
        }
        return SKY::$mem[$char][3];
    }

    static function sql($char, $return = false) {
        $x =& SKY::$mem[$char];
        $flags =& $x[0];
        if ($f1 = $flags & 1) {
            $new = array_join($x[3], function($k, $v) {
                return "$k " . escape($v, true);
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
               // sqlf('update $_memory d left join $_memory s on (s.id= if(d.id=15, 1, 15)) set d.tmemo=s.tmemo where d.id in (15,16)');
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

    const CORE = '0.111 2021-04-21T11:11:11+02:00 energy';

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
