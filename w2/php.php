<?php

class PHP
{
    const version = 0.397;

    const TYPE = 1;
    const _GLOB = 0;
    const _NS   = 0b0001;
    const _FUNC = 0b0010;
    const _CLASS = 0b0100;
    const _METH = 0b1000;

    static $php = false;
    static $name_tok = [[T_STRING, T_NS_SEPARATOR]];

    public $pad; # 0 for minified PHP
    public $tok;
    public $count = 0;
    public $syntax_fail = false;
    public $ns = '';
    public $use = [];

    private $stack = [];
    private $x = [];
    private $pos;
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
        PHP::$name_tok[0] += [2 => T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];
        PHP::$name_tok[1] = PHP::$name_tok[0] + [5 => T_NAMESPACE];
    }

    function __construct(string $in, $pad = 4) {
        PHP::$php or $this->ini_once();
        $this->pad = $pad;
        try {
            $this->tok = token_get_all(unl($in), TOKEN_PARSE);
        } catch (Throwable $e) {
            $this->tok = [$this->syntax_fail = $e->getMessage()];
        }
        $this->count = count($this->tok);
    }

    function __get($name) {
        return PHP::$php->{substr($name, 1)};
    }

    function __toString() {
        return $this->debug();
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
            $oc = $this->bracket($y);
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

    function debug() {
        $out = '';
        foreach ($this->rank() as $y) {
            [$y, $next, $ary] = $y;
            if (T_STRING == $y->tok) {
                $s = $y->i . ' ' . $y->tok . ' ' . token_name($y->tok) . ' ' . $y->str;
                if ($y->x)
                   $s .= " ------------------- SZ: $y->x";
                $out .= "===================== \n$s\n";
            }
        }
        return var_export($this->_def_3t, true) . $out;
    }

    function bracket($y) { # open/none=0/close 1|true 0|false -1
        if ($y->tok) # see ... T_STRING_VARNAME ?
            return in_array($y->tok, [T_DOLLAR_OPEN_CURLY_BRACES, T_CURLY_OPEN]); # bool !
        return array_search($y->str, $this->oc, true) ? -1 : (int)isset($this->oc[$y->str]);
    }

    function rank($func = false) {
        $prev = $curly = 0;
        $place = PHP::_GLOB;
        for ($y = $this->tok(); $y; $y = $next) {
            $skip = false;
            if ($this->is_name($y, $next)) {
                $y->x = $this->_def_3t[$prev] ?? false;
                $is_fun = 'FUNCTION' == $y->x;
                $open = $this->match('(', $next);
                if ($y->x) {
                    if ('NAMESPACE' == $y->x) {
                        $this->ns = $y->str;
                        $this->use = [];
                        $place = PHP::_NS; # namespace; - global 2exclude
                    } elseif ($is_fun) {
                        
                    }
                } elseif (T_NEW == $prev) {
                    $y->x = 'class';
                } elseif (T_DOUBLE_COLON == $prev) {
                    $y->x = $open ? '_method' : 'const-class';
                } elseif (T_OBJECT_OPERATOR == $prev) { # see .. T_NULLSAFE_OBJECT_OPERATOR
                    $y->x = $open ? 'method' : 'property';
                } elseif ($this->match(T_DOUBLE_COLON, $next)) {
                    $y->x = 'class';
                } elseif ($open) {
                    $y->x = $this->match(T_DOUBLE_COLON, $y, -1, false) ? '_method' : 'function';
                } elseif (in_array($y->str, $this->_types)) {
                    $y->x = PHP::TYPE;
                } else {
                    $y->x = '___________USAGE';
                }#T_INSTEADOF T_IMPLEMENTS T_HALT_COMPILER T_EXTENDS T_EVAL T_USE T_AS T_VAR
//if ($open) {$from = $this->get_params($to);$y->x .= $this->str($from, $to);}
            } elseif (T_FUNCTION === $prev && '&' === $y->str) {
                $skip = true;
            }
            yield [$y, $next, $prev];

            if ($skip || $this->is_ignore($y))
                continue;
            $prev = $y->tok ?: ord($y->str);
        }
    }

    function get_params(&$to) {
        for (
            $to = $this->pos, $ps = 1;
            $ps && ++$to < $this->count;
            '(' !== $this->tok[$to] or $ps++, ')' !== $this->tok[$to] or $ps--
        );
        return $this->pos;
    }

    function str($i, $to) {
        for ($s = ''; $i <= $to; $s .= is_array($this->tok[$i]) ? $this->tok[$i++][1] : $this->tok[$i++]);
        return $s;
    }

    function abs_name() {
        
    }

    function match($tok, $y, $step = 1, $skip = false) {
        $step > 0 or $y = $this->tok($y->i + $step);
        for (; $this->is_ignore($y, $skip); $y = $this->tok($y->i + $step));
        return $tok === ($y->tok ?: $y->str) ? ($this->pos = $y->i) : false;
    }

    function is_name($y, &$next) {
        $ok = fn($tok, int $ns = 0) => in_array($tok, PHP::$name_tok[$ns], true);
        $next = $this->tok($y->i + 1);
        if (!$ok($y->tok, (int)($next && T_NS_SEPARATOR == $next->tok)))
            return false;
        $y->tok = T_STRING;
        while ($next && $ok($next->tok)) {
            $y->str .= $next->str;
            $next = $this->tok($next->i + 1);
        }
        return true;
    }

    function is_ignore($y, $skip = false) {
        //if ($skip === $y->str)
          //  return true;
        return in_array($y->tok, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]); # T_ATTRIBUTE not ignore
    }
}
