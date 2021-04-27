<?php

class Install
{
    private $mem = false;
    private $fn = false;
    const DONE = 'Done.';

    static function run($page) {
        return ['page' => $page] + (array)(new Install)->{"_$page"}();
    }

    function memo($var = false, $default = '') {
        if (false === $this->mem) {
            $tmemo = sqlf('+select tmemo from $_memory where id=6');
            $this->mem = SKY::ghost('i', $tmemo, 'update $_memory set dt=now(), tmemo=%s where id=6');
        }
        if ($var) {
            if (is_array($default))
                return isset($this->mem[$var]) ? explode(' ', $this->mem[$var]) : $default;
            return isset($this->mem[$var]) ? $this->mem[$var] : $default;
        }
        return $this->mem;
    }

    function write_sky($fn, $head = false) {
        $handle = fopen($this->fn, $fn ? 'ab' : 'wb');
        if ($head) {
            fwrite($handle, $head . "\n");
        } else {
            if (!is_file($orig = $fn)) {
                $w2 = 'main/w2/' == substr($fn, 0, 8);
                if (!is_file($fn = DIR_S . ($w2 ? '/w2/' : '/') . basename($fn)))
                    throw new Err("Install: file `$orig` not exists");
            }
            $bin = file_get_contents($fn);
            if ('main/conf.php' == $fn) {
                $s = ['', '', ''];
                $i = 0;
                foreach (token_get_all($bin) as $x) {
                    list ($lex, $x) = is_array($x) ? $x : [0, $x];
                    if (!$i && T_VARIABLE == $lex && '$databases' == $x)
                        $i++;
                    if (1 == $i && ';' == $x)
                        $i++;
                    $s[$i] .= $x;
                }
                eval("$s[1];");
                $databases['pref'] = $databases['dsn']  = '';
                $s[1] = '$databases = ' . var_export($databases, true);
                $bin = implode('', $s);
                $bin = preg_replace("/(DIR_S')[^\)]+\)/", '$1, \'main\')', $bin);
                $size = strlen($bin);
            } else {
                $size = filesize($fn);
            }
            fwrite($handle, "FILE: $orig $size\n");
            fwrite($handle, $bin . "\n");
        }
        fclose($handle);
    }

    function write_sql($s, $append = true) {
        if (!is_file($fn = 'var/app.sql'))
            $append = false;
        file_put_contents($fn, "$s;\n", $append ? FILE_APPEND : null);
    }

    function _database() {
        if (!isset($_POST['step'])) return [
            'title' => 'var/app.sql',
            'tables' => sql("@show tables"),
            'mem' => $this->memo('tables', []),
        ];
        if (!$step = $_POST['step']) {
            $this->write_sql('set names utf8', !isset($_POST['empty']));
            $list = sql("@show tables");
            $ary = [];
            $create = array_filter($list, function($v) use (&$ary) {
                if ($set = isset($_POST["c_$v"]))
                    $ary[] = "c_$v";
                return $set;
            });
            $rows = array_filter($list, function($v) use (&$ary) {
                if ($set = isset($_POST["r_$v"]))
                    $ary[] = "r_$v";
                return $set;
            });
            $this->memo();
            SKY::i('tables', implode(' ', $ary));
            $n = 0;
            $max = count($create) + count($rows);
            $step = "$n\t$max\t0\t0\t" . implode(' ', $create) . "\t" . implode(' ', $rows);
            $msg = 'Init..';
        } else {
            $pref = $_POST['pref'];
            list($n, $max, $ct, $cr, $create, $rows) = explode("\t", $step);
            $n++;
            if ($create) {
                $create = explode(' ', $create);
                $tbl = array_shift($create);
                $create = implode(' ', $create);
                $sql = preg_replace("/\s+/sm", ' ', sql("-show create table $tbl")[1]);////////////////////////// s+
                $sql = preg_replace("/^CREATE TABLE `$pref(\w+)`/", 'create table `$1`', $sql);
                $this->write_sql($sql);
                $ct++;
                $msg = "Create table `$tbl`...";
            } elseif ($rows) {
                $rows = explode(' ', $rows);
                $tbl = array_shift($rows);
                $one = preg_replace("/^$pref(\w+)$/", '$1', $tbl);
                for ($q = sql('select * from $_`', $one); $r = $q->one('R'); ) {
                    foreach ($r as &$v)
                        $v = null === $v ? 'NULL' : (is_num($v) && '0' != $v[0] ? $v : $q->_dd->escape($v));
                    $this->write_sql("insert into `$one` values(" . implode(',', $r) . ")");
                    $cr++;
                }
                $rows = implode(' ', $rows);
                $msg = "Insert rows table `$tbl`...";
            } else {
                $this->write_sql('-- END');
                $this->memo();
                SKY::i([
                    'count_tr' => "$ct.$cr.",
                    'ts_sql' => time(),
                ]);
                $msg = self::DONE;
            }
            $step = "$n\t$max\t$ct\t$cr\t$create\t$rows";
        }
        json([
            'str' => $msg,
            'end' => $msg == self::DONE ? 1 : 0,
            'progress' => $n,
            'max' => $max,
            'step' => $step,
        ]);
    }

    function skip_file($fn) {
        return '.sky' == substr($fn, -4)
            || '.sky.txt' == substr($fn, -8)
            || "$this->fn.bz2" == $fn
            || 'moon.php' == basename($fn);
    }

