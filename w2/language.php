<?php

class Language
{
    public $list;
    public $error;
    public $lg; # selected
    public $sql;
    public $all = false;
    public $nsort = 0;
    public $nsync = 0;
    public $pages = ['*' => '* common'];
    public $page = '*';

    const NON_SORT = 0x8000; # bit no 16
    const NON_SYNC = 0x10000; # bit no 17

    private $t;

    static function translate($ary) {
        if (!DEV)
            return;
        $me = new Language; # format: "ID CONST VAL"
        if ($me->error)
            throw new Error("class Language error=$me->error");
        trace($ary, 'Collected new translations');
        $where = qp('lg=$+ and name=$+', DEFAULT_LG, $me->page = SKY::$reg['lg_page'] ?: '*');
        $next_id = 0x7FFF & $me->t->cell($where, 'flag', true);
        $new = array_join($ary, function($k) use (&$next_id) {
            return $next_id++ . '  ' . escape($k);
        });
        // qp('$cc($+, tmemo)', "$new\n") not work !
        $me->t->update(['$tmemo' => qp(qp('$cc($+, tmemo)'), "$new\n")], qp('name=$+', $me->page));
        $me->t->update(['.flag' => $next_id | self::NON_SORT]);
        $me->c_sync(false, false); # without sorting
    }

    static function names() {
        return unserialize(trim(view('_lng.array', [])));
    }

    function __construct() {
        global $sky;
        MVC::$cc->setLG_h();

        if (!$sky->d_lgt && '_lang?list' == URI)
            $sky->d_lgt = '_lgt';

        if (!$sky->d_lgt) {
            $this->error = 1;
        } elseif (!$this->list = $sky->lg) {
            $this->error = 2;
        } elseif (!DEFAULT_LG || !in_array(DEFAULT_LG, $this->list)) {
            $this->error = 3;
        }
        $this->t = MVC::$cc->{"t_$sky->d_lgt"};
        $this->page = $sky->d_lng_page ?: '*';
    }

    private function make_sql($page = '*') {
        if ($this->error)
            return true;
        global $sky;
        $ins = qp('insert into $_`', $sky->d_lgt);
        if (!SKY::$dd->_tables($sky->d_lgt) || '*' != $page) {
            $this->sql = array_join($this->list, function($k, $v) use ($ins, $page) {
                $flag = $v == DEFAULT_LG ? 1 + self::NON_SYNC : 0;
                return qp('$$ values(null, $+, $+, $., "", $now);', $ins, $v, $page, $flag);
            });
            return $this->error = 4;
        }
        $list = $this->t->all(qp('name="*"'), 'lg');
        $this->sql = '';
        if ($diff = array_diff($list, $this->list))
            $this->sql .= qp('delete from $_` where lg in ($@);', $sky->d_lgt, $diff);
        if ($diff = array_diff($this->list, $list)) {
            $this->sql .= "\n" . array_join($diff, function($k, $v) use ($ins, $sky) {
                $tpl = '$$ select null, $+, name, 1, tmemo, $now from $_` where lg=$+;';
                return qp($tpl, $ins, $v, $sky->d_lgt, DEFAULT_LG);
            });
        }
        return $this->sql ? ($this->error = 5) : false;
    }

    function c_mode($mode) {
        echo SKY::d('trans', $mode);
    }

    function c_list($lg) {
        if ($a = $_POST['a'] ?? false) {
            if ('-' == $a) { # drop page
                $this->t->delete(qp('"*"<>name and name=$+', $this->page));
                $_POST['page'] = '*';
            } elseif ('+' == $a) { # show all pages
                $lg = $this->all = DEFAULT_LG;
            } elseif ($a) {
                if ('!' == $a[0]) { # exec SQLs from POST
                    $this->multi_sql(substr($a, 1));
                } else { # add page
                    $this->make_sql($_POST['page'] = $a);
                    $this->multi_sql($this->sql);
                }
                $this->error = 0;
            }
        }
        if ($page = $_POST['page'] ?? false)
            SKY::d('lng_page', $this->page = $page);
        return [
            'obj' => $this,
            'e_list' => !$lg && $this->make_sql() ? [] : $this->listing($this->lg = $lg ?: DEFAULT_LG),
            'lg_names' => Language::names(),
        ];
    }

    function c_edit($lg) {
        if (2 == count($id = explode('.', $_POST['id'])))
            list ($this->page, $id) = $id;
        $ary = $this->act($lg, $id);
        return ['val' => escape($ary[2], true)];
    }

