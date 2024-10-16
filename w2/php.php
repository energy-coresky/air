<?php

class PHP
{
    const version = 0.511;

    const TYPE = 1;
    const _GLOB = 0;
    const _NS   = 2;
    const _FUNC = 4;
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

    private $stack = [];
    private $x = [];

    static function file($name, $pad = 4) {
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
        $p[58] = 'colon'; # ord(':') === 58
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
        for ($y = $this->tok(); $y; $y = $new) {
            $new = $this->tok($y->i + 1);
            if ($this->pad) {
                $prev = $this->tok($y->i - 1);
                if (T_COMMENT == $y->tok
#                    || T_WHITESPACE == $y->tok && in_array($prev->str, $this->_prev_space)
#                    || T_WHITESPACE == $y->tok && in_array($new->str, $this->_next_space)
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
//            $oc = $this->bracket($y);
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
            if (is_string($y->rank)) {
                $s = $y->x->pos . " $y->line " . token_name($y->tok) . ' ' . $this->get_name($y->str);
                if ($y->open)
                    $y->rank .= $this->str($y->open, $this->get_close($y));
                if ($y->rank)
                   $s .= " ------------------- $y->rank";
                $out .= "===================== \n$s\n";
            }
        }
        return var_export($this->_tokens, true) . var_export($this->_use_tokens, true) . $out;
    }

    function tok($i = 0) {
        if ($i < 0 || $i > $this->count)
            return false;
        $tok =& $this->tok[$i];
        is_array($tok) or $tok = [0, $tok]; # [ord($tok), $tok];  PHP::QUOTE == x22 is "
        return (object)[
            'i' => $i,
            'tok' => &$tok[0],
            'str' => &$tok[1],
            'line' => $tok[2] ?? fn() => $this->char_line($i),
            'x' => &$this->x,
        ];
    }// return (int)in_array($y->tok, [T_DOLLAR_OPEN_CURLY_BRACES, T_CURLY_OPEN]);
    //return array_search($y->str, $this->_oc, true) ? -1 : (int)isset($this->_oc[$y->str]);

    function bracket($y, &$in_str) {
        if ('"' == $y->str || in_array($y->tok, [T_START_HEREDOC, T_END_HEREDOC]))
            $in_str = !$in_str;
        if ($y->tok || $in_str)
            return 0;
        return '}' == $y->str ? -1 : (int)('{' == $y->str);
    }

    function next($y, &$next) {
        for (; $this->is_ignore($y); $y = $this->tok($y->i + 1));
        $next = $y->tok ?: $y->str;
        return $y->i;
    }

    function is_name($y, $prev) {
        $ok = fn($tok, int $ns = 0) => in_array($tok, PHP::$name_tok[$ns], true);
        $y->new = $this->tok($y->i + 1);
        if (!$ok($y->tok, (int)($y->new && T_NS_SEPARATOR == $y->new->tok)))
            return false; # $y->next not calculated !

        $y->tok = T_STRING;
        while ($y->new && $ok($y->new->tok)) { # collect T_NAME_xx for 7.4
            $y->str .= $y->new->str;
            $y->new = $this->tok($y->new->i + 1);
        }
        $y->is_def = false;
        $i = $this->next($y->new, $y->next);
        if ('(' === $y->next)
            $y->open = $i;

        if ($y->rank = $this->_tokens[$prev] ?? false) {
            $y->is_def = true;
        } elseif ($y->rank = $this->_use_tokens[$prev] ?? false) {
            if (is_array($y->rank))
                $y->rank = $y->open ? $y->rank[0] : $y->rank[1];
        } elseif (T_DOUBLE_COLON === $y->next) {
            $y->rank = 'class';
        } elseif ($y->open) {
            $y->rank = 'function';
        } elseif (in_array($y->str, $this->_types)) {
            $y->rank = PHP::TYPE;
        } elseif (T_VARIABLE === $y->next) {
            $y->rank = 'class-else';
        } elseif (!$y->x->in_str) {
            $y->rank = '___________USAGE';
        }
        return true;
    }

    function rank() {
        $this->x = (object)[ # T_HALT_COMPILER T_EVAL T_AS
            'nscurly' => 0,
            'curly' => 0,
            'use' => 0,
            'pos' => $prev = 0,
            'in_str' => false,
        ];
        $x =& $this->x;
        for ($y = $this->tok(); $y; $y = $y->new) {
            $y->open = $y->rank = $y->next = $skip = false;
            $x->curly += $y->curly = $this->bracket($y, $x->in_str);

            if ($this->is_name($y, $prev)) {
                if (T_NAMESPACE == $prev) {
                    $this->set_ns($y->str, $x);
                } elseif ($y->is_def && T_CONST != $prev) { # def class-like
                    if (T_FUNCTION == $prev) {
                        ($cls = PHP::_CLASS & $x->pos) ? ($y->rank = 'METHOD') : $x->nscurly = $x->curly;
                        $x->pos |= $cls ? PHP::_METH : PHP::_FUNC;
                    } else {
                        $x->pos |= PHP::_CLASS;
                        $x->nscurly = $x->curly;
                    }
                } elseif (in_array($prev, [T_EXTENDS, T_IMPLEMENTS, T_USE])) {
                    if (',' === $y->next)
                        $skip = true;
                }
            } elseif (-1 == $y->curly && ($d = $x->curly - $x->nscurly) < 2) { # $d is 0 or 1
                $x->pos &= $d ? ~PHP::_METH : ~PHP::_CLASS;
                $d or $x->pos &= ~PHP::_FUNC;
            } else {
                $this->other($y, $prev, $skip);
            }
            yield $prev => $y;

            if ($skip || $this->is_ignore($y))
                continue;
            $prev = $y->tok ?: ord($y->str);
        }
    }

    function other($y, $prev, &$skip) {
        if (T_FUNCTION == $prev && '&' === $y->str || ',' === $y->str)
            return $skip = true;
        if (T_NAMESPACE == $prev && (';' == $y->str || 1 == $y->curly))
            return $this->set_ns('', $y->x); # return to global namespace
        if (T_USE == $y->tok)
            return $y->x->use = 0;
        if (T_USE == $prev) {
            $func = T_FUNCTION == $y->tok;
            if ($func || T_CONST == $y->tok)
                $skip = $y->x->use = $func ? 1 : 2;
            return;
        }
    }

    function set_ns($ns, $x) {
        $this->ns = $ns;
        $this->use = [[], [], []];
        $x->pos = '' === $ns ? PHP::_GLOB : PHP::_NS;
    }

    function get_name($name) {
        return $this->ns . '\\' . $name;
    }

    function get_close($y) {
        for ($to = $y->open, $n = 1; $n && ++$to < $this->count; )
            '(' === $this->tok[$to] ? $n++ : (')' !== $this->tok[$to] ? 0 : $n--);
        return $to;
    }

    function get_modifiers($y) {
        $ary = [];
        if (in_array($y->tok, $this->_modifiers))
            $ary[] = $y->tok;
        // (T_VAR)T_PUBLIC
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
