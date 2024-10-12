<?php

class PHP
{
    const version = 0.390;

    const KEYWORD = 1; # ranks
    const TYPE = 2;
    const CHAR = 3;
    const IGNORE = 4;
    const _USE_LIKE = 50;

    static $php = false;

    public $pad; # 0 for minified PHP
    public $tok;
    public $count = 0;
    public $syntax_fail = false;

    private $ns = '';
    private $use = [];
    private $stack = [];
    private $x = [];
    private $oc = [
        '{' => '}',
        '(' => ')',
        '[' => ']',
    ];

    static function file($name, $pad = 4) {
        return new PHP(file_get_contents($name), $pad);
    }

    private function ini_once() {
        PHP::$php = Plan::php();
        foreach (PHP::$php->gt_74 as $i => $const)
            defined($const) or define($const, $i + 11001);
        $p =& PHP::$php->def_3t;
        $p = array_combine(array_map(fn($v) => constant("T_$v"), $p), $p);
        PHP::$php->is_ignore = fn($y) => in_array($y->tok, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]);
    }

    function __construct(string $in, $pad = 1) {
        PHP::$php or $this->ini_once();
        $this->pad = $pad;
        try {
            $this->tok = token_get_all(unl($in), TOKEN_PARSE);
        } catch (Throwable $e) {
            $this->tok = [$this->syntax_fail = $e->getMessage()];
        }
        $this->count = count($this->tok);
        /*
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
        return PHP::$php->{substr($name, 1)};
    }

    function __toString() {
        return $this->trace();
        return "\nlines: $qq\n";
        $out = '';
        for ($y = $this->tok(); $y; $y = $next) {
            $next = $this->tok($y->i + 1);
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
        return "\nlines: $qq\n";
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
            'tok' => &$tok[0],
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

 function trace() {
     $out = '1';
     foreach ($this->rank() as $y) {
         [$y, $next, $ary] = $y;
         if (T_STRING == $y->tok) {
             $s = $y->i . ' ' . $y->tok . ' ' . token_name($y->tok) . ' ' . $y->str;
             if ($y->x)
                $s .= " ------------------- SZ: $y->x";
             $out .= "===================== \n$s\n";
         }
     }
     return var_export($this->_def_3t,1) . $out;
 }

    function rank($func = false) {
        $ary = [0, 0, 0];
        $curly = 0;
        for ($y = $this->tok(); $y; $y = $next) {
            $next = $this->tok($y->i + 1);
            $rank =& $this->x[$y->i];
            if (T_STRING == $y->tok) {
                while ($next && in_array($next->tok, [T_STRING, T_NS_SEPARATOR])) {
                    $y->str .= $next->str;
                    $next = $this->tok($next->i + 1);
                }
                if (in_array($y->str, $this->_types)) {
                    $y->x = $rank = PHP::TYPE;
                } elseif ($x = $this->_def_3t[$ary[2]] ?? 0) {
                    $y->x = $rank = $x;
                } elseif (T_DOUBLE_COLON == $ary[2]) {
                    $y->x = $rank = $this->match('(', $next) ? '_method' : 'const-class';
                } elseif (T_OBJECT_OPERATOR == $ary[2]) {
                    $y->x = $rank = $this->match('(', $next) ? 'method' : 'property';
                } elseif ($this->match(T_DOUBLE_COLON, $next)) {
                    $y->x = $rank = 'class';
                } elseif ($this->match('(', $next)) {
                    $y->x = $rank = $this->match(T_DOUBLE_COLON, $y, -1) ? '_method' : 'function';
                } else {
                    $y->x = $rank = '___________USAGE';
                }
            }
            yield [$y, $next, $ary];

            if (($this->_is_ignore)($y))
                continue;
            array_shift($ary);
            $ary[] = $y->tok ?: ord($y->str);
        }
    }

    function match($t, $y, $step = 1) {
        $step > 0 or $y = $this->tok($y->i + $step);
        for (; ($this->_is_ignore)($y); $y = $this->tok($y->i + $step));
        return $t === ($y->tok ?: $y->str);
    }

    function abs_name() {
        
    }
}
/*
 elseif (T_VARIABLE == $y->tok) {
                $rank = T_DOUBLE_COLON === $ary[2] ? '_property' : PHP::_VAR;
            }
else {
                $rank = PHP::CHAR;
            }
            elseif (in_array($y->tok, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                $rank = PHP::IGNORE;
            } elseif (in_array($y->str, $this->_keywords)) { // 2do: chk
                $rank = PHP::KEYWORD;
            } */


