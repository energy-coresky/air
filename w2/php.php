<?php

class PHP
{
    const version = 0.001;

    static $php;

    public $in;
    public $array = [];
    public $pad = 4; # 0 for minified PHP

    function __construct(string $in = '') {
        self::$php or self::$php = yml('+ @inc(php)');
        $this->in = unl($in);
    }

    static function file($name) {
        echo new PHP(file_get_contents($name));
    }

    function __toString() {
        $this->array or $this->parse();
    }

    function tokens($y = false) {
        $y or $y = (object)['line' => 1];
        foreach (token_get_all($this->in) as $t) {
            [$y->tok, $t] = is_array($t) ? $t : [0, $t];
            yield $t => $y;
            $y->line += substr_count($t, "\n");
        }
        return $y->line;
    }

    function parse() {
        $this->array = [];
        $tokens = $this->tokens();
        foreach ($tokens as $t => $y) {
           //$y->tok = token_name($y->tok);
            $this->array[] = [$y->line, $y->tok, $t];
        }
        array_unshift($this->array, [$tokens->getReturn()]);
    }
}
