<?php

class dd_sqlite3
{
    public $name = 'SQLite3';
    public $quote = '`';
    public $conn;
    public $pref;

    function __construct($filename, $pref) {
        $this->conn = new SQLite3($filename) or exit('connect');
        $this->pref = $pref;
    }

    function info() {
        $ary = [
            'name' => $this->name,
            'version' => SQLite3::version()['versionString'],
            'charset' => sql('+pragma encoding'),
        ];
        return $ary + ['str' => implode(', ', $ary)];
    }

    function close() {
        $this->conn->close();
    }

    function escape($s, $quote = true) {
        return $quote ? "'" . $this->conn->escapeString($s) . "'" : $this->conn->escapeString($s);
    }

    function error() {
        return $this->conn->lastErrorMsg();
    }

    function query($sql_string, &$q) {
        $q = @$this->conn->query($sql_string);
        return $this->conn->lastErrorCode();
    }

    function one($q, $meth = 'A', $free = false) { # assoc as default
        if ('E' == $meth)
            return 'if ($r = $q->fetchArray(SQLITE3_ASSOC)) extract($r, EXTR_PREFIX_ALL, "r"); else $q->finalize(); return $r;';
        $is_obj = 'O' == $meth;
        if ($q instanceof SQL)
            $q = $q->stmt;
        $row = $q->fetchArray($is_obj || 'A' == $meth ? SQLITE3_ASSOC : SQLITE3_NUM);
        if ($row && ($is_obj || 'C' == $meth))
            $row = $is_obj ? (object)$row : $row[0];
        if ($free || !$row)
            $q->finalize();
        return $row;
    }

    function num($q, $rows = true) {
        return $rows ? 0 : $q->numColumns();////////////////
    }

    function insert_id() {
        return $this->conn->lastInsertRowID();
    }

    function affected() {
        return $this->conn->changes();
    }

    function free($q) {
        $q->finalize();
    }

    function multi_sql($sql) {
    }

    static function begin($lock_table = false) {
    }

    static function end($par = true) {
    }

    static function show($what = 'tables') {
        switch ($what) {
            case 'tables': sqlf('@select name from sqlite_master where type ="table" and name not like "sqlite_%"');
                break;
        }
    }

    function _xtrace() {
        ; #2do
    }

    function _tables($table = false) {
        $select = 'SELECT name FROM sqlite_master WHERE type = "table" AND name';
        if ($table)
            return (bool)sqlf("+$select LIKE %s", $this->pref . $table);
        return sqlf("@$select NOT LIKE 'sqlite_%'");
    }

    function _rows_count($table) {
        return sql('+select count(1) from $_`', $table);
    }

    function f_cc() {
        return implode(' || ', func_get_args());
    }

    function tz() {
        $min = (new DateTimeZone(PHP_TZ))->getOffset(new DateTime) / 60;
        return ($min < 0 ? ",'" : ",'+") . "$min minute'";
    }

    function f_dt($column = false, $sign = false, $n = 0, $period = 'day') {
        false !== $column or $column = "'now'";
        $tz = 'now' == strtolower(trim($column, "'\"")) ? $this->tz() : '';
        return $sign ? "datetime($column, '$sign$n $period'$tz)" : "datetime($column$tz)";
    }

    function build($type) {

        return ;
    }
}
