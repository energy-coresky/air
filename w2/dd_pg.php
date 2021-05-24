<?php

class dd_pg implements Database_driver
{
    use SQL_COMMON;

    public $name = 'Postgres';
    public $quote = '"';
    public $conn;
    public $pref;
    private $q;
    private $error_str;

    function __construct($dsn, $pref) {
        list ($dbname, $host, $user, $pass) = explode(' ', $dsn);
        $this->conn = pg_connect("host=$host dbname=$dbname user=$user password=$pass") or exit('connect');
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
        pg_close($this->conn);
    }

    function escape($s, $quote = true) { //pg_escape_literal pg_escape_identifier pg_escape_bytea 
        return $quote ? "'" . pg_escape_string($this->conn, $s) . "'" : pg_escape_string($this->conn, $s);
    }

    function error() {
        return $this->error_str;
    }

    function query($sql_string, &$q) {
        $this->q = $q = pg_query($sql_string);
        return $this->error_str = pg_result_error($q);
    }

    function one($q, $meth = 'A', $free = false) { # assoc as default
        if ('E' == $meth)
            return 'if ($r = pg_fetch_assoc($q)) extract($r, EXTR_PREFIX_ALL, "r"); else pg_free_result($q); return $r;';
        if ($q instanceof SQL)
            $q = $q->stmt;
        $row = 'A' == $meth ? pg_fetch_assoc($q) : ('O' == $meth ? pg_fetch_object($q) : pg_fetch_row($q));
        if ($row && 'C' == $meth)
            $row = $row[0];
        if ($free || !$row)
            mysqli_free_result($q);
        return $row;
    }

    function num($q, $rows = true) {
        //return $rows ? ($q) : ($q);
    }

    function insert_id() {
        //return ($this->conn);//////////////
    }

    function affected() {
        return pg_affected_rows($this->q);
    }

    function free($q) {
        pg_free_result($q);
    }

    function multi_sql($sql) {
    }

    static function begin($lock_table = false) {
    }

    static function end($par = true) {
    }

    function f_cc(...$in) {
        return implode(' || ', $in);
    }

    function f_dt($column = false, $sign = false, $n = 0, $period = 'day') {
        false !== $column or $column = "'now'";
        return $sign ? "datetime($column, '$sign$n $period')" : "datetime($column)";
    }

    function build($type) {

        return ;
    }
}