    function c_const($lg) {
        if (2 == count($id = explode('.', $_POST['id'])))
            list ($this->page, $id) = $id;
        $ary = $this->act($lg, $id);
        return ['val' => escape($ary[1], true)];
    }

    function c_all($in) {
        if (2 == count($id = explode('.', $in)))
            list ($this->page, $id) = $id;
        return [
            'id' => $in,
            'e_list' => ['row_c' => function() use ($id) {
                if (false === ($lg = current($this->list)))
                    return false;
                list (,$const, $val) = $id ? $this->act($lg, $id) : [0, '', ''];
                next($this->list);
                return [
                    'lg' => $lg,
                    'const' => $const,
                    'val' => escape($val, true),
                ];
            }],
        ];
    }

    function c_store($id) {
        $const = strtoupper($_POST['const']);
        $new = !$id; # $id pass by reference!
        if ($this->act(DEFAULT_LG, $id, 'all', [$const, escape($_POST[DEFAULT_LG]), $new], true))
            return [];
        foreach ($this->list as $lg)
            $lg == DEFAULT_LG or $this->act($lg, $id, 'all', [$const, escape($_POST[$lg]), $new]);
        return $this->c_list(DEFAULT_LG);
    }

    private function multi_sql($sql) {
        foreach (explode(';', $sql) as $sql) {
            if ($sql = trim($sql))
                sql($sql);
        }
    }

    function c_fix($lg) {
        if (isset($_POST['sql'])) {
            $this->multi_sql($_POST['sql']);
        } elseif (isset($_POST['save'])) {
            if ('~' != $_POST['const-prev']) {
                $const = strtoupper($_POST['save']);
                if ($this->act(DEFAULT_LG, $_POST['id'], 'const', $const, true))
                    return [];
                foreach ($this->list as $v)
                    $v == DEFAULT_LG or $this->act($v, $_POST['id'], 'const', $const);
            } else {
                $this->act($lg, $_POST['id'], 'text', escape($_POST['save']));
            }
        } else foreach ($this->list as $v) { # delete item
            $this->act($v, $_POST['delete'], 'delete');
        }
        return $this->c_list($lg);
    }

    function c_sync($lg, $is_user = true) {
        if ($lg) {
            $list = $this->t->all(qp('name<>"*" group by name'), 'name');
            $tmp = $this->page;
            foreach ([-1 => '*'] + $list as $page) {
                $this->page = $page;
                $this->c_sync(false, $is_user);
            }
            $this->page = $tmp;
            return $this->c_list($lg);
        }
       # static $dary;
        #if (is_null($dary))
            $dary = $this->load(DEFAULT_LG, $is_user, $is_user);
        $page = '*' === $this->page ? '' : "_$this->page";
        foreach ($this->list as $lg) {
            $out = [];
            $ary = $lg == DEFAULT_LG ? [] : $this->load($lg);
            $code = '';
            foreach ($dary as $k => $one) {
                list ($const, $val) = explode(' ', $one, 2);
                $val2 = $ary ? explode(' ', $ary[$k], 2)[1] : 0;
                if ('' === $const) {
                    $out[$val] = $val2;
                } else {
                    $code .= sprintf("const L_$const=%s;\n", var_export($ary ? $val2 : $val, true));
                }
            }
            if (!$page) {
                $fp = fopen(__FILE__, 'r');
                fseek($fp, __COMPILER_HALT_OFFSET__);
                $code .= stream_get_contents($fp);
                fclose($fp);
            }
            Plan::_p("lng/$lg$page.php", DEV::auto($out, $code));
        }
    }

