<?php

class Root
{
    static $menu = [1 => 'Overview', 'phpinfo()', 'Config', 'Cache', 'Guard', 'Databases'];
    static $core = [ # is core: hash json ?
        'Core', 'date', 'hash', 'json', 'pcre', 'Reflection', 'SPL', 'standard',
    ];

    static function menu() {
        global $sky;
        $a = $sky->log_dt;
        if (preg_match("/^_dev\?main=(7|8|9|10)$/", URI, $m))
            $a[$id = 14 - $m[1]] && sqlf('update $_memory set dt=null where id=%d', $id) && ($a[$id] = 0);
        $a = array_map(fn($v) => $v ? ' <span style="color:red">*</span>' : '', $a);
        $a[7] .= ' <span style="color:blue">(' . SKY::s('log_a') . ')</span>';
        return [7 => "Sky Log$a[7]", "Log Cron$a[6]", "Log Crash$a[5]", "Log Error$a[4]"];
    }

    static function run($n, $id) {
        global $sky;
        if ($n < 7) {
            $sky->tpl_menu = "?main=$n&id=%d";
            $funs = array_map('strtolower', Root::$menu);
            $funs[2] = substr($funs[2], 0, 7);
            return call_user_func(['Root', '_' . $funs[$n]], $id);
        } elseif ($n < 11) {
            echo Display::log(sqlf('+select tmemo from $_memory where id=%d', 14 - $n));
            return 7 == $n ? self::_skylog() : '';
        }
    }

