<?php

class dc_file implements Cache_driver
{
    use SQL_COMMON;

    public $name = 'File';
    public $quote = '"';
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
            'version' => '',///////
            'charset' => '',///////////
        ];
        return $ary + ['str' => implode(', ', $ary)];
    }
}