    function c_parse() {
        $app = Plan::_obj(['main'])->path;
        $const = $tfunc = [];
        foreach (Rare::walk_dirs($app) as $dir) {
            if ("$app/lng" == $dir)
                continue;
            foreach (Rare::list_path($dir, 'is_file') as $fn) {
                $ext = pathinfo($fn)['extension'] ?? '';
                if ('php' != $ext)
                    continue;
                $php = file_get_contents($fn);
                $fun = 0;
                foreach (token_get_all($php) as $v) {
                    list($id, $v) = is_array($v) ? $v : [0, $v];
                    if (T_WHITESPACE == $id)
                        continue;
                    if (T_STRING == $id) {
                        $fun = 't' == $v ? 1 : 0;
                        if (!$fun && preg_match('/^L_([A-Z_\d]+)$/', $v, $m))
                            $const[$m[1]][] = basename($fn);
                    } elseif (1 === $fun && !$id && '(' == $v) {
                        $fun = 2;
                    } elseif (2 === $fun && T_CONSTANT_ENCAPSED_STRING == $id) {
                        $fun = $v;
                    } elseif (is_string($fun) && !$id && in_array($v, [',', ')'])) {
                        $tfunc[eval("return $fun;")][] = basename($fn);
                        $fun = 0;
                    } else {
                        $fun = 0;
                    }
                }
            }
        }
        $php = function ($s) {
            if (!in_array($s[0], ["'", '"']))
                return $s;
            foreach (token_get_all("<?php $s") as $v) {
                if (is_array($v) && T_CONSTANT_ENCAPSED_STRING == $v[0])
                    return eval("return $v[1];");
            }
        };
        $view = Plan::view_obj(['main'])->path;
        foreach (Rare::list_path($view, 'is_file') as $fn) {
            $ext = pathinfo($fn)['extension'] ?? '';
            if ('jet' != $ext)
                continue;
            // 2do: rewrite jet parser
            $jet = file_get_contents($fn);
            if (preg_match_all('/L_([A-Z_\d]+)/', $jet, $m)) {
                foreach ($m[1] as $c)
                    $const[$c][] = basename($fn);
            }
            if (preg_match_all('/@t\(([^\)]+)\)/', $jet, $m)) {
                foreach ($m[1] as $c)
                    $tfunc[$php($c)][] = basename($fn);
            }
        }
        $map = function ($a) {
            $s = 1 == ($n = count($a)) ? '' : 's';
            $u = count($a = array_unique($a)) - 1;
            return "$n use$s in " . ($u < 2 ? implode(' & ', $a) : "$a[0] + $u files");
        };
        json([
            'const' => array_map($map, $const),
            'tfunc' => array_map($map, $tfunc),
        ]);
    }

    private function act($lg, &$id, $mode = 'read', $s = '', $test = false) {
        $row = $this->t->one(qp('lg=$+ and name=$+', $lg, $this->page));
        $mem = trim($row['tmemo'], "\n");
        $new = false;
        if (is_array($s))
            list($s, $val, $new) = $s;
        if (!$new) {
            $pos = strpos($mem, "$id ");
            if (0 !== $pos && "\n" != $mem[$pos - 1])
                $pos = 1 + strpos($mem, "\n$id ");
            $end = strpos($mem, "\n", $pos + 2);
        }
        $upd_flag = ['.flag' => $row['flag'] | self::NON_SYNC];
        switch ($mode) {
            case 'read':
                $mem = $end ? substr($mem, $pos, $end - $pos) : substr($mem, $pos);
                return explode(' ', $mem, 3);
            case 'delete':
                $mem = $end ? substr_replace($mem, '', $pos, 1 + $end - $pos) : substr_replace($mem, '', $pos);
                break;
            case 'text':
                $pos = 1 + strpos($mem, ' ', 1 + strpos($mem, ' ', $pos));
                $mem = $end ? substr_replace($mem, $s, $pos, $end - $pos) : substr_replace($mem, $s, $pos);
                break;
            case 'const':
            case 'all':
                if ('' !== $s && $test && $_POST['const-prev'] != $s) {
                    $ary = SKY::ghost('k', $mem);
                    foreach ($ary as $v) {
                        if (explode(' ', $v, 2)[0] === $s) {
                            echo 1; # non unique
                            return true;
                        }
                    }
                }
                if (!$id) {
                    $id = 0x7FFF & $row['flag']; # max 32767 items
                    $upd_flag = ['.flag' => ($id + 1) | self::NON_SORT | self::NON_SYNC];
                }
                if ($new) {
                    $mem = $mem ? "$id $s $val\n$mem" : "$id $s $val";
                } elseif ('all' == $mode) {
                    $s = "$id $s $val";
                    $mem = $end ? substr_replace($mem, $s, $pos, $end - $pos) : substr_replace($mem, $s, $pos);
                } else {
                    $pos = 1 + strpos($mem, ' ', $pos);
                    $mem = substr_replace($mem, $s, $pos, strpos($mem, ' ', $pos) - $pos);
                }
        }
        $lg == DEFAULT_LG or $upd_flag = [];
        $this->t->update(['tmemo' => $mem] + $upd_flag);
        return false;
    }

    private function sort(&$ary) {
        uasort($ary, function($a, $b) {
            $a0 = ord($a = mb_strtolower(explode(' ', $a, 2)[1]));
            $b0 = ord($b = mb_strtolower(explode(' ', $b, 2)[1]));
            $a_alfa = 'en' != DEFAULT_LG ? $a0 > 0x7F : 0x60 < $a0 && $a0 < 0x7B;
            $b_alfa = 'en' != DEFAULT_LG ? $b0 > 0x7F : 0x60 < $b0 && $b0 < 0x7B;
            if (!$a_alfa && $b_alfa)
                return 1;
            if (!$b_alfa && $a_alfa)
                return -1;
            return $a === $b ? 0 : ($a < $b ? -1 : 1);
        });
    }

