<?php

class dd_mysqli implements Database_driver
{
    public $name = 'MySQLi';
    public $quote = '`';
    public $conn;
    public $pref;

    function __construct($dsn, $pref) {
        $this->conn = mysqli_init() or exit('db');
        list ($dbname, $host, $user, $pass) = explode(' ', $dsn);
        @mysqli_real_connect($this->conn, $host, $user, $pass, $dbname) or exit('connect');
        $this->pref = $pref;
    }

    function info() {
        $ary = [
            'name' => $this->name,
            'version' => sqlf('+select version()'),
            'charset' => mysqli_character_set_name($this->conn),
        ];
        return $ary + ['str' => implode(', ', $ary)];
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

    function query($sql_string, &$q) {
        $q = mysqli_query($this->conn, $sql_string);
        return mysqli_errno($this->conn);
    }

    function one($q, $meth = 'A', $free = false) { # assoc as default
        if ('E' == $meth)
            return 'if ($r = mysqli_fetch_assoc($q)) extract($r, EXTR_PREFIX_ALL, "r"); else mysqli_free_result($q); return $r;';
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

    function multi_sql($sql) {
        global $sky;
        if (!$sql instanceof SQL) {
            is_string($sql) or $sql = '>>' . gettype($sql) . '<<';
            $sky->error_title = 'Multi SQL Error';
            trace("Not instance of SQL\nSQL-multi: $sql", true, SQL::NO_TRACE & $sql->mode ? 1 : 1 + $sql->mode & 7);
            return;
        }
    
        $sql_string = (string)$sql;
        if ($sql->error || $sky->begin_transaction && $sky->was_error)
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
    
        if ($sky->debug || $no && $sky->s_prod_error) {
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

        if ($sky->was_error || $sky->begin_transaction)
            return false;
        trace('transaction started', false, 1);
        if (!$sky->debug && !$sky->s_prod_error)
            $sky->s_prod_error = $sky->debug_begin = true;
        $sky->begin_transaction = mysqli_autocommit($this->conn, false) or trace(mysqli_error($this->conn), true, 1);
        if (!$sky->begin_transaction && $sky->debug_begin)
            $sky->s_prod_error = $sky->debug_begin = false;
        if ($sky->begin_transaction && ($sky->lock_table = $lock_table))
            sql('lock tables $$', $lock_table);
        return $sky->begin_transaction;
    }

    static function end($par = true) {
        global $sky;

        if ($sky->debug_begin)
            $sky->s_prod_error = $sky->debug_begin = false;
        if (!$sky->begin_transaction)
            return;
        ($ok = !$sky->was_error && mysqli_commit($this->conn)) ? mysqli_autocommit($this->conn, true) : mysqli_rollback($this->conn);
        if ($sky->lock_table)
            sql('unlock tables');
        trace('transaction finished', false, 1);
        $sky->begin_transaction = $sky->lock_table = false;
        return !$ok && is_callable($par) ? $par() : $ok && $par;
    }

    function _xtrace() {
        sqlf('update $_memory d left join $_memory s on (s.id= if(d.id=15, 1, 15)) set d.tmemo=s.tmemo where d.id in (15, 16)');
    }

    function _tables($table = false) {
        if ($table)
            return (bool)sqlf('+show tables like %s', $this->pref . $table);
        return sqlf('@show tables');
    }

    function _rows_count($table) {
        $row = sqlf("-show table status like %s", $this->pref . $table);
        return $row[4];
    }

    function f_cc() {
        $in = func_get_args();
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
