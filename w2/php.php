<?php

class PHP
{
    const version = '0.808';

    use SAW;

    const _ANONYM  = 1; # anonymous func, class
    const ARRF  = 3; # arrow func (for ->in_par and NOT for ->pos)
    const _FUNC  = 2;
    const _CLASS = 4;
    const _METH  = 8;

    static $data = false;
    static $warning = false;
    static $php_fn = false;

    public $tab; # 0 for minified PHP
    public $head = [ []/*class-like*/, []/*function*/, []/*const*/, ''/*namespace name*/];
    public $tok;
    public $count;
    public $in_str = false;
    public $in_par = 0;
    public $pos = 0;
    public $curly = 0;
    public $in_html = 0;
    public $max_length = 0;

    private $stack = [];
    private $x = [];
    private $part = '';
    private $saw_fn = '~/w2/_nice_php.yaml';

    static function file(string $name, $tab = 4) {
        return new self(file_get_contents(self::$php_fn = $name), $tab);
    }

    static function ary(array $in, $return = false) {
        $php = new self("<?php " . var_export($in, true) . ';', 2);
        $php->max_length = 80;
        if ($return)
            return (string)$php;
        echo $php;
    }

    static function ini_once() {
        self::$data = Plan::php();
        if (PHP_VERSION_ID !== self::$data->version) { # different console's and web versions
            self::$warning = 'PHP version do not match: ' . PHP_VERSION_ID . ' !== ' . self::$data->version;
            Plan::cache_d(['main', 'yml_main_php.php']);
            Plan::cache_dq(['main', 'saw_main_php.php']);
            self::$data = Plan::php(false);
        }
        foreach (self::$data->gt_74 as $i => $str)
            defined($const = "T_$str") or define($const, $i + 0x10000);
        (self::$data->ini_once)();
        $p =& self::$data->tokens_name;
        $p[0] += [2 => T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];
        $p[1] = $p[0] + [5 => T_NAMESPACE];
        return self::$data;
    }

    function __construct(string $in, $tab = 4) {
        self::$data or self::ini_once();
        $this->tab = $tab;
        try {
            $this->tok = token_get_all(unl($in), TOKEN_PARSE);
        } catch (Throwable $e) {
            $fn = self::$php_fn ? 'File ' . self::$php_fn . ', ' : '';
            self::$warning = $fn . 'Line ' . $e->getLine() . ', ' . $e->getMessage();
            $this->tok = token_get_all(unl($in));
        }
        $this->count = count($this->tok);
    }

    function __get($name) {
        if ('ns' == $name)
            return $this->head[3];
        return self::$data->{substr($name, 1)};
    }

    function __toString() {
        if (self::$warning)
            throw new Error(self::$warning);
        $this->nice(); # step 1
        $out = $this->_saw($this->tab ? 'nice' : 'minify'); # step 2
        $this->unbind(['nice', 'minify', 'expr_nl']);
        return $out;
    }

    private function left_bracket($y) {
        $len = strlen($y->line);
        if ($this->in_html || $len + $y->len < $this->max_length || $y->close < 0)
            return true; # continue line
        [$new, $str] = $this->_saw('nice', $y->new->i, $y->close);
        if (!$new->len || '[' == $y->str && '[' == $new->str)
            return false; # add new line
        if ($len + strlen($str) < $this->max_length) # add/continue new line
            return strlen($this->_saw('minify', $new->close, $y->close)) < 21;
        return false; # add new line
    }