    private function &load($lg, $is_sort = false, $is_sync = false) {
        $row = $this->t->one(qp('lg=$+ and name=$+', $lg, $this->page));
        $flag = $row['flag'];
        if ($lg == DEFAULT_LG) {
            $this->nsort = $flag & self::NON_SORT;
            $this->nsync = $flag & self::NON_SYNC;
            $is_sync AND $flag &= ~self::NON_SYNC;
        }
        $ary =& SKY::ghost('i', trim($row['tmemo'], "\n"), false, 1);
        if ($is_sort = $is_sort && $this->nsort) {
            $this->sort($ary);
            $flag &= ~self::NON_SORT;
            $this->nsort = 0;
            $mem = ['tmemo' => SKY::sql('i')];
        }
        trace($is_sort ? 'Sorted' : 'Not sorted', "{$lg}_$this->page");
        if ($row['flag'] != $flag)
            $this->t->update(['flag' => $flag] + ($mem ?? []));
        return $ary;
    }

    private function listing($lg) {
        $list = $this->t->all(qp('name<>"*" group by name'), 'name');
        $this->pages += array_combine($list, $list);
        if ($this->all) {
            $all = $this->t->all(qp('lg=$+', DEFAULT_LG), 'name, tmemo');
            array_walk($all, function (&$v, $k) {
                if ('' !== $v)
                    $v = "$k." . str_replace("\n", "\n$k.", trim($v, "\n")) . "\n";
            });
            $dary = SKY::ghost('i', trim(implode('', $all), "\n"));
            $this->sort($dary);
        } else {
            $dary = $this->load(DEFAULT_LG, isset($_POST['sort']));
        }
        $ary = $lg == DEFAULT_LG ? [] : $this->load($lg);
        $chars = [];
        return [
            'cnt' => count($dary),
            'chars' => function() use (&$chars) {
                return ' ' . implode(' ', $chars);
            },
            'row_c' => function() use (&$dary, &$ary, &$chars) {
                static $char = '', $prev = '', $pp = '', $color = false;
                if (false === ($v = current($dary)))
                    return false;
                $id = key($dary);
                if ($this->all && !in_array($page = explode('.', $id, 2)[0], [$pp, '*'])) {
                    $pp = $page;
                    $color = 'e0e7ff' == $color ? 'bfdbfe' : 'e0e7ff';
                }
                list ($k, $v) = explode(' ', $v, 2);
                next($dary);
                $nc = '%';
                if ('%' != $char) {
                    $ord = ord($c0 = mb_strtoupper(mb_substr($v, 0, 1)));
                    if ('en' != DEFAULT_LG ? $ord > 0x7F : 0x40 < $ord && $ord < 0x5B)
                        $nc = $c0;
                }
                if ($char != $nc) {
                    34 > ($sz = count($chars)) or $nc = '%';
                    $chars[] = a($nc, "#_$sz");
                }
                return [
                    'red' => $prev === $v, // duplicated
                    'val' => $prev = $v,
                    'val2' => $v2 = $ary ? explode(' ', $ary[$id], 2)[1] : '',
                    'pink' => $ary && $v == $v2, // not translated
                    'yell' => strlen($v) < strlen($k),
                    'key' => $k,
                    'bgid' => $color && '*' != $page ? ";background:#$color" : '',
                    'id' => $id,
                    'chr' => $char != $nc ? [$char = $nc, $sz] : false,
                ];
            },
            'td' => function($v) {
                return tag($v ? 'NO' : 'YES', 'class="yn-' . ($v ? 'r' : 'g') . '"', 'td');
            },
        ];
    }
}

__halt_compiler();
function t(...$in) {
    static $n = 0;

    if ($args = (bool)$in) {
        $s = array_shift($in);
        if ($in && is_array($in[0]))
            $in = $in[0];
    } elseif ($n++ % 2) {
        $s = ob_get_clean();
    } else {
        return ob_start();
    }

    if (isset(SKY::$reg['trans_late'][$s])) {
        DEFAULT_LG == LG or $s = SKY::$reg['trans_late'][$s];
    } elseif (DEV && 1 == SKY::d('trans')) {
        SKY::$reg['trans_coll'][$s] = 0;
    }
    $args or print $s;
    return $in ? vsprintf($s, $in) : $s;
}
