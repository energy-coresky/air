<?php

class dd_sqlite3 implements Database_driver
{
    use SQL_COMMON;

    public $name = 'SQLite3';
    public $quote = '"'; # no: `switch.sqlite3`.`memory` yes: `memory` yes: "memory" yes: 'memory'
    public $conn;
    public $pref;

    function __construct($filename, $pref) {
        if (!class_exists('SQLite3', false))
            throw new Error('SQLite3 class not exists');
        $this->conn = new SQLite3($filename);
        $this->conn->busyTimeout(30000); # 30 secs
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
        return $quote ? "'" . SQLite3::escapeString($s) . "'" : SQLite3::escapeString($s);
    }

    function unescape($s, $quote = true) {
        return $quote ? str_replace("''", "'", substr($s, 1, -1)) : str_replace("''", "'", $s);
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

    function _xtrace() {
        sqlf('update $_memory set tmemo=(select tmemo from $_memory where id=15) where id=16');
        sqlf('update $_memory set tmemo=(select tmemo from $_memory where id=1) where id=15');
    }

    static function show($what = 'tables') {
        switch ($what) {
            case 'tables': sqlf('@select name from sqlite_master where type ="table" and name not like "sqlite_%"');
                break;
        }
    }

    function _tables($table = false) {
        $select = 'SELECT name FROM sqlite_master WHERE type = "table" AND name';
        if ($table)
            return (bool)sqlf("+$select LIKE %s", $this->pref . $table);
        return sqlf("@$select NOT LIKE 'sqlite_%'");
    }

    function _struct($table = false) {
        $data = $this->sql(1, '@pragma table_info($_`)', $table);
        $out = [];
        array_walk($data, function(&$v, $k) use (&$out) {
            $out[$v[0]] = $v[1]; # default value or empty string
        });
        return $out;
    }

    function _rows_count($table) {
        return sql('+select count(1) from $_`', $table);
    }

    function f_fmt($in) {
        $in = explode(',', substr($in, 1, -1), 2);
        return "strftime($in[0], $in[1])";
    }

    function f_week($in) {
        return "strftime('%w', " . substr($in, 1, -1) . ")";
    }

    function f_cc(...$in) {
        return implode(' || ', $in);
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
