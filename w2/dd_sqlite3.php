<?php

class dd_sqlite3 implements DriverDatabase
{
    use SQL_COMMON;

    public $name = 'SQLite3';
    public $quote = '"'; # no: `switch.sqlite3`.`memory` yes: `memory` yes: "memory" yes: 'memory'
    public $conn;
    public $pref;
    public $cname;

    static $timezone = false;

    function __construct($filename, $pref) {
        if (!class_exists('SQLite3', false))
            throw new Error('SQLite3 class not exists');
        $this->conn = new SQLite3($filename);
        $this->conn->busyTimeout(30000); # 30 secs
        $this->pref = $pref;
        self::$timezone or self::$timezone = date_default_timezone_get();
    }

    function init($tz = null) {
    }

    function info() {
        $d = array_map(function($k) {
            $struct = $this->_struct($k);
            return ['Columns' => pre(implode(",\n", array_map(function($v) {
                return $v[2];
            }, $struct)))];
        }, $tables = $this->_tables());
        $ary = [
            'name' => $this->name,
            'version' => SQLite3::version()['versionString'],
            'charset' => $this->sqlf('+pragma encoding'),
        ];
        return $ary + ['str' => implode(', ', $ary)] + ['tables' => array_combine($tables, $d)];
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

    function has_result($s4, $stmt) {
        return in_array($s4, ['prag']);
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

    function affected($stmt = null) {
        return $this->conn->changes();
    }

    function free($q) {
        $q->finalize();
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
        $this->sqlf('update $_memory set tmemo=(select tmemo from $_memory where id=2) where id=3');
        $this->sqlf('update $_memory set tmemo=(select tmemo from $_memory where id=1) where id=2');
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
            return (bool)$this->sqlf("+$select LIKE %s", $this->pref . $table);
        return $this->sqlf("@$select NOT LIKE 'sqlite_%'");
    }

    function _struct($table = false) {
        $data = $this->sql(1, '@pragma table_info($_`)', $table);
        $out = [];
        array_walk($data, function(&$v, $k) use (&$out) {
            $d = "$this->quote$v[0]$this->quote $v[1] ";
            $d .= $v[4]
                ? 'PRIMARY KEY AUTOINCREMENT NOT NULL'
                : (!$v[2] ? 'DEFAULT NULL' : (null === $v[3] ? 'NOT NULL' : 'NOT NULL DEFAULT ' . $v[3]));
            $default = !$v[2] ? null : (null === $v[3] ? 0 : $v[3]);
            $out[$v[0]] = [$v, $default, $d, 0];
        });    # 0-original, 1-defvalue, 2-definition
        return $out;
    }

    function _rows_count($table) {
        return $this->sql('+select count(1) from $_`', $table);
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
        $min = (new DateTimeZone(self::$timezone))->getOffset(new DateTime) / 60;
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
