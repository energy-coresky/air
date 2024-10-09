<?php

class PHP
{
    const version = 0.303;

    const KEYWORD = 1;
    const USAGE = 2;
    const DEFINITION = 3;
    const CHARS = 4;
    const TYPE = 5;

    static $php;

    //public $lines = 1;
    public $pad; # 0 for minified PHP
    public $tok;
    public $count = 0;

    private $ns = '';
    private $use = [];
    private $stack = [];
    private $sz = [];
    private $oc = [
        '{' => '}',
        '(' => ')',
        '[' => ']',
    ];

    function __construct(string $in = '', $pad = 0) {
        defined('T_ENUM') or define('T_ENUM', 11001);
        self::$php or self::$php = yml('+ @object@inc(php)');
        $this->tok = token_get_all(unl($in), TOKEN_PARSE);
        $this->count = count($this->tok);
        //$this->lines = substr_count($this->in, "\n");
        $this->pad = $pad;
    }

    static function file($name) {
        echo new PHP(file_get_contents($name));
    }

    function trace($t) {
        static $i = 0; $i++;
        $s = $t->tok . ' ' . token_name($t->tok) . ' ' . $t->str;
        if ($t->sz)
            $s .= " ------------------- SZ: $t->sz";
        return "===================== $i\n$s\n";// . var_export($t, 1);
    }

    function __toString() {
        $this->parse_nice();
        $out = $x = '';
        for ($y = $this->tok(); $y; $y = $next) {
            $next = $this->tok($y->i + 1);
$x .= $this->trace($y);
            if (!$this->pad) {
                $prev = $this->tok($y->i - 1);
                if (T_COMMENT == $y->tok
#                    || T_WHITESPACE == $y->tok && in_array($prev->str, self::$php->prev_space)
#                    || T_WHITESPACE == $y->tok && in_array($next->str, self::$php->after_space)
                )
                    continue;
            }
            $out .= $y->str;
        }
        return $out . "\nlines: ??\n$x";
    }

    function tok($i = 0) {
        if ($i < 0 || $i > $this->count)
            return false;
        $tok =& $this->tok[$i];
        is_array($tok) or $tok = [0, $tok];
        return (object)[
            'i' => $i,
            'tok' => $tok[0],
            'str' => &$tok[1],
            'rank' => 0, # KEYWORD/CHARS/USAGE/DEFINITION
            'sz' => $this->sz[$i] ?? 0,
//            'oc' => 0, # open/none/close 1/0/-1
        ];
    }

    function parse_nice() {
        $sz =& $this->sz;
        $stk =& $this->stack;
        $open = array_keys($this->oc);
        $close = array_values($this->oc);
        for ($y = $this->tok(); $y; $y = $next) {
            $next = $this->tok($y->i + 1);

            if ($next && T_COMMENT == $y->tok && "\n" == $y->str[-1] && T_WHITESPACE == $next->tok) {
                $y->str = substr($y->str, 0, -1);
                $next->str = "\n" . $next->str;
            }
            
            if ($stk)
                $sz[end($stk)] += strlen(' ' == $y->str ? ' ' : trim($y->str));
            if (in_array($y->str, $open)) {
                $stk[] = $y->i;
                $sz[$y->i] = 1;
            } elseif (in_array($y->str, $close)) {
                $char = $this->tok[end($stk)][1];
                if ($y->str == $this->oc[$char]) { // checking!!
                    $j = array_pop($stk);
                    if ($stk)
                        $sz[end($stk)] += $sz[$j] - 1;
                }
            }
        }
    }

    function parse_rank() {
        $line = 1;
        foreach ($this->tokens() as $i => $y) {
            if (T_STRING == $y->tok) {
                if (in_array($y->str, self::$php->types))
                    $y->rank = PHP::TYPE;
                    
            } elseif (in_array($y->str, self::$php->keywords)) { // 2do: chk
                $y->rank = PHP::KEYWORD;
            }
            switch ($y->tok) {
                case T_NAMESPACE:
                    $this->ns = '';
                    break;
                case T_GLOBAL: ;
                case T_CONST: ;
                case T_VARIABLE: ;
                case T_INTERFACE: ;
                case T_TRAIT: ;
                case T_EVAL: ;
            }
            $line += substr_count($y->str, "\n");
        }
    }
}
