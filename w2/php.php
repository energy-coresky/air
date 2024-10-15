<?php

class PHP
{
    const version = 0.501;

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
    public $use = [[]/*class-like*/, []/*function*/, []/*const*/];

    private $stack = [];
    private $x = [];

    static function file($name, $pad = 4) {
        return new PHP(file_get_contents($name), $pad);
    }

    private function ini_once() {
        PHP::$php = Plan::php();
        foreach (PHP::$php->gt_74 as $i => $const)
            defined($const) or define($const, $i + 11001);
        $p =& PHP::$php->tokens; # definitions
        $p = array_combine(array_map(fn($k) => constant("T_$k"), $p), $p);
        $p =& PHP::$php->use_tokens; # usages
        $p = array_combine(array_map(fn($k) => constant("T_$k"), array_keys($p)), $p);
        $p[58] = 'colon'; # ord(':') === 58
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
#                    || T_WHITESPACE == $y->tok && in_array($next->str, $this->_next_space)
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
                if ($y->str == $this->_oc[$char[1] ?? $char[0]]) { // checking!!
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
            //if (T_STRING == $y->tok) {
            if (is_string($y->x)) {
                $s = "$y->i $y->line $y->tok " . token_name($y->tok) . ' ' . $this->get_name($y->str);
                if ($y->x)
                   $s .= " ------------------- $y->x";
                $out .= "===================== \n$s\n";
            }
        }
        return var_export($this->_tokens, true) . var_export($this->_use_tokens, true) . $out;
    }

    function bracket($y) { # open/none=0/close 1|true 0|false -1
        if ($y->tok) # see ... T_STRING_VARNAME ?
            return in_array($y->tok, [T_DOLLAR_OPEN_CURLY_BRACES, T_CURLY_OPEN]); # bool !
        return array_search($y->str, $this->_oc, true) ? -1 : (int)isset($this->_oc[$y->str]);
    }

    function next($y, &$next) {
        for (; $this->is_ignore($y); $y = $this->tok($y->i + 1));
        $next = $y->tok ?: $y->str;
        return $y->i;
    }

    function is_name($y, $prev, &$next) {
        $ok = fn($tok, int $ns = 0) => in_array($tok, PHP::$name_tok[$ns], true);
        $y->next = $this->tok($y->i + 1);
//     $next = $this->next($y->next);
        if (!$ok($y->tok, (int)($y->next && T_NS_SEPARATOR == $y->next->tok)))
            return false;
        $y->tok = T_STRING;
        while ($y->next && $ok($y->next->tok)) {
            $y->str .= $y->next->str;
            $y->next = $this->tok($y->next->i + 1);
        }
        $y->is_def = false;
        $i = $this->next($y->next, $next);
        if ('(' === $next)
            $y->i_open = $i;
        if ($y->x = $this->_tokens[$prev] ?? false) {
            $y->is_def = true;
        } elseif ($y->x = $this->_use_tokens[$prev] ?? false) {
            if (is_array($y->x))
                $y->x = $y->i_open ? $y->x[0] : $y->x[1];
        } elseif (T_DOUBLE_COLON === $next) {
            $y->x = 'class';
        } elseif ($y->i_open) {
            $y->x = 'function';
        } elseif (in_array($y->str, $this->_types)) {
            $y->x = PHP::TYPE;
        } elseif (T_VARIABLE === $next) {
            $y->x = 'class-else';
        } else {
            $y->x = '___________USAGE';
        }
        return true;
    }

    function rank($func = false) {
        $prev = $curly = 0;
        $pos = PHP::_GLOB;
        for ($y = $this->tok(); $y; $y = $y->next) { # T_HALT_COMPILER T_EVAL T_AS
            $y->i_open = $skip = false;
            if ($this->is_name($y, $prev, $next)) {
                if (T_NAMESPACE == $prev) {
                    $this->ns = $y->str;
                    $this->use = [];
                    $pos = PHP::_NS; # namespace; - global 2exclude
                } elseif ($y->is_def && T_CONST != $prev) { # def class-like
                    if (T_FUNCTION == $prev) {
                        if (PHP::_CLASS == $pos)
                            $y->x = 'METHOD';
                        if (PHP::_GLOB == $pos || PHP::_NS == $pos)
                            $pos = PHP::_FUNC;
                    } else {
                        $pos = PHP::_CLASS;
                    }
                } elseif (in_array($prev, [T_EXTENDS, T_IMPLEMENTS])) {
                    if (',' === $next)
                        $skip = true;
                }
if ($y->i_open) {$to = $this->get_params($y); $y->x .= $this->str($y->i_open, $to);}

            } elseif (T_FUNCTION === $prev && '&' === $y->str || ',' === $y->str) {
                $skip = true;
            }
            yield $prev => $y;

            if ($skip || $this->is_ignore($y))
                continue;
            $prev = $y->tok ?: ord($y->str);
        }
    }

    function get_name($name) {
        return $this->ns . '\\' . $name;
    }

    function get_params($y) {
        for ($to = $y->i_open, $n = 1; $n && ++$to < $this->count; )
            ')' === $this->tok[$to] ? $n-- : ('(' === $this->tok[$to] ? $n++ : 0);
        return $to;
    }

    function get_modifiers() {
        // (T_VAR)T_PUBLIC T_PROTECTED T_PRIVATE T_STATIC T_ABSTRACT T_FINAL T_READONLY(8.1)
    }

    function str($i, $to, $skip_ignore = false) { //2do $skip_ignore
        for ($s = ''; $i <= $to; $s .= is_array($this->tok[$i]) ? $this->tok[$i++][1] : $this->tok[$i++]);
        return $s;
    }

    function is_ignore($y) {
        return in_array($y->tok, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]); # T_ATTRIBUTE not ignore
    }
}
