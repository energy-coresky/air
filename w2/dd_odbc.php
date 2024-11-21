<?php

class dd_odbc implements DriverDatabase
{
    use SQL_COMMON;

    public $name = 'ODBC';
    public $quote = '"';
    public $conn;
    public $pref;
    private $q;
    private $error;

    function __construct($dsn, $pref) {
        if (!function_exists('odbc_connect'))
            throw new Error('Function odbc_connect() not exists');
        [$dbname, $host, $user, $pass] = explode(' ', $dsn);
        $this->conn = odbc_connect("", $user, $pass);
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
        oci_close($this->conn);
    }

    function escape($s, $quote = true) {
        //return $quote ? "'" . ($this->conn, $s) . "'" : ($this->conn, $s);////////////////
    }

    function unescape($s, $quote = true) {
    }

    function error() {
        return $this->error['message'];
    }

    function has_result($sql_string) {
    }

    function query($sql_string, &$q) {
        $this->q = $q = oci_parse($this->conn, $sql_string);
        oci_execute($q);
        $this->error = oci_error($this->conn);
        return $this->error ? $this->error['code'] : false;
    }

    function one($q, $meth = 'A', $free = false) { # assoc as default
        if ('E' == $meth)
            return 'if ($r = oci_fetch_assoc($q->stmt)) {
                        $r = array_change_key_case($r);
                        extract($r, EXTR_PREFIX_ALL, "r");
                    } else {
                        oci_free_statement($q->stmt);
                    }
                    return $r;';
        if ($q instanceof SQL)
            $q = $q->stmt;
        if (in_array($meth, ['R', 'C'])) {
            if ($row = oci_fetch_row($q))
                'C' != $meth or $row = $row[0];
        } elseif ($row = oci_fetch_assoc($q)) {
            $row = array_change_key_case($row);
            'O' != $meth or $row = (object)$row;
        }
        if ($free || !$row)
            oci_free_statement($q);
        return $row;
    }

    function num($q, $rows = true) {
        //return $rows ? ($q) : ($q);
    }

    function insert_id() {
  //      return ($this->conn);/////////////////
    }

    function affected() {
        return oci_num_rows($this->q);
    }

    function free($q) {
        oci_free_statement($q);
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

    function f_cc(...$in) {
        return implode(' || ', $in);
    }

    function f_dt($column = false, $sign = false, $n = 0, $period = 'day') {
        false !== $column or $column = "'now'";
        return $sign ? "datetime($column, '$sign$n $period')" : "datetime($column)";
    }

    function _xtrace() {
    }

    function _tables($table = false) {
    }

    function _rows_count($table) {
    }

    function build($type) {

        return ;
    }
}