    function _system() {
        global $sky;
        if (!isset($_POST['step'])) return [
            'modules' => get_loaded_extensions(),
            'mem' => $this->memo('modules', []),
            'and' => $this->memo('and', 0),
            'mysql' => mysqli_get_client_version(SQL::$dd->conn),//mysqli_get_server_info
            'd_row' => (object)$this->memo(),
            'vapp' => SKY::version()['app'][2] + 0.0001,
            'vcore' => SKY::version()['core'][0],
        ];
        if (!$step = $_POST['step']) {
            $modules = array_filter(get_loaded_extensions(), function($v) {
                return isset($_POST["m_$v"]);
            });
            $this->memo();
            $post = array_filter($_POST, function($k) {
                return in_array($k, ['desc', 'vphp', 'vphp2', 'vmysql', 'fn']);
            }, ARRAY_FILTER_USE_KEY);
            SKY::i($post + [
                'modules' => $head = implode(' ', $modules),
                'and' => $and = (int)isset($_POST['and']),
                'bz2' => (int)isset($_POST['bz2']),
            ]);
            $sky->s_version = time() . ' ' . SKY::version()['core'][0] . ' ' . $_POST['vapp'];
            if (!isset($_POST['do'])) {
                echo 1;
                return;
            }
            $n = $cf = $max = 0;
            $this->fn = 'var/' . $_POST['fn'] . '.sky';
            list(, $exf, $mkdir, $dirs) = $this->get_files(1);
            foreach ($dirs as $one)
                foreach ($this->filelist($one) as $fn)
                    isset($exf[$fn]) or $this->skip_file($fn) or $max++;
            $head = "type app\nmod_required $head";
            $head .= "\ndesc " . escape($_POST['desc']);
            $head .= "\nversion $_POST[vphp] $and $_POST[vphp2] $_POST[vmysql]\nwww " . WWW;
            $head .= "\ncompiled " . PHP_TZ . ' ' . $sky->s_version;
            $sql = isset($_POST['sql']) ? $this->memo('count_tr', '0.0.') : '0.0.';
            $head .= "\nftrd A^$max.$sql" . count($mkdir);
            $head .= "\n\nDIRS: " . strlen($mkdir = implode(' ', $mkdir)) . "\n$mkdir";
            $this->write_sky(false, $head);
            $msg = 'Init..';
        } else {
            list($n, $max, $cf, $dirs) = explode("\t", $step);
            $dirs = explode(' ', $dirs);
            do {
                $files = $this->filelist(current($dirs));
                if ($cf < count($files))
                    break;
                $cf = 0;
                array_shift($dirs);
            } while ($dirs);
            $this->fn = 'var/' . $this->memo('fn', 'app') . '.sky';
            if ($dirs) {
                list(, $exf) = $this->get_files();
                if (isset($exf[$fn = $files[$cf++]]) || $this->skip_file($fn)) {
                    $msg = "$n. " . sprintf(span_r, $fn) . ' (skipped)';
                } else {
                    $this->write_sky($fn);
                    $msg = "$n. $fn";
                }
            } else {
                $msg = self::DONE;
                $this->write_sky(true, 'END:');
                if ($this->memo('bz2', 0)) {
                    $bz = bzopen($this->fn . '.bz2', 'w');
                    $fh = fopen($this->fn, 'r');
                    while (!feof($fh))
                        bzwrite($bz, fread($fh, 8192));
                    fclose($fh);
                    bzclose($bz);
                }
            }
        }
        json([
            'str' => $msg,
            'end' => $msg == self::DONE ? 1 : 0,
            'progress' => $n++,
            'max' => $max,
            'step' => "$n\t$max\t$cf\t" . implode(' ', $dirs),
        ]);
    }

    function filelist($path) {
        $list = Rare::list_path($path, 'is_file');
        if ('main' == $path || 'main/w2' == $path) {
            $coresky = Rare::list_path(DIR_S . substr($path, 4), 'is_file');
            foreach ($coresky as $one) {
                $one = "$path/" . basename($one);
                in_array($one, $list) or $list[] = $one;
            }
        }
        return $list;
    }

    function get_files($mode = 0) {
        $saved = [[], []];
        $ary = $this->memo('files', []);
        array_walk($ary, function ($v) use (&$saved) {
            '|' == $v[0]
                ? ($saved[0][substr($v, 2)] = $v[1])
                : ($saved[1][$v] = 1);
        });
        if ($mode) {
            $all = Rare::walk_dirs('.');
            array_shift($all);
            in_array('main/w2', $all) or $all[] = 'main/w2';
            $saved[2] = array_filter($all, function($one) use ($saved) {
                $ary = explode('/', $one);
                while ($ary) {
                    $one = implode('/', $ary);
                    if (isset($saved[0][$one]) && 1 == $saved[0][$one])
                        return false;
                    array_pop($ary);
                }
                return true;
            });
            $saved[3] = array_filter($saved[2], function($one) use ($saved) {
                return !isset($saved[0][$one]);
            });
        }
        return $saved;
    }

    function _files() {
        $saved = $this->get_files();
        if (isset($_POST['fn'])) {
            $d = $_POST['dir'] ? 0 : 1; # dir or file
            if (0 == $_POST['m']) {
                unset($saved[$d][$_POST['fn']]);
            } else {
                $saved[$d][$_POST['fn']] = $_POST['m'];
            }
            $ary = [];
            array_walk($saved[0], function ($m, $dn) use (&$ary) {
                $ary[] = "|$m$dn"; # dirs
            });
            array_walk($saved[1], function ($m, $fn) use (&$ary) {
                $ary[] = $fn; # files
            });
            SKY::i('files', implode(' ', $ary));
        }
        $files = Rare::walk_dirs('.');
        array_shift($files);
        $files = array_flip($files);
        array_walk($files, function (&$v, $k) {
            $v = [0, Rare::list_path($k, 'is_file')];
        });
        return [
            'title' => 'Directories & files, check for exclude',
            'files' => $files,
            'exd' => $saved[0],
            'exf' => $saved[1],
        ];
    }
}
