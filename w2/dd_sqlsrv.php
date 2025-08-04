<?php

class dd_sqlsrv implements DriverDatabase
{
    use SQL_COMMON;

    public $name = 'MicrosoftSQL';
    public $quote = '"'; # no: `switch.sqlite3`.`memory` yes: `memory` yes: "memory" yes: 'memory'
    public $conn;
    public $pref;
    public $cname;

    static $timezone = false;

    function __construct($dsn, $pref) {
        if (!function_exists('sqlsrv_connect'))
            throw new Error('sqlsrv_connect function not exists');
        $ary = bang($dsn, '=', ' '); # "server=Name\sqlexpress Database=dbName UID=username PWD=password"
        $this->conn = sqlsrv_connect($qq=array_shift($ary), $ary);
        $this->pref = $pref;
        self::$timezone or self::$timezone = date_default_timezone_get();
    }

    function init($tz = null) {
    }

    function info() {
        $ary = [
            'name' => $this->name,
            'version' => unbang(sqlsrv_server_info($this->conn), '=', ' '),
            'client' => unbang(sqlsrv_client_info($this->conn), '=', ' '),
            'charset' => 'Utf-8',//$this->sqlf('+pragma encoding'),    mysqli_character_set_name($this->conn)
        ];
        return $ary + ['str' => implode(', ', $ary)] + ['tables' => array_map(function($v) {
            $v['crdate'] = $v['crdate']->format(DATE_DT);
            $v['refdate'] = $v['refdate']->format(DATE_DT);
            return $v;
        }, $this->sqlf("%SELECT * FROM sysobjects WHERE xtype='U'"))];
    }

    function close() {
        sqlsrv_close($this->conn);
    }

    function escape($s, $quote = true) {
        return $quote ? "'" . SQLite3::escapeString($s) . "'" : SQLite3::escapeString($s);
    }

    function unescape($s, $quote = true) {
        return $quote ? str_replace("''", "'", substr($s, 1, -1)) : str_replace("''", "'", $s);
    }

    function error() {
        return sqlsrv_errors()[0]['message'];
    }

    function has_result($s4) {
        return in_array($s4, ['prag']); // sqlsrv_has_rows($q->stmt)
    }

    function query($sql_string, &$q) {
        $q = @sqlsrv_query($this->conn, $sql_string);
        return sqlsrv_errors();
    }

    function one($q, $meth = 'A', $free = false) { # assoc as default
        if ('E' == $meth)
            return 'if ($r = sqlsrv_fetch_array($q->stmt, SQLSRV_FETCH_ASSOC)) extract($r, EXTR_PREFIX_ALL, "r"); else sqlsrv_free_stmt($q->stmt); return $r;';
        if ($q instanceof SQL)
            $q = $q->stmt;
        $row = 'A' == $meth ? sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC) : ('O' == $meth ? sqlsrv_fetch_object($q) : sqlsrv_fetch_array($q, SQLSRV_FETCH_NUMERIC));
        if ($row && 'C' == $meth)
            $row = $row[0];
        if ($free || !$row)
            sqlsrv_free_stmt($q);
        return $row;
    }

    function num($q, $rows = true) {
        if ($q instanceof SQL)
            $q = $q->stmt;
        return $rows ? sqlsrv_num_rows($q) : sqlsrv_num_fields($q);
    }

    function insert_id() {
        return 0;//$this->conn->lastInsertRowID();
    }

    function affected() {
        return sqlsrv_rows_affected($q->stmt);//$this->conn
    }

    function free($q) {
        if ($q instanceof SQL)
            $q = $q->stmt;
        sqlsrv_free_stmt($q);
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
        $select = "SELECT * FROM sysobjects WHERE xtype='U'";
        #if ($table)
        #    return (bool)$this->sqlf("+$select LIKE %s", $this->pref . $table);
        return $this->sqlf("@$select");  //  NOT LIKE 'sqlite_%'
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