    private function indents($oc, $y, $prev, $put, &$exp) {
        $stk =& $this->stack;
        if ($oc > 0) { # open
            $curly = '{' == $y->str && !in_array($y->reason, $this->_not_nl_curly);
            if (!$curly && $this->left_bracket($y)) {
                if ($for = T_FOR == $prev)
                    $this->in_par = true;
                $stk[] = [$for ? 0 : false, $exp];
                return $y->line .= $y->str; # continue line
            }
            if ($curly && T_MATCH == $y->reason)
                $curly = false;
            $stk[] = [$y->cnt[0] ?? !$curly, $exp, $y->str, $y->len];
            $this->x[$y->close] = $y->i;
            if ($y->new->i == $y->close)
                $y->new = $this->tok($y->new->i);
            if ($class = $curly && in_array($y->reason, [T_CLASS, T_INTERFACE, T_TRAIT]))
                $put("\n") or $put('{');
            $class ? $put(1, "\n") : $put(1, $curly ? " {\n" : "$y->str\n");
        } else { # close
            [$comma, $exp] = array_pop($stk);
            if (0 === $comma)
                $this->in_par = false;
            if (!$y->len) {
                if (']' == $y->str && ', ' == substr($y->line, -2))
                    $y->line = substr($y->line, 0, -2); # modify source
                return $y->line .= $y->str;
            }
            if (']' == $y->str && $y->line && ' ' != $y->line[-1]) # modify source
                $put(",\n");
            $put(T_SWITCH == $y->reason ? -2 : -1, '' === trim($y->line) ? '' : "\n", $y->str);
        }
    }

    function mod_attribute($y) {
        $i = $y->i;
        do {
            $y->str .= $str = is_array($this->tok[++$i]) ? $this->tok[$i][1] : $this->tok[$i];
        } while (']' !== $str);
        $this->count -= $len = $i - $y->i;
        array_splice($this->tok, 1 + $y->i, $len); # modify tokens
    }

    function mod_array(&$y) {
        $new = $this->tok($y->i, true);
        if ('(' == $new->str) {
            $this->count -= $len = $new->i - $y->i;
            array_splice($this->tok, $y->i, 1 + $len, '['); # modify code
            return $y = $this->tok($y->i);
        }
        return false;
    }

    function del_arrow(&$y, &$pv, &$key) {
        if (!$y->ignore && in_array($pv->str, ['[', ','], true)) {
            if ($y->str === (string)($key++ - 1)) {
                $new = $this->tok($y->i, true);
                if (T_DOUBLE_ARROW == $new->tok) {
                    if (T_WHITESPACE === $this->tok[$new->i + 1][0])
                        $new = $this->tok($new->i + 1);
                    $this->count -= $len = 1 + $new->i - $y->i;
                    array_splice($this->tok, $y->i, $len); # modify code
                    return $y->new = $pv = $this->tok($y->i);
                }
            }
            $key = 0; # off
        } elseif (!in_array($y->tok, $this->_optim_key) && ',' != $y->str) {
            $key = 0; # off
        }
        return false;
    }

