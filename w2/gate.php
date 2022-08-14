<?php

# For Licence and Disclaimer of this code, see https://coresky.net/license

class Gate
{
    const HAS_T3     = 1;
    const RAW_INPUT  = 2;
    const AUTHORIZED = 16;
    const OBJ_ADDR   = 32;
    const OBJ_PFS    = 64;

    static $cshow = 0;
    static $ware = 'main';
    static $controller_data = false; # not loaded initialy

    public $url = '';

    function __construct() {
        global $sky;
        $sky->memory();
    }

    static function instance() {
        static $me;
        return $me ?: ($me = new Gate);
    }

    static function load_array($class = false) {
        $sky_gate = (array)Plan::_rq([Gate::$ware, 'gate.php']);
        return $class ? ($sky_gate[$class] ?? []) : $sky_gate;
    }

    static function controllers($in = false) {
        $glob = Plan::_b([Gate::$ware, 'mvc/c_*.php']);
        if ($fn = Plan::_t('mvc/default_c.php'))
            array_unshift($glob, $fn);
        $list = $deleted = [];
        $val = $in ? 1 : Plan::$ware;
        foreach ($glob as $v) {
            $v = basename($v, '.php');
            $list['default_c' == $v ? '*' : substr($v, 2)] = $val; //2do just Plan::$ware?
        };
        foreach (Gate::load_array() as $k => $v) {
            $k = 'default_c' == $k ? '*' : substr($k, 2);
            isset($list[$k]) or $deleted[$k] = 0;
        }
        return !$in ? $list : $list + $deleted;
    }

    static function put_cache($class, $fn_src, $fn_dst) {
        if (false === Gate::$controller_data) {
            $sky_gate = Gate::load_array();
            Gate::$controller_data = $sky_gate[$class] ?? [];
        }
        Plan::gate_p($fn_dst, Gate::instance()->parse($fn_src, $class));
    }

    function view_code($ary, $class, $func) {
        $this->url = '';
        $cmode = $this->contr_mode($class, $func);
        $code = highlight_string("<?\n" . $this->code($ary, $cmode), true);
        $code = substr_replace($code, '', strpos($code, '&lt;?'), 5);
        return substr_replace($code, '', strpos($code, '<br />'), 6);
    }

    function argc($s) {
        $this->arg_c = 0;
        $s = preg_replace("/\s+/", '', $s);
        if ('()' == $s || '' === $s)
            return;
        $this->arg_c++;
        foreach (token_get_all("<?php $s") as $v)
            if (is_string($v) && ',' == $v)
                $this->arg_c++;
    }

    function parse($src_fn, $dst_class = false) {
        Plan::_r([Gate::$ware, $src_fn]);
        $reflect = new ReflectionClass(basename($src_fn, '.php'));
        $methods = array_filter(
            $reflect->getMethods(ReflectionMethod::IS_PUBLIC),
            function ($v) {
                return in_array(substr($v->name, 0, 2), ['j_', 'a_'])
                    || in_array(substr($v->name, -2), ['_j', '_a']);
            }
        );
        $list = [];
        foreach ($methods as $method) {
            $list[$method->name] = '(' . implode(', ', array_map(function ($v) {
                return '$' . $v->name;
            }, $reflect->getMethod($method->name)->getParameters())) . ')';
        }
        if (!$dst_class)
            return $list;

        $gape = DEV ? "trace('GATE: $dst_class, ' . (\$recompile ? 'recompiled' : 'used cached'));\n\n" : '';
        $gape .= "class Gape extends Bolt\n{";

        foreach ($list as $name => $pars) {
            $this->argc($pars);
            isset(Gate::$controller_data[$name]) or Gate::$controller_data[$name] = [];
            $cmode = $this->contr_mode($dst_class, $name);
            $php = $this->code(Gate::$controller_data[$name], $cmode, false);
            $php = "\t\t" . str_replace("\n", "\n\t\t", substr($php, 0, -1)) . "\n";
            $gape .= "\n\tfunction $name(\$sky, \$user) {\n$php\t}\n";
        }

        $content = Plan::_g([Gate::$ware, $src_fn]);
        if (!preg_match("/^<\?(php)?(.*?)class $dst_class extends(.+)$/s", $content, $match))
            throw new Error("File `$src_fn` must start from &lt;?");
        return '<?php' . $match[2] . "$gape}\n\nclass {$dst_class}_cached extends" . $match[3];
    }

