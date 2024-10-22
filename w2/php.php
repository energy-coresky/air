<?php

class PHP
{
    const version = 0.525;

    const _ANOF  = 1;
    const _FUNC  = 2;
    const _CLASS = 4;
    const _METH  = 8;
    const A_CLASS     = 0x1000;
    const A_TRAIT     = 0x2000;
    const A_INTERFACE = 0x4000;
    const A_ENUM      = 0x8000;

    static $data = false;

    public $pad; # 0 for minified PHP
    public $head = [[]/*class-like*/, []/*function*/, []/*const*/, ''/*namespace name*/];
    public $tok;
    public $count;
    public $parse_error = false;
    public $in_str = false;
    public $pos = 0;
    public $curly = 0;

    private $stack = [];
    private $x = [];
    private $part = '';
    private $conv = [T_CLASS => 0, T_FUNCTION => 1, T_CONST => 2];

    static function file(string $name, $pad = 4) {
        return new PHP(file_get_contents($name), $pad);
    }

    static function ini_once() {
        PHP::$data = Plan::php();
        foreach (PHP::$data->gt_74 as $i => $str)
            defined($const = "T_$str") or define($const, $i + 0x10000);
        $p =& PHP::$data->tokens_def;
        $p = array_combine(array_map(fn($k) => constant("T_$k"), $p), $p);
        $p =& PHP::$data->tokens_use;
        $p = array_combine(array_map(fn($k) => constant("T_$k"), array_keys($p)), $p);
        array_walk(PHP::$data->modifiers, fn(&$v) => $v = constant("T_$v"));
        $p =& PHP::$data->tokens_name;
        $p[0] += [2 => T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];
        $p[1] = $p[0] + [5 => T_NAMESPACE];
        return PHP::$data;
    }

    function __construct(string $in, $pad = 4) {
        PHP::$data or PHP::ini_once();
        $this->pad = $pad;
        try {
            $this->tok = token_get_all(unl($in), TOKEN_PARSE);
        } catch (Throwable $e) {
            $this->tok = [$this->parse_error = 'Line ' . $e->getLine() . ', ' . $e->getMessage()];
        }
        if (PHP_VERSION !== PHP::$data->version) {
            //~~~~~~~~~ Debug::drop_all_cache();
            throw new Error('PHP version do not match: ' . PHP_VERSION . ' !== ' . PHP::$data->version);
        }
        $this->count = count($this->tok);
    }

    function __get($name) {
        if ('ns' == $name)
            return $this->head[3];
        return PHP::$data->{substr($name, 1)};
    }

