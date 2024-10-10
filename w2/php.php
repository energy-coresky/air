<?php

class PHP
{
    const version = 0.389;

    const KEYWORD = 1; # ranks
    const TYPE = 2;
    const CHAR = 3;
    const IGNORE = 4;
    const _NS = 10; # definitions
    const _IF = 11; # usages: 20 + definitions
    const _TRT = 12;
    const _ENUM = 13;
    const _VAR = 14;
    const _FUN = 15;
    const _CON = 16;
    const _DEF = 17;
    const _CLS = 18;
    const _EVAL = 19;
    const _USE_LIKE = 50;

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
        $this->pad = $pad; /*
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
            }*/
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
     if ($t->x)
         $s .= " ------------------- SZ: $t->x";
     return "===================== $i\n$s\n";// . var_export($t, 1);
 }

    function __toString() {
        $this->parse_rank();
        $out = $trc = '';
        for ($y = $this->tok(); $y; $y = $next) {
            $next = $this->tok($y->i + 1);
$trc .= $this->trace($y);
            if ($this->pad) {
                $prev = $this->tok($y->i - 1);
                if (T_COMMENT == $y->tok
#                    || T_WHITESPACE == $y->tok && in_array($prev->str, $this->_prev_space)
#                    || T_WHITESPACE == $y->tok && in_array($next->str, $this->_after_space)
                )
                    continue;
            }
            $out .= $y->str;
        }
       $qq = var_export($this->_def_3t,1);
        return $out . "\nlines: $qq\n$trc";
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
            'line' => $tok[2] ?? fn() => $this->char_line($i),
            'x' => $this->x[$i] ?? 0,
            'oc' => function () use (&$tok) { # open/none=0/close 1|true 0|false -1
                if ($tok[0])
                    return in_array($tok[0], [T_DOLLAR_OPEN_CURLY_BRACES, T_CURLY_OPEN]); # bool !
                return array_search($tok[1], $this->oc, true) ? -1 : (int)isset($this->oc[$tok[1]]);
            },
        ];
    }

    function parse_nice() {
        $x =& $this->x;
        $stk =& $this->stack;
        for ($y = $this->tok(); $y; $y = $next) {
            $next = $this->tok($y->i + 1);

            if ($next && T_COMMENT == $y->tok && "\n" == $y->str[-1] && T_WHITESPACE == $next->tok) {
                $y->str = substr($y->str, 0, -1);
                $next->str = "\n" . $next->str;
            }
            
            if ($stk)
                $x[end($stk)] += strlen(' ' == $y->str ? ' ' : trim($y->str));
            $oc = ($y->oc)();
            if (1 == $oc) {
                $stk[] = $y->i;
                $x[$y->i] = 1;
            } elseif (-1 == $oc) {
                $char = $this->tok[end($stk)][1];
                if ($y->str == $this->oc[$char[1] ?? $char[0]]) { // checking!!
                    $j = array_pop($stk);
                    if ($stk)
                        $x[end($stk)] += $x[$j] - 1;
                }
            }
        }
    }

    function parse_rank($func = false) {
        $ary = [3, 3, 3];
        for ($y = $this->tok(); $y; $y = $next) {
            $next = $this->tok($y->i + 1);
            $rank =& $this->x[$y->i];
            if (T_STRING == $y->tok) {
                if (in_array($y->str, $this->_types)) {
                    $rank = PHP::TYPE;
                } elseif (T_WHITESPACE === $ary[2]) {
                    if ($x = $this->_def_3t[$ary[1]] ?? 0)
                        $rank = $x;
                } elseif (T_OBJECT_OPERATOR === $ary[2]) {
                    $rank = 'property';
                }

            } elseif (in_array($y->tok, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                $rank = PHP::IGNORE;
            } elseif (in_array($y->str, $this->_keywords)) { // 2do: chk
                $rank = PHP::KEYWORD;
            } elseif (T_VARIABLE == $y->tok) {
                $rank = T_DOUBLE_COLON == $ary[2] ? '_property' : PHP::_VAR;
            } else {
                $rank = PHP::CHAR;
            }
            array_shift($ary);
            $ary[] = $y->tok ?: $y->str;
            
        }
    }

    function match() {
    }

    function abs_name() {
        
    }
}