    function nice() {
        isset(self::$data->not_nl_curly) or $this->load($this->saw_fn, 'php');
        $this->max_length or $this->max_length = 130;
        $this->stack[] = [0, 0, 0, 0, $si = 0, []];
        $this->x[$i = 0] = [0, [1], 0, $reason = $key = $_key = 0];
        for ($y = $pv = $this->tok(); $y; $y = $y->new):
            $mod = in_array($y->tok, [T_ARRAY, T_LIST]) && $this->mod_array($y);
            $y->ignore = in_array($y->tok, $this->_tokens_ign);
            if ('[' == ($chr = $y->tok || $this->in_str ? '' : $y->str)) {
                [$_key, $key] = [$key, 1];
            } elseif ($key && $this->del_arrow($y, $pv, $key)) {
                continue;
            }
            $stk =& $this->stack[$si];
            $x =& $this->x[$i];
            $x[0] += $len = ' ' == $y->str ? 1 : strlen(trim($y->str));
            $stk[4] += $len;
            if (in_array($y->tok = $this->_not_nl_curly[$y->tok] ?? $y->tok, $this->_curly_reason))
                $reason = $y->tok;
            $oc = $this->int_bracket($y, true);
            if ($oc > 0) {
                //$stk[5][$y->i] = $stk[4];
                if ('(' == $chr)
                    $stk[3] or $stk[3] = $y->i;
                $this->stack[++$si] = [$i = $y->i, $mod, $_key, 0, 0, []]; # i(open), mod, key, i(expr), len, cnt
                $this->x[$i] = [1, [1], $reason, $reason = 0]; # len, cnt, reason, close
            } elseif ($oc < 0) {
                $this->push_x($stk);
                [$_i, $mod, $_key] = array_pop($this->stack);
                if ('[' === $this->tok[$_i])
                    $key = $_key;
                if ($mod)
                    $y->str = ']'; # modify code
                $z =& $this->x[$_i];
                $x =& $this->x[$i = $this->stack[--$si][0]];
                $z[3] = $y->i; # set close
                $x[0] += $len = $z[0] - 1;
                $this->stack[$si][4] += $len;
                if ($z[0] > 32)
                    unset($x[1][0]);
                if (($z[1][0] ?? 3) < 2)
                    unset($z[1][0]);
                if (in_array($z[2], $this->_expr_reset))
                    $this->stack[$si][3] = $this->stack[$si][4] = 0;
                if (')' == $chr)
                    $reason = $z[2];
            } elseif (in_array($chr, $this->_expr_chr)) {
                $stk[5][$y->i] = $stk[4];
            } elseif (!$this->in_str && in_array($y->tok, $this->_expr_tok)) {
                $stk[5][$y->i] = $stk[4];
            } elseif (T_DOUBLE_ARROW == $y->tok) {
                unset($x[1][0]); # reset cnts commas
                $x[0] += 3; # add len
            } elseif (T_CLOSE_TAG == $y->tok || in_array($chr, [';', ','])) {
                if (',' == $chr && ($x[1][0] ?? false))
                    $x[1][0]++; # calc commas
                $this->push_x($stk);
                $reason = 0;
            } elseif (T_WHITESPACE !== $y->tok && T_OPEN_TAG !== $y->tok) {
                $stk[3] or $stk[3] = $y->i;
                $y->tok or T_FUNCTION == $reason or $reason = 0;
                if (T_COMMENT == $y->tok && '/*' != substr($y->str, 0, 2))
                    $x[0] += $this->max_length; # set big len
                if (T_ATTRIBUTE == $y->tok)
                    $this->mod_attribute($y);
            }
            $y->new = $this->tok($y->i + 1);
            $y->ignore or $pv = $y;
        endfor;
    }

    function push_x(&$stk) { # -> ?-> T_NULLSAFE_OBJECT_OPERATOR => T_OBJECT_OPERATOR replaced!
        $s3 = $stk[3];
        if ($s3 && $stk[5] && $stk[4] > 55) {
            if ($close = isset($this->x[$s3][3]) ? -$this->x[$s3][3] : 0)
                $stk[5] = [-1 => $this->x[$s3][0]] + $stk[5];
            $this->x[$s3] = [$stk[4], $stk[5], 0, $close];
        }
        $stk[3] = $stk[4] = 0;
        $stk[5] = [];
    }

    function int_bracket($y, $is_nice = false) {
        if ('"' == $y->str && !$y->tok || in_array($y->tok, [T_START_HEREDOC, T_END_HEREDOC])) {
            $this->in_str = !$this->in_str;
            return 0;
        }
        if ($y->tok || $this->in_str)
            return 0;
        if ($is_nice)
            return array_search($y->str, $this->_oc) ? -1 : (int)isset($this->_oc[$y->str]);
        return '}' == $y->str ? -1 : (int)('{' == $y->str);
    }

