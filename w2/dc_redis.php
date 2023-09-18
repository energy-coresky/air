<?php

function run_redis(&$_code, $_data) {
    $_data && extract($_data->data, EXTR_REFS);
    return eval(substr($_code, 5));
}

class dc_redis implements DriverCache
{
    const TTL = 2592000; # 30 days

    public $type = 'Redis';
    public $conn;

    private $obj;
    private $path;

    function __construct($cfg) {
        [$host, $port, $pwd] = explode(':', $cfg['dsn'], 3) + ['localhost', 6379, false];
        $this->conn = new Redis();
        if (!$this->conn->connect($host, (int)$port))
            throw new Error('Cannot connect to Redis');
        if ($pwd)
            $this->conn->auth($pwd);
    }

    function info() {
        $ary = ['name' => $this->name, 'version' => $this->conn->info()['redis_version']];
        return $ary + ['str' => implode(', ', $ary)];
    }

    function setup($obj) {
        $this->obj = $obj;
        $this->path = $obj->path . '/' . ($obj->pref ?? '');
        return $obj->quiet && !$this->conn->exists($this->path . $obj->quiet);
    }

    function test($name) {
        return $this->conn->exists($this->path . $name) ? $this->path . $name : false;
    }

    function get($name) {
        return $this->conn->get($this->path . $name);
    }

    function run($name, $vars = false) {
        $code = $this->conn->get($this->path . $name);
        return run_redis($code, $vars);
    }

    function mtime($name) {
        return time() - self::TTL + $this->conn->ttl($this->path . $name);
    }

    function append($name, $data) {
        return $this->conn->append($this->path . $name, $data);
    }

    function put($name, $data, $ttl = false) {
        return $this->conn->setEx($this->path . $name, self::TTL, $data);
    }

    function glob($mask = '*') {
        return $this->conn->keys($this->path . $mask);
    }

    function drop($name) {
        return (int)$this->conn->del($this->path . $name);
    }

    function drop_all($mask = '*') {
        // use ->unlink(..) perform the actual deletion asynchronously
        return (int)$this->conn->del($this->conn->keys($this->path . $mask));
    }
}
