<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

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
    public $error = false;
    public $stmt = true;
    public $i = 0;
    public $qstr = false;
    public $_dd; # database driver for this object
    public $qb_ary = false;

    static $query_num = 0;
    static $dd;  # selected database driver

    private static $onduty = 'memory'; # table name without prefix
    private static $re_func = '\B\$([a-z]+)([ \t]*)(\(((?>[^()]+)|(?3))*\))?'; // revision later
    private static $connections = [];

    private $in;
    private $depth = 3; # for constructor (if parse error to show)

    function __construct($in = false) {
        if (is_string($in)) {
            SQL::open($in, $this); # single query to different connection
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
        static $exec = ['insert', 'update', 'delete', 'replace'];
        if ( in_array($name, $exec))
            return $this->_dd->build($this, $name);

        static $method =  ['table', 'columns', 'where', 'join', 'group', 'having', 'order', 'limit'];
        if (!in_array($name, $method))
            throw new Err("SQL::$name() - unknown method");

        $this->qb_ary or $this->qb_ary = array_combine($method, array_fill(0, count($method), []));
        $this->qb_ary[$name] = array_merge($this->qb_ary[$name], $args[0]);
        return $this;
    }

    static function open($name = '', $obj = false) {
        if (isset(SQL::$connections[$name])) {
            $dd = SQL::$connections[$name];
        } else {
            '' === $name ? ($cfg =& SKY::$databases) : ($cfg =& SKY::$databases[$name]);
            $driver = "dd_$cfg[driver]";
            $dd = SQL::$connections[$name] = new $driver($cfg['dsn'], $cfg['pref']);
            unset($cfg['dsn']);
        }
        return $obj ? ($obj->_dd = $dd) : (SQL::$dd = $dd); # set selected database driver
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

    static function onduty($table = false) {
        $t = SQL::$onduty;
        false === $table or SQL::$onduty = $table;
        return $t;
    }

    function one($meth = 'A', $free = false) {
        if ($this->qb_ary)
            return $this->_dd->build($this, 'one', $meth);
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

    function replace_nop($match = false, $str = false) {
        if (!$match) {
            $re = '\$_[a-z_\d]*';
            if (SQL::$re_func && !(SQL::NO_FUNC & $this->mode))
                $re .= '|' . SQL::$re_func;
            return $str ? preg_replace_callback("~$re~u", [$this, __FUNCTION__], $str) : $re;
        } elseif ('_' == $match[0][1]) { # $_ (on-duty table) or $_sometable
            return '`' . $this->_dd->pref . (2 == strlen($match[0]) ? SQL::onduty() : substr($match[0], 2)) . '`';
        }
        if (isset($match[3]) && strpos($match[3], '$'))
            $match[3] = preg_replace_callback('~' . SQL::$re_func . '~u', [$this, __FUNCTION__], $match[3]);
        return Func::replace($match, $this->_dd);
    }

    private function replace_par($match) {
        global $sky;

        $c2 = $match[0][1];
        if ('\\' == $match[0][0]) # \1..\9 - backlinks
            return $this->in[$c2 - 1];
        if ('$_`' == $match[0])  # variable table
            return $val = '`' . $this->_dd->pref . str_replace('`', '``', trim($val =& $this->in[$this->i++], '`')) . '`';
        if ('_' == $c2 || ord($c2) > 0x60)
            return $this->replace_nop($match);

        $val =& $this->in[$this->i++];
        if ($this->error)
            return $val = '??'; # run faster
        if (is_bool($val))
            return $val = (int)$val;

        $param = 'Parameter N ' . (1 + $this->i) . ' - ';
        switch ($match[0]) {
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
                    return $val = $this->array_join($val, '@@' == $match[0]);
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


//////////////////////////////////////////////////////////////////////////
class eVar implements Iterator
{
    private $state = 0;
    private $i = -1;
    private $max_i = 500; # -1 is infinite
    private $row;
    private $e;
    private $dd = false;

    function __construct(Array $e) {
        $this->e = $e;
    }

    function __get($name) {
        return isset($this->e[$name]) ? $this->e[$name] : null;
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
        if (isset($this->e['max_i']))
            $this->max_i = $this->e['max_i'];
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
