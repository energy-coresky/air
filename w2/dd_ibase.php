<?php

class dd_ibase implements Database_driver
{
    use SQL_COMMON;

    public $name = 'FireBird';
    public $quote = '"';
    public $conn;
    public $pref;

    function __construct($dsn, $pref) {
        if (!function_exists('mysqli_init'))
            throw new Error('Function mysqli_init() not exists');
        $this->conn = mysqli_init() or exit('db');
        list ($dbname, $host, $user, $pass) = explode(' ', $dsn);
        mysqli_real_connect($this->conn, $host, $user, $pass, $dbname);
        $this->pref = $pref;
    }

    function info() {
        $ary = [
            'name' => $this->name,
            'version' => '',///////
            'charset' => '',///////////
        ];
        return $ary + ['str' => implode(', ', $ary)];
    }

    function close() {
        mysqli_close($this->conn);
    }

    function escape($s, $quote = true) {
        return $quote ? "'" . mysqli_real_escape_string($this->conn, $s) . "'" : mysqli_real_escape_string($this->conn, $s);
    }

    function error() {
        return ibase_errmsg();
    }

    function query($sql_string, &$q) {
        $q = @ibase_query($this->conn, $sql_string);
        return ibase_errcode();
    }

    function one($q, $meth = 'A', $free = false) { # assoc as default
        if ('E' == $meth)
            return 'if ($r = ibase_fetch_assoc($q->stmt)) extract($r, EXTR_PREFIX_ALL, "r"); else ibase_free_result($q->stmt); return $r;';
        if ($q instanceof SQL)
            $q = $q->stmt;
        $row = 'A' == $meth ? ibase_fetch_assoc($q) : ('O' == $meth ? ibase_fetch_object($q) : ibase_fetch_row($q));
        if ($row && 'C' == $meth)
            $row = $row[0];
        if ($free || !$row)
            ibase_free_result($q);
        return $row;
    }

    function all($q, $meth = 'A', $mode = 0) { # 0-usial 1-c0_is_key 2-using_eVar_iterator
    }

    function num($q, $rows = true) {
        return $rows ? mysqli_num_rows($q) : mysqli_num_fields($q);
    }

    function insert_id() {
        //return mysqli_insert_id($this->conn);
    }

    function affected() {
        return ibase_affected_rows();
    }

    function free($q) {
        ibase_free_result($q);
    }

    function multi_sql($sql) {
    }

    static function begin($lock_table = false) {
    }

    static function end($par = true) {
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
