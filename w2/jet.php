<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class Jet
{
    private $parsed = [];
    private $files = [];
    private $div = '<div style="display:none" id="err-top"></div>';

    private static $directive = false;
    private static $custom = [];
    private static $verb;
    private static $inc;
    private static $use;
    private static $ref;
    private static $block;
    private static $id;
    private static $empty = [];

    static $tpl = [];
    static $loop = [];
    static $fn;

    static function directive($name, $func = null) {
        Jet::$custom += is_array($name) ? $name : [$name => $func];
    }

    function __construct($name, $layout = false, $fn = false, $return = null) {
        global $sky;

        if ($fn) {
            Jet::$block = Jet::$use = Jet::$ref = Jet::$inc = Jet::$verb = [];
            Jet::$id = 1;
            Jet::$fn = basename($fn);
        }
        $marker = '';
        if ($layout) {
            $this->body = $name;
            $name = '_' == $layout[0] ? $layout : "y_$layout";
        }
        if (is_string($name)) {
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

        $this->parse($name, $marker);

        if ($fn)
            file_put_contents($fn, $this->compile($layout, $return));
    }

    private function compile($layout, $return) {
        $top = $bottom = $replace = $set0 = [];
        $occupied = ['k', 'e', 'y'];
        static $inner;

        $outer = function ($name) use (&$bottom, &$replace, &$set0, &$inner) {
            list ($id, $pf, $parsed, $files, $scheme2, $loop) = Jet::$block[$name];
            if (!$id)
                return ''; // #use without @block
            if (isset($replace[$id]))
                return $replace[$id];
            $this->files += $files;
            $tpl = $inner($name, $pf, $parsed);

            if (isset(Jet::$use[$name])) { // has @use
                $cnt_u = count(Jet::$use[$name]);
                $set0[] = $bv = '$_b' . $id;
                $if = "else: ?>$tpl<?php endif";
                for ($j = 0; $j < $cnt_u; $j++) {
                    list ($use_id, $pf, $parsed, $files) = Jet::$use[$name][$j];
                    $this->files += $files;
                    $replace[$use_id] = "<?php $bv = $use_id ?>";
                    $if = "if ($use_id == $bv): ?>" . $inner($name, $pf, $parsed) . "<?php $if";
                    $j == $cnt_u - 1 or $if = "else$if";
                }
                if ($scheme2) { # @use last
                    if ($loop)
                        $set0[] = $iv = '$_i' . $id;
                    $ob = sprintf('$_ob[%s]', $loop ? "\"$id-$iv\"" : -$id);
                    $tpl = "<?php \$_ob[] = ob_get_clean(); $ob = ''; ";
                    $tpl .= 'ob_start()' . ($loop ? "; $iv++ ?>" : ' ?>');
                    $if = "ob_start(); $if; $ob = ob_get_clean();";
                    $bottom[] = $loop
                        ? "for ($iv = 0; isset($ob); $iv++): $if endfor"
                        : "if (isset($ob)): $if endif";
                } else { # @block last
                    $tpl = "<?php $if ?>";
                }
            }
            return $replace[$id] = $tpl;
        };

        $inner = function ($name, $pf, $parsed) use (&$top, &$occupied, &$outer) {
            $tpl = '';
            array_walk_recursive($parsed, function ($str, $id) use (&$tpl, &$outer) {
                if (isset(Jet::$ref[$id])) {
                    $block_id = Jet::$block[$str][0] ?? 0;
                    $str = $block_id ? (Jet::$ref[$id] ? $outer($str) : "<?php \$_b$block_id = $id ?>") : '';
                }
                $tpl .= $str;
            });
            if ($closure = strpos($tpl, '?'))
                $closure = preg_match("/\\$\w+/s", $tpl);
            if ($pf && ' ' == $pf[1]) {
                $s = $closure ? '$_a = SKY::$vars; ' : '';
                if (DEV)
                    $s .= 'MVC::in_tpl(false); ';
                if ($closure)
                    $s .= '$_b = (array)';
                $s .= "MVC::handle('$pf[0]_$name', \$_vars)";
                if (DEV)
                    $s .= "; trace(MVC::\$ctrl . '::$pf[0]_$name() ^', 'BLK-VIEW')";
                if ($closure)
                    $s .= '; MVC::vars($_a, $_b); extract($_a, EXTR_REFS)';
                if (DEV)
                    $s .= '; MVC::in_tpl()';
                if ($closure)
                    return "<?php call_user_func(function() use(&\$_vars) { $s ?>$tpl<?php }) ?>";
                return "<?php $s ?>$tpl";
            } elseif ($pf) { # asterisk
                if (in_array($pf[0], $occupied))
                    throw new Error("Jet blocks: char `$pf[0]` occupied");
                $occupied[] = $pf[0];
                $pf[1] = '_';
                $top[] = "\$_$pf = (array)MVC::handle('$pf$name', \$_vars); MVC::vars(\$_vars, \$_$pf, '$pf');";
            }
            return $tpl;
        };

        foreach (array_keys(Jet::$block) as $name)
            $outer($name);

        $out = "<?php\n#" . ($list = implode(' ', array_keys($this->files))) . "\n";
        if ($top)
            $out .= implode("\n", $top) . "\n";
        if ($set0)
            $out .= implode(" = ", $set0) . " = 0;\n";
        if ($bottom)
            $out .= "\$_ob = []; ";
        if ($bottom || $return)
            $out .= "ob_start(); ";
        $out .= "extract(\$_vars, EXTR_REFS) ?>";
        if (DEV) {
            $out .= "<?php\ntrace('TPL: $list'); MVC::in_tpl(true);";
            $out .= "\nif (" . ($return ? 'true' : 'false') . ' != ($sky->return || 1 == $sky->ajax && !$sky->is_sub))';
            $out .= "\nthrow new Error('Return status do not match for file: ' . __FILE__) ?>";
        }
        array_walk_recursive($this->parsed, function ($str, $id) use (&$out, &$replace) {
            $out .= isset(Jet::$ref[$id]) ? ($replace[$id] ?? '') : $str;
        });
        if ($bottom)
            $out .= "<?php \$_ob[] = ob_get_clean();\n" . implode(";\n", $bottom) . ' ?>';
        if (DEV && !$layout)
            $out .= '<?php if (2 == DEV::cfg("var")): DEV::ed_var(get_defined_vars()); endif ?>';
        if (DEV)
            $out .= "<?php MVC::in_tpl() ?>";
        if ($return) {
            $out .= '<?php return ' . ($bottom ? 'implode("", $_ob);' : 'ob_get_clean();');
        } else {
            $out .= $bottom ? '<?php echo implode("", $_ob); return "";' : "<?php return '';";
        }
        $str = $ct = ''; /* optimize `?><?php` in parsed templates */
        $buf = [];
        foreach (token_get_all($out) as $token) {
            is_array($token) or $token = [0, $token];
            if ($token[0] == T_OPEN_TAG && $ct) {
                array_pop($buf);
                T_WHITESPACE != end($buf)[0] or array_pop($buf);
                $token = [0, in_array(end($buf)[1], [':', ';']) ? "\n" : ";\n"];
            }
            $buf[] = $token;
            $ct = T_CLOSE_TAG == $token[0];
        }
        while ($buf)
            $str .= array_shift($buf)[1];
        return strtr($str, Jet::$verb) . "\n";
    }

    private function save($in, $new = false) {
        if ($new || is_array($in)) {
            $this->parsed[++Jet::$id] = $in;
            return Jet::$id++;
        }
        isset($this->parsed[Jet::$id]) or $this->parsed[Jet::$id] = '';
        $this->parsed[Jet::$id] .= $in;
        return '';
    }

    private function parse($name, $marker = '') {
        $this->marker = false;
        if ($inline = is_array($name)) {
            list ($in, $this->marker) = $name;
        } else {
            Jet::$tpl[] = [$name, $marker];
            $this->files[$name] = 1;
            $in = file_get_contents(MVC::fn_tpl($name));
        }
        if ('' !== $marker) {
            if (3 != count($ary = preg_split("/^\#([\.\w+]*?\.{$marker}\b[\.\w+]*).*$/m", $in, 3)))
                throw new Error("Jet: cannot find `$name.$marker`");
            $in = $ary[1];
            $this->marker = $marker;
        }
        # @verb
        $in = preg_replace_callback('/@verb(.*?)~verb/s', function ($m) {
            Jet::$verb[$lab = '%__verb_' . count(Jet::$verb) . '__%'] = $m[1];
            return $lab;
        }, $in);
        # disable PHP tags
        foreach (token_get_all($in) as $token) {
            if (T_OPEN_TAG == $token[0])
                throw new Error("Jet: cannot use PHP tags, apply @php(..) instead");
        }
        # delete nested part markers
        $in = preg_replace('/([\r\n]+|\A)\s*#\.[\.\w+]+.*?([\r\n]+|\z)/s', '$2', $in);
        # preprocessor
        $in = $this->preprocessor($in);
        # the main parser
        $offset = 0;
        while (false !== ($pos = strpos($in, '#use(', $offset)))
            $offset = $this->_block($pos, $in, false);
        while (preg_match('/^(.*?)(~|@)([a-z]+)(.*)$/s', $in, $m)) {
            $this->save($this->echos($m[1]));
            $in = substr($m[4], strlen($br = Rare::bracket($m[4])));
            $code = $this->statements($m[3], $br ? substr($br, 1, -1) : '', '~' == $m[2], $in);
            $this->save(null === $code ? $m[2] . $m[3] . $br : $code);
        }
        $this->save($this->echos($in));
        $inline or array_pop(Jet::$tpl);
    }

    private function statements($tag, $arg, $end, &$str) {
        $q = function ($pattern, $arg) {
            return sprintf("<?php $pattern ?>", in_array(@$arg[0], [false, "'", '"'])
                ? $arg
                : '"' . strtr($arg, ["\\" => "\\\\", '"' => '\\"']) . '"'
            );
        };
        switch ($tag) {
            case 'php':
                return $arg ? '<?php ' . trim($arg) . ' ?>' : ($end ? '?>' : '<?php');
            case 'unless':
                $arg = preg_match("/^\\$\w+$/", $arg) ? "!$arg" : "!($arg)";
            case 'if':
                return $end ? "<?php endif ?>" : "<?php if ($arg): ?>";
            case 'cache':
                return $end ? '<?php Rare::cache(); endif ?>' : $q('if (Rare::cache(%s)):', $arg);
            case 'loop':
                return $this->_loop($end, $arg);
        }
        if ($end)
            return;

        switch ($tag) {
            case 'inc':
                return $this->_inc('' === $arg ? '*' : $arg);
            case 'block':
            case 'use':
                return !$arg ? null : $this->_block($arg, $str, 'block' == $tag);
            case 'view':
                return $q(DEV ? "MVC::in_tpl(false);view(%s);MVC::in_tpl()" : 'view(%s)', $arg);
            case 'pdaxt':
                return sprintf('<?php MVC::pdaxt(%s) ?>', $arg);
            case 'else':
                return '<?php else: ?>';
            case 'elseif':
                return "<?php elseif ($arg): ?>";
            case 't':
                return $q('echo t(%s)', $arg);
            case 'p':
                return $q('echo \'"\' . PATH . %s . \'"\'', $arg);
            case 'dump':
                return "<?php echo '<pre>' . html(print_r($arg, true)) . '</pre>' ?>";
            case 'mime':
                $this->div = '';
                return $q('MVC::doctype(%s)', $arg);
            case 'href':
                return 'href="javascript:;" onclick="' . $this->echos($arg) . '"';
            case 'csrf':
                return '<?php echo hidden() ?>';
            case 'head':
                return "<?php MVC::head($arg) ?>";
            case 'tail':
                $ed_var = DEV ? ' if (2 == DEV::cfg("var")): DEV::ed_var(get_defined_vars()); endif;' : '';
                return "<?php$ed_var MVC::tail($arg) ?>";
            case 'continue':
                return $arg ? "<?php if ($arg): continue; endif ?>" : '<?php continue ?>';
            case 'break':
                return $arg ? "<?php if ($arg): break; endif ?>" : '<?php break ?>';
            case 'empty':
                if ('do' == end(Jet::$loop))
                    throw new Error('Jet: no @empty statement for `do-while`');
                Jet::$empty[] = $i = count(Jet::$loop) - 1;
                return $this->_loop(true, '') . '<?php if (!' . ($i ? '$_' . (1 + $i) : '$_') . '): ?>';
        }
        if (isset(Jet::$custom[$tag]))
            return call_user_func(Jet::$custom[$tag], $arg, $this); # user defined
    }

    private function echos(&$str) {
        return preg_replace_callback('/[~@]?{[{!\-](.*?)[\-!}]}/s', function ($m) {
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

    private function preprocessor($in) {
        $ary = [];
        $p =& $ary;
        $d = [&$p];
        $tmp = '';
        while (preg_match("/^(.*?)\#((if\(|elseif\()|(end|else)\b)(.*)$/s", $in, $m)) {
            $in = $m[5];
            $arg = ($br = '(' == $m[2][-1]) ? Rare::bracket('(' . $m[5]) : false;
            $if = 'if(' == $m[2];
            if (count($d) < 2 && !$if || '()' == $arg || $br && !$arg) {
                $tmp .= $m[1] . '#' . $m[2];
                continue;
            }
            if ('end' == $m[2]) {
                array_push($p, $tmp . $m[1], 'end');
                array_pop($d);
                $p =& $d[count($d) - 1];
            } else {
                if ($arg)
                    $in = substr($m[5], strlen($arg) - 1);
                if ($if) {
                    array_push($p, $tmp . $m[1], []);
                    $tmp = $m[1] = '';
                    $p =& $p[count($p) - 1];
                    $d[] =& $p;
                }
                array_push($p, $tmp . $m[1], $arg ?: 'else');
            }
            $tmp = '';
        }
        $p[] = $tmp . $in;

        $eval = function ($arg) {
            static $ary;
            if (null === $ary) {
                $ary = [':_0' => '$sky->_0', ':_1' => '$sky->_1', ':_2' => '$sky->_2'];
                foreach (is_file($fn = 'main/app/jet.let') ? file($fn) : [] as $one) {
                    if (preg_match("/^(:\w+)\s+(.+)/", $one, $m))
                        $ary[$m[1]] = $m[2];
                }
            }
            for ($i = 0; $i < 22; $arg = $new, $i++) {
                $new = preg_replace_callback("/(:\w+)/", function ($m) use (&$ary) {
                    return '(' . $ary[$m[1]] . ')';
                }, $arg);
                if ($arg === $new)
                    break;
            }
            if ($i > 21)
                throw new Error("Jet preprocessor: cycled pseudo-variables");
            global $sky;
            return eval("return $arg;");
        };

        $crop = function ($ary) use(&$crop, &$eval) {
            $out = '';
            foreach ($ary as $v) {
                if (is_string($v)) {
                    $out .= $v;
                    continue;
                }
                $i = $ce = $ok = $len = 0;
                for ($cnt = count($v); $i < $cnt; $i++) {
                    if (++$i == $cnt)
                        break;
                    if (is_array($el = $v[$i]))
                        continue;
                    !$ce or $ce++;
                    if ('else' === $el) {
                        $ce++;
                        $ok or $ok = $i;
                    }
                    if ($ok && !$len)
                        $len = $i - $ok - 1;
                    if (!$ok && '(' === $el[0] && $eval($el))
                        $ok = $i;
                }
                if ($ce > 2 || 'end' !== end($v)) { # reassemble on wrong syntax
                    foreach ($v as $i => $ok) {
                        if (is_array($ok)) {
                            $out .= $crop([$ok]);
                        } elseif (0 == $i % 2) {
                            $out .= $ok;
                        } else {
                            $out .= 1 != $i ? ('e' == $ok[0] ? "#$ok" : "#elseif$ok") : "#if$ok";
                        }
                    };
                } elseif ($ok) {
                    $out .= $crop(array_slice($v, $ok + 1, $len));
                }
            }
            return $out;
        };

        return $crop($ary);
    }

    private function _loop($end, $arg) {
        $cnt = count(Jet::$loop);
        if ($end) {
            $iv = $cnt - 1 ? '$_' . $cnt : '$_';
            if ($arg) { # do-while
                array_pop(Jet::$loop);
                return sprintf("<?php $iv++; } while (%s); ?>", preg_match('/^\$e_\w+$/', $arg) ? "\$row = $arg->one()" : $arg);
            } elseif (end(Jet::$empty) == $cnt) {
                array_pop(Jet::$empty);
                return '<?php endif ?>';
            }
            return sprintf('<?php %s++; end%s ?>', $iv, array_pop(Jet::$loop));
        }
        $iv = $cnt ? '$_' . (1 + $cnt) : '$_';
        if (!$arg) { # do-while
            Jet::$loop[] = 'do';
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
        Jet::$loop[] = ($loop = $is_foreach ? 'foreach' : ($is_for ? 'for' : 'while'));
        return "<?php $iv = 0; $loop ($arg): ?>";
    }

    private function test_cycled(&$tpl, $op) {
        $file = Jet::$tpl[count(Jet::$tpl) - 1][0];
        if ('.' == $tpl[0])
            $tpl = $file . $tpl;
        if (in_array($tpl, Jet::$inc))
            throw new Error("Jet: cycled $op($tpl)");
        Jet::$inc[] = $this->marker ? "$file.$this->marker" : $file;
    }

    private function _inc($tpl) {
        $red = '';
        if ('*' == $tpl) {
            $this->save($this->div);
            if ('_' === $this->body)
                return $this->save('<?php echo $sky->ob ?>');
            $tpl = $this->body;
        } elseif (DEV && 'r_' == substr($tpl, 0, 2)) { # red label
            $red = '<?php if ($sky->s_red_label): ?>' . tag("@inc($tpl)", 'class="red_label"');
            $this->save($red . '<?php else: ?>');
            $red = '<?php endif ?>';
        }

        $this->test_cycled($tpl, '@inc');
        $jet = new Jet($tpl);
        $this->save($jet->parsed);
        $this->save($red);
        $this->files += $jet->files;
        return '';
    }

    private function _block($arg, &$str, $is_block) {
        $regexp = '/^(.+?) as ([a-z][ \*]|)(\w+)$/';
        if ($is_block) {
            if (preg_match($regexp, $arg, $m)) {
                list (, $tpl, $pf, $name) = $m;
            } elseif (preg_match('/^([a-z][ \*]|)(\w+) (.*?)~block(\W|\z)/s', "$arg $str", $m)) {
                list (, $pf, $name, $tpl) = $m;
                $str = substr($str, 6 + strlen($tpl));
                $tpl = "`$tpl`";
            } else {
                return null;
            }
            $exist = isset(Jet::$block[$name]);
            if ($exist && Jet::$block[$name][0])
                throw new Error("Jet: duplicated @block($name)");
            Jet::$block[$name][0] = $id = $this->save($name, true);
            Jet::$block[$name][4] = 0;
            Jet::$block[$name][5] = count(Jet::$loop);
            Jet::$ref[$id] = true;
            if ($exist)
                return ''; // @block(..) already overloaded by #use(..)
            $type = 2;
        } else {
            if ($type = (int)is_int($arg)) { // 0 for @use, 1 for #use
                $s1 = substr($str, $pos = 4 + $arg);
                $arg = ($br = Rare::bracket($s1)) ? substr($br, 1, -1) : '';
            }
            if (!preg_match('/^(\.()(\w+))$/', $arg, $m) && !preg_match($regexp, $arg, $m))
                return $type ? $pos : null;
            list (, $tpl, $pf, $name) = $m;
            if ($type)
                $str = substr($str, 0, $pos - 4) . substr($str, $pos + strlen($br));
        }
        if ('`' == $tpl[0]) {
            $tpl = [substr($tpl, 1, -1), $this->marker];
        } else {
            $this->test_cycled($tpl, $is_block ? '@block' : ($type ? '#use' : '@use'));
        }
        $jet = new Jet($tpl);
        $data = [0, $pf, $jet->parsed, $jet->files, Jet::$block[$name][4] ?? 0, Jet::$block[$name][5] ?? 0];

        if ($type) { // #use(..) or @block(..)
            Jet::$block[$name] = [Jet::$block[$name][0] ?? 0] + $data;
        } else { // @use(..)
            Jet::$ref[$id = $this->save($name, true)] = false;
            Jet::$use[$name][] = [$id] + $data;
            if (isset(Jet::$block[$name]))
                Jet::$block[$name][4] = 1;
        }

        return 1 == $type ? $pos - 4 : '';
    }
}
