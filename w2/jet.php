<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class Jet
{
    private $parsed;
    private $replace = [];
    private $files = [];
    private $empty = [];
    private $loop = [];
    private $div = '<div style="display:none" id="err-top"></div>';
    private $cur_tpf;
    private $cur_ml;

    private static $vars = [];
    private static $block = [];
    private static $inc = [];

    static $directive = false;
    static $custom = [];

    static function directive($name, $func = null) {
        Jet::$custom += is_array($name) ? $name : [$name => $func];
    }

    static function q($pattern, $arg) {
        $arg = in_array(@$arg[0], [false, "'", '"']) ? $arg : '"' . strtr($arg, ["\\" => "\\\\", '"' => '\\"']) . '"';
        return sprintf("<?php $pattern ?>", $arg);
    }

    function __construct($name, $layout = '', $fn = false, $vars = null) {
        global $sky;

        if (null !== $vars)
            Jet::$vars = $vars;
        $marker = '';
        if ($layout) {
            $this->body = $name;
            $name = "y_$layout";
        } elseif (is_string($name)) {
            if (strpos($name, '.'))
                list($name, $marker) = explode('.', $name, 2);
            $dir = $sky->style ? DIR_V . "/$sky->style" : DIR_V;
            if ($sky->is_mobile && '_' == $name[0] && is_file("$dir/b$name.php"))
                $name = "b$name";
        }
        if (!Jet::$directive) {
            MVC::handle('jet_c');
            if (is_file($fn_jet = 'main/app/jet.php'))
                require $fn_jet;
            Jet::$directive = true;
        }

        $this->parsed = $this->parse($name, $marker);

        if ($fn) {
            $code = $this->proc_blocks(); # must calc $this->files first!
            $out = "<?php\n#" . ($list = implode(' ', array_keys($this->files))) . "\n$code";
            if (DEV)
                $out .= "trace('TPL: $list'); MVC::in_tpl(true);\n";
            $out .= "extract(\$_vars, EXTR_REFS) ?>" . strtr($this->parsed, $this->replace);
            if (DEV && $vars && !$layout)
                $out .= '<?php if (2 == Ext::cfg("var")): Ext::ed_var(get_defined_vars()); endif ?>';
            if (DEV)
                $out .= "<?php MVC::in_tpl();";
            file_put_contents($fn, $this->optimize($out));
        }
    }

    static function warm_all($is_prod) {
        //2do
    }

    private function proc_blocks() {
        $top = $this->replace = [];
        $used = ['k', 'e', 'y'];
        foreach (Jet::$block as $name => $ary) {
            list ($lab, $pf, $code, $files) = $ary;
            if (!$lab)
                continue; # @use without @block
            $this->files += $files;
            if ($pf && ' ' == $pf[1]) {
                $php = ($is_php = strpos($code, '?')) ? '$_a = SKY::$vars; ' : '';
                if (DEV)
                    $php .= 'MVC::in_tpl(false); ';
                if ($is_php)
                    $php .= '$_b = (array)';
                $php .= "MVC::handle('$pf[0]_$name', \$_vars)";
                if ($is_php)
                    $php .= '; MVC::vars($_a, $_b); extract($_a, EXTR_REFS)';
                if (DEV)
                    $php .= '; MVC::in_tpl()';
                $this->replace[$lab] = $is_php
                    ? "<?php call_user_func(function() use(&\$_vars) { $php ?>$code<?php }) ?>"
                    : "<?php $php ?>$code";
                continue;
            }
            $this->replace[$lab] = $code;
            if ($pf) {
                if (in_array($pf[0], $used))
                    throw new Error("Jet blocks: char `$pf[0]` occupied");
                $used[] = $pf[0];
                $pf[1] = '_';
                $top[] = "\$_$pf = (array)MVC::handle('$pf$name', \$_vars); MVC::vars(\$_vars, \$_$pf, '$pf');";
            }
        }
        Jet::$block = Jet::$inc = [];
        return $top ? implode("\n", $top) . "\n" : '';
    }

    private function parse($name, $marker = '') {
        $this->cur_ml = false;
        if (is_array($name)) {
            list ($in, $this->cur_tpf, $this->cur_ml) = $name;
        } else {
            $this->files[$this->cur_tpf = $name] = 1;
            $in = file_get_contents(MVC::fn_tpl($name));
        }
        if ('' !== $marker) {
            $re = "/([\r\n]+|\A)\s*\#([\.\w+]*?\.{$marker}[\.\w+]*?)( .*?)?([\r\n]+|\z)/s";
            preg_match($re, $in, $match);
            if (3 != count($ary = preg_split($re, $in, 3)))
                throw new Error("Jet: cannot find `$name.$marker`");
            $in = $ary[1];
            $this->cur_ml = substr($match[2], 1);
        }
        # @verb
        $in = preg_replace_callback('/@verb(.*?)~verb/s', function ($m) {
            $this->replace[$lab = '%__verb_' . count($this->replace) . '__%'] = $m[1];
            return $lab;
        }, $in);
        # delete nested part markers
        $in = preg_replace('/([\r\n]+|\A)\s*#\.[\.\w+]+.*?([\r\n]+|\z)/s', '$2', $in);
        # preprocessor
        for ($i = 0; $i < 20 && $this->preprocessor($in); $i++);
        # @block
        $in = preg_replace_callback('/@block\(([a-z][ \*]|)(\w+)\)(.*?)~block/s', function ($m) {
            $this->_block($m[1], $m[2], $m[3], $lab = '%__block_' . $m[2] . '__%');
            return $lab;
        }, $in);

        $out = '';
        foreach (token_get_all($in) as $token) {
            if (is_array($token)) {
                list($id, $str) = $token;
                $token = $str;
                if ($id == T_INLINE_HTML) {
                    $this->parse_statements($token);
                    $this->parse_echos($token);
                }
            }
            $out .= $token;
        }

        return strtr($out, $this->replace);
    }

    private function preprocessor(&$in) {
        $_i = $_u = 0;
        $re = '/\B\#(end|if|elseif|else)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x';
        $in = preg_replace_callback($re, function ($_match) use (&$_i, &$_u) {
            if ($_u < 0)
                return $_match[0];
            extract(Jet::$vars, EXTR_REFS);
            $_label = '%__ife_' . ++$_i . '__%';
            switch ($_match[1]) {
                case 'end':
                    $_u = $_u ? -$_u : -99;
                    return $_label;
                case 'if':
                    if ($_i > 1)
                        throw new Error("Jet preprocessor: cannot use nested #if..");
                case 'elseif':
                    if (!isset($_match[3]))
                        return $_match[0];
                    $_u or !eval("return $_match[3];") or $_u = $_i;
                    return $_label;
                case 'else':
                    $_u or $_u = $_i;
                    return $_label;
            }
        }, $in);
        if ($_i) {
            for ($ok = false, $prv = $i = 1; $i <= $_i; $i++, $prv = $pos) {
                $pos = strpos($in, "%__ife_{$i}__%");
                if ($i > 1) { # from second step
                    $size = $ok ? ($i > 10 ? 12 : 11) : $pos - $prv;
                    $pos -= $size;
                    $in = substr($in, 0, $prv) . substr($in, $prv + $size);
                    $ok = false;
                }
                if ($i == -$_u)
                    $ok = true;
                if ($i == $_i) # last cycle
                    $in = str_replace("%__ife_{$i}__%", '', $in);
            }
        }
        return $_i;
    }

    /* delete `<?php  ?>` and merge `?><?php` in parsed templates */
    private function optimize($in) {
        $out = $tmp = '';
        $flag = false;
        foreach (token_get_all($in) as $token) {
            if (is_array($token)) {
                list($id, $str) = $token;
                if (T_OPEN_TAG == $id) {
                    if ($flag) {
                        $out = substr($out, 0, -strlen($flag)) . ';';
                        $tmp = '';
                    } else {
                        $out .= $tmp;
                        $tmp = $str;
                    }
                } elseif (T_CLOSE_TAG == $id) {
                    '' === $tmp and $flag = $str;
                    '' === $tmp ? ($out .= $str) : ($tmp = '');
                    continue;
                } elseif (T_WHITESPACE == $id) {
                    '' === $tmp ? ($out .= $str) : ($tmp .= $str);
                } else {
                    $out .= $tmp . $str;
                    $tmp = '';
                }
            } else {
                $out .= $tmp . $token;
                $tmp = '';
            }
            $flag = false;
        }
        return $out;
    }

    private function parse_echos(&$str) {
        $str = preg_replace_callback('/[~@]?{[{!\-](.*?)[\-!}]}/s', function ($m) {
            if ('@' == $m[0][0])
                return substr($m[0], 1); # verbatim
            $tilda = '~' == $m[0][0];
            $esc = '%s';
            switch ($m[0][1 + (int)$tilda]) {
            case '-':
                return $tilda ? '' : '<?php /*' . $m[1] . '*/ ?>'; # Jet comment
            case '{':
                $esc = 'html(%s)'; # echo escaped
            case '!':
                $or = $and = false;
                $left = $right = '';
                foreach (token_get_all("<?php $m[1]") as $t) { # a ?: b 
                    if (is_array($t)) {
                        if (T_OPEN_TAG == $t[0])
                            continue;
                        if (in_array($t[0], [T_LOGICAL_OR, T_LOGICAL_AND])) {
                            T_LOGICAL_OR == $t[0] ? ($or = true) : ($and = true);
                            continue;
                        }
                        $t = $t[1];
                    }
                    $or || $and ? ($right .= $t) : ($left .= $t);
                }
                if (!$or && !$and) {
                    $val = trim($m[1]);
                    $op = $tilda ? "isset($val) ? $val : ''" : $val;
                    return sprintf("<?php echo $esc ?>", $op);
                }
                $left = trim($left);
                $right = trim($right);
                if ($and) {
                    $op = $tilda ? "isset($left) && $left" : $left;
                    return sprintf("<?php echo %s ? $esc : '' ?>", $op, $right);
                }
                # else `or`
                $op = $tilda
                    ? "isset($left) && '' !== trim($left) ? $left : $right"
                    : "isset($left) ? $left : $right";
                return sprintf("<?php echo $esc ?>", $op);
            }
        }, $str);
    }

    private function parse_statements(&$str) {
        $str = preg_replace_callback('/[~@]([a-z]+)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', function ($m) {
            $end = '~' == $m[0][0];
            $sp = $m[2] ? ' ' : '';
            $arg = isset($m[3]) ? substr($m[3], 1, -1) : '';
            switch ($tag = $m[1]) {
                case 'php': return $end ? "?>$sp" : "<?php$sp";
                case 'unless': $arg = "!($arg)";
                case 'if': return $end ? "<?php endif ?>$sp" : "<?php if ($arg): ?>";
                case 'cache': return $end ? '<?php Rare::cache(); endif ?>' : Jet::q('if (Rare::cache(%s)):', $arg);
                case 'loop': return $this->_loop($end, $arg); # for, foreach, while, do { .. } while(..)
            }
            if ($end)
                return $m[0];

            if (isset(Jet::$custom[$tag])) # user defined
                return call_user_func(Jet::$custom[$tag], $arg, $this);
            switch ($tag) {
                case 'inc': return $this->_file('' === $arg ? '*' : $arg);
                case 'use': return (string)$this->_block($arg, $m[0]);
                case 'view': return Jet::q(DEV ? "MVC::in_tpl(false);view(%s);MVC::in_tpl()" : 'view(%s)', $arg);
                case 'pdaxt': return sprintf('<?php MVC::pdaxt(%s) ?>', $arg ? $arg : '') . $sp;
                case 'else': return '<?php else: ?>' . $sp;
                case 'elseif': return "<?php elseif ($arg): ?>";
                case 't': return Jet::q('echo t(%s)', $arg);
                case 'p': return Jet::q('echo \'"\' . PATH . %s . \'"\'', $arg);
                case 'dump': return "<?php echo '<pre>' . html(print_r($arg, true)) . '</pre>' ?>";
                case 'mime': $this->div = ''; return Jet::q('MVC::doctype(%s)', $arg);
                case 'href': return 'href="javascript:;" onclick="' . $arg . '"';
                case 'csrf': return '<?php echo hidden() ?>' . $sp;
                case 'head': return "<?php MVC::head($arg) ?>";
                case 'tail':
                    $ed_var = DEV ? ' if (2 == Ext::cfg("var")): Ext::ed_var(get_defined_vars()); endif;' : '';
                    return "<?php$ed_var MVC::tail($arg) ?>";
                case 'continue': return $arg ? "<?php if ($arg): continue; endif ?>" : '<?php continue ?>' . $sp;
                case 'break': return $arg ? "<?php if ($arg): break; endif ?>" : '<?php break ?>' . $sp;
                case 'empty':
                    if ('do' == end($this->loop))
                        throw new Error('Jet: no @empty statement for `do-while`');
                    $this->empty[] = $i = count($this->loop) - 1;
                    return $this->_loop(true, '') . '<?php if (!' . ($i ? '$_' . (1 + $i) : '$_') . '): ?>';
            }
            return $m[0];
        }, $str);
    }

    private function _loop($end, $arg) {
        $cnt = count($this->loop);
        if ($end) {
            $iv = $cnt - 1 ? '$_' . $cnt : '$_';
            if ($arg) { # do-while
                array_pop($this->loop);
                return sprintf("<?php $iv++; } while (%s); ?>", preg_match('/^\$e_\w+$/', $arg) ? "\$row = $arg->one()" : $arg);
            } elseif (end($this->empty) == $cnt) {
                array_pop($this->empty);
                return '<?php endif ?>';
            }
            return sprintf('<?php %s++; end%s ?>', $iv, array_pop($this->loop));
        }

        $iv = $cnt ? '$_' . (1 + $cnt) : '$_';
        if (!$arg) { # do-while
            $this->loop[] = 'do';
            return "<?php $iv = 0; do { ?>";
        }
        $is_for = $is_foreach = false;
        foreach (token_get_all("<?php $arg") as $t) {
            $is_for |= is_string($t) && ';' == $t;
            $is_foreach |= is_array($t) && T_AS == $t[0];
        }
        if (!$is_foreach && '$e_' == substr($arg, 0, 3)) {
            $is_foreach = true;
            $arg .= ' as $row'; # eVar style cycle
        }
        $this->loop[] = ($for = $is_foreach ? 'foreach' : ($is_for ? 'for' : 'while'));
        return "<?php $iv = 0; $for ($arg): ?>";
    }

    private function test_cycled(&$tpl, $op) {
        if ('.' == $tpl[0])
            $tpl = $this->cur_tpf . $tpl;
        if (in_array($tpl, Jet::$inc))
            throw new Error("Jet: cycled @$op($tpl)");
        Jet::$inc[] = $this->cur_tpf;
        if (!$this->cur_ml)
            return;
        $ary = explode('.', $this->cur_ml);
        array_walk($ary, function ($v) {
            Jet::$inc[] = "$this->cur_tpf.$v";
        });
    }

    private function _file($tpl) {
        $red = '%s';
        $div = '';
        if ('*' == $tpl) {
            $div = $this->div;
            if ('_' === $this->body)
                return $div . '<?php echo $sky->ob ?>';
            $tpl = $this->body;
        } elseif (DEV && 'r_' == substr($tpl, 0, 2)) { # red label
            $red = '<?php if ($sky->s_red_label): ?>'
                . tag("@inc($tpl)", 'class="red_label"')
                . "<?php else: ?>%s<?php endif ?>";
        }

        $this->test_cycled($tpl, 'inc');
        if (isset($this->replace[$lab = "%__inc_{$tpl}__%"]))
            return $div . $lab;

        $me = new Jet($tpl);
        $this->replace[$lab] = sprintf($red, $me->parsed);
        $this->files += $me->files;
        return $div . $lab;
    }

    private function _block($pf, $name, $tpl = '', $lab = false) {
        if ($inline = $lab) { # from @block(..)
            if (isset(Jet::$block[$name])) {
                if (Jet::$block[$name][0])
                    throw new Error("Jet: duplicated @block($name) definishion");
                Jet::$block[$name][0] = $lab;
                return; # @block(..) already overloaded by @use(..)
            }
        } else { # from @use(..)
            if (!preg_match('/^(.*?) as ([a-z][ \*]|)(\w+)$/', $pf, $m))
                return $name;
            list (, $tpl, $pf, $name) = $m;
            if ($inline = '`' == $tpl[0]) {
                $tpl = substr($tpl, 1, -1);
            } else {
                $this->test_cycled($tpl, 'use');
            }
        }

        $me = new Jet($inline ? [$tpl, $this->cur_tpf, $this->cur_ml] : $tpl);
        Jet::$block[$name] = [$lab ? $lab : (Jet::$block[$name][0] ?? false), $pf, $me->parsed, $me->files];
    }
}