    function __toString() {
        return "\nlines: \n";
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

    function bracket($y) {
        if ('"' == $y->str || in_array($y->tok, [T_START_HEREDOC, T_END_HEREDOC]))
            $this->in_str = !$this->in_str;
        if ($y->tok || $this->in_str)
            return 0;
        return '}' == $y->str ? -1 : (int)('{' == $y->str);
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

    function rank() {
        for ($y = $this->tok($prev = $ux = 0, true); $y; $y = $y->new) {
            $skip = false;
            $this->curly += $y->curly = $this->bracket($y);

            if ($this->rank_name($y, $prev)) {
                $use = T_USE == $prev;
                if ($use || T_EXTENDS == $prev || T_IMPLEMENTS == $prev)
                    $skip = ',' === $y->next;
                if ($use)
                    PHP::_CLASS == $this->pos ? ($y->rank = T_CLASS) : $this->prev_use($y, $ux, $skip);
            } elseif (-1 == $y->curly && $this->stack && $this->curly == end($this->stack)[1]) {
                $this->pos &= array_pop($this->stack)[0];
            } elseif (T_NAMESPACE == $prev && (';' == $y->str || 1 == $y->curly)) {
                $this->head = [[], [], [], '']; # return to global namespace
            } elseif (T_FUNCTION == $prev && '&' === $y->str || ',' === $y->str || $this->part) {
                $skip = true;
            } elseif (T_FUNCTION == $prev && '(' == $y->str && !$this->pos) { # anonymous function in the global
                array_push($this->stack, [~($this->pos = PHP::_ANOF), $this->curly]);
            } elseif (T_USE == $y->tok) {
                $ux = 0;
            } elseif (T_USE == $prev) {
                $func = T_FUNCTION == $y->tok;
                if ($func || T_CONST == $y->tok)
                    $skip = $ux = $func ? 1 : 2;
            }

            yield $prev => $y;
            $skip or $prev = $y->tok ?: ord($y->str);
        }
    }

    private function rank_name($y, $prev) {
        if ($ok = $this->get_name($y)) {
            '(' !== $y->next or $y->open = $y->new->i;

            if ($y->rank = $this->_tokens_def[$prev] ?? false) {
                $y->is_def = true;
                if (T_NAMESPACE == $prev) {
                    $this->head = [[], [], [], $y->str];
                } elseif (T_CONST == $prev) {
                    if (PHP::_CLASS & $this->pos) {
                        $y->rank = 'CLASS-CONST';
                    } elseif ($this->ns) {
                        $this->head[2][$y->str] = "$this->ns\\$y->str";
                    }
                } elseif (T_FUNCTION == $prev) {
                    if (PHP::_CLASS & $this->pos) {
                        $y->rank = 'METHOD';
                        $this->pos |= $pos = PHP::_METH;
                    } else {
                        if ($this->ns)
                            $this->head[1][$y->str] = "$this->ns\\$y->str";
                        $this->pos |= $pos = PHP::_FUNC;
                    }
                    array_push($this->stack, [~$pos, $this->curly]);
                } else { # class-like definition
                    $this->pos |= $pos = PHP::_CLASS;
                    array_push($this->stack, [~$pos, $this->curly]);
                }
            } elseif ($y->rank = $this->_tokens_use[$prev] ?? false) {
                if (is_array($y->rank))
                    $y->rank = $y->rank[(int)!$y->open];
            } elseif (T_DOUBLE_COLON === $y->next) {
                $y->rank = in_array(strtolower($y->str), ['self', 'parent', 'static']) ? T_LIST : T_CLASS;
            } elseif ($y->open) {
                $y->rank = T_FUNCTION;
            } elseif (in_array($y->str, $this->_types)) {
                $y->rank = T_LIST;
            } elseif (T_VARIABLE === $y->next) {
                $y->rank = T_CLASS;////////
            } elseif (!$this->in_str && !in_array($prev, [T_USE])) {
                $y->rank = T_CONST;//////
            }
        }
        return $ok;
    }

    function get_name($y, $ok = false) {
        $get_new = function ($y, $i) {
            $y->new = $this->tok($i, true);
            $y->next = $y->new ? ($y->new->tok ?: $y->new->str) : 0;
        };
        $y->open = $y->rank = $y->is_def = false;
        $get_new($y, $y->i);
        $name = fn($tok, int $ns = 1) => in_array($tok, $this->_tokens_name[$ns]);
        if ($ok = $ok || $name($y->tok, (int)(T_NS_SEPARATOR === $y->next))) {
            $y->tok = T_STRING;
            while ($name($y->new->tok)) {
                $y->str .= $y->new->str; # collect T_NAME_xx for 7.4
                $get_new($y, $y->new->i);
            }
        }
        return $ok;
    }

    private function prev_use(&$y, $ux, &$skip) {
        $str = $this->part . $y->str;
        if ('{' === $y->next) {
            $skip = $this->part = $y->str;
        } elseif ($skip || ';' === $y->next || '}' === $y->next) {
            $ary = explode('\\', $str);
            $this->head[$ux][end($ary)] = $str;
        } elseif (T_AS === $y->next) {
            $this->get_name($y = $this->tok($y->new->i + 1, true), true);
            $this->head[$ux][$y->str] = $str;
            $skip = ',' === $y->next;
        }
        if ('}' === $y->next)
            $this->part = '';
    }

    function get_real($y) {
        $ns = '' === $this->ns ? '' : "$this->ns\\";
        if ($y->is_def)
            return $ns . $y->str;
        if ('\\' == $y->str[0])
            return substr($y->str, 1);
        if ('namespace\\' == strtolower(substr($y->str, 0, 10)))
            return $ns . substr($y->str, 10);
        if (3 == ($ux = $this->conv[$y->rank] ?? 3))
            return "??$y->str";
        $a = explode('\\', $y->str, 2);
        if (isset($this->head[$ux][$a[0]]))
            return $this->head[$ux][$a[0]] . (isset($a[1]) ? "\\$a[1]" : '');
        if (T_CLASS == $y->rank)
            return $ns . $y->str;
        return ($ns ? '?' : '') . $y->str;
    }

    function get_close($y) {
        for ($to = $y->open, $n = 1; $n && ++$to < $this->count; )
            '(' === $this->tok[$to] ? $n++ : (')' !== $this->tok[$to] ? 0 : $n--);
        return $to;
    }

    function get_modifiers($y, $i = 4, $add_public = false) { //2do $add_public
        for ($ary = [], $i = $y->i - $i; $y = $this->tok($i--); ) {
            if (in_array($y->tok, $this->_tokens_ign)) # T_ATTRIBUTE not ignore
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

    function tok($i = 0, $new = false) {
        if ($new)
            while (is_array($this->tok[++$i] ?? false) && in_array($this->tok[$i][0], $this->_tokens_ign));
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
}
