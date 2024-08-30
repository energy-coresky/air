<?php

class ZML # universal descriptor
{
    const version = 0.001;
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

    function read() {
        for ([$pos, $chunk] = $this->head; true; ) {
            if (preg_match("/^([A-Z]+:) ?(.*?) ?(\d*)\n/s", $chunk, $val)) {
                $y = (object)array_combine(['line', 'op', 'arg', 'len'], $val);
                if ('END:' == $y->op)
                    return;
                $chunk = substr($chunk, $size = strlen($y->line));
                if ('' !== $y->len) {
                    $size += 1 + ($y->len = (int)$y->len);
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