    function contr_mode($class, $func) {
        $this->_j = in_array($func, ['empty_j', 'default_j']) || 'j_' == substr($func, 0, 2);
        $default = in_array($func, ['default_a', 'default_j']);
        if (!$this->_j && !$default && 'a_' != substr($func, 0, 2) && 'empty_a' !== $func)
            return false; # used in parser
        if ('default_c' == $class && $default)
            return [1, false, false];
        if ('default_c' != $class && !$default)
            return [3, substr($class, 2), 'e' == $func[0] ? '' : substr($func, 2)];
        return [2, $default ? substr($class, 2) : substr($func, 2), $default];
    }

    function code($ary, $cmode, $is_view = true) {
        global $sky;
        list ($flag, $meth, $addr, $pfs) = $ary + [0, [], [], []];
        if ($this->_j)
            $meth = [0];
        if (!$meth && 'main' == $cmode[1] && '' === $cmode[2]) # for main page
            $meth = [1];
        $this->_e = !DEV || $is_view && $sky->n_sg_prod ? 'die;' : 'e();';
        $errors = $s0 = '';
        if (!$cnt_meth = count($meth))
            $errors .= "$this->_e # no HTTP methods defined\n";
        $eq0 = 1 == $cnt_meth
            ? ["$meth[0] == \$sky->method"]
            : ['in_array($sky->method, [' . implode(', ', $meth) . '])'];
        if (Gate::AUTHORIZED & $flag)
            $eq0[] = '$user->auth';
        $is_post = in_array(0, $meth);
        if ($this->pfs_c = count($pfs))
            $is_post or $errors .= "$this->_e # no POST HTTP method selected\n";
        $this->opcnt = [0, 0, 0]; # optimize count of surl GET POST
        $this->pfs_ends = $this->eq_a = $this->eq_b = $this->eq_z = [];
        $php = $this->process_addr($addr, $flag, $cmode, $eq0);
        if ($is_post)
            $php .= $this->process_pfs($pfs, $flag, $cnt_meth, $eq0);
        if ($this->ends || $this->pfs_ends) {
            $php .= $this->ends ? "return [" . implode(', ', $this->ends) . "]" : 'return $post';
            if ($this->pfs_ends && $this->ends)
                $php .= ' + $post';
            $php .= ";\n";
            $this->ends = array_merge($this->pfs_ends, $this->ends);
        }
        if ($this->arg_c != count($this->ends))
            $errors .= "$this->_e # parameters counts doesn't match\n";
        $cx = ['s', 'g', 'p'];
        $cx2 = ['sky->surl', '_GET', '_POST'];
        $cnt = count($eq0) - 1;
        foreach ($eq0 as $k => &$v) { # set count vars
            $match = preg_match('/count\(\$(_|s)(k|G|P)\S+\) > (\d+)/', $v, $m);
            if (!$match && $k < $cnt)
                continue;
            if ($match) {
                $i = '_' != $m[1] ? 0 : ('G' == $m[2] ? 1 : 2);
                if ($this->opcnt[$i]) {
                    $v = '$cnt_' . $cx[$i] . " > $m[3]";
                    $this->opcnt[$i] = 0;
                    $s0 .= '$cnt_' . $cx[$i] . ' = count($' . $cx2[$i] . ");\n";
                } elseif ($k < $cnt)
                    continue;
            }
            if ($k == $cnt) { # last step
                foreach ($this->opcnt as $i => $f)
                    !$f or $s0 .= '$cnt_' . $cx[$i] . ' = count($' . $cx2[$i] . ");\n";
            }
        }
        if ($this->eq_a)
            $php = implode(' = ', $this->eq_a) . " = [];\n$php";
        if ($this->eq_b)
            $php = implode(' = ', $this->eq_b) . " = false;\n$php";
        if ($this->eq_z)
            $php = implode(' = ', $this->eq_z) . " = 0;\n$php";
        return $s0 . implode(' && ', $eq0) . " or $this->_e\n" . $php . $errors;
    }

