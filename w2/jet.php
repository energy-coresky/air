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
    private $current = '';
    private static $vars = [];
    static $directive = false;
    static $custom = [];

    static function directive($name, $func = null) {
        Jet::$custom += is_array($name) ? $name : [$name => $func];
    }

    static function q($pattern, $arg) {
        $arg = in_array(@$arg[0], [false, "'", '"']) ? $arg : '"' . escape($arg, false, '\\"') . '"';
        return sprintf("<?php $pattern ?>", $arg);
    }
    
    function __construct($layout, $name, $fn = false, $vars = null) {
        global $sky;

        $this->body = $name;
        if (null !== $vars)
            Jet::$vars = $vars;
        $part = '';
        if ($layout) {
            $name = "y_$layout";
        } else {
            if (strpos($name, '.'))
                list($name, $part) = explode('.', $name, 2);
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
        $this->files[$name] = 1;
        $this->parsed = $this->parse($this->current = $name, $part);
        if ($fn) {
            $out = "<?php\n#" . ($list = implode(' ', array_keys($this->files))) . "\n";
            $out .= (DEV ? "trace('TPL: $list')?>" : '?>') . $this->parsed;
            if (DEV && $vars && !$layout)
                $out .= '<?php if (2 == Ext::cfg("var")): Ext::ed_var(get_defined_vars()); endif; ?>';
            file_put_contents($fn, $out);
        }
    }
    
    private function parse($name, $part = '') {
        $in = file_get_contents(MVC::fn_tpl($name));
        if ('' !== $part) {
            $ary = preg_split("/([\r\n]+|\A)\s*\#[\.\w+]*?\.{$part}[\.\w+]*?( .*?)?([\r\n]+|\z)/s", $in, 3);
            $in = '';
            if (count($ary) != 3)
                throw new Error("Jet: cannot find `$name.$part`");
            else
                $in = $ary[1];
        }
        for ($i = 0; $i < 20 && $this->preprocessor($in); $i++);
        $in = preg_replace('/([\r\n]+|\A)\s*#\.[\.\w+]+.*?([\r\n]+|\z)/s', '$2', $in); # delete nested part markers
        
        $in = preg_replace_callback('/@verb(.*?)~verb/s', function ($m) {
            $this->replace[$lab = '%__verb_' . count($this->replace) . '__%'] = $m[1];
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

        return $this->optimize(strtr($out, $this->replace));
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
            switch ($m[1]) {
                case 'php': return $end ? "?>$sp" : "<?php$sp";
                case 'unless': $arg = "!($arg)";
                case 'if': return $end ? "<?php endif ?>$sp" : "<?php if ($arg): ?>";
                case 'cache': return $end ? '<?php Rare::cache(); endif ?>' : "<?php if (Rare::cache($arg)): ?>";
                case 'loop': return $this->_loop($end, $arg); # for, foreach, while, do { .. } while(..)
            }

            if ($end)
                return $m[0];
            if (isset(Jet::$custom[$m[1]])) # user defined
                return call_user_func(Jet::$custom[$m[1]], $arg, $this);

            switch ($m[1]) {
                case 'inc': return $this->_file('' === $arg ? '*' : $arg);
                case 'view': return Jet::q('view(%s)', $arg);
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

    function _loop($end, $arg) {
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
        } elseif (preg_match('/^\$e_\w+$/', $arg)) { # eVar style cycle
            $this->loop[] = 'foreach';
            return "<?php $iv = 0; foreach ($arg as \$row): ?>";
        }
        $is_for = $is_foreach = false;
        foreach (token_get_all("<?php $arg") as $t) {
            $is_for |= is_string($t) && ';' == $t;
            $is_foreach |= is_array($t) && T_AS == $t[0];
        }
        $this->loop[] = ($for = $is_foreach ? 'foreach' : ($is_for ? 'for' : 'while'));
        return "<?php $iv = 0; $for ($arg): ?>";
    }

    function _file($name) {
        $red = '%s';
        $div = '';
        if ('*' == $name) {
            $div = $this->div;
            if ('_' === $this->body)
                return $div . '<?php echo $_stdout ?>';
            $name = $this->body;
        } elseif (DEV && 'r_' == substr($name, 0, 2)) { # red label
            $red = '<?php if ($sky->s_red_label): ?>'
                . tag("@inc($name)", 'class="red_label"')
                . "<?php else: ?>%s<?php endif ?>";
        }
        if ('.' == $name[0])
            $name = $this->current . $name;
        # 2do: throw new Error('Jet: cycled @inc()');
        $lab = "%__inc_{$name}__%";
        if (isset($this->replace[$lab]))
            return $div . $lab;
        $me = new Jet('', $name);
        $this->replace[$lab] = sprintf($red, $me->parsed);
        $this->files += $me->files;
        return $div . $lab;
    }
}
