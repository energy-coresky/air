<?php

class Shmem
{
    private $id;
    private $size;
    private $char;
    private $timeout;
    private $namespace; # logical name

    const DEFAULT_SIZE = 65536;
    const REGISTRY_CHAR = 'a';
    const REGISTRY_SIZE = 4096;

    private function __construct($id, $char, $timeout, $namespace) {
        $this->id = $id;
        $this->size = shmop_size($id);
        $this->char = $char;
        $this->timeout = $timeout;
        $this->namespace = $namespace;
    }

    static function open($namespace, $timeout = 0) {
        global $sky;

        $map = self::loadMap($regId);
        if ($char = $map[$namespace] ?? false) {
            self::end_id($regId);
        } else {
            $key = ftok(__FILE__, $char = self::findFreeChar($map));
            // Safety cleanup
            if ($old = @shmop_open($key, "w", 0, 0)) {
                @shmop_delete($old);
                self::end_id($old);
                usleep(50000);
            }
            if (!shmop_open($key, "c", 0777, self::DEFAULT_SIZE))
                throw new Error("Failed to create block '$char'");
            $map[$namespace] = $char;
            self::saveMap($regId, $map);
        }
        
        // Open physical block
        $id = shmop_open(ftok(__FILE__, $char), "w", 0, 0);
        if (!$id)
            throw new Error("Failed to open block '$char'");
            
        $sky->shutdown[] = [$obj = new self($id, $char, $timeout, $namespace), 'close'];
        return $obj;
    }

    public function close() {
        if (!$this->id)
            return;
        self::end_id($this->id, true);
        $this->id = null;
        $map = self::loadMap($regId);
        unset($map[$this->namespace]);
        if (empty($map)) {
            self::end_id($regId, true);
        } else {
            self::saveMap($regId, $map);
        }
    }

    public function read() {
        if (empty($map = $this->resync($regId)))
            return [];
        self::end_id($regId);

        $header = shmop_read($this->id, 0, 4);
        if ($header === false)
            return [];
        $len = unpack('Vlen', $header)['len'];
        if ($len < 2 || $len > $this->size)
            return [];
        return json_decode(shmop_read($this->id, 4, $len), true);
    }

    public function write($data, $retry = 0): bool {
        if (empty($map = $this->resync($regId)))
            return false;
        self::end_id($regId);
        
        $retry or $data = pack('V', strlen($data = json_encode($data, JSON_UNESCAPED_SLASHES))) . $data;
        
        if (strlen($data) <= $this->size) {
            shmop_write($this->id, $data, 0);
            return true;
        }

        if (empty($map = $this->resync($regId, $changed)))
            return false; // Namespace gone?

        if ($changed) {
            self::end_id($regId);
            if (strlen($data) <= $this->size) {
                shmop_write($this->id, $data, 0);
                return true;
            }
            
            if ($retry > 10)
                throw new Error("Shared memory concurrency conflict");
            if ($this->timeout)
                usleep($this->timeout);
            return $this->write($data, ++$retry);
        }

        $this->char = $map[$this->namespace] = self::findFreeChar($map);
        $this->size = (int)ceil((strlen($data) * 1.5) / 1024) * 1024;
        $id = shmop_open(ftok(__FILE__, $this->char), "c", 0777, $this->size);
        if (!$id)
            throw new Error("Failed to allocate new block '$this->char'");
        shmop_write($id, $data, 0);
        self::saveMap($regId, $map); // This closes $regId
        self::end_id($this->id, true);
        $this->id = $id;
        return true;
    }

    private static function saveMap($id, array $map) {
        $json = json_encode($map, JSON_UNESCAPED_SLASHES);
        shmop_write($id, pack('V', strlen($json)) . $json, 0);
        self::end_id($id);
    }

    private static function loadMap(&$id): array {
        $key = ftok(__FILE__, self::REGISTRY_CHAR);
        
        // Try to open existing
        if ($id = @shmop_open($key, "w", 0, 0)) {
            $header = shmop_read($id, 0, 4);
            // Check for empty/corrupt header
            if ($header === false) {
                 // Rare case: opened but failed read? fallback to recreate logic below
                 self::end_id($id, true); // Delete corrupt
            } else {
                $len = unpack('Vlen', $header)['len'];
                // Valid data exists
                if ($len >= 2) {
                    $data = shmop_read($id, 4, $len);
                    return json_decode($data, true) ?? [];
                }
                // Len < 2 means registry is empty/invalid
                self::end_id($id); // Close it, we will overwrite or ignore
            }
        }

        // Create new registry
        if (!$id = shmop_open($key, "c", 0777, self::REGISTRY_SIZE))
            throw new Error("Failed to create registry");

        shmop_write($id, pack('V', 2) . '{}', 0);
        return [];
    }

    private static function findFreeChar($map) {
        $used = array_values($map);
        for ($i = 98; $i <= 122; $i++) { # 'b'..'z'
            if (!in_array($c = chr($i), $used))
                return $c;
        }
        throw new Error("Out of memory slots");
    }

    private function resync(&$regId, &$changed = null): array {
        $map = self::loadMap($regId);
        if (!$char = $map[$this->namespace] ?? false) {
            self::end_id($regId);
            return [];
        }

        if ($changed = ($char !== $this->char)) {
            self::end_id($this->id);
            $this->id = @shmop_open(ftok(__FILE__, $char), "w", 0, 0);
            if (!$this->id)
                throw new Error("Failed to switch block to '$char'");
            $this->char = $char;
            $this->size = shmop_size($this->id);
        }
        return $map;
    }

    private static function end_id($id, $is_delete = false) {
        $is_delete && @shmop_delete($id);
        PHP_VERSION_ID < 80000 && @shmop_close($id);
    }

    function __destruct() {
        $this->id && self::end_id($this->id);
    }
}