    function comp_ary($opcnt, $sz, $s) {
        $ary = ['s' => '$sky->surl', 'g' => '$_GET', 'p' => '$_POST'];
        if (-1 == $sz)
            return "!$ary[$s]";
        if ($opcnt)
            return $sz ? "\$cnt_$s > $sz" : "\$cnt_$s";
        return $sz ? "count($ary[$s]) > $sz" : $ary[$s];
    }

    function span($in, $c, $ns = 0) {
        if (2 == $ns)
            return tag($in, 'style="font-weight:bold;color:' . $c . '"', 'span');
        return tag($in, 'style="background:' . $c . ($ns ? ';border-bottom:2px solid red' : '') . '"', 'span');
    }

    function process_addr($addr, $flag, $cmode, &$eq0) {
        global $sky;
        list($i, $p0, $p1) = $cmode;
        $this->i = $i;

        $ctrl = $p0 ? $this->span($p0, 2 == $i && !$p1 ? 'green' : 'blue', 2) : '';
        $act = '' === $p1 ? '' : $this->span($p1, $this->_j ? 'red' : 'green', 2);
        $this->url = $sky->n_sg_prod || !defined('PATH') ? _PUBLIC . '/' : SNAME . PATH;
        $this->ends = [];
        $php = $this->ra = $this->ns = '';
        $this->raw_input = $this->sz_surl = $this->sz_ary = 0;
        $t3 = Gate::HAS_T3 & $flag;
        $this->start = $this->_j ? 1 : 0;

        if ($this->cnt_ary = $this->addr_c = count($addr)) {
            $this->current = $addr;
            foreach ($addr as $v)
                $v[4] or '' === $v[1] ? $this->sz_surl++ : $this->sz_ary++;
            $this->ary_as_object = Gate::OBJ_ADDR & $flag;
            foreach ($addr as $pos => $v) {
                $skip = 0;
                $key = isset($v[1]) ? $v[1] : '';
                $b0 = 0;
                if (!$pos) { # first row
                    if ('' === $key) {
                        $this->sz_surl += $i - 1;
                        $this->url .= ($p0 ? $ctrl : '') . ('' === $p1 || is_bool($p1) ? '' : "/$act");
                        if ($v[3] === $p1) {
                            $skip = 2;
                            $this->i--;
                            $this->sz_surl--;
                        }
                    } elseif ($key === $p1) { # $i == 3
                        $this->sz_surl += 1;
                        $this->url .= "$ctrl?$act";
                        $skip = 1;
                    } elseif ($key === $p0 && 2 == $i) {
                        $this->url .= $this->_j ? "?AJAX=$ctrl&$ctrl=" : "?$ctrl=";
                        $skip = 1;
                    } elseif (3 == $i) {
                        $t3 ? ($this->sz_surl = 2) : ($this->sz_ary += 1);
                        $this->url .= $this->_j ? "?AJAX=$ctrl&$ctrl=$act" : ($t3 ? "$ctrl/$act" : "?$ctrl=$act");
                        $b0 = $this->_j || !$t3;
                    } elseif (2 == $i) {
                        $this->sz_surl = 1;
                        $this->url .= $ctrl;
                    }
                }
                if (!$this->start && '' !== $key) {
                    $this->start = $this->i;
                    if ($b0)
                        $this->start -= 2;
                    $this->ns = '';
                }
                $php .= $this->each_row($pos, $v, false, $skip);
            }
        } elseif (1 == $i) {
            $this->url .= $this->_j ? "?AJAX=" : '';
        } elseif ('main' !== $p0 || '' !== $p1) {
            $this->url .= $this->_j ? "?AJAX=$ctrl&$ctrl=" : ($t3 ? $ctrl : "?$ctrl");
            if (3 == $i && '' !== $p1)
                $this->url .= $this->_j ? $act : ($t3 ? "?$act" : "=$act");
            $this->sz_surl = $this->_j || !$t3 ? 0 : 1;
            $this->sz_ary = $this->_j || !$t3 || 3 == $i && '' !== $p1 ? 1 : 0;
        }
        if (!$this->_j && $this->sz_surl - $i)
            $eq0[] = $this->comp_ary($this->opcnt[0], $this->sz_surl - 1, 's');
        if (1 == $this->sz_ary && 3 == $i && !$t3)
            $this->sz_ary = false;
        if ($this->sz_ary)
            $eq0[] = $this->comp_ary($this->opcnt[1], $this->sz_ary - 1, 'g');
        return $php;
    }

