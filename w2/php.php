<?php

class PHP
{
    const version = 0.388;

    const KEYWORD = 1;
    const USAGE = 2;
    const DEFINITION = 3;
    const IGNORE = 4;
    const TYPE = 5;
    const CHAR = 6;

    public $pad; # 0 for minified PHP
    public $tok;
    public $count = 0;
    public $syntax_fail = false;

    private $php;
    private $ns = '';
    private $use = [];
    private $stack = [];
    private $x = [];
    private $oc = [
        '{' => '}',
        '(' => ')',
        '[' => ']',
    ];

    function __construct(string $in, $pad = 1) {
        defined('T_ENUM') or define('T_ENUM', 11001);
        $this->php = Plan::php();
        try {
            $this->tok = token_get_all(unl($in), TOKEN_PARSE);
        } catch (Throwable $e) {
            $this->tok = [$this->syntax_fail = $e->getMessage()];
        }
        $this->count = count($this->tok);
        $this->pad = $pad;
    }

    function __get($name) {
        $name = substr($name, 1);
        return $this->php->$name;
    }

    static function file($name, $pad = 4) {
        return new PHP(file_get_contents($name), $pad);
    }

 function trace($t) {
     static $i = 0; $i++;
     $s = $t->tok . ' ' . token_name($t->tok) . ' ' . $t->str;
     if (!$t->x)
         $s .= " ------------------- SZ: $t->x";
     return "===================== $i\n$s\n";// . var_export($t, 1);
 }

    function __toString() {
        $this->parse_rank();
        $out = $trc = '';
        for ($y = $this->tok(); $y; $y = $next) {
            $next = $this->tok($y->i + 1);
$trc .= $this->trace($y);
            if (!$this->pad) {
                $prev = $this->tok($y->i - 1);
                if (T_COMMENT == $y->tok
#                    || T_WHITESPACE == $y->tok && in_array($prev->str, $this->_prev_space)
#                    || T_WHITESPACE == $y->tok && in_array($next->str, $this->_after_space)
                )
                    continue;
            }
            $out .= $y->str;
        }
        return $out . "\nlines: ??\n$trc";
    }

    function char_line($i) {
        for ($d = 1; $tok =& $this->tok[$i - $d++]; ) {
            if ($tok[0])
                return $tok[2] + substr_count($tok[1], "\n");
        }
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
            'line' => $tok[0] ? $tok[2] : fn() => $this->char_line($i),
            'x' => $this->x[$i] ?? 0,
            'oc' => 0, # open/none/close 1/0/-1
        ];
    }

    function parse_nice() {
        $x =& $this->x;
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
                $x[end($stk)] += strlen(' ' == $y->str ? ' ' : trim($y->str));
            if (in_array($y->str, $open)) {
                $stk[] = $y->i;
                $x[$y->i] = 1;
            } elseif (in_array($y->str, $close)) {
                $char = $this->tok[end($stk)][1];
                if ($y->str == $this->oc[$char]) { // checking!!
                    $j = array_pop($stk);
                    if ($stk)
                        $x[end($stk)] += $x[$j] - 1;
                }
            }
        }
    }

    function match() {
    }

    function parse_rank() {
        
        for ($y = $this->tok(); $y; $y = $next) {
            $next = $this->tok($y->i + 1);
            if (T_STRING == $y->tok) {
                if (in_array($y->str, $this->_types))
                    $this->x[$y->i] = PHP::TYPE;
                    
            } elseif (in_array($y->tok, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                $this->x[$y->i] = PHP::IGNORE;
            } elseif (in_array($y->str, $this->_keywords)) { // 2do: chk
                $this->x[$y->i] = PHP::KEYWORD;
            } else {
                $this->x[$y->i] = PHP::CHAR;
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
            
        }
    }
}