    static function _skylog() {
        $y = '' === SKY::s('log_y') ? [] : explode(' ', SKY::s('log_y'));
        $opt = [-2 => 'off', -1 => 'all'] + $y;
        false !== ($act = array_search(SKY::s('log_a'), $opt)) or $act = -2;
        if ($m = $_POST['m'] ?? false) {
            [$s, $a] = [$_POST['s'] ?? '', $_POST['a'] ?? ''];
            if ('s' === $m && isset($opt[$s])) {
                SKY::s('log_a', $opt[$s]);
            } elseif ('a' === $m && preg_match("/^[a-z\d]+$/", $a) && !in_array($a, $opt)) {
                SKY::s('log_a', $y[] = $a);
                SKY::s('log_y', implode(' ', $y));
            } elseif ('d' === $m && $act >= 0) {
                unset($y[$act]);
                SKY::s('log_a', 'off');
                SKY::s('log_y', implode(' ', $y));
            }
            jump(URI, false);
        }
        return view('_dev.skylog', ['act' => $act, 'opt' => $opt]);
    }

    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _overview($i) {
        global $sky;

        $menu = ['Summary', 'Constants', 'Functions', 'Classes', 'Other', 'License', 'Readme'];
        $top = menu($i, $menu);
        $ml = 'style="margin-left:35px;"';
        $tpl = '<span ' . $ml . '><u>Extension</u></span> &nbsp; <form>%s<select name="t" id="sm-select">%s</select></form>';
        $priv = tag('<input ' . ($sky->d_pp ? 'checked ' : '') . $ml . ' type="checkbox" onchange="sky.d.pp(this)"> show private & protected', '', 'label');
        $ext = get_loaded_extensions();
        natcasesort($ext);
        $echo = function ($ary, $t = 'c') {
            'e' == $t or sort($ary);
            $val = array_map(function ($v) use ($t) {
                global $sky;
                $v = explode(' ', $v)['e' == $t ? 2 : 0];
                $sky->d_manual or $sky->d_manual = 'en';
                $sky->d_se or $sky->d_se = 'https://yandex.ru/search/?text=%s';
                $vv = str_replace('_', '-', strtolower($v));
                $q = ('f' == $t ? 'function.' : ('e' == $t ? 'book.' : 'class.')) . $vv;
                $m = a('manual', Plan::php()->php . "$sky->d_manual/$q.php", 'target="_blank"');
                $s = a('stackoverflow', sprintf($sky->d_se, urlencode("php $v site:stackoverflow.com")), 'target="_blank"');
                return a('show', ["sky.d.reflect(this, '$t')"]) . " | $m | $s";
            }, $ary);
            echo Debug::out(array_combine($ary, $val), false);
        };
        $add_ext = function (&$ary, &$all) {
            $ary = array_map(function ($v) use (&$all) {
                return $v . ' (' . ($all[$v] ?? '<b>user</b>') . ')';
            }, $ary);
        };
        switch ($menu[$i]) {
            case 'Summary':
                $_exec = function_exists('shell_exec') ? 'shell_exec' : function ($arg) {
                    return $arg;
                };

                $ltime = PHP_OS == 'WINNT' ? preg_replace("@[\r\n\t ]+@", ' ', $_exec('date /t & time /t')) : $_exec('date'); # 2do - MAC PC
                $utime = PHP_OS == 'WINNT' ? preg_replace("@^.*?([\d /\.:]{10,}( [A|P]M)?).*$@s", '$1', (string)$_exec('net stats srv')) : $_exec('uptime');
                $db = SKY::$dd->info();
                echo Debug::out([
                    'Default site title' => $sky->s_title,
                    'Primary configuration' => sprintf('DEV = %d, DEBUG = %d, DIR_S = %s, ENC = %s, PHPDIR = %s', DEV, DEBUG, DIR_S, ENC, PHP_BINDIR),
                    'System' => (PHP_OS == 'WINNT' ? 'WINNT' : $_exec('uname -a')),
                    'Server IP, uptime' => ($_SERVER['SERVER_ADDR'] ?? '::1') . ' ' . $utime, # $_exec('uptime')
                    'Locale / mb_internal_encoding / mb_detect_order' => setlocale(LC_ALL, 0) . ' / ' . mb_internal_encoding() . ' / ' . implode(', ', mb_detect_order()),
                    'Zend engine version:' => zend_version(),
                    'PHP:' => phpversion(),
                    'Database:' => "$db[name], $db[version], Client charset: $db[charset]",
                    'HTTP server version' => $_SERVER['SERVER_SOFTWARE'],
                    'Visitors online:' => $sky->s_online,
                    'PHP NOW:' => NOW . ' (' . date_default_timezone_get() . '), ' . gmdate(DATE_DT) . ' (GMT)',
                    'SQL NOW:' => ($t = sql('+select $now')) . ' ' . (NOW == $t ? L::g('equal') : L::r('not equal')),
                    'Server localtime' => $ltime . ' ' . ($sky->date(NOW) . ' ' == $ltime ? L::g('equal') : L::r('not equal')),
                    'Cron layer last tick:' => (new Schedule)->n_cron_dt,
                    'Backup settings:' => sql('+select dt from $_memory where id=7'),
                    'Timestamp NOW:' => time(),
                    'Max timestamp:' => sprintf('%d (PHP_INT_MAX), GMT: %s', PHP_INT_MAX, gmdate(DATE_DT, PHP_INT_MAX)),
                    'Table `memory`, rows:' => sql('+select count(1) from $_memory'),
                    'Table `visitors`, rows:' => sql('+select count(1) from $_visitors'),
                    'Table `users`, rows:' => sql('+select count(1) from $_users'),
                    'E-Mail days count:' => $sky->s_email_cnt,
                    'func `shell_exec`:' => function_exists('shell_exec') ? L::g('exists') : L::r('not exists'),
                ], false);
                break;

            case 'Constants':
                $types = array_keys($ary = get_defined_constants(true));
                sort($types);
                unset($types[array_search('user', $types)]);
                $types = [-1 => 'user'] + $types;
                $t = isset($_GET['t']) ? intval($_GET['t']) : -1;
                $out = $ary[$types[$t]];
                ksort($out);
                echo Debug::out($out);
                $top .= sprintf($tpl, hidden(['main' => 1, 'id' => 1]), option($t, $types));
                break;

            case 'Functions':
                $mods = [];
                $types = array_keys($ary = get_defined_functions());
                $types = array_merge($types, array_filter($ext, function ($v) use (&$ary, &$mods) {
                    if (!$func = get_extension_funcs($v))
                        return false;
                    $ary[$v] = $func;
                    $mods += array_combine($func, array_pad([], count($func), $v));
                    return true;
                }));
                $idx = $types[$t = $_GET['t'] ?? array_search('user', $types)];
                if ('internal' == $idx) {
                    $ary[$idx] = array_map(function ($v) use (&$mods) {
                        return $v . ' (' . ($mods[$v] ?? '??') . ')';
                    }, $ary[$idx]);
                }
                $echo($ary[$idx], 'f');
                $top .= sprintf($tpl, hidden(['main' => 1, 'id' => 2]), option($t, $types));
                break;

            case 'Classes':
                $ary = Debug::get_classes(get_declared_classes(), $ext, $t = isset($_GET['t']) ? intval($_GET['t']) : -2);
                -1 != $t or $add_ext($ary[1], $ary[2]);
                $echo($ary[1]);
                $top .= sprintf($tpl, hidden(['main' => 1, 'id' => 3]), option($t, $ary[0])) . $priv;
                break;

            case 'Other':
                echo tag('Traits', '', 'h3');
                $ary = Debug::get_classes(get_declared_traits(), $ext, -1);
                $add_ext($ary[1], $ary[2]);
                $echo($ary[1], 't');
                echo tag('Interfaces', '', 'h3');
                $ary = Debug::get_classes(get_declared_interfaces(), $ext, -1);
                $add_ext($ary[1], $ary[2]);
                $echo($ary[1], 'i');
                echo tag('Loaded extensions, Dependencies, Classes/Functions/Constants', '', 'h3');
                $echo(array_map(function ($v) {
                    $e = new ReflectionExtension($v);
                    if ($d = $e->getDependencies()) {
                        array_walk($d, function (&$v, $k) {
                            $set = ['Conflicts' => 'r', 'Required' => 'g', 'Optional' => 'z'];
                            $v = call_user_func("L::$set[$v]", $k);
                        });
                        $v .= ('Phar' == $v ? ' <br>' : ' ') . implode(' ', $d);
                    }
                    return tag(count($e->getClassNames())
                        . '/' . count($e->getFunctions())
                        . '/' . count($e->getConstants()) . '&nbsp;') . " $v ";
                }, $ext), 'e');
                echo tag('Not loaded extensions', '', 'h3');
                $echo(array_map(function ($v) {
                    return tag('?') . " $v ";
                }, array_diff(Plan::php()->extensions, $ext)), 'e');
                $top .= $priv;
                break;
            case 'License': $fn = '/LICENSE';
            case 'Readme':
                //$fn = $menu[$i] == 'Readme';
                $file = file_get_contents(DIR_S . ($fn ?? '/README.md'));
                echo Display::md($file);
                break;
        }
        return $top;
    }

    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _phpinfo($i) {
        $menu = [-1 => 'ALL', 'General', 'Credits', 'Configuration', 'Modules', 'Environment', 'Variables', 'License'];
        printf('<iframe src="?main=INFO_%s" id="phpinfo"></iframe>', strtoupper($menu[$i]));
        return menu($i, $menu);
    }

    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _config($i) {
        global $sky;
        $menu = ['System', 'Cron', 'Install', $char = '/etc/'];
        $ary = [['s' => 8], ['n' => 9], ['i' => 11], []];
        foreach (SKY::$plans['main']['class'] as $class => $ware) {
            if (!method_exists($class, 'ghost'))
                continue;
            $menu[] = $class;
            $ary[] = $class::ghost();
        }
        $etc = 3 == $i;
        $edit = $etc ? isset($_GET['fn']) : !isset($_GET['show']);
        $show = $edit ? false : ($_GET['show'] ?? true);
        $TOP = menu($i, $menu, $sky->tpl_menu . ($edit ? '&edit' : '&show'), ' &nbsp; ');
        if ($etc) {
            $path = WWW . 'm/etc';
            if ($edit) {
                $_GET['fn'] = str_replace('/', '', $_GET['fn']);
                $ary = ['etc_file' => '' === $_GET['fn'] ? '' : file_get_contents("$path/$_GET[fn]")];
            } else {
                $ary = array_flip(Rare::list_path($path, 'is_file'));
                array_walk($ary, function(&$v, $k) use ($path) {
                    $size = filesize($k);
                    $v = date(DATE_DT, filemtime($k)) . ' ';
                    $v .= $size > 1024 ? "- $size bytes" : tag(a('edit &nbsp;', "?main=3&id=3&fn=" . substr($k, 1 + strlen($path))));
                });
            }
        } else {
            list ($imemo, $dt) = sqlf('-select imemo, dt from $_memory where id=%d', $id = current($ary = $ary[$i]));
            $ary = $sky->memory($id, $char = key($ary));
            $edit or array_walk($ary, function(&$v, $k) use ($i, $sky) {
                $v = html($v) . (DEV && '_dev' == $sky->_0 ? tag(a('drop &nbsp;', "?main=3&id=$i&show=$k")) : '');
            });
            $str = 'This action can damage application. Are you sure drop variable';
            if ($show)
                echo tag("$str `" . tag($show, 'cid="' . "$char.$id" . '"', 'span') . "`?", 'id="drop-var"');
            $pad = pad();
            $TOP .= $pad . "imemo=$imemo, dt=$dt$pad" . a($edit ? 'Show' : 'Edit', "?main=3&id=$i&" . ($edit ? 'show' : 'edit'));
        }

        if ($_POST) {
            if ($etc) {
                $fn = "$path/" . (isset($_POST['fn']) ? $_POST['fn'] : $_GET['fn']);
                file_put_contents($fn, $_POST['etc_file']);
            } elseif ($_POST['-t-'] ?? false) {
                unset($_POST['-t-']);
                Plan::_p(['main', 'cron.times'], unbang($_POST));
            } else {
                $ary = $_POST + $ary;
                ksort($ary);
                SKY::$char($ary);
            }
            isset($_POST['fn']) ? jump('?main=3&id=3') : jump(URI, false);
        }

        if (!$edit) {
            echo Debug::out($ary, false);
            if ($etc)
                echo '<br>' . a('Write new file', '?main=3&id=3&fn');
            return $TOP;
        }

        switch ($char) {
            case 's':
                $form = yml('+ @inc(system)');
            break;
            case 'n':
                $form = yml('+ @inc(cron)');
            break;
            case '/etc/':
                $form = '' === $_GET['fn'] ? ['fn' => ['New filename', '']] : ["<b>$_GET[fn]</b><br>"];
                $form += ['etc_file' => ['', 'textarea', 'style="width:90%" rows="20"']];
                break;
            case 'i':
                $form = [];
            break;
            default:
                $class = $menu[$i];
                $form = $class::ghost(true);
            break;
        }
        $form && print(tag(Form::A($ary, $form + [-2 => ['Save', 'submit']]), 'style=""'));
        if (1 == $i && ($times = Plan::_gq(['main', 'cron.times']))) {
            echo tag('cron.times', 'style="padding-left:270px"', 'h1');
            $form = ['-t-' => 1];
            $ary = bang(unl(trim($times)));
            foreach ($ary as $k => $v)
                $form[$k] = ["<b>$k</b>", ''];
            echo tag(Form::A($ary, $form + [-2 => ['Save', 'submit']]), '');
        }
        return $TOP;
    }

