<?php

class ZML # universal descriptor
{
    const version = 0.001;
    const chunk = 8192;

    static $zml;
    static $custom = [];

    private $fh;
    private $pos;
    private $bang = [];
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
    }

    function read() {
        $chunk = fread($this->fh, ZML::chunk);
        if (!$pos = strpos($chunk, "\n\n"))
            throw new Error("ZML head for `$fn` not found");
        $this->bang = bang(substr($chunk, 0, $pos));
        $chunk = substr($chunk, $pos += 2);
        for (;true;) {
            if (preg_match("/^([A-Z]+:) ?(.*?) ?(\d*)\n/s", $chunk, $val)) {
                $y = (object)array_combine(['line', 'op', 'arg', 'len'], $val);
                if ('END:' == $y->op)
                    return;
                $chunk = substr($chunk, $size = strlen($y->line));
                if ('' !== $y->len) {
                    $size += 1 + ($y->len = (int)$y->len);
                    strlen($chunk) >= $y->len or $chunk .= fread($this->fh, ZML::chunk + $y->len);
                    $y->data = substr($chunk, 0, $y->len);
                }
                yield $pos => $y;
                $pos += $size;
                $chunk = substr($chunk, 1 + $y->len);
            } elseif (feof($this->fh)) {
                throw new Error("ZML `END:` not found");
            } else {
                $chunk .= fread($this->fh, ZML::chunk);
            }
        }
    }

    function info(&$ary) {
        foreach ($this->read() as $pos => $y) {
            $ary[$pos] = trim($y->line);
        }
        return $this->bang;
    }
}
