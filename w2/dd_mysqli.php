<?php

class dd_mysqli implements DriverDatabase
{
    use SQL_COMMON;

    public $name = 'MySQLi';
    public $dbname = '';
    public $quote = '`';
    public $conn;
    public $pref;
    public $cname;

    function __construct($dsn, $pref) {
        if (!function_exists('mysqli_init'))
            throw new Error('Function mysqli_init() not exists');
        $this->conn = mysqli_init();
        [$dbname, $host, $user, $pass] = explode(' ', $dsn);
        $this->dbname = $dbname;
        mysqli_real_connect($this->conn, $host, $user, $pass, $dbname);
        $this->pref = $pref;
    }

    function init($tz = null) {
        if (!mysqli_set_charset($this->conn, 'utf8'))
            throw new Error('mysqli_set_charset');
        $this->sqlf('set time_zone=%s', null === $tz ? date('P') : $tz);
    }

    function info() {
        $ary = [
            'name' => $this->name,
            'version' => $this->sqlf('+select version()'),
            'charset' => mysqli_character_set_name($this->conn),
        ];
        return $ary + ['str' => implode(', ', $ary)] + ['tables' => $this->sqlf("%show table status from `$this->dbname`")];
    }

    function close() {
        mysqli_close($this->conn);
    }

    function escape($s, $quote = true) {
        return $quote ? "'" . mysqli_real_escape_string($this->conn, $s) . "'" : mysqli_real_escape_string($this->conn, $s);
    }

    function unescape($s, $quote = true) {
        if ($quote)
            $s = substr($s, 1, -1);
        return strtr($s, ['\0' => "\x00", '\n' => "\n", '\r' => "\r", '\\\\' => "\\", '\\\'' => "'", '\"' => '"', '\Z' => "\x1a"]);
    }

    function error() {
        return mysqli_error($this->conn);
    }

    function has_result($s4) {
        return in_array($s4, ['show', 'help']);
    }

    function query($sql_string, &$q) {
        $q = mysqli_query($this->conn, $sql_string);
        //$has_result = $q instanceof mysqli_result;
        return mysqli_errno($this->conn);
    }

    function one($q, $meth = 'A', $free = false) { # assoc as default
        if ('E' == $meth)
            return 'if ($r = mysqli_fetch_assoc($q->stmt)) extract($r, EXTR_PREFIX_ALL, "r"); else mysqli_free_result($q->stmt); return $r;';
        if ($q instanceof SQL)
            $q = $q->stmt;
        $row = 'A' == $meth ? mysqli_fetch_assoc($q) : ('O' == $meth ? mysqli_fetch_object($q) : mysqli_fetch_row($q));
        if ($row && 'C' == $meth)
            $row = $row[0];
        if ($free || !$row)
            mysqli_free_result($q);
        return $row;
    }

    function num($q, $rows = true) {
        if ($q instanceof SQL)
            $q = $q->stmt;
        return $rows ? mysqli_num_rows($q) : mysqli_num_fields($q);
    }

    function insert_id() {
        return mysqli_insert_id($this->conn);
    }

    function affected() {
        return mysqli_affected_rows($this->conn);
    }

    function free($q) {
        if ($q instanceof SQL)
            $q = $q->stmt;
        mysqli_free_result($q);
    }

    function check_other() {
        return 'utf8' === mysqli_character_set_name($this->conn) ? false : 'error in mysqli_character_set_name';
    }

