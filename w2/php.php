<?php

class PHP
{
    const version = 0.541;

    use Processor;

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
    public $html_cnt = 0;
    public $max_length = 0;

    private $stack = [];
    private $x = [];
    private $part = '';
    private $wind_cfg = '~/w2/__beauty.yaml';

    static function file(string $name, $tab = 4) {
        return new self(file_get_contents($name), $tab);
    }

    static function ary(array $in, $return = false) {
        $php = new self("<?php " . var_export($in, true) . ';', 2);
        $php->max_length = 80;
        if ($return)
            return (string)$php;
        echo $php;
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
        (PHP::$data->ini_once)();
        $p =& PHP::$data->tokens_name;
        $p[0] += [2 => T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];
        $p[1] = $p[0] + [5 => T_NAMESPACE];
        return PHP::$data;
    }

    function __construct(string $in, $tab = 4) {
        PHP::$data or PHP::ini_once();
        $this->tab = $tab;
        try {
            $this->tok = token_get_all(unl($in), TOKEN_PARSE);
        } catch (Throwable $e) {
            PHP::$warning = 'Line ' . $e->getLine() . ', ' . $e->getMessage();
            $this->tok = token_get_all(unl($in));
        }
        $this->count = count($this->tok);
    }

    function __get($name) {
        if ('ns' == $name)
            return $this->head[3];
        return PHP::$data->{substr($name, 1)};
    }

    function __toString() {
        if (PHP::$warning)
            throw new Error(PHP::$warning);
        $this->nice();
        if ($this->tab)
            return $this->wind_nice_php(0); # step 2

        # else minifier
        $not = fn($chr) => !preg_match("/[a-z_\d\$]/i", $chr);
        for ($out = '', $y = $this->tok(); $y; $y = $new) { //2do 1+++$a;
            $new = $this->tok($y->i + 1);
            if (T_COMMENT == $y->tok || T_DOC_COMMENT == $y->tok) # 2do ->save_comment
                continue;
            if (T_OPEN_TAG == $y->tok) {
                $echo = in_array($new->tok, [T_ECHO, T_PRINT]) && ($new = $this->tok($y->i + 2));
                $y->str = $echo ? '<?=' : ($this->html_cnt > 1 ? '<?' : '<?php ');
            }
            if (!$y->i)
                $y->str .= "/* Minified with Coresky framework, https://coresky.net */";
            
            if (T_WHITESPACE == $y->tok) {//2do
                if ($not($pv->str[-1]) || !$new || $not($new->str[0]))
                    continue;
                $y->str = ' ';
            }
            $this->int_bracket($y);
            if (!$this->in_str && ']' == $y->str && ',' == $pv->str)
                $out = substr($out, 0, -1);

            $out .= $y->str;
            $pv = $y;
        }
        return $out;
    }

    private function left_bracket($y) {
        $len = strlen($y->line);
        if ($len + $y->len < $this->max_length) // $y->comma > 2
            return true; # continue line
        [$new, $str] = $this->wind_nice_php($y->new->i, $y->close);
        if (!$new->len || '[' == $y->str && '[' == $new->str)
            return false; # add new line
        if ($len + strlen($str) < $this->max_length) {
            [, $distance] = $this->wind_nice_php($new->close, $y->close);
            if (strlen($distance) > 20)
                return false; # add new line
            $y->new = $new;
            $y->str .= $str;
            return true; # continue line
        }
        return false; # add new line
    }

