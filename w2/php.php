<?php

class PHP
{
    const version = 0.515;

    const _GLOB  = 0;
    const _NS    = 2;
    const _FUNC  = 4;
    const _CLASS = 8;
    const _METH = 16;

    static $php = false;
    static $name_tok = [[T_STRING, T_NS_SEPARATOR]];

    public $pad; # 0 for minified PHP
    public $tok;
    public $count = 0;
    public $syntax_fail = false;
    public $ns = '';
    public $use = [[]/*class-like*/, []/*function*/, []/*const*/];
    public $in_str = false;
    public $pos = 0;
    public $curly = 0;
    public $ns_crly = 0;

    private $stack = [];
    private $x = [];

    static function file(string $name, $pad = 4) {
        return new PHP(file_get_contents($name), $pad);
    }

    private function ini_once() {
        PHP::$php = Plan::php();
        foreach (PHP::$php->gt_74 as $i => $str)
            defined($const = "T_$str") or define($const, $i + 11001);
        $p =& PHP::$php->tokens; # definitions
        $p = array_combine(array_map(fn($k) => constant("T_$k"), $p), $p);
        $p =& PHP::$php->use_tokens; # usages
        $p = array_combine(array_map(fn($k) => constant("T_$k"), array_keys($p)), $p);
        $p[58] = T_CASE; # ord(':') === 58
        array_walk(PHP::$php->modifiers, fn(&$v) => $v = constant("T_$v"));
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

    function nice() {
        $x =& $this->x;
        $stk =& $this->stack;
        for ($y = $this->tok(); $y; $y = $new) {
            $new = $this->tok($y->i + 1);

            if ($new && T_COMMENT == $y->tok && "\n" == $y->str[-1] && T_WHITESPACE == $new->tok) {
                $y->str = substr($y->str, 0, -1);
                $new->str = "\n" . $new->str;
            }
            
            if ($stk)
                $x[end($stk)] += strlen(' ' == $y->str ? ' ' : trim($y->str));
//            $oc = $this->bracket($y); return array_search($y->str, $this->_oc, true) ? -1 : (int)isset($this->_oc[$y->str]);
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
        if ($this->syntax_fail)
            throw new Error($this->syntax_fail);
        $out = '';
        foreach ($this->rank() as $y) {
            if (T_STRING == $y->tok) {
                $s = $this->curly . " $y->line " . token_name($y->tok) . ' ' . $this->get_name($y);
                $s .= " ------------------- " . (is_int($y->rank) ? strtolower(token_name($y->rank)) : $y->rank);
                if ($y->open)
                    $s .= $this->str($y->open, $this->get_close($y));
                $out .= "===================== \n$s\n";
            } #else { $out .= $this->curly . " == $y->str\n"; }
        }
        return var_export($this->_tokens, true) . var_export($this->_use_tokens, true) . $out;
    }

    function tok($i = 0) {
        if ($i < 0 || $i > $this->count)
            return false;
        $tok =& $this->tok[$i];
        $is = is_array($tok) or $ary = [0, &$tok];
        $is ? ($p =& $tok) : ($p =& $ary);
        return (object)[
            'i' => $i,
            'tok' => &$p[0],
            'str' => &$p[1],
            'line' => $tok[2] ?? fn() => $this->char_line($i),
            'x' => &$this->x,
        ];
    }

    function bracket($y) {
        if ('"' == $y->str || in_array($y->tok, [T_START_HEREDOC, T_END_HEREDOC]))
            $this->in_str = !$this->in_str;
        if ($y->tok || $this->in_str)
            return 0;
        return '}' == $y->str ? -1 : (int)('{' == $y->str);
    }

    function is_name($y, $prev) {
        $ok = fn($tok, int $ns = 1) => in_array($tok, PHP::$name_tok[$ns]);

        $y->new = $this->tok($y->i + 1);
        for ($z = $y->new; $z && $this->is_ignore($z); $z = $this->tok($z->i + 1));
        $y->next = $z ? ($z->tok ?: $z->str) : 0;
        if (!$ok($y->tok, (int)(T_NS_SEPARATOR === $y->next)))
            return false;

        $y->tok = T_STRING;
        while ($ok($z->tok)) {
            $y->str .= $z->str; # collect T_NAME_xx for 7.4
            $y->new = $this->tok($z->i + 1);
            for ($z = $y->new; $this->is_ignore($z); $z = $this->tok($z->i + 1));
        }
        if ('(' === ($y->next = $z->tok ?: $z->str))
            $y->open = $z->i;

        if ($y->rank = $this->_tokens[$prev] ?? false) {
            $y->is_def = true;
        } elseif ($y->rank = $this->_use_tokens[$prev] ?? false) {
            if (is_array($y->rank))
                $y->rank = $y->rank[(int)!$y->open];
        } elseif (T_DOUBLE_COLON === $y->next) {
            $y->rank = T_CLASS;
        } elseif ($y->open) {
            $y->rank = T_FUNCTION;
        } elseif (in_array($y->str, $this->_types)) {
            $y->rank = T_LIST;
        } elseif (T_VARIABLE === $y->next) {
            $y->rank = T_CLASS;////////
        } elseif (!$this->in_str) {
            $y->rank = T_CONST;//////
        }
        return true;
    }

    function rank() { # T_HALT_COMPILER T_EVAL T_AS
        $prev = $ux = 0;
        for ($y = $this->tok(); $y; $y = $y->new) {
            $skip = $y->open = $y->rank = $y->next = $y->is_def = false;
            $this->curly += $y->curly = $this->bracket($y);

            if ($this->is_name($y, $prev)) {
                if (T_NAMESPACE == $prev) {
                    $this->set_ns($y->str, (int)('{' === $y->next));
                } elseif ($y->is_def && T_CONST != $prev) { # def class-like
                    if (T_FUNCTION == $prev) {
                        if ($cls = PHP::_CLASS & $this->pos)
                            $y->rank = 'METHOD';
                        $this->pos |= $cls ? PHP::_METH : PHP::_FUNC;
                    } else {
                        $this->pos |= PHP::_CLASS;
                    }
                } elseif (in_array($prev, [T_EXTENDS, T_IMPLEMENTS, T_USE])) {
                    ',' !== $y->next or $skip = true;
                    if (T_USE == $prev) {
                        if ($this->pos > 7) {
                            $y->rank = T_TRAIT; # fix use trait;
                        } else {
                            $this->use[$ux][] = $y->str;
                        }
                    }
                }
            } else {
                $this->other($y, $prev, $skip, $ux);
            }

            yield $prev => $y;
            if ($skip || $this->is_ignore($y))
                continue;
            $prev = $y->tok ?: ord($y->str);
        }
    }

    function other($y, $prev, &$skip, &$ux) {
        if (T_FUNCTION == $prev && '&' === $y->str || ',' === $y->str) {
            $skip = true;
        } elseif (T_NAMESPACE == $prev && (';' == $y->str || 1 == $y->curly)) {
            $this->set_ns('', $y->curly); # return to global namespace
        } elseif (T_USE == $y->tok) {
            $ux = 0;
        } elseif (T_USE == $prev) {
            $func = T_FUNCTION == $y->tok;
            if ($func || T_CONST == $y->tok)
                $skip = $ux = $func ? 1 : 2;
        } elseif (-1 == $y->curly && ($d = $this->curly - $this->ns_crly) < 2) {
            $this->pos &= $d ? ~PHP::_METH : ~PHP::_CLASS;
            $d or $this->pos &= ~PHP::_FUNC; # $d is 0 or 1
        }
    }

    function set_ns($ns, $crly) {
        $this->ns = $ns;
        $this->use = [[], [], []];
        $this->pos = '' === $ns ? PHP::_GLOB : PHP::_NS;
        $this->ns_crly = $crly;
    }

    function get_name($y) {
        //if (T_VAR == $y->rank)
            return $y->str;
        return $this->ns . '\\' . $y->str;
    }

    function get_close($y) {
        for ($to = $y->open, $n = 1; $n && ++$to < $this->count; )
            '(' === $this->tok[$to] ? $n++ : (')' !== $this->tok[$to] ? 0 : $n--);
        return $to;
    }

    function get_modifiers($y, $i = 4, $add_public = false) { //2do $add_public
        for ($ary = [], $i = $y->i - $i; $y = $this->tok($i--); ) {
            if ($this->is_ignore($y))
                continue;
            if (!in_array($y->tok, $this->_modifiers))
                break;
            $ary[] = T_VAR == $y->tok ? 'public' : strtolower($y->str);
        }
        return $ary;
    }

    function str($i, $to, $skip_ignore = false) { //2do $skip_ignore
        for ($s = ''; $i <= $to; $s .= is_array($this->tok[$i]) ? $this->tok[$i++][1] : $this->tok[$i++]);
        return $s;
    }

    function is_ignore($y) {
        return in_array($y->tok, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]); # T_ATTRIBUTE not ignore
    }
}