    function process_pfs($pfs, $flag, $cnt_meth, &$eq0) {
        if ($cnt_meth > 1) {
            $start = count($this->ends);
            $tmp = $this->ends;
            $this->ends = [];
        }
        $php = $this->ra = $this->ns = '';
        if ($this->raw_input = Gate::RAW_INPUT & $flag)
            $php .= "\$input = file_get_contents('php://input');\n";
        if ($this->cnt_ary = $this->pfs_c) {
            $this->sz_ary = 0;
            $this->i = $this->start = 1;
            $this->current = $pfs;
            if (!$this->raw_input)
                foreach ($pfs as $v)
                    $v[4] or $this->sz_ary++;
            $this->ary_as_object = Gate::OBJ_PFS & $flag;
            foreach ($pfs as $pos => $v)
                $php .= $this->each_row($pos, $v, true);
            if ($this->sz_ary) {
                $exp = $this->comp_ary($this->opcnt[2], $this->sz_ary - 1, 'p');
                1 == $cnt_meth ? ($eq0[] = $exp) : $php = "$exp or $this->_e\n$php";
            }
        } elseif ($this->raw_input) {
            $php .= "$this->_e # no BODY fields selected\n";
        }
        if ($cnt_meth > 1) {
            if ($this->pfs_ends = $this->ends) {
                $earg = implode(', ', array_pad(["false"], count($this->ends), "false"));
                $start = $start ? "$start => " : '';
                $php = "if (0 == \$sky->method) {\n$php\$post = [$start" . implode(', ', $this->ends) . "];";
                $php = "\$post = [$start$earg];\n" . str_replace("\n", "\n\t", $php) . "\n}\n";
            }
            $this->ends = $tmp;
        }
        return $php;
    }

    function q_required($pos) {
        foreach ($this->current as $i => $v) {
            if ($i > $pos) {
                if (!$this->start)
                    return '' == $v[1];
                if (!preg_match("/^\w*$/", $v[1])) # key the re
                    return true;
            }
        }
        return false;
    }

    function comp_ns($int, $qreq = false, $name = false) {
        $s = $int ? "$int$this->ns" : substr($this->ns, 3);
        if (!$name) # $_GET $_POST or $sky->surl
            return $s;
        if ($this->raw_input)
            return '';
        $int += substr_count($this->ns, '+');
        $sz = '_' == $name[1] ? $this->sz_ary : $this->sz_surl;
        if ($int >= $sz) {
            $ary = ['s' => '$sky->surl', 'g' => '$_GET', 'p' => '$_POST'];
            $n = array_keys($ary)[$k = '_' != $name[1] ? 0 : ('G' == $name[2] ? 1 : 2)];
            if (!$this->opcnt[$k] && !$qreq && !$sz)
                return $s ? "count($ary[$n]) > $s" : $ary[$n];
            $this->opcnt[$k] = 1;
            return $s ? "\$cnt_$n > $s" : "\$cnt_$n";
        }
        return '';
    }