    private function indents($oc, $y, $prev, &$depth, $put) {
        $stk =& $this->stack;

        if ($oc > 0) { # open
            $curly = '{' == $y->str && !in_array($y->reason, $this->_not_open_curly);
            if (!$curly && $this->left_bracket($y)) {
                if ($for = T_FOR == $prev)
                    $this->in_par = true;
                $stk[] = $for ? 0 : false;
                return $y->str; # continue $line
            }
            $stk[] = !$curly;
            $this->x[$y->close] = $y->i;
            if ($y->new->i == $y->close)
                $y->new = $this->tok($y->new->i);
            if ($class = $curly && in_array($y->reason, [T_CLASS, T_INTERFACE, T_TRAIT]))
                $put("\n") or $put("{");
            $depth++;
            $class ? $put("\n") : $put($curly ? " {\n" : "$y->str\n");
        } elseif ($oc < 0) { # close
            if (0 === array_pop($stk))
                $this->in_par = false;
            if (!$y->len) {
                if (']' == $y->str && ', ' == substr($y->line, -2))
                    $y->line = substr($y->line, 0, -2); # modify source
                return $y->str;
            }
            if (']' == $y->str && $y->line && ' ' != $y->line[-1]) # modify source
                $put(",\n");
            $depth--;
            T_SWITCH != $y->reason or $depth--;
            $put('' === trim($y->line) ? '' : "\n", $y->str);
        } else { # comma
            if (!$stk || !end($stk))
                return ', ';
            $put(",\n");
        }
        return '';
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

    function del_arrow(&$y, &$prev, &$key, $ignore) {
        if (!$ignore && in_array($prev, ['[', ','], true)) {
            if ($y->str === (string)($key++ - 1)) {
                $new = $this->tok($y->i, true);
                if (T_DOUBLE_ARROW == $new->tok) {
                    if (T_WHITESPACE === $this->tok[$new->i + 1][0])
                        $new = $this->tok($new->i + 1);
                    $this->count -= $len = 1 + $new->i - $y->i;
                    array_splice($this->tok, $y->i, $len); # modify code
                    $prev = T_DOUBLE_ARROW;
                    return $y->new = $this->tok($y->i);
                }
            }
            $key = 0; # off
        } elseif (!in_array($y->tok, $this->_optim_key) && ',' != $y->str) {
            $key = 0; # off
        }
        return false;
    }

    function nice() {
        if (!isset(PHP::$data->not_open_curly))
            Plan::set('main', fn() => yml($fn = 'nice_php', "+ @inc(ary_$fn) $this->wind_cfg"));
        $stk =& $this->stack;
        $reason = $prev = $key = $_key = 0;
        for ($y = $this->tok(); $y; $y = $y->new) { # step 1
            $mod = in_array($y->tok, [T_ARRAY, T_LIST]) && $this->mod_array($y);
            $ignore = in_array($y->tok, $this->_tokens_ign);
            if (!$this->in_str && '[' === $y->str) {
                [$_key, $key] = [$key, 1];
            } elseif ($key && $this->del_arrow($y, $prev, $key, $ignore)) {
                continue;
            }
            if ($stk) {
                $this->x[$i = end($stk)[0]][0] += strlen(' ' == $y->str ? ' ' : trim($y->str));
                ',' != $y->str or $this->x[$i][2]++;/// comas?
            }
            if (in_array($y->tok = $this->_not_open_curly[$y->tok] ?? $y->tok, $this->_curly_reason))
                $reason = $y->tok;

            $oc = $this->int_bracket($y, true);
            if ($oc > 0) {
                $stk[] = [$y->i, $mod, $_key];
                $rsn = in_array($prev, $this->_not_open_curly) ? $prev : $reason;
                if (!$rsn && '{' == $y->str)
                    $rsn = $_rsn;
                $this->x[$y->i] = [1, 0, 1, $rsn];
                # len, close, comma, reason
                if (T_SWITCH == $reason)
                    [$_rsn, $reason] = [$reason, 0];
            } elseif ($oc < 0) {
                [$i, $mod, $_key] = array_pop($stk);
                if ('[' === $this->tok[$i])
                    $key = $_key;
                if ($mod)
                    $y->str = ']'; # modify code
                $this->x[$i][1] = $y->i;
                if ($stk)
                    $this->x[end($stk)[0]][0] += $this->x[$i][0] - 1;
            }
            T_INLINE_HTML == $y->tok && $this->html_cnt++;
            $y->new = $this->tok($y->i + 1);
            $ignore or $prev = $y->tok ?: $y->str;
        }
        $this->max_length or $this->max_length = $this->html_cnt > 1 ? 320 : 120;
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
        for ($y = $this->tok($prev = $ux = $dar = 0, true); $y; $y = $y->new) {
            $skip = false;
            $this->curly += $y->curly = $this->int_bracket($y);

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
        static $list;
        $list or $list = Plan::set('main', fn() => yml('modifiers', "+ @inc(modifiers) $this->wind_cfg"));
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
            if ($tok[0])
                return $tok[2] + substr_count($tok[1], "\n");
        }
    }

    function tok($i = 0, $new = false) {
        static $keys = ['len', 'close', 'comma', 'reason'];
        if ($new)
            while (is_array($this->tok[++$i] ?? 0) && in_array($this->tok[$i][0], $this->_tokens_ign));
        if ($i < 0 || $i >= $this->count)
            return false;
        $ary = ['len' => false, 'com' => 0];
        if ($v = $this->x[$i] ?? false)
            $ary = array_combine($keys, is_array($v) ? $v : $this->x[$v]) + $ary;
        $y = (object)$ary;
        $tok =& $this->tok[$y->i = $i];
        $is_array = is_array($tok) or $ary = [0, &$tok];
        $is_array ? ($p =& $tok) : ($p =& $ary);
        $y->tok =& $p[0];
        $y->str =& $p[1];
        $y->line = $p[2] ?? fn() => $this->char_line($i);
        return $y;
    }
}
