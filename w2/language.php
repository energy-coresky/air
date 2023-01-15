<?php

class Language
{
    const version = '0.99';

    public $langs;
    public $error;
    public $lg; # selected
    public $sql;
    public $all = false;
    public $gpd = false;
    public $nsort = 0;
    public $nsync = 0;
    public $pages = ['*' => '* common'];
    public $page = '*';

    const NON_SORT = 0x8000; # bit no 16
    const NON_SYNC = 0x10000; # bit no 17

    private $t;

    static function translate($ary, $page = false) {
        if (!DEV)
            return;
        $me = new Language;
        if ($me->error)
            throw new Error("class Language error=$me->error");
        trace($ary, 'Collected new translations');
        $where = qp('lg=$+ and name=$+', DEFAULT_LG, $page = $page ?: SKY::$reg['lg_page'] ?: '*');
        $next_id = 0x7FFF & $me->t->cell($where, 'flag', true);
        $new = array_join($ary, function($k) use (&$next_id) {
            return $next_id++ . '  ' . escape($k); # format: "ID CONST VAL"
        });
        // qp('$cc($+, tmemo)', "$new\n") not work !
        $me->t->update(['$tmemo' => qp(qp('$cc($+, tmemo)'), "$new\n")], qp('name=$+', $page));
        $me->t->update(['.flag' => $next_id | self::NON_SORT]);
        $me->c_sync($page);
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
        } elseif (!$this->langs = $sky->langs) {
            $this->error = 2;
        } elseif (!DEFAULT_LG || !in_array(DEFAULT_LG, $this->langs)) {
            $this->error = 3;
        } else {
            $this->langs = array_diff($this->langs, [DEFAULT_LG]);
            $this->langs = array_merge([DEFAULT_LG], $this->langs); # default is first
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
            $this->sql = array_join($this->langs, function($k, $v) use ($ins, $page) {
                $flag = $v == DEFAULT_LG ? 1 + self::NON_SYNC : 0;
                return qp('$$ values(null, $+, $+, $., "", $now);', $ins, $v, $page, $flag);
            });
            return $this->error = 4;
        }
        $list = $this->t->all(qp('name="*"'), 'lg');
        $this->sql = '';
        if ($diff = array_diff($list, $this->langs))
            $this->sql .= qp('delete from $_` where lg in ($@);', $sky->d_lgt, $diff);
        if ($diff = array_diff($this->langs, $list)) {
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
            } elseif ('$' == $a || '$+' == $a) { # get parsed data
                $lg = $this->gpd = DEFAULT_LG;
                '$' == $a or $this->all = $lg;
            } else {
                if ('!' == $a[0]) { # exec SQLs from POST
                    $this->sql = substr($a, 1);
                } else { # add page
                    $this->make_sql($_POST['page'] = $a);
                }
                foreach (explode(';', $this->sql) as $sql) {
                    if ($sql = trim($sql))
                        sql($sql);
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

    function c_api() {
        $input = file_get_contents('php://input');
        $in = (object)unjson($input, true);
        if ('hallo' == $in->tell) {
            
        } elseif (DEFAULT_LG == $in->lg) {
            list ($page, $id) = explode('.', $in->id);
            foreach ($in->list as $lg => $translated) {
                if (DEFAULT_LG == $lg)
                    continue;
            }
        }
        json([
            'tell' => $tell,
            'langs' => $this->langs,
            'list' => $list,
            'id' => "$page.$id",
        ]);
    }

    private function id($id) {
        if (strpos($id, '.'))
            list ($this->page, $id) = explode('.', $id, 2);
        return $id;
    }

    function c_delete($id) {
        $id = $this->id($id);
        foreach ($this->langs as $lg) # delete item
            $this->act($lg, $id, 'delete');
        return true;
    }

    function c_save($in) {
        $lng = substr($in, 0, 2);
        $id = $this->id($in = substr($in, 2));
        $this->all = strpos($in, '.');
        if (!isset($_POST['s'])) {
            $const = strtoupper($_POST['const']);
            $new = !$id; # $id passed by reference!
            foreach ($this->langs as $lg) {
                 if ($this->act($lg, $id, 'bulk', [$const, escape($_POST[$lg]), $new]))
                     return true;
            }
        } elseif ('~' == $_POST['const-prev']) {
            $this->act($lng, $id, 'text', escape($_POST['s']));
        } else {
            foreach ($this->langs as $lg) {
                if ($this->act($lg, $id, 'const', strtoupper($_POST['s'])))
                    return true;
            }
        }
        return ['e_list' => $this->listing($lng, [$in])];
    }

    function c_edit($lg) {
        $id = $this->id($_POST['id']);
        $m = $_POST['m'] ? 1 : 0;
        return ['val' => escape($this->act($lg, $id)[$m], true)];
    }

    function c_bulk($in) {
        $id = $this->id($in);
        return [
            'id' => $in,
            'e_list' => ['row_c' => function() use ($id) {
                if (false === ($lg = current($this->langs)))
                    return false;
                list ($const, $val) = $id ? $this->act($lg, $id) : ['', ''];
                next($this->langs);
                return [
                    'lg' => $lg,
                    'const' => $const,
                    'val' => escape($val, true),
                ];
            }],
        ];
    }

    function c_group($mode) {
        if ('m' == $mode && !preg_match("/^(\*|[a-z\d_]+)$/", $to = $_POST['to']))
            throw new Error('SkyLang error');
        if (strpos($_POST['row'][0], '.')) {
            $pg = [];
            $this->all = true;
            array_walk($_POST['row'], function ($id) use (&$pg) {
                list ($page, $id) = explode('.', $id, 2);
                $pg[$page][] = $id;
            });
        } else {
            $pg[$this->page] = $_POST['row'];
        }
        $ary = $moved = [];
        foreach ($pg as $page => $list) {
            $this->page = $page;
            foreach ($this->langs as $lg)
                $ary[$lg] = $this->act($lg, $list, 'delete');
            if ('m' == $mode) { # move
                $this->page = $to;
                foreach ($ary as $lg => $set) {
                    $this->act($lg, $id, 'insert', $set); # $id passed by reference!
                    if ($this->all && $lg == DEFAULT_LG)
                        for ($i = 0, $c = count($set); $i < $c; $moved[] = "$to." . ($id + $i++));
                }
            }
        }
        return $moved ? ['e_list' => $this->listing(DEFAULT_LG, $moved)] : true;
    }

    private function act($lg, &$id, $mode = 'read', $s = '') {
        $def_lg = $lg == DEFAULT_LG;
        $row = $this->t->one(qp('lg=$+ and name=$+', $lg, $this->page));
        $ary =& SKY::ghost('j', trim($row['tmemo'], "\n"), false, 1);
        $flag = ['.flag' => $row['flag'] | self::NON_SYNC];
        $out = [];
        switch ($mode) {
            case 'read':
                return explode(' ', $ary[$id], 2);
            case 'text':
                $ary[$id] = explode(' ', $ary[$id], 2)[0] . " $s";
                break;
            case 'delete':
                is_array($id) or $id = [$id];
                foreach ($id as $one) {
                    $out[] = $ary[$one];
                    unset($ary[$one]);
                }
                break;
            case 'insert':
                $next_id = $def_lg ? ($id = 0x7FFF & $row['flag']) : $id;
                foreach ($s as $v)
                    $out[$next_id++] = $v;
                $ary = $out + $ary;
                $flag = ['.flag' => $next_id | self::NON_SORT | self::NON_SYNC];
                break;
            case 'const':
            case 'bulk':
                $new = false;
                if (is_array($s))
                    list($s, $val, $new) = $s;
                if ($def_lg && '' !== $s && $_POST['const-prev'] != $s) {
                    foreach ($this->load_all() as $v) {
                        if (explode(' ', $v, 2)[0] === $s) {
                            echo 1; # non unique
                            return true;
                        }
                    }
                }
                if (!$id) {
                    $id = 0x7FFF & $row['flag']; # max 32767 items
                    $flag = ['.flag' => ($id + 1) | self::NON_SORT | self::NON_SYNC];
                }
                if ($new) {
                    $ary = [$id => "$s $val"] + $ary;
                } elseif ('bulk' == $mode) {
                    $ary[$id] = "$s $val";
                } else { # const mode
                    $ary[$id] = "$s " . explode(' ', $ary[$id], 2)[1];
                }
        }
        $this->t->update(['tmemo' => SKY::sql('j')] + ($def_lg ? $flag : []));
        return $out;
    }

    function c_sync($page) {
        if (!$page) { # sync all
            $list = $this->t->all(qp('lg=$+', DEFAULT_LG), 'name, flag');
            foreach ($list as $pg => $flag) {
                if ($flag & self::NON_SYNC)
                    $this->c_sync($pg);
            }
            return false === $page ? array_keys($list) : true;
        }
        $dary = $this->load(DEFAULT_LG, false, true, $page);
        $pg = '*' === $page ? '' : "_$page";
        foreach ($this->langs as $i => $lg) {
            $out = [];
            $ary = $i ? $this->load($lg, false, false, $page) : [];
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
            if (!$pg) {
                $fp = fopen(__FILE__, 'r');
                fseek($fp, __COMPILER_HALT_OFFSET__);
                $code .= stream_get_contents($fp);
                fclose($fp);
            }
            Plan::_p("lng/$lg$pg.php", DEV::auto($out, $code));
        }
    }

    function c_parse($get_cache) {
        if ($get_cache)
            return json(unserialize(Plan::cache_g('lg_flash')));

        $app = $jet = $const = $tfunc = [];
        $data = $new = [[], []];
        foreach ($this->c_sync(false) as $page) {
            $page = '*' === $page ? DEFAULT_LG : DEFAULT_LG . "_$page";
            $data[1] += Plan::_r("lng/$page.php");
        }
        $data[0] = get_defined_constants(true)['user'];

        $put = function ($x, $str, $fn, &$cnt) use (&$data, &$new) {
            $set = isset($data[$x][$x ? $str : "L_$str"]) or $new[$x][$str] = 0;
            $set ? $cnt[$x][0]++ : $cnt[$x][1]++;
            return basename($fn);
        };
        $php = function ($s) {
            if (!in_array($s[0], ["'", '"']))
                return $s;
            foreach (token_get_all("<?php $s") as $v) {
                if (is_array($v) && T_CONSTANT_ENCAPSED_STRING == $v[0])
                    return eval("return $v[1];");
            }
        };

        $path = Plan::app_obj(['main'])->path;
        foreach (Rare::walk_dirs($path) as $dir) {
            if ("$path/lng" == $dir)
                continue;
            foreach (Rare::list_path($dir, 'is_file') as $fn) {
                if ('php' != pathinfo($fn)['extension'] ?? '')
                    continue;
                $app[$fn] = [[0, 0], [0, $fun = 0]];
                foreach (token_get_all(file_get_contents($fn)) as $v) {
                    list($id, $v) = is_array($v) ? $v : [0, $v];
                    if (T_WHITESPACE == $id)
                        continue;
                    if (T_STRING == $id) {
                        $fun = 't' == $v ? 1 : 0;
                        if (!$fun && preg_match('/^L_([A-Z_\d]+)$/', $v, $m))
                            $const[$m[1]][] = $put(0, $m[1], $fn, $app[$fn]);
                    } elseif (1 === $fun && !$id && '(' == $v) {
                        $fun = 2;
                    } elseif (2 === $fun && T_CONSTANT_ENCAPSED_STRING == $id) {
                        $fun = $v;
                    } elseif (is_string($fun) && !$id && in_array($v, [',', ')'])) {
                        $tfunc[$k = eval("return $fun;")][] = $put(1, $k, $fn, $app[$fn]);
                        $fun = 0;
                    } else {
                        $fun = 0;
                    }
                }
            }
        }
        $path = Plan::view_obj(['main'])->path;
        foreach (Rare::list_path($path, 'is_file') as $fn) {
            if ('jet' != pathinfo($fn)['extension'] ?? '')
                continue;
            $jet[$fn] = [[0, 0], [0, 0]]; // 2do: rewrite jet parser ?
            if (preg_match_all('/L_([A-Z_\d]+)/', $tpl = file_get_contents($fn), $m)) {
                foreach ($m[1] as $c)
                    $const[$c][] = $put(0, $c, $fn, $jet[$fn]);
            }
            if (preg_match_all('/@t\(([^\)]+)\)/', $tpl, $m)) {
                foreach ($m[1] as $c)
                    $tfunc[$k = $php($c)][] = $put(1, $k, $fn, $jet[$fn]);
            }
        }
        if ($new[1])
            Language::translate($new[1], '*');

        echo Plan::cache_p('lg_flash', serialize([
            'const' => array_map($map = function ($a) {
                $s = 1 == ($n = count($a)) ? '' : 's';
                $u = count($a = array_unique($a)) - 1;
                return "$n use$s in " . ($u < 2 ? implode(' & ', $a) : "$a[0] + $u files");
            }, $const),
            'tfunc' => array_map($map, $tfunc),
            'fcnt' => count($app) + count($jet),
            'files' => view('_lng.files', ['rows' => function () use (&$app, &$jet) {
                $html = function (&$ary, &$v) {
                    $s = key($ary);
                    $v = array_shift($ary);
                    $v[0][1] and $v[0][0] .= sprintf(span_r, ' + ' . $v[0][1]);
                    $v[1][1] and $v[1][0] .= sprintf(span_r, ' + ' . $v[1][1]);
                    $v[0][0] or $v[0][0] = '-';
                    $v[1][0] or $v[1][0] = '-';
                    return $s;
                };
                for ($i = 1, $s = ''; $app || $jet; $i++, $ac = $jc = [[''], ['']]) {
                    $a = $app ? $html($app, $ac) : '';
                    $j = $jet ? $html($jet, $jc) : '';
                    $s .= td([
                        [$a ? $i : '', 'align="center"'], $a, $ac[0][0], $ac[1][0], ['', 'class="td1p"'],
                        [$j ? $i : '', 'align="center"'], $j, $jc[0][0], $jc[1][0], ['', 'class="td1p"'],
                    ]);
                }
                return $s;
            }]),
            'warning' => $new[0] ? 'L_' . implode(', L_', array_keys($new[0])) : '',
            'message' => count($new[1]),
        ]));
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

    private function &load($lg, $is_sort = false, $is_sync = false, $page = false) {
        $page or $page = $this->page;
        $row = $this->t->one(qp('lg=$+ and name=$+', $lg, $page));
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
        trace($is_sort ? 'Sorted' : 'Not sorted', "{$lg}_$page");
        if ($row['flag'] != $flag)
            $this->t->update(['flag' => $flag] + ($mem ?? []));
        return $ary;
    }

    private function &load_all() {
        $all = $this->t->all(qp('lg=$+', DEFAULT_LG), '$cc(name, ".", flag), tmemo');
        array_walk($all, function (&$v, $k) {
            list ($k, $flag) = explode('.', $k);
            $this->nsync |= $flag & self::NON_SYNC;
            if ('' !== $v)
                $v = "$k." . str_replace("\n", "\n$k.", trim($v, "\n")) . "\n";
        });
        return SKY::ghost('i', trim(implode('', $all), "\n"));
    }

    private function listing($lg, $only = []) {
        $list = $this->t->all(qp('name<>"*" and lg=$+', DEFAULT_LG), 'name');
        $this->pages += array_combine($list, $list);
        if ($this->all) {
            $dary =& $this->load_all();
            $this->sort($dary);
        } else {
            $dary = $this->load(DEFAULT_LG, isset($_POST['sort']));
            $this->all = 1 == count($this->pages) ? false : '';
        }
        $ary = $lg == DEFAULT_LG ? [] : $this->load($lg);
        $chars = [];
        return [
            'cnt' => count($dary),
            'chars' => function() use (&$chars) {
                return ' ' . implode(' ', $chars);
            },
            'row_c' => function() use (&$dary, &$ary, &$chars, $only) {
                static $char = '', $prev = '', $pp = '', $color = false;

                if (false === ($v = current($dary)))
                    return false;
                $id = key($dary);
                next($dary);
                if ($only && ($char = '%') && !in_array($id, $only))
                    return true;
                list ($key, $v) = explode(' ', $v, 2);
                if ($this->all && !in_array($page = explode('.', $id, 2)[0], [$pp, '*'])) {
                    $pp = $page;
                    $color = 'cfc' == $color ? 'e0e7ff' : 'cfc';
                }
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
                    'red' => (int)$prev === $v, // duplicated
                    'val' => $prev = $v,
                    'val2' => $v2 = $ary ? explode(' ', $ary[$id], 2)[1] : '',
                    'pink' => $ary && $v == $v2, // not translated
                    'yell' => strlen($v) < strlen($key),
                    'key' => $key,
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