    function rank() {
        $uei = fn($tok, &$use) => ($use = T_USE == $tok) || T_EXTENDS == $tok || T_IMPLEMENTS == $tok;
        $stk =& $this->stack;
        for ($y = $this->tok($prev = $ux = $dar = 0, true); $y; $y = $y->new) {
            $skip = false;
            $this->curly += $y->curly = $this->int_bracket($y);

            if ($this->rank_name($y, $prev)) {
                if ($uei($prev, $use))
                    $skip = ',' === $y->next;
                $use && $this->prev_use($y, $ux, $skip);
            } elseif (-1 == $y->curly && $stk && $this->curly == end($stk)[1]) {
                $this->pos &= ~array_pop($stk)[0];
            } elseif (T_NAMESPACE == $prev && (';' == $y->str || 1 == $y->curly)) {
                $this->head = [[], [], [], '']; # return to global namespace
            } elseif (
                T_FUNCTION == $prev && '&' === $y->str
                    || ',' === $y->str && $uei($prev, $use)
                    || '' != $this->part
                    || $this->in_par && '?' == $y->str
            ) {
                $skip = true;
            } elseif (T_FUNCTION == $prev && '(' == $y->str || T_NEW == $prev && T_CLASS == $y->tok) {
                $this->in_par = self::_ANONYM;
                $this->pos or array_push($stk, [$this->pos = self::_ANONYM, $this->curly]);
            } elseif (1 == $y->curly || $dar == $y->i) {
                $this->in_par = 0;
            } elseif (T_FN == $y->tok) { # arrow function
                $dar = $this->get_close($y, $y->new);
                for ($this->in_par = self::ARRF; T_DOUBLE_ARROW !== $this->tok[++$dar][0]; );
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
                if (self::_CLASS & $this->pos) {
                    $y->rank = 'CLASS-CONST';
                } elseif ($this->ns) {
                    $this->head[2][$y->str] = "$this->ns\\$y->str";
                }
            } elseif (T_FUNCTION == $prev) {
                if (self::_CLASS & $this->pos) {
                    $y->rank = 'METHOD';
                    $this->pos |= $this->in_par = self::_METH;
                } else {
                    if ($this->ns)
                        $this->head[1][$y->str] = "$this->ns\\$y->str";
                    $this->pos |= $this->in_par = self::_FUNC;
                }
                array_push($this->stack, [$this->in_par, $this->curly]);
            } else { # class-like definition
                $this->pos |= self::_CLASS;
                array_push($this->stack, [self::_CLASS, $this->curly]);
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
        static $list;
        $list or $list = Plan::set('main', fn() => yml('modifiers', "+ @inc(modifiers) $this->saw_fn"));
        for ($ary = [], $i = $y->i - $i; $y = $this->tok($i--); ) {
            if (in_array($y->tok, $this->_tokens_ign)) # T_ATTRIBUTE not ignore
                continue;
            if (!in_array($y->tok, $list))
                break;
            $ary[] = T_VAR == $y->tok ? 'public' : strtolower($y->str);
        }
        return $ary;
    }

    function str($i, $to) {
        for ($s = ''; $i <= $to; $s .= is_array($this->tok[$i]) ? $this->tok[$i++][1] : $this->tok[$i++]);
        return $s;
    }

    function char_line($i) {
        for ($d = 1; $tok =& $this->tok[$i - $d++]; ) {
            if (is_array($tok))
                return $tok[2] + substr_count($tok[1], "\n");
        }
    }

    function tok($i = 0, $new = false) {
        if ($new)
            while (is_array($this->tok[++$i] ?? 0) && in_array($this->tok[$i][0], $this->_tokens_ign));
        if ($i < 0 || $i >= $this->count)
            return false;
        $ary = ['len' => 0, 'com' => 0];
        if ($v = $this->x[$i] ?? false) {
            is_array($v) or $v = $this->x[$v];
            $ary = array_combine(['len', 'cnt', 'reason', 'close'], $v) + $ary;
        }
        $y = (object)$ary;
        $tok =& $this->tok[$y->i = $i];
        $is_array = is_array($tok) or $ary = [0, &$tok];
        $is_array ? ($p =& $tok) : ($p =& $ary);
        $y->tok =& $p[0];
        $y->str =& $p[1];
        $y->line = $p[2] ?? 0;
        return $y;
    }
}