    function multi_sql($sql) {
        global $sky;
        if (!$sql instanceof SQL) {
            is_string($sql) or $sql = '>>' . gettype($sql) . '<<';
            $ary = ['Multi SQL Error', "Not instance of SQL\nSQL-multi: $sql"];
            trace($ary, true, SQL::NO_TRACE & $sql->mode ? 1 : 1 + $sql->mode & 7);
            return;
        }
    
        $sql_string = (string)$sql;
        if ($sql->error || $sky->begin_transaction && ($sky->was_error & SKY::ERR_DETECT))
            return;
        mysqli_multi_query($this->conn, $sql_string);
        $no = 0;
        do {
            if ($q = mysqli_store_result($this->conn)) {
                mysqli_free_result($q);
            } elseif ($_no = mysqli_errno($this->conn)) {
                $no = $_no;
                $error = mysqli_error($this->conn);
            }
        } while (mysqli_more_results($this->conn) && mysqli_next_result($this->conn));
    
        if (SKY::$debug || $no && $sky->s_log_error) {
            SQL::$query_num++;
            if ($show_error = (bool)$no)
                $sky->error_title = 'Multi SQL Error';
            $depth = SQL::NO_TRACE & $sql->mode ? (int)$show_error : 1 + $sql->mode & 7;
            if ($depth)
                trace(($show_error ? "ERROR in MySQL - $no: $error\n" : '') . "SQL-multi: $sql_string", $show_error, $depth);
        }
    }

    static function begin($lock_table = false) {
        global $sky;

        if ($sky->was_error & SKY::ERR_DETECT || $sky->begin_transaction)
            return false;
        trace('transaction started', false, 1);
        if (!SKY::$debug && !$sky->s_log_error)
            $sky->s_log_error = $sky->begin_debug = true;
        $sky->begin_transaction = mysqli_autocommit($this->conn, false) or trace(mysqli_error($this->conn), true, 1);
        if (!$sky->begin_transaction && $sky->begin_debug)
            $sky->s_log_error = $sky->begin_debug = false;
        if ($sky->begin_transaction && ($sky->lock_table = $lock_table))
            $this->sql('lock tables $$', $lock_table);
        return $sky->begin_transaction;
    }

    static function end($par = true) {
        global $sky;

        if ($sky->begin_debug)
            $sky->s_log_error = $sky->begin_debug = false;
        if (!$sky->begin_transaction)
            return;
        ($ok = !($sky->was_error & SKY::ERR_DETECT) && mysqli_commit($this->conn)) ? mysqli_autocommit($this->conn, true) : mysqli_rollback($this->conn);
        if ($sky->lock_table)
            $this->sql('unlock tables');
        trace('transaction finished', false, 1);
        $sky->begin_transaction = $sky->lock_table = false;
        return !$ok && is_callable($par) ? $par() : $ok && $par;
    }

    function _xtrace() {
        $this->sqlf('update $_memory d left join $_memory s on (s.id= if(d.id=2, 1, 2)) set d.tmemo=s.tmemo where d.id in (2, 3)');
    }

    function _tables($table = false) {
        if ($table)
            return (bool)$this->sqlf('+show tables like %s', $this->pref . $table);
        return $this->sqlf('@show tables');
    }

    function _struct($table = false) {
        $data = $this->sql(1, '@explain $_`', $table);
        array_walk($data, function(&$v, $k) {
            $d = "$this->quote$k$this->quote $v[0] ";
            $d .= $v[4]
                ? 'NOT NULL AUTO_INCREMENT'
                : (null === $v[3] ? ('YES' === $v[1] ? 'DEFAULT NULL' : 'NOT NULL') : 'NOT NULL DEFAULT ' . var_export($v[3], 1));
            $default = null === $v[3] ? ('YES' === $v[1] ? NULL : 0) : $v[3];
            $v = [$v, $default, $d, $v[4] ? "PRIMARY KEY ($this->quote$k$this->quote)" : 0];
        });    # 0-original, 1-defvalue, 2-definition
        return $data;
    }

    function _rows_count($table) {
        $row = $this->sqlf("-show table status like %s", $this->pref . $table);
        return $row[4];
    }

    function f_fmt($in) {
        $in = explode(',', substr($in, 1, -1), 2);
        return "date_format($in[1], $in[0])";
    }

    function f_week($in) {
        return "strftime('%w', $in)";
    }

    function f_cc(...$in) {
        return 'concat(' . implode(', ', $in) . ')';
    }

    function f_dt($column = false, $sign = false, $n = 0, $period = 'day') {
        false !== $column or $column = 'now()';
        return $sign ? "$column $sign interval $n $period" : $column;
    }

    function build($type) {

        return ;
    }
}