    function each_row($pos, $val, $is_pfs, $skip = 0) {
        list ($kname, $key, $vname, $val, $ns) = $val + ['', '', '', '', 0];
        $this->key = $php = '';
        $qreq = $this->raw_input ? false : $ns && $this->q_required($pos);
        $pos++;
        $row = [];
        if ('' === $key) { # key is empty string
            if ($is_pfs ? !$this->raw_input : $this->start)
                $php .= "$this->_e # required key on $pos " . ($is_pfs ? 'postfield' : 'address') . " block\n";
        } else {
            $skip or $row[] = $this->bless($kname, $key, $is_pfs, $ns, $qreq, 0);
            $this->i++;
        }
        2 == $skip or $row[] = $this->bless($vname, $val, $is_pfs, $ns, $qreq, 1, $skip);
        $this->i++;
        $php .= implode($ns ? '' : ' && ', $row);
        if ($this->cnt_ary == $pos && $this->ra) { # last step + has ->ra
            $this->ra = "[\n$this->ra" . "]";
            if ($this->ary_as_object)
                $this->ra = "(object)$this->ra";
            if ($is_pfs || !$this->pfs_c) {
                $this->ends[] = $this->ra;
            } else {
                $this->ends[] = $var = $is_pfs ? '$post' : '$get';
                $php .= "$var = $this->ra;\n";
            }
        }
        return $php;
    }

    function get_src($is_pfs, $re, $df, &$ary, $skip) {
        if ($is_pfs) {
            $ary = '$_POST';
            if ($this->raw_input)
                return ['$input', 0];
        } else {
            $ary = '$_GET';
            if ($this->i - $skip <= 3 && !$this->ns)
                return ['$sky->_' . ($this->i - $skip - 1), 3];
            if (!$this->start)
                return ['$sky->surl[' . $this->comp_ns($this->i - 1 - $df) . ']', 0];
        }
        if (!$k = $this->i - $this->start)
            return ["key($ary)", 2];
        if ($k % 2) # values
            return [$ary . "[$this->key]", 0];
        if ($re) # for keys only
            return ["key(array_slice($ary, " . $this->comp_ns(floor($k / 2) - $df) . ", 1, true))", 1];
        return [false, 0]; # keys for isset
    }

