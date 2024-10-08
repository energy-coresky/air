<?php

class Install
{
    private $mem = false;
    private $fn = false;

    const DONE = 'Done.';

    static $cli = true;

    static function run($page) {
        $page or $page = 'database';
        MVC::body("_inst.$page[0]");
        return (array)(new Install)->{"_$page"}();
    }

    function memo($var = false, $default = '') {
        global $sky;
        false !== $this->mem or $this->mem = $sky->memory(11, 'i');
        if (!$var)
            return $this->mem;
        if (is_array($default))
            return isset($this->mem[$var]) ? explode(' ', $this->mem[$var]) : $default;
        return $this->mem[$var] ?? $default;
    }

    function write_sky($fn, $head = false) {
        $handle = fopen($this->fn, $fn ? 'ab' : 'wb');
        if ($head) {
            fwrite($handle, $head . "\n");
        } else {
            if (!is_file($orig = $fn)) {
                $base = basename($fn);
                $fn = DIR_M . '/w2/' == substr($orig, 0, 8)
                    ? DIR_S . "/w2/$base"
                    : (WWW == substr($orig, 0, strlen(WWW))
                        ? DIR_S . "/assets/$base"
                        : DIR_S . "/$base"
                    );
                if (!is_file($fn))
                    throw new Error("Install: file `$orig` not exists");
            }
            $bin = file_get_contents($fn);
            if (DIR_M . '/config.yaml' == $fn && 'SQLite3' != SKY::$dd->name && isset($_POST['sql'])) {
                $bin = preg_replace("/\b(pref|dsn):\s*[^,}\r\n]+/", '$1: ""', $bin);
            } elseif ('bootstrap.php' == $fn) {
                $bin = preg_replace("/(DIR_S')[^;]+;/", '$1, \'main\');', $bin);
            } elseif (WWW . 'index.php' == $fn) {
                self::$cli = false;
                $bin = common_c::make_h(true);
                common_c::make_h(false);
            }
            fwrite($handle, "FILE: $orig " . strlen($bin) . "\n");
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
            'tables' => SKY::$dd->_tables(),
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
            [$n, $max, $ct, $cr, $create, $rows] = explode("\t", $step);
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
        if ('copy' == $sky->_2) {
            $this->memo();
            $m = array_diff(explode(' ', $sky->i_gr_extns), explode(' ', $sky->i_gr_nmand), Root::$core);
            $this->mem['modules'] = $m = implode(' ', $m);
            SKY::i('modules', $m);
        }
        if (!isset($_POST['step'])) return [
            'modules' => Globals::extensions(true),
            'mem' => $this->memo('modules', []),
            'and' => $this->memo('and', 0),
            'mysql' => mysqli_get_client_version(),//mysqli_get_server_info
            'row' => (object)$this->memo(),
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
            $n = $cf = $max = 0;
            $this->fn = 'var/' . $_POST['fn'] . '.sky';
            [, $exf, $mkdir, $dirs] = $this->get_files(1);
            array_shift($mkdir); # skip `.`
            foreach ($dirs as $one) {
                foreach ($this->filelist($one) as $fn) {
                    if (isset($exf[$fn]) || $this->skip_file($fn))
                        continue;
                    $max++;
                    $this->filep($fn);
                }
            }
            $head = "type app\nmod_required $head";
            $head .= "\ndesc " . escape($_POST['desc']);
            $head .= "\nversion $_POST[vphp] $and $_POST[vphp2] $_POST[vmysql]\nwww " . WWW;
            $head .= "\ncompiled " . date_default_timezone_get() . ' ' . $sky->s_version;
            $head .= "\nfilep " . $this->filep();
            $sql = isset($_POST['sql']) ? $this->memo('count_tr', '0.0.') : '0.0.';
            $head .= "\nftrd A^$max.$sql" . count($mkdir);
            $head .= "\n\nDIRS: " . strlen($mkdir = implode(' ', $mkdir)) . "\n$mkdir";
            $this->write_sky(false, $head);
            $msg = 'Init..';
        } else {
            [$n, $max, $cf, $dirs] = explode("\t", $step);
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
                [, $exf] = $this->get_files();
                if (isset($exf[$fn = $files[$cf++]]) || $this->skip_file($fn)) {
                    $msg = "$n. " . L::r($fn) . ' (skipped)';
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

    function filep($fn = null) {
        static $ary = [];
        if (null === $fn)
            return implode(' ', $ary);
        $cnt = count($x = explode('/', $fn));
        if ($cnt > 2 || $cnt == 2 && WWW != $x[0] . '/')
            return;
        $ary[] = $fn;
    }

    function filelist($path) {
        $list = Rare::list_path($path, 'is_file');
        if (DIR_M == $path || DIR_M . '/w2' == $path) {
            $coresky = Rare::list_path(DIR_S . substr($path, 4), 'is_file');
            foreach ($coresky as $one) {
                $one = "$path/" . basename($one);
                in_array($one, $list) or $list[] = $one;
            }
        } elseif (WWW . 'm' == $path && is_dir($assets = DIR_S . '/assets')) {
            $assets = Rare::list_path($assets, 'is_file');
            foreach ($assets as $one) {
                $one = WWW . 'm/' . basename($one);
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
            in_array(DIR_M . '/w2', $all) or $all[] = DIR_M . '/w2';
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
        $files = array_flip(Rare::walk_dirs('.'));
        array_walk($files, fn(&$v, $k) => $v = [0, Rare::list_path($k, 'is_file')]);
        return [
            'files' => $files,
            'exd' => $saved[0],
            'exf' => $saved[1],
        ];
    }

    static function make($forward = true, array $plus = []) {
        global $sky;
        static $index;

        $fn = WWW . 'index.php';
        if (!$forward)
            return self::$cli && file_put_contents($fn, $index);
        $sky->memory(11, 'i');
        $other = '';
        foreach ($plus as $name)
            $other .= "\n    function (\$ok) {\n" . call_user_func($name) . "    },";
        $file = view('_inst.first_run', [
            'vphp' => $sky->i_vphp ?: '7.3',
            'vph2' => $sky->i_vphp2 ?: '9.0',
            'exts' => $sky->i_modules ?: 'ctype intl mbstring tokenizer',
            'tests' => $other,
        ]);
        $index = file_get_contents($fn);
        $file = strtr($file, ['<.' => '<?', '.>' => '?>']) . $index;
        return self::$cli ? file_put_contents($fn, $file) : $file;
    }
}
