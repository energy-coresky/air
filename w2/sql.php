<?php

//////////////////////////////////////////////////////////////////////////
final class SQL
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
    public $parse_error = false;
    public $stmt = true;
    public $qb_ary = false;
    public $_dd; # database driver for this object

    private $table; # onduty table for this object
    private $quote;

    static $last_error = 0;
    static $query_num = 0;
    static $dd;  # selected database driver
    static $dd_h = 'common_c::dd_h';

    private static $re_func = '\B\$([a-z]+)([ \t]*)(\(((?>[^()]+)|(?3))*\))?'; // revision later
    private static $connections = [];

    private $in;
    private $i = 0;
    private $qstr = false;
    private $depth = 3; # for constructor (if parse error to show)

    function __construct($in, $func = false) {
        $this->_dd = SQL::$dd;
        $tpl = array_shift($in);
        $is_dd = $tpl instanceof DriverDatabase;
        if ($is_dd || $tpl instanceof MVC_BASE) {
            $this->_dd = $is_dd ? $tpl : $tpl->dd();
            $is_dd or $this->table = (string)$tpl;
            $tpl = array_shift($in);
        }
        if (is_int($tpl)) {
            $this->mode = $tpl;
            $tpl = array_shift($in);
        }
        $this->quote = $this->_dd->quote;
        $this->qstr = $tpl;
        $this->in   = $in;
        if ($func)
            $this->$func();
    }

    function __toString() {
        return $this->qstr;
    }

    function build($type, $p2 = false) {
        trace($type,'qb');
        trace($this->qb_ary,'qb');
    }

    function __call($name, $args) { # query builder methods
        static $exec = ['insert', 'update', 'delete', 'replace'];
        if ( in_array($name, $exec)) {
            $this->qb_ary['columns'] = array_merge($this->qb_ary['columns'], $args);
            return $this->build($name);
        }
        static $method =  ['table', 'columns', 'where', 'join', 'group', 'having', 'order', 'limit'];
        if (!in_array($name, $method))
            throw new Error("SQL::$name() - unknown method");

        $this->qb_ary or $this->qb_ary = array_combine($method, array_fill(0, count($method), []));
        $this->qb_ary[$name] = array_merge($this->qb_ary[$name], $args);
        return $this;
    }

    static function open($name = '', $p2 = false) {
        if (isset(SQL::$connections[$name])) {
            $dd = SQL::$connections[$name];
        } else {
            '' === $name && !isset(SKY::$databases['']) ? ($cfg =& SKY::$databases) : ($cfg =& SKY::$databases[$name]);
            $driver = "dd_$cfg[driver]";
            
            $dd = new $driver($cfg['dsn'], $cfg['pref'] ?? '');
            if (!$dd->conn)
                return false;
            $dd->cname = $name;
            SQL::$connections[$name] = $dd;
            trace("name=$name, driver=$dd->name", 'DATABASE');

            unset($cfg['dsn']);
            call_user_func(SQL::$dd_h, $dd, $name);
        }
        return $p2 || !SQL::$dd ? (SQL::$dd = $dd) : $dd;
    }

    static function close($name = false) {
        if ($name) {
            SQL::$connections[$name]->close();
            unset(SQL::$connections[$name]);
        } else {
            foreach (SQL::$connections as $dd)
                $dd->close();
        }
    }

    static function onduty($table) {
        return SQL::$dd->onduty($table);
    }

    function has_result() {
        $s4 = strtolower(substr(trim($this->qstr), 0, 4));
        if (in_array($s4, ['sele', 'expl']))
            return true;
        return $this->_dd->has_result($s4);
    }

    function one($meth = 'A', $free = false) {
        if ($this->qb_ary)
            return $this->build('one', $meth);
        if (true !== $this->stmt)
            return $this->_dd->one($this->stmt, $meth, $free);
    }

    function all($meth = 'A', $mode = null) { # NULL-usual true-c0_is_key
        if ('I' == $meth)
            return new eVar(['query' => $this, 'row_c' => $mode]);

        $m2 = $mode && 'R' != $meth ? 'A' : $meth;
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

    function exec() {
        if ($this->parse_error)
            return false;
      $qstr = (string)$this->qstr; # build sql string
        if (SQL::NO_EXEC & $this->mode)
            return $qstr;

        if (false !== strpos('+-~>^@%#', $char = $qstr[0])) {
            $qstr = substr($qstr, 1);
        } else {
            $char = '';
        }
        if ('^' == $char)
            return '$q = sql(' . ++$this->mode . ',' . var_export($qstr, true) . ');' . $this->_dd->one(null, 'E');

        global $sky;
        $ts = microtime(true);
        if ($no = SQL::$last_error = $this->_dd->query($qstr, $this->stmt))
            $sky->was_error |= SKY::ERR_DETECT;
        if (SKY::$debug || $no && $sky->s_log_error) {
            $this->mode++; # add 1 depth
            $ts = !DEV || ($ts = microtime(true) - $ts) < 0.1 ? '' : sprintf("%01.3f sec ", $ts);
            SQL::$query_num++;
            $is_error = (bool)$no;
            if ($depth = SQL::NO_TRACE & $this->mode ? (int)$is_error : 1 + $this->mode & 7) {
                $msg = "SQL: $ts$char$qstr";
                if ($is_error)
                    $msg = ['SQL Error', "ERROR in {$this->_dd->name} - $no: " . $this->_dd->error() . "\n$msg"];
                trace($msg, $is_error, $depth);
            }
        }

        if ($no)
            return false;
        switch (strtolower(substr($qstr, 0, 6))) {
            case 'delete': case 'update': case 'replac': return $this->_dd->affected();
            case 'insert': return $this->_dd->insert_id();
            default: switch ($char) {
                case '+': return $this->_dd->one($this->stmt, 'C', true);
                case '-': return $this->_dd->one($this->stmt, 'R', true);
                case '~': return $this->_dd->one($this->stmt, 'A', true);
                case '>': return $this->_dd->one($this->stmt, 'O', true);
                case '@': return $this->all('R', true);
                case '%': return $this->all('A', true);
                case '#': return $this->all('O', true);
                case '&': return new eVar(['query' => $this]);
            }
        }
        return $this;
    }

    function append(...$in) {
        $tmp = $this->qstr;
        $this->qstr = $tmp . $this->parseT($in);
        return $this;
    }

    function prepend(...$in) {
        $tmp = $this->qstr;
        $this->qstr = $this->parseT($in) . $tmp;
        return $this;
    }

    private function parseF() {
        if ((SQL::NO_PARSE & $this->mode) && !$this->in)
            return;

        if (false !== strpos($this->qstr, '$'))
            $this->qstr = $this->replace_nop(false, $this->qstr);

        if (!$this->in)
            return;

        $this->qstr = vsprintf($this->qstr, array_map(function ($val) {
            $this->i++;
            return is_array($val)
                ? $this->array_join($val)
                : ($val instanceof Closure
                    ? $val($this)
                    : (is_num($val) ? $val : $this->_dd->escape($val)));
        }, $this->in));
    }

    private function parseT($in = false) {
        $this->parse_error = false;
        if ($in) {
            $this->depth = 2;
            $this->qstr = array_shift($in);
            $this->in = $in;
        }

        if (!$this->in)
            return (SQL::NO_PARSE & $this->mode) || false === strpos($this->qstr, '$')
                ? $this->qstr
                : ($this->qstr = $this->replace_nop(false, $this->qstr));

        $this->i = 0;
        $re = '\$_`|\$[@\$\.\+`]|\\\\[1-9]|@@|!!|' . $this->replace_nop();

        if ($this->qstr = preg_replace_callback("~$re~u", [$this, 'replace_par'], $this->qstr))
            count($this->in) == $this->i or $this->parse_error = 'Placeholder\'s count don\'t match parameters count';
    
        if ($this->parse_error) {
            $ary = ['SQL parse Error', "$this->parse_error\nSQL-x: $this->qstr"];
            trace($ary, true, $this->depth + ($this->mode & 7));
        }
        return $this->qstr;
    }

    function replace_nop($match = false, $str = false) {
        if (!$match) {
            $re = '\$_[a-z_\d]*';
            if (SQL::$re_func && !(SQL::NO_FUNC & $this->mode))
                $re .= '|' . SQL::$re_func;
            return $str ? preg_replace_callback("~$re~u", [$this, __FUNCTION__], $str) : $re;
        } elseif ('_' == $match[0][1]) { # $_ (onduty table) or $_sometable
            $table = 2 == strlen($match[0]) ? ($this->table ? $this->table : (string)$this->_dd) : substr($match[0], 2);
            return $this->quote . $this->_dd->pref . $table . $this->quote;
        }
        if (isset($match[3]) && strpos($match[3], '$'))
            $match[3] = preg_replace_callback('~' . SQL::$re_func . '~u', [$this, __FUNCTION__], $match[3]);
        return Func::replace($match, $this->_dd);
    }

    private function replace_par($match) {
        global $sky;

        $c2 = $match[0][1];
        $q = $this->quote;
        if ('\\' == $match[0][0]) # \1..\9 - backlinks
            return $this->in[$c2 - 1];
        if ('$_`' == $match[0])  # variable table
            return $val = $q . $this->_dd->pref . str_replace($q, $q . $q, trim($val =& $this->in[$this->i++], $q)) . $q;
        if ('_' == $c2 || ord($c2) > 0x60)
            return $this->replace_nop($match);

        $val =& $this->in[$this->i++];
        if ($this->parse_error)
            return $val = '??'; # run faster
        if (is_bool($val))
            return $val = (int)$val;

        $param = 'Parameter N ' . (1 + $this->i) . ' - ';
        switch ($match[0]) {
            case '$.': # numbers
                if (is_num($val))
                    return $val;
                SKY::$debug ? ($this->parse_error = "$param not numeric") : die;
            break;
            case '$+': # string (scalar)
                if (null === $val)
                    return $val = 'NULL';
                if (is_scalar($val))
                    return $val = is_num($val) ? $val : $this->_dd->escape($val);
                SKY::$debug ? ($this->parse_error = "$param not a scalar") : die;
            break;
            case '$`': # column of a table, free to use
                if (is_string($val))
                    return $val = $q . str_replace($q, $q . $q, trim($val, $q)) . $q;
                $this->parse_error = "$param not a string";
            break;
            case '$$': # free to use, parsed sql part
                # ? $this->mode |= $val->mode;
                if ($val instanceof SQL)
                    return $val = (string)$val;
                if (null === $val)
                    return $val = 'NULL';
                if ('' === $val || is_num($val))
                    return $val;
                $this->parse_error = "$param is not instanceof SQL";
            break;
            case '$@':
                if (is_array($val) && is_num(key($val)))
                    return $val = $this->array_join(array_values($val));
                SKY::$debug ? ($this->parse_error = "$param not array or key0 not numeric") : die;
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
                    return $val = $this->array_join($val, '@@' == $match[0]);
                $this->parse_error = "$param not an array or scalar";
            break;
        }
        return $val = '>>' . gettype($val) . '<<';
    }

    function array_join(Array $ary, $esc = true) {
        $keys = $vals = [];
        array_walk($ary, function ($v, $k) use (&$keys, &$vals, $esc) {
            $param = sprintf('Parameter N %d`%s` - ', 1 + $this->i, $k);
            $char = is_string($k) ? $k[0] : false;
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
                $this->parse_error = "$param not a scalar";
            } elseif ('+' == $char) {
                $esc = true;
            } elseif ($pref) {
                $esc = false;
                if ('.' == $char && !is_num($v))
                    $this->parse_error = "$param not numeric";
                if ('$' == $char) {
                    if ($v instanceof SQL) $v = (string)$v;
                    elseif ('' !== $v && !ctype_digit((string)$v)) $this->parse_error = "$param not instance of SQL";
                }
            }
            if ($this->parse_error)
                $v = '>>' . gettype($v) . '<<';
            $q = $this->quote;
            $keys[] = '`' == $char ? $q . str_replace($q, $q . $q, trim($k, $q)) . $q : $k;
            if (!$esc && '$now' === $v)
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

//////////////////////////////////////////////////////////////////////////
class Func
{
    static function replace($match, $dd) {
        switch($match[1]) {
            case 'now':
                return $dd->f_dt();
            case 'fmt':
                return $dd->f_fmt($match[3]);
            case 'week':
                return $dd->f_week($match[3]);
            case 'cc':
                return call_user_func_array([$dd, 'f_cc'], Func::parse($match[3]));
            default:
                return $match[0]; # as is////////////////////////////////////////////////////////
        }
    }

    static function parse($m3) {
        $ary = [];
        $s = '';
        $p = 0;
        foreach (token_get_all("<?php $m3") as $i => $x) {
            list ($lex, $x) = is_array($x) ? $x : [0, $x];

            if (!$i || 1 == $i) //  || T_WHITESPACE == $lex
                continue;
            '(' != $x or $p++;
            ')' != $x or $p--;

            if (',' == $x && !$p) {
                $ary[] = $s;
                $s = '';
            } else {
                $s .= $x;
            }
        }
        $ary[] = substr($s, 0, -1);

        return $ary;
    }
}

//////////////////////////////////////////////////////////////////////////
interface DriverDatabase
{
    function init($tz = null);
    function info();
    function close();
    function escape($s, $quote = true);
    function unescape($s, $quote = true);
    function error();
    function query($sql_string, &$q);
    function one($q, $meth = 'A', $free = false);
    function num($q, $rows = true);
    function insert_id();
    function affected();
    function free($q);
    function multi_sql($sql);
    function _xtrace();
    function _tables($table = false);
    function _rows_count($table);
    function f_cc();
    function f_dt($column = false, $sign = false, $n = 0, $period = 'day');
    function build($type);
    static function begin($lock_table = false);
    static function end($par = true);
}
