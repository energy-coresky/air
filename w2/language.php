<?php

class Language
{
    public $list;
    public $names;
    public $error;  //TODO: rerr
    public $lg; # selected
    public $sql;
    public $nsort = 0;
    public $nsync = 0;

    const NON_SORT = 0x8000; # bit no 16
    const NON_SYNC = 0x10000; # bit no 17

    private $t;

    static function translate($coll) { # format: "ID CONST VAL"
        $me = new Language;
        trace($coll, 'Collected new translations');
        $next_id = 0x7FFF & $this->t->cell($where = qp('lg=$+ and name="*"', DEFAULT_LG), 'flag');
        $new = array_join($coll, function($k) use (&$next_id) {
            return $next_id++ . '  ' . escape($k);
        });
        $this->t->update(['$tmemo' => qp('$cc($+, tmemo)', "$new\n")], qp('name="*"'));
        $this->t->update(['.flag' => $next_id | self::NON_SORT], $where);
        $me->c_generate(null, false); # without sorting
    }

    static function names() {
        return unserialize(trim(view('_lng.array', [])));
    }

    function __construct() {
        global $sky;
        MVC::$cc->setLG_h();

        if (1 !== DEBUG) {
            $this->error = 1;
        } elseif (!$this->list = $sky->lg) {
            $this->error = 2;
        } elseif (!DEFAULT_LG || !in_array(DEFAULT_LG, $this->list)) {
            $this->error = 3;
        } elseif (!$sky->d_lgt) {
            $this->error = 6;
        } else {
            $this->names = Language::names();
            $this->t = MVC::$cc->{"t_$sky->d_lgt"};
        }
    }

    private function check_table() {
        if ($this->error)
            return true;
        global $sky;
        $ins = qp('insert into $_`', $sky->d_lgt);
        if (!SKY::$dd->_tables($sky->d_lgt)) {
            $this->sql = array_join($this->list, function($k, $v) use ($ins) {
                return qp('$$ values(null, $+, "*", 1, "", $now);', $ins, $v);
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

    function c_list($lg) {
        return [
            'obj' => $this,
            'e_list' => !$lg && $this->check_table() ? [] : $this->listing($this->lg = $lg ?: DEFAULT_LG),
        ];
    }

    function c_edit($lg) {
        $ary = $this->act($lg, $_POST['id']);
        return ['val' => escape($ary[2], true)];
    }

    function c_const($lg) {
        $ary = $this->act($lg, $_POST['id']);
        return ['val' => escape($ary[1], true)];
    }

    function c_all($id) {
        return [
            'id' => $id,
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

    function c_fix($lg) {
        if (isset($_POST['sql'])) {
            multi_sql(qp($_POST['sql']));/////////////////////////////////////////
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
    
    function c_generate($_, $is_user = true) {
        $dary = $this->load(DEFAULT_LG, $is_user, $is_user);
        foreach ($this->list as $lg) {
            $out = [];
            $ary = $lg == DEFAULT_LG ? [] : $this->load($lg);
            $file = "<?php\n\n# This is auto-generated file. Do not edit!\n\n";
            foreach ($dary as $k => $one) {
                list ($const, $val) = explode(' ', $one, 2);
                $val2 = $ary ? explode(' ', $ary[$k], 2)[1] : 0;
                if ('' === $const) {
                    $out[$val] = $val2;
                } else {
                    $file .= sprintf("const L_$const=%s;\n", var_export($ary ? $val2 : $val, true));
                }
            }
            $file .= "\nreturn " . var_export($out, true) . ";\n\n";
            Plan::_p("lng/$lg.php", $file);
        }
        return [];
    }

    function c_test() { //Plan::_t
        $place = ['view', DIR_M . '/mvc', DIR_M . '/w3'];
        list($i, $d, $n) = explode(' ', $_POST['v'] ? $_POST['v'] : '0  ');
        for ($flag = 0; true; ) {
            foreach (Rare::walk_dirs($place[$i]) as $dir) if ('' === $d || $dir == $d || $flag) {
                $flag or $flag = 1;
                foreach (Rare::list_path($dir, 'is_file') as $fn) if ('' === $n || 2 == $flag || $fn === $n) {
                    $flag = 2;
                    if ($fn === $n)
                        continue;
                    preg_match_all('/L_([A-Z_\d]+)/', file_get_contents($fn), $m);
                    if ($m[0]) {
                        echo "$i $dir $fn " . implode(' ', $m[1]);
                        return;
                    }
                }
            }
            if (++$i == count($place)) {
                echo '.';
                return;
            }
        }
    }

    private function act($lg, &$id, $mode = 'read', $s = '', $test = false) {
        $row = $this->t->one(qp('lg=$+ and name="*"', $lg));
        $mem = rtrim($row['mem'], "\n");
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
                $mem = $end ? substr_replace($mem, '', $pos, 1 + $end - $pos) : substr_replace($mem, '', $pos - 1);
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
                        list ($const) = explode(' ', $v, 2);
                        if ($const === $s) {
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

    private function &load($lg, $is_sort = false, $is_sync = false) {
        global $sky;
        $row = $this->t->one(qp('lg=$+ and name="*"', $lg));
        $flag = $row['flag'];
        $update = '';
        if ($def = $lg == DEFAULT_LG) {
            $this->nsort = $flag & self::NON_SORT;
            $this->nsync = $flag & self::NON_SYNC;
            $update = "update \$_$sky->d_lgt set tmemo=%s where id=$row[id]";
        }
        $ary =& SKY::ghost($def ? 'i' : 'j', rtrim($row['tmemo'], "\n"), $update);
        if ($sorted = $is_sort && $this->nsort) {
            uasort(SKY::$mem['i'][3], function($a, $b) {
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
            trace('Sorted');
            $flag &= ~self::NON_SORT;
            SKY::$mem['i'][0] = 1; # save
            $this->nsort = 0;
        }
        if ($is_sync || $sorted)
            $this->t->update(['flag' => $is_sync ? $flag & ~self::NON_SYNC : $flag]);
        return $ary;
    }

    private function listing($lg) {
        $dary = $this->load(DEFAULT_LG, isset($_POST['sort']));
        $ary = $lg == DEFAULT_LG ? [] : $this->load($lg);
        $chars = [];
        return [
            'cnt' => count($dary),
            'chars' => function() use (&$chars) {
                return ' ' . implode(' ', $chars);
            },
            'row_c' => function($row) use (&$dary, &$ary, &$chars) {
                static $char = '', $prev = '';
                if (false === ($v = current($dary)))
                    return false;
                $id = key($dary);
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
                    'id' => $id,
                    'chr' => $char != $nc ? [$char = $nc, $sz] : false,
                ];
            },
        ];
    }
}

