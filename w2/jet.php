<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class Jet
{
    private $parsed = [];
    private $files = [];
    private $loop = [];

    private static $custom = [];
    private static $verb;
    private static $inc;
    private static $block;
    private static $use;
    private static $ptr;
    private static $top;
    private static $id;
    private static $empty = [];
    private static $if = [];
    private static $depth = [];

    static $tpl = [];
    static $fn;

    static function directive($name, $func = null) {
        Jet::$custom += is_array($name) ? $name : [$name => $func];
    }

    function __construct($name, $layout = false, $fn = false, $return = null) {
        if ($fn) {
            Jet::$block = Jet::$use = Jet::$ptr = Jet::$inc = Jet::$verb = [];
            $this->parsed[Jet::$id = 1] = '';
            Jet::$top =& $this->parsed[1];
            Jet::$fn = explode('-', basename($fn), 2)[1];
            $this->occupied = ['k', 'e', 'y'];
            if (MVC::$mc) # not console!
                MVC::handle('jet_c');
            Plan::_rq('mvc/jet.php');
        }
        if ($layout) {
            $this->body = $name;
            $name = '_' == $layout[0] ? $layout : "y_$layout";
        }
        if (is_string($name) && strpos($name, '.'))
            list($name, $marker) = explode('.', $name, 2);

        $this->parsed[++Jet::$id] = '';
        $this->parse($name, $marker ?? '');
        if ($fn)
            Plan::jet_p($fn, $this->compile($layout, $return));
    }

    private function compile($layout, $return) {
        uasort(Jet::$block, function ($a, $b) { # sort 0-depth blocks
            return $a[0] > $b[0] ? 1 : -1;
        });
        $tail = false;
        $set0 = $this->first = $second = [];

        $ok = function ($name, $mode = false) {
            foreach (Jet::$use[$name] as $k => $use) {
                $pu =& Jet::$ptr[$use[0]];
                if ($pu[2] && !$pu[5]) {
                    if (!$mode)
                        return false;
                    unset(Jet::$use[$name][$k]);
                }
            }
            return true;
        };
        foreach (Jet::$block as $name => $block) {
            $id = $block[0];
            if ($id && !Jet::$ptr[$id][2]) # root block
                $this->first[] = $name;
        }
        while ($this->first || $second) {
            while ($this->first) {
                $no_use = !isset(Jet::$use[$name = array_shift($this->first)]);
                $no_use || $ok($name) ? $this->block($name, $tail, $set0, $no_use) : ($second[] = $name);
            }
            if ($second) {
                $ok($name = array_shift($second), true);
                $this->block($name, $tail, $set0, false);
            }
        }

        $out = "<?php\n#" . ($list = implode(' ', array_keys($this->files))) . "\n";
        if ($set0)
            Jet::$top .= implode(" = ", $set0) . " = 0;\n";
        if ($tail)
            Jet::$top .= "\$_ob = []; ";
        if ($tail || $return)
            Jet::$top .= "ob_start(); ";
        Jet::$top .= "extract(\$_vars, EXTR_REFS) ?>";
        if (DEV) {
            Jet::$top .= "<?php\ntrace('TPL: $list'); MVC::in_tpl(true);";
            Jet::$top .= "\nif (" . ($return ? 'true' : 'false') . ' != ($sky->return || 1 == $sky->ajax && !$sky->is_sub))';
            Jet::$top .= "\nthrow new Error('Return status do not match for file: ' . __FILE__) ?>";
        }
        array_walk_recursive($this->parsed, function ($str, $id) use (&$out) {
            $out .= is_string($id) ? '' : $str;
        });
        if ($tail)
            $out .= ' ?>';
        if (DEV && !$layout)
            $out .= '<?php if (2 == $sky->d_var): DEV::ed_var(get_defined_vars()); endif ?>';
        if (DEV)
            $out .= "<?php MVC::in_tpl() ?>";
        if ($return) {
            $out .= '<?php return ' . ($tail ? 'implode("", $_ob);' : 'ob_get_clean();');
        } else {
            $out .= $tail ? '<?php echo implode("", $_ob); return "";' : "<?php return '';";
        }

        return strtr(Rare::optimize($out), Jet::$verb) . "\n";
    }

    private function code($name, $jet) {
        $this->files += $jet->files;
        $s = '';
        array_walk_recursive($jet->parsed, function (&$v, $id) use (&$s) {
            if (is_string($id)) { # nested @block or @use
                $pv =& Jet::$ptr[$v];
                if ($pv[0])
                    $this->first[] = $id;
                if ($this->u_last) {
                    $pv[5] = Jet::$id;
                    $this->parsed[++Jet::$id] = '';
                } else {
                    $pv[5] = $this->b_id;
                }
                $pv[3] += $this->b_loop; # in loop
                $pv[4] += $this->b_if + $this->u_if; # in if
            } else {
                if ($this->b_loop)
                    $this->correct($v, $this->b_loop);
                $s .= $v;
            }
        });

        if (($pf = $jet->pf) && ' ' == $pf[1]) { # space
            if ($closure = strpos($s, '?'))
                $closure = preg_match("/\\$\w+/s", $s);
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
            if (!$closure)
                return ["<?php $s ?>", $jet->parsed];
            return ["<?php call_user_func(function() use(&\$_vars) { $s ?>", $jet->parsed, '<?php }) ?>'];

        } elseif ($pf) { # asterisk
            if (in_array($pf[0], $this->occupied))
                throw new Error("Jet blocks: char `$pf[0]` occupied");
            $this->occupied[] = $pf[0];
            $pf[1] = '_';
            Jet::$top .= "\$_$pf = (array)MVC::handle('$pf$name', \$_vars); MVC::vars(\$_vars, \$_$pf, '$pf');\n";
        }
        return $jet->parsed;
    }

    private function block($name, &$tail, &$set0, $no_use) {
        list ($id, $jet) = Jet::$block[$name];
        $pb =& Jet::$ptr[$id];
        $this->b_id = $pb[5] ?: $id;
        $this->b_loop = $pb[3];
        $this->b_if = $pb[4];
        $this->u_if = $this->u_last = 0;
        if ($no_use)
            return $pb[1] = $this->code($name, $jet);

        usort(Jet::$use[$name], function ($a, $b) {
            $a = Jet::$ptr[$a[0]][5] ?: $a[0];
            $b = Jet::$ptr[$b[0]][5] ?: $b[0];
            return $a > $b ? 1 : -1;
        });
        $skip = [];
        foreach (Jet::$use[$name] as $j => $use) {
            $pu =& Jet::$ptr[$use[0]];
            $last_id = $pu[5] ?: $use[0];
            if (!$this->u_if = $pu[4]) {
                $skip[] = $j;
                $jet = $use[1];
            }
        }
        if (!$this->u_if)
            return $pb[1] = $this->code($name, $jet);

        $set0[] = $bv = '$_b' . $id;
        $this->u_last = $last_id > $this->b_id;
        $if = ['else: ?>', $this->code($name, $jet), '<?php endif'];
        foreach (Jet::$use[$name] as $k => $use) {
            if (in_array($k, $skip))
                continue;
            list ($use_id, $jet) = $use; # @use code to add
            Jet::$ptr[$use_id][1] = "<?php $bv = $use_id ?>";
            $if = ["if ($use_id == $bv): ?>", $this->code($name, $jet), '<?php ', $if];
            $k == $j or $if = ['else', $if];
        }
        if (!$this->u_last) # @block last
            return $pb[1] = ['<?php ', $if, ' ?>'];

        if ($this->b_loop)
            $set0[] = $iv = '$_i' . $id;
        $ob = sprintf('$_ob[%s]', $this->b_loop ? "\"$id-$iv\"" : -$id);
        $pb[1] = "<?php \$_ob[] = ob_get_clean(); $ob = ''; "
            . 'ob_start()' . ($this->b_loop ? "; $iv++ ?>" : ' ?>');
        $if = ['ob_start(); ', $if, "; $ob = ob_get_clean();"];
        if (!$tail) {
            $tail = true;
            $this->parsed[Jet::$id] .= "<?php \$_ob[] = ob_get_clean()";
        }
        if ($this->b_loop) {
            $this->parsed[Jet::$id] .= ";\nfor ($iv = 0; isset($ob); $iv++): ";
        } else {
            $this->parsed[Jet::$id] .= $this->b_if ? ";\nif (isset($ob)): " : ";\n";
        }
        $this->parsed[++Jet::$id] = $if;
        $this->parsed[++Jet::$id] = $this->b_loop ? ' endfor' : ($this->b_if ? ' endif' : '');
    }

    private function correct(&$str, $loopd = -1) {
        if (is_array($str)) {      // 2do: $_, $_2.. delete if not used !
            return array_walk_recursive($str, function (&$v) {
                $this->correct($v, count($this->loop));
            });
        }
        $buf = [];
        foreach (token_get_all($str) as $tn) {
            is_array($tn) or $tn = [0, $tn];
            if (T_VARIABLE == $tn[0] && preg_match("/^\\\$_(\d|)$/", $tn[1], $m)) {
                $depth = $loopd + ($m[1] ?: 1);
                $tn[1] = '$_' . (1 == $depth ? '' : $depth);
            }
            $buf[] = $tn;
        }
        for ($str = ''; $buf; $str .= array_shift($buf)[1]);
    }

    private function parse($name, $marker = '') {
        $this->marker = false;
        if ($inline = is_array($name)) {
            list ($in, $this->marker) = $name;
        } else {
            Jet::$tpl[] = [$name, $marker];
            $this->files[$name] = 1;
            $in = Plan::view_('g', "$name.jet");
        }
        if ('' !== $marker) {
            if (3 != count($ary = preg_split("/^\#[\.\w+]*?\.{$marker}\b[\.\w+]*.*$/m", $in, 3)))
                throw new Error("Jet: cannot find `$name.$marker`");
            $in = preg_replace("/^\r?\n?(.*?)\r?\n?$/s", '$1', $ary[1]);
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
        $in = preg_replace('/(\r?\n|\r|\A)#\.\w[\.\w]*.*?(\r?\n|\r|\z)/s', '$2', $in);
        # preprocessor
        $in = $this->preprocessor($in);
        # the main parser
        $offset = 0;
        while (false !== ($pos = strpos($in, '#use(', $offset)))
            $offset = $this->_block($pos, $in, false);
        while (preg_match('/^(.*?)(~|@)([a-z]+)(.*)$/s', $in, $m)) {
            if ($m[1] && '~' == $m[1][-1]) {
                $this->parsed[Jet::$id] .= $this->echos(substr($m[1], 0, -1) . $m[2] . $m[3]);
                $in = $m[4];
            } else {
                $this->parsed[Jet::$id] .= $this->echos($m[1]);
                $in = substr($m[4], strlen($br = Rare::bracket($m[4])));
                $code = $this->statements($m[3], $br ? substr($br, 1, -1) : '', '~' == $m[2], $in, $br);
                $this->parsed[Jet::$id] .= null === $code ? $m[2] . $m[3] . $br : $code;
            }
        }
        $this->parsed[Jet::$id] .= $this->echos($in);
        $inline or array_pop(Jet::$tpl);
    }

    private function statements($tag, $arg, $end, &$str, &$br) {
        $q = function ($p, $arg) {
            $ok = '' !== $arg && in_array($arg[0], ["'", '"']);
            return sprintf("<?php $p ?>", $ok ? $arg : '"' . strtr($arg, ["\\" => "\\\\", '"' => '\\"']) . '"');
        };
        switch ($tag) {
            case 'php':
                return $arg ? '<?php ' . trim($arg) . ' ?>' : ($end ? '?>' : '<?php');
            case 'unless':
                $arg = preg_match("/^\\$\w+$/", $arg) ? "!$arg" : "!($arg)";
            case 'if':
            case 'cache':
                $end ? array_pop(Jet::$if) : array_push(Jet::$if, 1);
                if ('cache' == $tag)
                    return $end ? '<?php Rare::cache(); endif ?>' : $q('if (Rare::cache(%s)):', $arg);
                return $end ? "<?php endif ?>" : "<?php if ($arg): ?>";
            case 'loop':
                return $this->_loop($end, $arg);
        }
        if ($end)
            return;
        if (isset(Jet::$custom[$tag]))
            return call_user_func(Jet::$custom[$tag], $arg, $this); # user defined

        switch ($tag) {
            case 'inc':
                $this->_inc('' === $arg ? '*' : $arg);
                return '';
            case 'block':
            case 'use':
                return !$arg ? null : $this->_block($arg, $str, 'block' == $tag);
            case 'view':
                return $q(DEV ? "MVC::in_tpl(false);view(%s);MVC::in_tpl()" : 'view(%s)', $arg);
            case 'eat':
                for ($len = strlen($str), $i = 0; $i < $len && in_array($str[$i], [' ', "\r", "\n", "\t"]); $i++);
                if ($i)
                    $str = substr($str, $i);
                return '';
            case 'svg':
                $p = explode('.', $arg, 2);
                return (string)(new SVG($p[0], $p[1] ?? false));
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
                return $q('MVC::doctype(%s)', $arg);
            case 'href':
                return 'href="javascript:;" onclick="' . $this->echos($arg) . '"';
            case 'csrf':
                return '<?php echo hidden() ?>';
            case 'head':
                return "<?php MVC::head($arg) ?>";
            case 'tail':
                $ed_var = DEV ? ' if (2 == $sky->d_var): DEV::ed_var(get_defined_vars()); endif;' : '';
                return "<?php$ed_var MVC::tail($arg) ?>";
            case 'continue':
                return $arg ? "<?php if ($arg): continue; endif ?>" : '<?php continue ?>';
            case 'break':
                return $arg ? "<?php if ($arg): break; endif ?>" : '<?php break ?>';
            case 'empty':
                if ('do' == end($this->loop))
                    throw new Error('Jet: no @empty statement for `do-while`');
                Jet::$empty[] = $i = count($this->loop) - 1;
                Jet::$if[count(Jet::$if) - 1] = 1;
                array_push(Jet::$if, 0);
                return $this->_loop(true, '') . '<?php if (!' . ($i ? '$_' . (1 + $i) : '$_') . '): ?>';
            default:
                return !$br || '()' == $br ? null : "<?php echo $br ? ' $tag' : '' ?>";
        }
    }

    private function echos($str) {
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
                $lines = ($txt = Plan::_gq('mvc/jet.let')) ? explode("\n", $txt) : [];
                foreach ($lines as $one) {
                    if (preg_match("/^(:\w+)\s+(.+)/", $one, $m))
                        $ary[$m[1]] = $m[2];
                }
            }
            for ($i = 0; $i < 22; $arg = $new, $i++) {
                $new = preg_replace_callback("/:\w+/", function ($m) use (&$ary) {
                    return isset($ary[$m[0]]) ? '(' . $ary[$m[0]] . ')' : $m[0];
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
        $cnt = count($this->loop);
        if ($end) {
            $iv = $cnt - 1 ? '$_' . $cnt : '$_';
            if ('' !== $arg) { # do-while
                array_pop($this->loop);
                return sprintf("<?php $iv++; } while (%s); ?>", preg_match('/^\$e_\w+$/', $arg) ? "\$row = $arg->one()" : $arg);
            } elseif (end(Jet::$empty) == $cnt) {
                array_pop(Jet::$empty);
                array_pop(Jet::$if);
                return '<?php endif ?>';
            }
            array_pop(Jet::$if);
            return sprintf('<?php %s++; end%s ?>', $iv, array_pop($this->loop));
        }
        $iv = $cnt ? '$_' . (1 + $cnt) : '$_';
        if ('' === $arg) { # do-while
            $this->loop[] = 'do';
            return "<?php $iv = 0; do { ?>";
        }
        array_push(Jet::$if, 0);
        $is_for = $is_foreach = false;
        foreach (token_get_all("<?php $arg") as $t) {
            $is_for |= is_string($t) && ';' == $t;
            $is_foreach |= is_array($t) && T_AS == $t[0];
        }
        if (!$is_foreach && '$e_' == substr($arg, 0, 3)) {
            $is_foreach = true;
            $arg .= ' as $row'; # eVar style cycle
        }
        $this->loop[] = ($loop = $is_foreach ? 'foreach' : ($is_for ? 'for' : 'while'));
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
            if ('_' === $this->body)
                return $this->parsed[Jet::$id] .= '<?php echo $sky->ob ?>';
            $tpl = $this->body;
        } elseif (DEV && 'r_' == substr($tpl, 0, 2)) { # red label
            $red = '<?php if ($sky->s_red_label): ?>' . tag("@inc($tpl)", 'class="red_label"');
            $this->parsed[Jet::$id] .= $red . '<?php else: ?>';
            $red = '<?php endif ?>';
        }

        $this->test_cycled($tpl, '@inc');
        $jet = new Jet($tpl);
        $this->files += $jet->files;
        $this->parsed[++Jet::$id] = $jet->parsed;
        if (count($this->loop))
            $this->correct($this->parsed[Jet::$id]);
        $this->parsed[++Jet::$id] = $red;
    }

    private function _block($arg, &$str, $is_block) {
        $regexp = '/^(.+?) as ([a-z][ \*]|)(\w+)$/';
        $get_id = function ($name) use ($is_block) {
            $id = ++Jet::$id;
            $this->parsed[$id] = [$name => $id];
            Jet::$ptr[$id] = [$is_block, &$this->parsed[$id], Jet::$depth, count($this->loop), count(Jet::$if), 0];
            $this->parsed[++Jet::$id] = '';
            return $id;
        };
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
            Jet::$block[$name][0] = $get_id($name);
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
            if ($type) {
                $str = substr($str, 0, $pos - 4) . substr($str, $pos + strlen($br));
            } else {
                $id = $get_id($name);
            }
        }
        if ('`' == $tpl[0]) {
            $tpl = [substr($tpl, 1, -1), $this->marker];
        } else {
            $this->test_cycled($tpl, $is_block ? '@block' : ($type ? '#use' : '@use'));
        }
        Jet::$depth[] = 1;
        $jet = new Jet($tpl);
        $jet->pf = $pf;
        $this->parsed[++Jet::$id] = '';
        array_pop(Jet::$depth);

        if ($type) { // #use(..) or @block(..)
            Jet::$block[$name] = [Jet::$block[$name][0] ?? 0, $jet];
        } else { // @use(..)
            Jet::$use[$name][] = [$id, $jet];
        }

        return 1 == $type ? $pos - 4 : '';
    }
}
