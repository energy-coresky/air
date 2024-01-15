<?php

function run_redis(&$_code, $_data) {
    $_data && extract($_data->data, EXTR_REFS);
    return eval(substr($_code, 5)) ?? 1; # simulate "require"
}

class dc_redis implements DriverCache
{
    const TTL = 604800; # 1 week
    const EDGE = 10; # 10 sec

    public $type = 'Redis';
    public $conn;

    private $path;

    function __construct($cfg) {
        [$host, $port, $pwd] = explode(':', $cfg['dsn'], 3) + ['localhost', 6379, false];
        $this->conn = new Redis;
        if (!$this->conn->connect($host, (int)$port))
            throw new Error('Cannot connect to Redis');
        if ($pwd)
            $this->conn->auth($pwd);
    }

    function info() {
        $ary = ['type' => $this->type, 'version' => $this->conn->info()['redis_version']];
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
        return run_redis($code, $vars);
    }

    function mtime($key) {
        //return -1 == $ttl ? PHP_INT_MAX : time() - self::TTL + $ttl;
        return time() - self::TTL + $this->conn->ttl($this->path . $key);
    }

    function append($key, $data) {
        return $this->conn->append($this->path . $key, $data);
    }

    function put($key, $data, $ttl = false) {
        return $this->conn->setEx($this->path . $key, self::TTL, $data);
    }

    function set($key, $data) {
        return $this->conn->set($this->path . $key, $data); // what retun?
    }

    function glob($mask = '*') {
        return $this->conn->keys($this->path . $mask);
    }

    function drop($key) {
        return (int)$this->conn->del($this->path . $key);
    }

    function drop_all($mask = '*') {
        // use ->unlink(..) perform the actual deletion asynchronously
        $keys = $this->conn->keys($this->path . $mask);
        return count($keys) ? (int)$this->conn->del($keys) : 1;
    }
}
