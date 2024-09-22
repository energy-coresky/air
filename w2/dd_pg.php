<?php

class dd_pg implements DriverDatabase
{
    use SQL_COMMON;

    public $name = 'Postgres';
    public $quote = '"';
    public $conn;
    public $pref;
    private $q;
    private $error_str;

    function __construct($dsn, $pref) {
        if (!function_exists('pg_connect'))
            throw new Error('Function pg_connect() not exists');
        [$dbname, $host, $user, $pass] = explode(' ', $dsn);
        $this->conn = pg_connect("host=$host dbname=$dbname user=$user password=$pass");
        $this->pref = $pref;
    }

    function init($tz = null) {
    }

    function info() {
        $ary = [
            'name' => $this->name,
            'version' => sqlf('+select version()'),
            'charset' => mysqli_character_set_name($this->conn),
        ];
        return $ary + ['str' => implode(', ', $ary)] + ['tables' => []];
    }

    function close() {
        pg_close($this->conn);
    }

    function escape($s, $quote = true) { //pg_escape_literal pg_escape_identifier pg_escape_bytea 
        return $quote ? "'" . pg_escape_string($this->conn, $s) . "'" : pg_escape_string($this->conn, $s);
    }

    function unescape($s, $quote = true) {
        if ($quote)
            $s = substr($s, 1, -1);
        return strtr($s, ['\0' => "\x00", '\n' => "\n", '\r' => "\r", '\\\\' => "\\", '\\\'' => "'", '\"' => '"', '\Z' => "\x1a"]);
    }

    function error() {
        return $this->error_str;
    }


    function has_result($sql_string) {
    }

    function query($sql_string, &$q) {
        $this->q = $q = pg_query($sql_string);
        return $this->error_str = pg_result_error($q);
    }

    function one($q, $meth = 'A', $free = false) { # assoc as default
        if ('E' == $meth)
            return 'if ($r = pg_fetch_assoc($q->stmt)) extract($r, EXTR_PREFIX_ALL, "r"); else pg_free_result($q->stmt); return $r;';
        if ($q instanceof SQL)
            $q = $q->stmt;
        $row = 'A' == $meth ? pg_fetch_assoc($q) : ('O' == $meth ? pg_fetch_object($q) : pg_fetch_row($q));
        if ($row && 'C' == $meth)
            $row = $row[0];
        if ($free || !$row)
            pg_free_result($q);
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

    function check_other() {
        return false;
    }

    function multi_sql($sql) {
    }

    static function begin($lock_table = false) {
    }

    static function end($par = true) {
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
