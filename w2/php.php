<?php

class PHP
{
    const version = 0.531;

    const _ANONYM  = 1; # anonymous func, class
    const ARRF  = 3; # arrow func (for ->in_par and NOT for ->pos)
    const _FUNC  = 2;
    const _CLASS = 4;
    const _METH  = 8;

    static $data = false;
    static $warning = false;

    public $tab; # 0 for minified PHP
    public $head = [[]/*class-like*/, []/*function*/, []/*const*/, ''/*namespace name*/];
    public $tok;
    public $count;
    public $in_str = false;
    public $in_par = 0;
    public $pos = 0;
    public $curly = 0;

   public $stack = [];
   public $x = [];
    private $part = '';
    private $is_nice = false;

    static function file(string $name, $tab = 4) {
        return new PHP(file_get_contents($name), $tab);
    }

    static function ini_once() {
        PHP::$data = Plan::php();
        if (PHP_VERSION_ID !== PHP::$data->version) { # different console's and web versions
            PHP::$warning = 'PHP version do not match: ' . PHP_VERSION_ID . ' !== ' . PHP::$data->version;
            Plan::cache_d(['main', 'yml_main_php.php']);
            PHP::$data = Plan::php(false);
        }

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

    function __construct(string $in, $tab = 4) {
        PHP::$data or PHP::ini_once();
        $this->tab = $tab;
        try {
            $this->tok = token_get_all($in, TOKEN_PARSE);
        } catch (Throwable $e) {
            PHP::$warning = 'Line ' . $e->getLine() . ', ' . $e->getMessage();
            $this->tok = token_get_all($in);
        }
        $this->count = count($this->tok);
    }

    function __get($name) {
        if ('ns' == $name)
            return $this->head[3];
        return PHP::$data->{substr($name, 1)};
    }

    function __toString() {
        if ($this->tab) {
            $this->nice();
            return $this->beautifier(0);
        }
        # else minifier
        $not = fn($chr) => !preg_match("/[a-z_\d\$]/i", $chr);
        for ($out = '', $y = $this->tok(); $y; $y = $new) {
            $new = $this->tok($y->i + 1);
            if (T_COMMENT == $y->tok || T_DOC_COMMENT == $y->tok) # 2do ->save_comment
                continue;
            if (T_OPEN_TAG == $y->tok)
                $y->str = '<?php ';
            if (T_WHITESPACE == $y->tok) {//2do
                if ($not($prv->str[-1]) || !$new || $not($new->str[0]))
                    continue;
                $y->str = ' ';
            }

            $out .= $y->str;
            $prv = $y;
        }
        return $out;
    }

    function beautifier($at) {
        $depth = $dar = $dec = $prv = 0;
        $out = $line = '';
        $flush = function ($s1, $s2 = '') use (&$out, &$line, &$depth) {
            '' === $s1 or $out .= $line . $s1;
            $line = str_pad('', $depth * $this->tab) . $s2;
        };
        $alfa = fn($chr) => preg_match("/[a-z_\d\$]/i", $chr);

        for ($y = $this->tok($at); $y; $y = $y->new) {
            $y->new = $this->tok($y->i + 1);
            $oc = $this->bracket($y);
            $ws = T_WHITESPACE == $y->tok;
            if ($at && strlen($out . $line) > 120)
                return [$y, $out . $line];
            if ($ws || $this->in_str) {
                $ws or $line .= $y->str;
                continue;
            }

            if (T_OPEN_TAG == $y->tok) {
                $flush(trim($y->str) . "\n\n");
            } elseif (in_array($y->tok, $this->_space_after)) {
                $line .= $y->str . ' ';
                if (T_IF == $y->tok) {
                    $dar = $this->get_close($y, $y->new);
                    if ('{' === $this->tok($dar, true)->str)
                        $dar = 0;
                }
            } elseif (in_array($y->tok ?: $y->str, $this->_space_op, true)) {
                $line .= " $y->str ";
            } elseif ($dar == $y->i) {
                $depth++;
                $flush(")\n");
                $dec = true;
            } elseif ($oc || ',' == $y->str) {
                if ($at) {
                    if ($oc > 0)
                        return [$y, $out . $line];
                    $line .= ',' == $y->str ? ', ' : $y->str;
                } else {
                    $y->len = strlen($line);
                    $line .= $this->open_close($oc, $y, $prv->str, $depth, $flush);
                }
            } elseif (':' == $y->str && '?' == $prv->str) {
                $line .= ": ";
            } elseif ($prv && '}' == $prv->str && $alfa($y->str[0])) {
                $flush("\n\n", $y->str);
            } elseif (';' == $y->str) {
                if ($dec) {
                    $dec = false;
                    $depth--;
                }
                $flush(";\n");
            } else {
                if ($prv && $alfa($prv->str[-1]) && $alfa($y->str[0]))
                    $line .= ' ';
                $line .= $y->str;
            }
            $prv = $y;
        }
        return $out;
    }

    function open_close($oc, $y, $prev, &$depth, $flush) {
        static $stk = [];
        $test = function ($y, $len, $close) {
            if ($y->len + $len < 120)
                return true;
            [$new, $str] = $this->beautifier($y->new->i);
            if ($y->len + strlen($str) < 120) {
                [$_len, $_close] = $this->x[$new->i];
                if ($y->len + $_len > 120 && $close != $this->tok($_close, true)->i)
                    return false; # add new line
                $y->new = $new;
                $y->str .= $str;
                return true;
            }
            return false; # add new line
        };

        if ($oc > 0) { # open
            [$len, $close] = $this->x[$y->i];
            if ('{' != $y->str && $test($y, $len, $close)) {
                $stk[] = true;
                return $y->str;
            }
            $stk[] = '{' == $y->str;
            $this->x[$y->i] = $close;
            $depth++;
            $flush("$y->str\n");
        } elseif ($oc < 0) { # close
            array_pop($stk);
            if (!array_search($y->i, $this->x, true))
                return $y->str;
            $depth--;
            in_array($prev, [';', ',']) ? $flush('', $y->str) : $flush("\n", $y->str);
        } else { # comma
            if (!$stk || end($stk))
                return ", ";
            $flush(",\n");
        }
        return '';
    }

    function char_line($i) {
        for ($d = 1; $tok =& $this->tok[$i - $d++]; ) {
            if ($tok[0])
                return $tok[2] + substr_count($tok[1], "\n");
        }
    }

    function bracket($y) {
        if ('"' == $y->str || in_array($y->tok, [T_START_HEREDOC, T_END_HEREDOC])) {
            $this->in_str = !$this->in_str;
            return 0;
        }
        if ($y->tok || $this->in_str)
            return 0;
        if ($this->is_nice)
            return array_search($y->str, $this->_oc) ? -1 : (int)isset($this->_oc[$y->str]);
        return '}' == $y->str ? -1 : (int)('{' == $y->str);
    }

    function nice() {
        $this->is_nice = true;
        if (PHP::$warning)
            throw new Error(PHP::$warning);
        $stk =& $this->stack;
        for ($y = $this->tok(); $y; $y = $new) {
            $new = $this->tok($y->i + 1);

            if ($new && T_COMMENT == $y->tok && "\n" == $y->str[-1] && T_WHITESPACE == $new->tok) {
                $y->str = substr($y->str, 0, -1);
                $new->str = "\n" . $new->str;
            }
            
            if ($stk)
                $this->x[end($stk)][0] += strlen(' ' == $y->str ? ' ' : trim($y->str));

            $oc = $this->bracket($y);
            if (1 == $oc) {
                $stk[] = $y->i;
                $this->x[$y->i] = [1];
            } elseif (-1 == $oc) {
                $j = array_pop($stk);
                $this->x[$j][1] = $y->i;
                if ($stk)
                    $this->x[end($stk)][0] += $this->x[$j][0] - 1;
            }
        }
    }

    function rank() {
        $uei = fn($tok, &$use) => ($use = T_USE == $tok) || T_EXTENDS == $tok || T_IMPLEMENTS == $tok;
        for ($y = $this->tok($prev = $ux = $dar = 0, true); $y; $y = $y->new) {
            $skip = false;
            $this->curly += $y->curly = $this->bracket($y);

            if ($this->rank_name($y, $prev)) {
                if ($uei($prev, $use))
                    $skip = ',' === $y->next;
                $use && $this->prev_use($y, $ux, $skip);
            } elseif (-1 == $y->curly && $this->stack && $this->curly == end($this->stack)[1]) {
                $this->pos &= ~array_pop($this->stack)[0];
            } elseif (T_NAMESPACE == $prev && (';' == $y->str || 1 == $y->curly)) {
                $this->head = [[], [], [], '']; # return to global namespace
            } elseif (T_FUNCTION == $prev && '&' === $y->str
                || ',' === $y->str && $uei($prev, $use)
                || '' != $this->part
                || $this->in_par && '?' == $y->str) {
                    $skip = true;
            } elseif (T_FUNCTION == $prev && '(' == $y->str || T_NEW == $prev && T_CLASS == $y->tok) {
                $this->in_par = PHP::_ANONYM;
                $this->pos or array_push($this->stack, [$this->pos = PHP::_ANONYM, $this->curly]);
            } elseif (1 == $y->curly || $dar == $y->i) {
                $this->in_par = 0;
            } elseif (T_FN == $y->tok) { # arrow function
                $dar = $this->get_close($y, $y->new);
                for ($this->in_par = PHP::ARRF; T_DOUBLE_ARROW !== $this->tok[++$dar][0]; );
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
        if (!$this->get_name($y))
            return false;
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
                    $this->pos |= $this->in_par = PHP::_METH;
                } else {
                    if ($this->ns)
                        $this->head[1][$y->str] = "$this->ns\\$y->str";
                    $this->pos |= $this->in_par = PHP::_FUNC;
                }
                array_push($this->stack, [$this->in_par, $this->curly]);
            } else { # class-like definition
                $this->pos |= PHP::_CLASS;
                array_push($this->stack, [PHP::_CLASS, $this->curly]);
            }
        } elseif ($y->rank = $this->_tokens_use[$prev] ?? false) {
            if (is_array($y->rank))
                $y->rank = $y->rank[(int)!$y->open];
        } elseif (T_DOUBLE_COLON === $y->next) {
            $y->rank = T_CLASS;
        } elseif ($y->open) {
            $y->rank = T_FUNCTION;
        } elseif (T_GOTO == $prev) {
            $y->rank = T_GOTO;
        } elseif (':' === $y->next && in_array($prev, [/* (, */ 0x28, 0x2C, /* {}; */ 0x7B, 0x7D, 0x3B])) {
            $y->rank = T_GOTO; # named arguments from >= PHP 8.0.0 OR labels for goto ;
        } elseif (in_array($y->str, $this->_types)) {
            $y->rank = T_LIST;
        } elseif ($this->in_par && 0x3A /* : */ == $prev || T_VARIABLE == $y->next) {
            $y->rank = T_CLASS; // 2do DNF from 8.2
        } elseif (!$this->in_str && T_USE != $prev) {
            $y->rank = T_CONST;
        }
        if (in_array(strtolower($y->str), ['self', 'parent', 'static']))
            $y->rank = T_LIST;
        return true;
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
            '(' !== $y->next or $y->open = $y->new->i;
        }
        return $ok;
    }

    private function prev_use(&$y, $ux, &$skip) {
        if ($this->pos) {
            $y->rank = T_CLASS; # (trait)
            if ('{' === $y->next) { # skip redeclare trait's methods
                for ($i = $y->new->i; '}' !== $this->tok[++$i]; );
                $y->new = $this->tok($i + 1, true);
                $y->next = $y->new->tok ?: $y->new->str;
            }
            return $skip = ',' === $y->next;
        }
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

    function get_real($y, &$ns_name = '') {
        static $conv = [T_CLASS => 0, T_FUNCTION => 1, T_CONST => 2];
        $ns_name = '';
        if ('\\' === $y->str[0])
            return substr($y->str, 1);
        $ns = '' === $this->head[3] ? '' : $this->head[3] . '\\';
        if ('namespace\\' == strtolower(substr($y->str, 0, 10)))
            return $ns . substr($y->str, 10);
        if (3 == ($ux = $conv[$y->rank] ?? 3))
            return "?$y->str";
        $two = 2 == count($a = explode('\\', $y->str, 2));
        if (isset($this->head[$ux][$a[0]]))
            return $this->head[$ux][$a[0]] . ($two ? "\\$a[1]" : '');
        if (T_CLASS == $y->rank || $two)
            return $ns . $y->str;
        if ($ns)
            $ns_name = $ns . $y->str;
        return $y->str;
    }

    function get_close($y, $fn = false) {
        if ($fn)
            for ($y->open = $fn->i - 1; '(' !== $this->tok[++$y->open]; );
        for ($to = $y->open, $n = 1; $n && ++$to < $this->count; )
            '(' === $this->tok[$to] ? $n++ : (')' !== $this->tok[$to] ? 0 : $n--);
        return $to;
    }

    function get_modifiers($y, $i = 4, $add_public = false) {
        for ($ary = [], $i = $y->i - $i; $y = $this->tok($i--); ) {
            if (in_array($y->tok, $this->_tokens_ign)) # T_ATTRIBUTE not ignore
                continue;
            if (!in_array($y->tok, $this->_modifiers))
                break;
            $ary[] = T_VAR == $y->tok ? 'public' : strtolower($y->str);
        }
        return $ary;
    }

    function str($i, $to) {
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
        ];
    }
}