    static function post() {
        if (isset($_POST['extra'])) {
            $html = file_get_contents($url = $_POST['extra']);
            $url = DOMAIN . urlencode($u = substr($url, strlen(HOME)));
            is_dir('var/extra') or mkdir('var/extra');
            'main/error' == $u
                ? Plan::mem_p(['main', 'error.html'], $html)
                : file_put_contents("var/extra/$url.html", $html);
        } else foreach ($_POST['id'] as $file) {
            if (strpos($file, ':')) {
                [$plan, $file] = explode(':', $file);
                call_user_func("Plan::{$plan}_d", ['main', basename($file)]);
            } else {
                unlink($file);
            }
        }
        jump(URI);
    }

    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _cache($i = null) {
        global $sky;

        $menu = ['Hard cache', 'Jet cache', 'Gate cache', 'Extra cache', 'Static cache'];
        '_dev' == $sky->_0 or $menu += [5 => 'Drop ALL cache'];
        $right = $dir = false;
        switch ($i) {
            case 3:
                $dir = 'var/extra';
            case 2:
            case 1:
            case 0:
                $_POST && self::post();
                $ary = ['cache', 'jet', 'gate', ''];
                if ($name = $ary[(int)$i]) {
                    $obj = call_user_func("Plan::{$name}_obj", ['main']);
                    $dc = $obj->dc->type;
                    if ('File' == $dc) {
                        $dir = $obj->path;
                        $right = "<b>Driver: File</b>";
                    } else {
                        $keys = call_user_func("Plan::{$name}_b", ['main', '*']);
                        $info = $obj->dc->info()['str'];
                        $right = "<b>Driver: <r>$info</r></b>";
                    }
                }
                $dc = isset($dc) && 'File' != $dc ? "$name:" : '';
                $mtime = function ($fn) use ($name) {
                    $ts = call_user_func("Plan::{$name}_m", ['main', basename($fn)]);
                    return !$ts ? 'permanent' : date(DATE_DT, $ts);
                };
                if (!$dir || is_dir($dir)) {
                    $glob = $dir ? glob("$dir/*") : $keys;
                    if (3 == $i && Plan::mem_t('error.html'))
                        $glob[] = Plan::mem_obj(['main'])->path . '/error.html';
                    $files = [];
                    foreach ($glob as $k) {
                        $tinfo = $dir ? date(DATE_DT, stat($k)['mtime']) : $mtime($k);
                        $v = tag(sprintf(TPL_CHECKBOX . ' ', $dc . $k, '') . $tinfo, '', 'label');
                        $files["<span>$k</span>"] = $v;
                    }
                    if ($files) {
                        echo '<form method="post">';
                        Debug::out($files, false, '');
                        echo '<br>' . js('var x=0;') . a('[un]check all', "javascript:$('#table input').prop('checked',x=!x)");
                        echo pad() . hidden() . '<input type="submit" value="delete checked" /></form>';
                    } else {
                        echo '<h1>Cache dir is empty</h1>';
                    }
                } else {
                    echo '<h1>Cache dir is absent</h1>';
                }
                if (3 == $i)
                    echo view('_std.save_cache', []);
            break;
            case 4:
                if ($_POST) {
                    $s = substr($sky->s_statp, 0, -1) + 1;
                    $sky->s_statp = $s > 9999 ? '1000p' : $s . 'p';
                    jump(URI);
                }
                echo Form::A(['pre' => "$sky->s_statp/*.js |.css"], [
                    'pre' => ['Current prefix for static cache:<br>(increment automatically)', '', 'disabled'],
                    ['Made new prefix', 'submit', 'name=u'],
                ]);
                $files = bang(SKY::d('statics'), function(&$a, $v) {
                    [$k, $v] = explode('#', $v, 2);
                    $a["<span>$k</span>"] = date(DATE_DT, $v);
                }, ',');
                Debug::out($files, false, '');
            break;
            case 5:
                Debug::drop_all_cache();
                jump('?main=4');
            break;
        }
        return menu($i, $menu) . tag($right, 'style="margin-left:35px"', 'span');
    }

    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _guard($i = null) {
        global $sky;

        if (isset($_POST['guard_exc'])) {
            SKY::a($_POST);
            jump(URI);
        }
        $cont = 1 == $i ? "deny from all\n" : "<html><body>Forbidden folder</body></html>\n";
        $file = 1 == $i ? '.htaccess' : 'index.htm';
        if ($_POST) {
            foreach ($_POST['id'] as $dir) file_exists("$dir/$file") or file_put_contents("$dir/$file", $cont);
            jump(URI);
        }
        $dirs = Rare::walk_dirs(WWW ? rtrim(WWW, '/') : '.', preg_split("/\s+/", $sky->a_guard_exc)); # [^\w\/\.]+
        $out = array_flip(array_filter($dirs, function($v) {
            return !DEV || '_' != $v[0];
        }));
        echo 'Exclude paths: <form method="post"><br>';
        printf('<input name="guard_exc" value="%s" size="75%%" /><input type="submit" value="save" />%s</form>', $sky->a_guard_exc, hidden());
        echo '<h1>Directories:</h1><form method="post">';
        $size = strlen($cont);
        switch ($i) {
            case 0:
            case 1:
                $ok = '<img src="_img?ok2" />';
                foreach ($out as $path => $j) if ('_' == $path[0]) $out[$path] = L::r('Dir must NOT exist on production!'); else {
                    if (is_file($fn = "$path/$file")) {
                        $fs = filesize($fn);
                        $out[$path] = "$ok - " . ($fs == $size ? "$fs bytes" : L::r("$fs bytes"));
                    } else {
                        $out[$path] = tag(sprintf(TPL_CHECKBOX . ' ', $path, '') . "$file file not exists", '', 'label');
                    }
                }
            break;
            case 2: echo "\n\n\n2do - check & fix all PHP files for code: defned('STRT... or eval(\$me... or die;";
            break;
        }
        Debug::out($out, false);
        echo '<br>' . js('var x=0;') . a('[un] check all', "javascript:$('#table input').prop('checked',x=!x)");
        echo pad() . hidden() . '<input type="submit" value="write checked" /></form>';

        return menu($i, ['index.htm', '.htaccess']);
    }

    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _databases($i = null) {
        global $sky;

        [$ware, $name] = explode('::', $db = $sky->_4 ?: 'main::core');
        $tpl = "?main=6&db=$db&dt=";
        $act = (int)!$sky->_6;
        $TOP = a('Summary', $tpl, $sky->_6 ? '' : 'class="active"');
        $TOP .= '<b style="margin:0 10px 0 30px">Migrations:</b> ';
        $out = $sel = [];
        if ($list = Plan::mem_b(['main', "migration_*_{$ware}_{$name}.sql"])) {
            $list = array_reverse($list);
            array_walk($list, function($v) use (&$TOP, &$sel, $tpl, $sky, &$act) {
                static $i = 0;
                //list($year, $month, $day) = sscanf(substr($v, -14, 10), "%d-%d-%d");
                $d = date('j M Y', $ts = strtotime(substr(basename($v), 10, 10)));
                if ($i++ < 3) {
                    $TOP .= ' ' . a($d, $tpl . $ts, ($q = $ts == $sky->_6) ? 'class="active"' : '');
                    $act |= (int)$q;
                } else {
                    $sel[$tpl . $ts] = $d;
                }
            });
            if ($sel) {
                $style = 'style="font-size:12px" onchange="location.href=$(this).val()"';
                $sel = option(substr(URI, 4), [$tpl => '..other days'] + $sel);
                $TOP .= a(tag($sel, $style, 'select'), '#', ($act ? '' : 'active ') . 'style="margin-left:30px;"');
            }
        } else {
            $TOP .= '<u>none</u>';
        }
        if (!$sky->_6) {
            $databases = cfg([$ware])->databases;
            $DSN = $databases['core' == $name ? '' : $name]['dsn'] ?? $databases['dsn'] ?? L::g('DSN erased at that point');
            if (!DEV)
                $DSN = L::r('Production');
            try {
                $dd = SQL::open($name, $ware);
            } catch (Throwable $e) {
                $dd = false;
            }
            if ($dd) {
                $info = $dd->info();
                Debug::out([
                    'Server' => 'Version: ' . $info['version'] . ', Charset: ' . $info['charset'],
                    'Driver, DSN' => "$dd->name, $DSN",
                    'Table\'s prefix' => $dd->pref ?: L::g('no prefix'),
                    'Tables count' => count($info['tables']),
                    'Migrations days' => count($list),
                    'Last exec migration' => '',
                ], 0);
                if (!$list = $info['tables']) {
                    echo '<h1>No tables in this database</h1>';
                } else {
                    $i = 0;
                    echo th([-2 => '##', -1 => 'Table'] + array_keys(current($list)), 'id="table"');
                    foreach ($list as $k => $v)
                        echo td([-1 => [1 + $i, 'style="width:5%"'], -2 => $k] + array_values($v), eval(zebra));
                    echo '</table>';
                }
            } else {
                echo "<h1>Can't connect to the database. DSN: $DSN</h1>";
            }
        } else {
            $out = explode("\n", unl(trim(Plan::mem_g('migration_' . date("Y-m-d", $sky->_6) . "_{$ware}_{$name}.sql"))));
            $i = 0;
            echo th(['#', 'SQL'], 'id="table"');
            foreach ($out as $v)
                echo td([[1 + $i, 'style="width:5%"'], pre(html(escape($v, true)))], eval(zebra));
            echo '</table>';
        }
        return $TOP;
    }
}
