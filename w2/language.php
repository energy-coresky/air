<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class Language
{
    public $list;
    public $toname;
    public $error;  //TODO: rerr
    public $lg;
    public $sql;
    public $nsort = 0;
    public $nsync = 0;
    const NON_SORT = 0x8000; # bit no 16
    const NON_SYNC = 0x10000; # bit no 17

    static function translate($coll) { # format: "ID CONST VAL"
        trace($coll, 'Collected new translations');
        $next_id = 0x7FFF & sqlf('+select flag from $_language where lg=%s and name="*"', DEFAULT_LG);
        $new = array_join($coll, function($k, $v) use (&$next_id) {
            return $next_id++ . '  ' . escape($k);
        });
        sqlf('update $_language set tmemo=trim("\n" from concat(%s, tmemo)) where name="*"', "$new\n");
        sqlf('update $_language set flag=%d where lg=%s and name="*"', $next_id | self::NON_SORT, DEFAULT_LG);
        $lang = new Language;
        $lang->c_generate(null, false); # without sorting
    }

    static function all() {
        return unserialize(trim(view('_lng.array', [])));
    }

    function __construct() {
        $lg = MVC::$cc->setLG_h();
        if (!$this->error = (int)(1 !== DEBUG))
            $lg && ($this->list = $lg) or $this->error = 2;
        $this->error or DEFAULT_LG && in_array(DEFAULT_LG, $this->list) or $this->error = 3;
        if ($this->list)
            $this->toname = Language::all();
    }

    function c_list($lg) {
        global $sky;
        $err = $this->error || 'list' == $sky->_1 && $this->fail_rows();
        return [
            'obj' => $this,
            'e_list' => ($this->lg = $lg) && !$err ? $this->listing($lg) : [],
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
        return ['e_list' => $this->items($this->list, $id), 'id' => $id];
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
            multi_sql(qp($_POST['sql']));
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
    
    function c_generate($lg, $is_user = true) {
        $dary = $this->load(DEFAULT_LG, $is_user, $is_user);
        foreach ($this->list as $v) {
            $lgt = [];
            $def = $v == DEFAULT_LG or $ary = $this->load($v);
            $file = "<?php\n\n# This is auto-generated file. Do not edit!\n\n";
            foreach ($dary as $k => $one) {
                list ($const, $val) = explode(' ', $one, 2);
                $val2 = $val;
                $def or list(,$val2) = explode(' ', $ary[$k], 2);
                if ('' === $const) {
                    $lgt[$val] = $def ? 0 : $val2;
                } else {
                    $file .= sprintf("define('L_$const', %s);\n", var_export($val2, true));
                }
            }
            if ($lgt)
                $file .= "\n\$lgt = " . var_export($lgt, true) . ";\n\n";
            Plan::_p("lng/$v.php", $file);
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
        $new = false;
        list($row_id, $mem, $flag) = sqlf('-select id, tmemo, flag from $_language where lg=%s and name="*"', $lg);
        $upd = '';
        if (is_array($s))
            list($s, $val, $new) = $s;
        if (!$new) {
            $pos = strpos($mem, "$id ");
            if (0 !== $pos && "\n" != $mem[$pos - 1])
                $pos = 1 + strpos($mem, "\n$id ");
            $end = strpos($mem, "\n", $pos + 2);
        }
        switch ($mode) {
            case 'read':
                $mem = $end ? substr($mem, $pos, $end - $pos) : substr($mem, $pos);
                return explode(' ', $mem, 3);
            case 'delete':
                $mem = $end ? substr_replace($mem, '', $pos, 1 + $end - $pos) : substr_replace($mem, '', $pos - 1);
                $upd = ", flag=" . ($flag | self::NON_SYNC);
                break;
            case 'text':
                $pos = 1 + strpos($mem, ' ', 1 + strpos($mem, ' ', $pos));
                $mem = $end ? substr_replace($mem, $s, $pos, $end - $pos) : substr_replace($mem, $s, $pos);
                $upd = ", flag=" . ($flag | self::NON_SYNC);
                break;
            case 'const': case 'all':
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
                $upd = ", flag=" . ($flag | self::NON_SYNC);
                if (!$id) {
                    $id = 0x7FFF & $flag; # max 32767 items
                    $upd = ", flag=" . (($id + 1) | self::NON_SORT | self::NON_SYNC);
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
        $lg == DEFAULT_LG or $upd = '';
        sqlf('update $_language set tmemo=%s' . $upd . ' where id=%d', $mem, $row_id);
        return false;
    }

    private function fail_rows() {
        if (!SKY::$dd->_tables('language')) {
            $this->sql = array_join($this->list, function($k, $v) {
                return 'insert into ' . SQL::$dd->pref . "language values(null, '$v', '*', 1, '', now());";
            });
            return $this->error = 4;
        }
        $list = sqlf('@select lg from $_language where name="*"');
        $this->sql = ($diff = array_diff($list, $this->list))
            ? (string)qp('delete from $_language where lg in ($@);', $diff)
            : '';
        if ($diff = array_diff($this->list, $list)) {
            $this->sql .= "\n" . array_join($diff, function($k, $v) {
                return qp('insert into $_language select null, $+, name, 1, tmemo, now()'
                    . ' from $_language where lg=$+;', $v, DEFAULT_LG);
            });
        }
        return $this->sql ? ($this->error = 5) : false;
    }

    private function items($list, $id) {
        return ['row_c' => function($row) use (&$list, $id) {
            $lg = current($list);
            if (false === $lg)
                return false;
            list (,$const, $val) = $id ? $this->act($lg, $id) : [0, '', ''];
            next($list);
            return [
                'lg' => $lg,
                'const' => $const,
                'val' => escape($val, true),
            ];
        }];
    }

    private function &load($lg, $is_sort = false, $is_sync = false) {
        list($id, $flag, $tmemo) = sqlf('-select id, flag, tmemo from $_language where lg=%s and name="*"', $lg);
        if ($def = $lg == DEFAULT_LG) {
            $this->nsort = $flag & self::NON_SORT;
            $this->nsync = $flag & self::NON_SYNC;
        }
        $ary =& SKY::ghost($def ? 'i' : 'j', $tmemo, $def ? 'update $_language set tmemo=%s where id=' . $id : '');
        if ($sorted = $is_sort && $this->nsort) {
            uasort(SKY::$mem['i'][3], function($a, $b) {
                $a = explode(' ', $a, 2);
                $b = explode(' ', $b, 2);
                $a0 = ord($a = strtolower($a[1]));
                $b0 = ord($b = strtolower($b[1]));
                if ($a0 < 0x61) {
                    $a0 += 96;
                    if ($b0 < 0x61)
                        $b0 += 96;
                    return $a0 < $b0 ? -1 : 1;
                }
                if ($b0 < 0x61)
                    return -1;
                return $a === $b ? 0 : ($a < $b ? -1 : 1);
            });
            trace('Sorted');
            $flag &= ~self::NON_SORT;
            SKY::$mem['i'][0] = 1; # save
            $this->nsort = 0;
        }
        if ($is_sync || $sorted)
            sqlf('update $_language set flag=%d where id=%d', $is_sync ? $flag & ~self::NON_SYNC : $flag, $id);
        return $ary;
    }

    private function listing($lg) {
        $char = $prev = $ary = '';
        $dary = $this->load(DEFAULT_LG, isset($_POST['sort']));
        $lg == DEFAULT_LG or $ary = $this->load($lg);
        return [
            'cnt' => count($dary),
            'row_c' => function($row) use ($lg, &$dary, &$char, &$prev, $ary) {
                if (false === ($v = current($dary)))
                    return false;
                $id = key($dary);
                list ($k, $v) = explode(' ', $v, 2);
                next($dary);
                $v2 = '';
                $def = $lg == DEFAULT_LG or list (,$v2) = explode(' ', $ary[$id], 2);
                $cmp = '%' == $char ? '%' : strtoupper($v[0]);
                $ord = ord($cmp);
                0x40 < $ord && $ord < 0x5B or $cmp = '%';
                return [
                    'id' => $id,
                    'char' => $char != $cmp ? ($char = $cmp) : false,
                    'pink' => !$def && $v == $v2,
                    'yell' => strlen($v) < strlen($k),
                    'red' => $v === $prev,
                    'key' => $k,
                    'val' => $prev = $v,
                    'val2' => $v2,
                ];
            },
        ];
    }
}