    function bless($name, $data, $is_pfs, $ns, $qreq, $is_val, $skip = 0) {
        if ($rb = strpos($data, ')')) # round brackets
            '(' != $data[0] or 1 + $rb != strlen($data) or $rb = false;
        $sb = $rb || $is_val ? false : '[]' == substr($data, -2); # square brackets
        $re = $rb || !$sb && !preg_match("/^\w*$/", $data);
        if ($sb)
            $data = substr($data, 0, -2);
        $df = substr_count($this->ns, '+');
        list($src, $var) = $this->get_src($is_pfs, $re, $df, $ary, $skip);
        $x = $this->i + ($is_pfs ? 2 + 2 * $this->addr_c : 0);
        $out = $comp = $e5 = $e7 = '';
        $e2 = 2 == $var; # key($_ARY)
        $e3 = $e2 || 3 === $var; # $sky->_..
        static $b2, $b3, $b4, $b5;
        if ($is_val) {
            if ($b5)
                $rb = false;
            $nsd = $this->i - 1 - $df;
            if (!$this->start && $df && ($comp = $this->comp_ns($nsd, $qreq, '$sky->surl'))) {
                $out .= $ns ? "if ($comp)\n\t" : "$comp or $this->_e\n";
            }
            if ($e5 = !$this->start && $qreq) {
                $out .= "\$q$x = (int)";
                $this->ns .= " + \$q$x";
            }
            $e7 = !$this->key || "'" == $this->key[0];
            $rule = $this->key && ($re || "'" == $this->key[0]) || $comp;
        } else { # key
            $b2 = $b3 = '';
            $b4 = $re;
            $b5 = $sb;
            $nsd = floor(($this->i - $this->start) / 2) - $df;
            if ($comp = $e2 || !$e3 && $re ? $this->comp_ns($nsd, $qreq, $ary) : '') {
                $e3 or $out .= $ns ? ($b3 = "if ($comp) {\n") : "$comp or $this->_e\n";
            }
            if (1 == $var) { # array_slice
                if ($b3)
                    $out .= "\t";
                $out .= "\$k$x = $src;\n";
                $var = $src = "\$k$x";
                $this->key = $ns && !$rb ? "\$v$x = $var" : $var;
            } else {
                $this->key = !$re ? "'$data'" : ($rb || !$ns ? '' : "\$v$x = ") . ($e2 ? '$tmp' : $src);
            }
            if ($ns) {
                $this->ns .= " + \$q$x";
                if ($this->raw_input && !$rb && $re)
                    $b2 = "\$v$x = ";
                if ($b3)
                    $out .= "\t";
                $out .= 'if (' . ($qreq ? "\$q$x = (int)" : '');
                if ($e3)
                    $out .= ($qreq && ($comp || !$re) ? "($comp" : $comp) . ($comp ? ' && ' : '');
            }
            $rule = $re && (!$rb || $comp || $e2);
        }
        if ($re) {
            $pre = $e2 ? '$tmp = ' : ($ns && !$rb && $is_val ? "\$v$x = " : '');
            if ($b2 && $is_val)
                $pre .= $b2;
            $func = $b5 ? 'array_match' : 'preg_match';
            $b5 = false;
            $out .= "$func('/^$data$/', $pre$src" . ($rb ? ", \$v$x" : '') . ")";
            if ($ns) {
                $out .= $this->start ? '' : ($rb ? ';' : " or \$v$x = false;");
                $is_val or $out .= ($e2 && $qreq && ($comp || !$re)  ? '))' : ')') . ($comp && !$e3 ? "\n\t\t" : "\n\t");
            }
            $rb ? ($var = "end(\$v$x)") : ($ns ? ($var = "\$v$x") : (is_string($var) or $var = $src));
            $name ? ($this->ra .= "\t'$name' => $var,\n") : ($this->ends[] = $var);
            $data = $name ? "<i>$name</i>=&gt;" : $var;
        } elseif (!$src) { # key only
            $out .= "isset({$ary}['$data'])" . ($ns ? ")\n\t" : '');
        } elseif ($ns) {
            $var = "\$v$x";
            $src = $b2 . $src;
            $out .= $e7
                ? ($e5 ? '(' : '') . "'$data' === ($var = $src)" . ($e5 ? ')' : '') . ($this->key ? '' : " or $var = false;")
                : "'$data' === $src" . ($qreq ? ')' : '');
            $is_val or $out .= $this->start ? ")\n\t" : ";";
            if ($is_val && !$b4)
                $name ? ($this->ra .= "\t'$name' => $var,\n") : ($this->ends[] = $var);
        } else {
            $out .= "'$data' === $src";
        }
        if ($is_val) {
            $out .= $ns && !$this->key ? "\n" : " or $this->_e\n";
            if ($b3)
                $out .= "}\n";
        }
        if ($ns && $rule)
            $rb ? ($this->eq_a[] = "\$v$x") : ($this->eq_b[] = "\$v$x");
        if ($qreq && $comp && !$e3)
            $this->eq_z[] = "\$q$x";
        if ($is_pfs)
            return $out;
        if ($this->start) {
            $q = $is_val
                ? ('' === $data ? '' : ($ns ? tag('=', 'style="border-bottom:2px solid red"', 'span') : '='))
                : ($this->i == $this->start ? '?' : '&');
            $this->url .= $re || $ns && $e7
                ? $q . $this->span($data, $is_val ? 'pink' : '#0f0', $ns)
                : $q . ($ns ? tag($data, 'style="border-bottom:2px solid red"', 'span') : $data);
        } else {
            $this->url .= (1 == $this->i ? '' : '/') . ($ns || $re ? $this->span($data, 'silver', $ns) : $data);
        }
        return $out;
    }
}
