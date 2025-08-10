<?php

class dd_sqlsrv implements DriverDatabase
{
    use SQL_COMMON;

    public $name = 'MS SQL';
    public $quote = '"';
    public $conn;
    public $pref;
    public $cname;

    static $timezone = false;
    static $charset = '';
    static $code = 'if ($r = sqlsrv_fetch_array($q->stmt, SQLSRV_FETCH_ASSOC)) extract($r, EXTR_PREFIX_ALL, "r"); else sqlsrv_free_stmt($q->stmt); return $r;';

    private $last_err = 0;
    private $last_insert_id = 0;

    function __construct($dsn, $pref) {
        if (!function_exists('sqlsrv_connect'))
            throw new Error('sqlsrv_connect function not exists');
        $ary = bang($dsn, '=', ' '); # "0=serverName\Instanc Database=dbName UID=username PWD=password"
        $this->conn = sqlsrv_connect(array_shift($ary), $ary += ['CharacterSet' => 'UTF-8']);
        $this->pref = $pref;
        self::$charset = $ary['CharacterSet'];
        self::$timezone or self::$timezone = date_default_timezone_get();
    }

    function init($tz = null) {
    }

    function __info() {
        $ary = [
            'name' => $this->name,
            'version' => unbang(sqlsrv_server_info($this->conn), '=', ' '),
            'client' => unbang(sqlsrv_client_info($this->conn), '=', ' '),
            'charset' => self::$charset,
        ];
        return $ary + ['str' => implode(', ', $ary)] + ['tables' => array_map(function($v) {
            $v['crdate'] = $v['crdate']->format(DATE_DT);
            $v['refdate'] = $v['refdate']->format(DATE_DT);
            return $v;
        }, $this->sqlf("%SELECT * FROM sysobjects WHERE xtype='U'"))];
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
            'version' => unbang(sqlsrv_server_info($this->conn), '=', ' '),
            'client' => unbang(sqlsrv_client_info($this->conn), '=', ' '),
            'charset' => self::$charset,
        ];
        return $ary + ['str' => implode(', ', $ary)] + ['tables' => array_combine($tables, $d)];
    }

    function close() {
        sqlsrv_close($this->conn);
    }

   function ms_escape($data) {
       if (!isset($data) or empty($data))
           return '';
       if (is_numeric($data))
           return $data;
       $non_displayables = array(
           '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
           '/%1[0-9a-f]/',             // url encoded 16-31
           '/[\x00-\x08]/',            // 00-08
           '/\x0b/',                       // 11
           '/\x0c/',                       // 12
           '/[\x0e-\x1f]/'              // 14-31
       );
       foreach ( $non_displayables as $regex )
           $data = preg_replace( $regex, '', $data);
       return str_replace("'", "''", $data);
   }

    function escape($s, $quote = true) {
        return $quote ? "'" . str_replace("'", "''", $s) . "'" : str_replace("'", "''", $s);
    }

    function unescape($s, $quote = true) {
        return $quote ? str_replace("''", "'", substr($s, 1, -1)) : str_replace("''", "'", $s);
    }

    function error($last_query = true) {
        $e = $last_query ? $this->last_err : (sqlsrv_errors(SQLSRV_ERR_ERRORS) ?? 0);
        return $e ? implode(' | ', array_map(fn($v) => $v['message'], $e)) : '';
    }

    function has_result($s4, $stmt) {
        return sqlsrv_has_rows($stmt);
    }

    function query($sql, &$q) {
        if ($ins = 'insert' == strtolower(substr($sql, 0, 6)))
            $sql .= " DECLARE @IDA INT SELECT @IDA = SCOPE_IDENTITY() SELECT @IDA";
        $q = @sqlsrv_query($this->conn, $sql);
        $this->last_err = sqlsrv_errors(SQLSRV_ERR_ERRORS) ?? 0;
        if ($ins && !$this->last_err) {
            sqlsrv_next_result($q);
            $this->last_insert_id = sqlsrv_fetch_array($q, SQLSRV_FETCH_NUMERIC)[0];
        }
        return $this->last_err ? implode('.', array_map(fn($v) => $v['code'], $this->last_err)) : 0;
    }

    function one($q, $meth = 'A', $free = false) { # assoc as default
        if ($q instanceof SQL)
            $q = $q->stmt;
        $data = function ($row, $cell = false) use ($q, $free) {
            if ($free || !$row)
                sqlsrv_free_stmt($q);
            return $cell && $row ? $row[0] : $row;
        };
        switch ($meth) {
            case 'E': # eval
                return self::$code;
            case 'C': # cell
            case 'R': # row
                return $data(sqlsrv_fetch_array($q, SQLSRV_FETCH_NUMERIC), 'C' == $meth);
            case 'A': # associated
                return $data(sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC));
            case 'O': # object
                return $data(sqlsrv_fetch_object($q));
        }
    }

    function num($q, $rows = true) {
        if ($q instanceof SQL)
            $q = $q->stmt;
        return $rows ? sqlsrv_num_rows($q) : sqlsrv_num_fields($q);
    }

    function insert_id() {
        return $this->last_insert_id;
    }

    function affected($stmt = null) {
        return sqlsrv_rows_affected($stmt);
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
        $select = "SELECT name FROM sysobjects WHERE xtype='U'";
        if ($table)
            return (bool)$this->sqlf("+$select AND name like %s", $this->pref . $table);
        return $this->sqlf("@$select");
    }

    function _struct($table = false) {
        $ary = iterator_to_array($this->sql(1, '&exec sp_columns $_`', $table), false);
        $key = [];
        $val = array_map(function($v) use (&$key) {
            $key[] = $v->COLUMN_NAME;
            $i = ' identity' == substr($v->TYPE_NAME, -9);
            #$d = "$this->quote$v->COLUMN_NAME$this->quote $v->TYPE_NAME($v->PRECISION) ";
            $d = "[$v->COLUMN_NAME] $v->TYPE_NAME" . ($i ? '(1,1)' : "($v->PRECISION) ");
 # int datetime
            $d .= $v->NULLABLE ? ($i ? '' : 'NULL') : ($i ? '' : 'NOT NULL');
            if (!empty($v->COLUMN_DEF))
                $d .= ' DEFAULT ' . $v->COLUMN_DEF;
            $default = '(NULL)' == $v->COLUMN_DEF ? null : ('' === $v->COLUMN_DEF ? '' : substr($v->COLUMN_DEF, 1, -1));
            return [$v, $default, $d, 0];
        }, $ary);    # 0-original, 1-defvalue, 2-definition
        return array_combine($key, $val);
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
