<?php

class ZML # universal descriptor
{
    const version = '0.101';
    const chunk = 8192;

    static $zml;
    static $custom = [];

    public $bang = [];

    private $fh;
    private $head;
    private $array = [];

    function __construct($fn = false) {
        self::$zml or self::$zml = yml('+ @inc(zml)');
        if ($fn)
            $this->open($fn);
    }

    function close() {
        fclose($this->fh);
    }

    function open($fn) {
        $this->fh = fopen($fn, "c+b");
        $head = fread($this->fh, ZML::chunk);
        if ('type ' != substr($head, 0, 5))
            throw new Error("ZML type key for `$fn` not found");
        if (!$pos = strpos($head, "\n\n"))
            throw new Error("ZML head for `$fn` not found");
        $this->bang = bang(substr($head, 0, $pos));
        $this->head = [$pos += 2, substr($head, $pos)];
    }

    private function match($y, &$chunk) {
        if (!preg_match("/^!?([A-Z]+):(| ([^\n]+) (\d+)| ([^\n]+))\n/s", $chunk, $m))
            return false;
        $y->data = $y->arg = $y->len = '';
        [$y->line, $y->op] = $m;
        $chunk = substr($chunk, $size = strlen($y->line));
        if ('' === $m[2])
            return $size;
        if ('!' == $y->line[0]) {
            $y->arg = substr($m[2], 1);
        } elseif ('' !== $m[4]) {
            $y->arg = $m[3];
            $y->len = (int)$m[4];
        } else {
            is_num($m[5]) ? ($y->len = (int)$m[5]) : ($y->arg = $m[5]);
        }
        return $size;
    }

    function read($y = false) {
        $y or $y = new stdClass;
        for ([$pos, $chunk] = $this->head; true; ) {
            if ($size = $this->match($y, $chunk)) {
                if ('END' == $y->op)
                    return;
                if ('' !== $y->len) {
                    $size += 1 + $y->len;
                    strlen($chunk) > $y->len or $chunk .= fread($this->fh, ZML::chunk + $y->len);
                    $y->data = substr($chunk, 0, $y->len);
                    $chunk = substr($chunk, 1 + $y->len);
                }
                yield $pos => $y;
                $pos += $size;
            } elseif (feof($this->fh)) {
                throw new Error("ZML `END:` not found");
            } else {
                $chunk .= fread($this->fh, ZML::chunk);
            }
        }
    }
}
