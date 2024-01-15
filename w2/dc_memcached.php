<?php

function run_memcached(&$_code, $_data) {
    $_data && extract($_data->data, EXTR_REFS);
    return eval(substr($_code, 5)) ?? 1; # simulate "require"
}

class dc_memcached implements DriverCache # 2do: https://www.php.net/manual/en/memcached.set.php
{
    const TTL = 604800; # 1 week
    const EDGE = 10; # 10 sec

    public $type = 'Memcached';
    public $conn;

    private $path;

    function __construct($cfg) {
        [$host, $port] = explode(':', $cfg['dsn'], 2) + ['localhost', 11211];
        $this->conn = new Memcached;
        if (!$this->conn->addServer($host, (int)$port))
            throw new Error('Cannot connect to Memcached');
    }

    function info() {
        $ary = ['type' => $this->type, 'version' => $this->conn->getVersion()];
        return $ary + ['str' => implode(', ', $ary)];
    }

    function setup($obj, $quiet = false) {
        $this->path = $obj->path . '/' . ($obj->pref ?? '');
        return $quiet && !$this->test($quiet);
    }

    function test($key) {
        $ttl = $this->conn->ttl($this->path . $key);
        return -1 == $ttl || $ttl > self::EDGE ? $this->path . $key : false;
    }

    function get($key) {
        return $this->conn->get($this->path . $key);
    }

    function run($key, $vars = false) {
        $code = $this->conn->get($this->path . $key);
        return run_memcached($code, $vars);
    }

    function mtime($key) {
        //return time() - self::TTL + $this->conn->ttl($this->path . $key);
    }

    function append($key, $data) {
        return $this->conn->append($this->path . $key, $data);
    }

    function put($key, $data, $ttl = false) {
        return $this->conn->setEx($this->path . $key, self::TTL, $data);
    }

    function set($key, $data) {
        return $this->conn->set($this->path . $key, $data);
    }

    function glob($mask = '*') { # 2do
        if (!DEV)
            return [];
        $all = $this->conn->getAllKeys();
        //return 
    }

    function drop($key) {
        return (int)$this->conn->delete($this->path . $key);
    }

    function drop_all($mask = '*') {
        $keys = $this->conn->keys($this->path . $mask);
        return count($keys) ? (int)$this->conn->delete($keys) : 1;
    }
}
