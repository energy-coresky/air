<?php

class Root
{
    static $menu1 = [1 => 'Overview', 'phpinfo()', 'Config', 'Cache', 'Guard', 'Database'];
    static $menu2 = [7 => 'Special', 'Log CRON', 'Log CRASH', 'Log ERROR'];
    static $h4 = ['',
        'OVERVIEW SYSTEM INFORMATION',
        'PHP CORE INFORMATION',
        'SYSTEM CONFIGURATION',
        'SYSTEM CACHE',
        'SYSTEM GUARD PAGE',
        'DATABASE MIGRATIONS',
        'USER LOG',
        'LOG CRON',
        'LOG CRASH',
        'LOG ERROR',
    ];
    
    const js = 'http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js';

    static function admin($sky) {
        $files = array_map(function ($v) {
            return substr(basename($v), 1, -4);
        }, glob('admin/_*.php'));
        $files = array_merge(array_splice($files, array_search('main', $files), 1), $files);
        $buttons = implode("\t", array_map('ucfirst', $files));
        $root_access = ceil(count($files) / 7) . "\t" . implode("\t", array_keys($files));
        SKY::a('menu', serialize([-2 => $uris = implode("\t", $files), -1 => $buttons, $uris, $root_access]));
        $sky->is_front or jump('?main=0');
    }

    static function dev() {
        global $sky;

        $val = explode(' ', $sky->s_version) + ['', '', '0', 'APP'];
        $val[2] += 0.0001;
        $key = [0, 1, 'ver', 'app'];
        $form1 = [
            ['Application', [
                'app' => ['', '', 'size="11"'],
                'ver' => ['', 'number', 'style="width:77px" step="0.0001"'],
                [tag(SKY::version()['app'][3] . ' from ' . date('c', SKY::version()['app'][0])), 'ni'],
            ]],
            ['Core', 'ni', SKY::CORE],
            ['Save', 'submit'],
        ];

        $br = '<br>' . str_repeat(' &nbsp;', 7) .'other CONSTANTS see in the ' . a('Admin section', 'adm?main=0&id=4');
        $phpman = [
            'en' => 'English',
            'pt_BR' => 'Brazilian Portuguese',
            'zh' => 'Chinese (Simplified)',
            'fr' => 'French',
            'de' => 'German',
            'ja' => 'Japanese',
            'ru' => 'Russian',
            'es' => 'Spanish',
            'tr' => 'Turkish',
        ];
        $form2 = [
            'dev' => ['Set debug=0 for DEV-tools', 'chk'],
            'var' => ['Show Vars in the tracing', 'radio', ['none', 'from Globals', 'from Templates']],
            'sql' => ['Show SQLs in the tracing', 'chk'],
            'const' => ['Show user-defined global CONSTANTs in the tracing,' . $br, 'chk'],
            'class' => ['Show CLASSEs', 'chk'],
            'cron'  => ['Run cron when click on DEV instance', 'chk'],
       //     ['', [['See also ' . a('Admin\'s configuration', 'adm?main=2') . ' settings', 'li']]],
            Form::X([], '<hr>'),
            ['Check static files for changes (file or path to *.js & *.css files), example: `m,C:/web/air/assets`', 'li'],
            'static' => ['', '', 'size="50"'],
            'lgt' => ['Language table name', '', 'size="25"'],
            'trans' => ['Language class mode', 'radio', ['manual edit', 'auto-detect items', 'translation api ON']],
            'manual' => ['PHP manual laguage', 'select', $phpman],
            'se' => ['Search engine tpl', '', 'size="50"'],
            //'se4so' => ['SE for stackoveflow site', '', 'size="50"'],
            ['Save', 'submit'],
        ];
        if (isset($_POST['app'])) {
            SKY::s('version', time() . ' ' . SKY::version()['core'][0] . " $_POST[ver] $_POST[app]");
        } elseif ($_POST) {
            SKY::d($_POST);
        }
        return [
            'form1' => Form::A(array_combine($key, $val), $form1),
            'form2' => Form::A(SKY::$mem['d'][3], $form2),
            'h4' => '',
        ];
    }

    static function run($n, $id) {
        if ($n < 7) {
            define('TPL_MENU', "?main=$n&id=%d");
            $funs = array_map('strtolower', Root::$menu1);
            $funs[2] = substr($funs[2], 0, 7);
            return call_user_func(['Root', '_' . $funs[$n]], $id);
        }
        $cr = [7 => 10, 2, 11, 4];
        echo '<pre>' . sqlf('+select tmemo from $_memory where id=%d', $cr[$n]) . '</pre>';
    }

    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _overview($i) {
        global $sky;

        $menu = ['Summary', 'Constants', 'Functions', 'Classes', 'Other'];
        $top = menu($i, $menu);
        $ml = 'style="margin-left:55px;"';
        $tpl = '<span ' . $ml . '><u>Extension</u></span> &nbsp; <form>%s<select name="t" id="sm-select">%s</select></form>';
        $priv = tag('<input ' . ($sky->d_pp ? 'checked ' : '') . $ml . ' type="checkbox" onchange="sky.d.pp(this)"> show private & protected', '', 'label');
        $ext = get_loaded_extensions();
        sort($ext);
        $echo = function ($ary, $t = 'c') {
            'e' == $t or sort($ary);
            $val = array_map(function ($v) use ($t) {
                global $sky;
                if ('e' == $t)
                    $v = explode(' ', $v)[2];
                $sky->d_manual or $sky->d_manual = 'en';
                $sky->d_se or $sky->d_se = 'https://yandex.ru/search/?text=%s';
                $vv = str_replace('_', '-', strtolower($v));
                $q = ('f' == $t ? 'function.' : ('e' == $t ? 'book.' : 'class.')) . $vv;
                $m = a('manual', "https://www.php.net/manual/$sky->d_manual/$q.php", 'target="_blank"');
                $s = a('stackoverflow', sprintf($sky->d_se, urlencode("php $v site:stackoverflow.com")), 'target="_blank"');
                return a('show', ["sky.d.reflect(this, '$t')"]) . " | $m | $s";
            }, $ary);
            echo Admin::out(array_combine($ary, $val), false);
        };
        switch ($menu[$i]) {
            case 'Summary':
                $_exec = function_exists('shell_exec') ? 'shell_exec' : function ($arg, $default = false) {
                    return $default === false ? $arg : $default;
                };

                $ltime = PHP_OS == 'WINNT' ? preg_replace("@[\r\n\t ]+@", ' ', $_exec('date /t & time /t')) : $_exec('date'); # 2do - MAC PC
                $utime = PHP_OS == 'WINNT' ? preg_replace("@^.*?([\d /\.:]{10,}( [A|P]M)?).*$@s", '$1', $_exec('net stats srv')) : $_exec('uptime');
                $db = SKY::$dd->info();
                echo Admin::out([
                    'Default site title' => $sky->s_title,
                    'Primary configuration' => sprintf('DEV = %d, DEBUG = %d, DIR_S = %s, ENC = %s, PHPDIR = %s', DEV, DEBUG, DIR_S, ENC, PHP_BINDIR),
                    'System' => PHP_OS == 'WINNT' ? 'WINNT' : $_exec('uname -a', PHP_OS),
                    'Server IP' => $_SERVER['SERVER_ADDR'] ?? '::1',
                    'Server localtime' => $ltime . ' ' . ($sky->date(NOW) . ' ' == $ltime ? sprintf(span_g, 'equal') : sprintf(span_r, 'not equal')),
                    'Server uptime' => $utime,    # $_exec('uptime')
                    'Zend engine version:' => zend_version(),
                    'PHP:' => phpversion(),
                    'Database:' => "$db[name], $db[version], Client charset: $db[charset]",
                    'HTTP server version' => $_SERVER['SERVER_SOFTWARE'],
                    'Visitors online:' => $sky->s_online,
                    'PHP NOW:' => NOW . ' (' . PHP_TZ . '), ' . gmdate(DATE_DT) . ' (GMT)',
                    'SQL NOW:' => ($t = sql('+select $now')) . ' ' . (NOW == $t ? sprintf(span_g, 'equal') : sprintf(span_r, 'not equal')),
                    'Cron layer last tick:' => (new Schedule)->n_cron_dt,
                    'Backup settings:' => sql('+select dt from $_memory where id=7'),
                    'Timestamp NOW:' => time(),
                    'Max timestamp:' => sprintf('%d (PHP_INT_MAX), GMT: %s', PHP_INT_MAX, gmdate(DATE_DT, PHP_INT_MAX)),
                    'Table `memory`, rows:' => sql('+select count(1) from $_memory'),
                    'Table `visitors`, rows:' => sql('+select count(1) from $_visitors'),
                    'Table `users`, rows:' => sql('+select count(1) from $_users'),
                    'E-Mail days count:' => $sky->s_email_cnt,
                    'func `shell_exec`:' => function_exists('shell_exec') ? sprintf(span_g, 'exists') : sprintf(span_r, 'not exists'),
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
                echo Admin::out($out);
                $top .= sprintf($tpl, hidden(['main' => 1, 'id' => 1]), option($t, $types));
                break;

            case 'Functions':
                $types = array_keys($ary = get_defined_functions());
                $types = array_merge($types, array_filter($ext, function ($v) use (&$ary) {
                    if (!$func = get_extension_funcs($v))
                        return false;
                    $ary[$v] = $func;
                    return true;
                }));
                $t = isset($_GET['t']) ? intval($_GET['t']) : array_search('user', $types);
                $echo($ary[$types[$t]], 'f');
                $top .= sprintf($tpl, hidden(['main' => 1, 'id' => 2]), option($t, $types));
                break;

            case 'Classes':
                $t = isset($_GET['t']) ? intval($_GET['t']) : -2;
                $all = get_declared_classes();
                $ary = [];
                $types = array_filter($ext, function ($v) use (&$ary, $t) {
                    if (!$cls = (new ReflectionExtension($v))->getClassNames())
                        return false;
                    $t < 0 ? ($ary = array_merge($ary, $cls)) : $ary[$v] = $cls;
                    return true;
                });
                $types = [-1 => 'all', -2 => 'user'] + $types;
                $echo(-2 == $t ? array_diff($all, $ary) : (-1 == $t ? $all : array_intersect($all, $ary[$types[$t]])));
                $top .= sprintf($tpl, hidden(['main' => 1, 'id' => 3]), option($t, $types)) . $priv;
                break;

            case 'Other':
                echo tag('Traits', '', 'h3');
                $echo(get_declared_traits(), 't');
                echo tag('Interfaces', '', 'h3');
                $echo(get_declared_interfaces(), 'i');
                echo tag('Loaded extensions, Dependencies, Constants/Functions/Classes', '', 'h3');
                $echo(array_map(function ($v) {
                    $e = new ReflectionExtension($v);
                    if ($d = $e->getDependencies()) {
                        array_walk($d, function (&$v, $k) {
                            $set = ['Conflicts' => span_r, 'Required' => span_g, 'Optional' => span_b];
                            $v = sprintf($set[$v], $k);
                        });
                        $v .= ('Phar' == $v ? ' <br>' : ' ') . implode(' ', $d);
                    }
                    return tag(count($e->getConstants())
                        . '/' . count($e->getFunctions())
                        . '/' . count($e->getClassNames()) . '&nbsp;') . " $v ";
                }, $ext), 'e');
                $top .= $priv;
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
        $menu = ['System', 'Admin', 'Cron', '/etc/'];
        $etc = 3 == $i;
        $edit = $etc ? isset($_GET['fn']) : !isset($_GET['show']);
        $show = $edit ? false : ($_GET['show'] ?? true);
        $TOP = menu($i, $menu, TPL_MENU . ($edit ? '&edit' : '&show'), ' &nbsp; ');
        if ($etc) {
            $char = 'f';
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
           new Admin;
            $ary = [['s' => 3], ['a' => 8], ['n' => 9]];
            list ($imemo, $dt) = sqlf('-select imemo, dt from $_memory where id=%d', $id = current($ary = $ary[$i]));
            $ary = $sky->memory($id, $char = key($ary));
            $edit or array_walk($ary, function(&$v, $k) use ($i, $sky) {
                $v = html($v) . (DEV && '_dev' == $sky->_0 ? tag(a('drop &nbsp;', "?main=3&id=$i&show=$k")) : '');
            });
            $str = 'This action can damage application. Are you sure drop variable';
            if ($show)
                echo tag("$str `" . tag($show, 'cid="' . "$char.$id" . '"', 'span') . "`?", 'id="drop-var"');
            $TOP .= pad() . "imemo=$imemo, dt=$dt" . pad() . a($edit ? 'Show' : 'Edit', "?main=3&id=$i&" . ($edit ? 'show' : 'edit'));
        }

        if ($_POST) {
            if ($etc) {
                $fn = "$path/" . (isset($_POST['fn']) ? $_POST['fn'] : $_GET['fn']);
                file_put_contents($fn, $_POST['etc_file']);
            } else {
                $ary = $_POST + $ary;
                if ('s' == $char) {
                    $chk = ['trace_single', 'trace_cli', 'prod_error', 'trace_root', 'error_403', 'error_404', 'quiet_eerr', 'crash_log', 'dev_cron', 'jet_prod'];
                    foreach ($chk as $v) $ary[$v] = (int)isset($_POST[$v]);
                }
                ksort($ary);
                SKY::$char($ary);
            }
            isset($_POST['fn']) ? jump('?main=3&id=3') : jump(URI);
        }

        if (!$edit) {
            echo Admin::out($ary, false);
            if ($etc)
                echo '<br>' . a('Write new file', '?main=3&id=3&fn');
            return $TOP;
        }

        switch ($char) {
            case 's':
                $form = Root::form_conf();
                break;
            case 'a':
                $form = [
                    'qq' => ['', 'QQ', '', '+2:00'],
                ];
                break;
            case 'n':
                $form = [
                    'clear_nc' => ['Visitor\'s cleaning (no cookie), days', '', '', 2],
                    'clear_hc' => ['Visitor\'s cleaning (has cookie), days', '', '', 10],
                    'clear_ua' => ['Visitor\'s cleaning (authed), days', '', '', 1000],
                    'www'   => ['Index file directory', '', '', 'web~public_html'],
                ];
                break;
            default: 
                $form = '' === $_GET['fn'] ? ['fn' => ['New filename', '']] : ["<b>$_GET[fn]</b><br>"];
                $form += ['etc_file' => ['', 'textarea', 'style="width:90%" rows="20"']];
            break;
        }
        echo tag(Form::A($ary, $form + [-2 => ['Save', 'submit']]), 'style="width:75%"');
        return $TOP;
    }

    static function form_conf() {
        return [
            '<fieldset><legend>Primary settings</legend>',
                ['', [['<b><u>Production</u></b>', 'li']]],
                'trace_root'    => ['Debug mode on production for `root` profile', 'checkbox'],
                'trace_single'  => ['Single-click tracing on production to `X-tracing`', 'checkbox'],
                ['', [['<b><u>Production & DEV</u></b>', 'li']]],
                'trace_cli'     => ['Use X-tracing for CLI', 'checkbox'],
                'error_404'     => ['Use `Stop` on "return 404"', 'checkbox'],
                'quiet_eerr'    => ['No `Exception` in Log CRASH', 'checkbox'],
                'error_403'     => ['Use 403 code for `die`', 'checkbox'],
                'prod_error'    => ['Use Log ERROR', 'checkbox'],
                'crash_log'     => ['Use Log CRASH', 'checkbox'],
       #         ['', [['<br>See also ' . a('DEV instance', '_dev') . ' settings', 'li']]],
            '</fieldset>',
            '<fieldset><legend>Visitor\'s & users settings</legend>',
                'c_manda'   => ['Hide all content if no cookies', 'radio', ['No', 'Yes']],
                'j_manda'   => ['Hide all content if no javascripts', 'radio', ['No', 'Yes']],
                'c_name'    => ['Cookie name', '', '', 'sky'],
                'c_upd'     => ['Cookie updates, minutes', '', '', 60],
                'visit'     => ['One visit break after, off minutes', '', '', 5],
                'reg_req'   => ['Users required for registrations', 'radio', ['Both', 'Login', 'E-mail']],
            '</fieldset>',
            '<fieldset><legend>Cache & Jet settings</legend>',
                'cache_act' => ['Hard cache', 'radio', ['Off', 'On']],
                'cache_sec' => ['Default TTL, seconds', '', '', 300],
                'red_label' => ['Red label', 'radio', ['Off', 'On'], 1],
                ['', [['<b><u>The Jet compiller</u></b>', 'li']]],
                'jet_cact' => ['Jet cache', 'radio', ['Off', 'On'], 1],
         #       'jet_swap' => ['Swap @inc & @require commands', 'checkbox'],
          #      'jet_prod' => ['Recompile Jet-files on production when edit tpls', 'checkbox'],
        #       'jet_0php' => ['Allow native PHP', 'checkbox'],
        #       'jet_1php' => ['Allow @php Jet command', 'checkbox'],
            '</fieldset>',
        ];
    }

    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _cache($i = null) {
        global $sky;

        $menu = ['Hard cache', 'Jet cache', 'Gate cache', 'Extra cache', 'Static cache'];
        '_dev' == $sky->_0 or $menu += [5 => 'Drop ALL cache'];
        switch ($i) {
            case 3:
                $extra = tag(is_file($fn = 'var/extra.txt') ? html(file_get_contents($fn)) : '', 'rows="20" cols="70" name="extra"', 'textarea');
                $extra .= '<br>TTL: <input name="ttl" value="' . $sky->s_extra_ttl . '"/>';
                $extra .= ' <input type="submit" value="save"/>' . hidden();
                echo tag('EXTRA=' . EXTRA . ', extra.txt:<br><form method="post">' . "$extra</form>", 'class="fl"');
            case 0:
            case 1:
            case 2:
                $ary = ['var/cache', 'var/jet', 'var/gate', 'var/extra'];
                if (is_dir($path = $ary[(int)$i])) {
                    if (isset($_POST['extra'])) {
                        file_put_contents('var/extra.txt', $_POST['extra']);
                        $sky->s_extra_ttl = (int)$_POST['ttl'];
                        jump(URI);
                    } elseif ($_POST) {
                        foreach ($_POST['id'] as $file)
                            unlink($file);
                        jump(URI);
                    }
                    $files = array_flip(glob("$path/*"));
                    foreach ($files as $k => $v) {
                        $s = stat($k);
                        $files[$k] = tag(sprintf(TPL_CHECKBOX . ' ', $k, '') . date(DATE_DT, $s['mtime']), '', 'label');
                    }
                    if ($files) {
                        echo '<div class="fl"><form method="post">';
                        Admin::out($files, false);
                        echo '<br>' . js('var x=0;') . a('[un]check all', "javascript:$('#table input').prop('checked',x=!x)");
                        echo pad() . hidden() . '<input type="submit" value="delete checked" /></form></div>';
                    } else {
                        echo '<h1>Cache dir is empty</h1>';
                    }
                } else {
                    echo '<h1>Cache dir is absent</h1>';
                }
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
            break;
            case 5:
                Admin::drop_all_cache();
                jump('?main=4');
            break;
        }
        return menu($i, $menu);
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
        printf('<input name="guard_exc" value="%s" size="75%%" /><input type="submit" value="save" />%s</form>', @$adm->a_guard_exc, hidden());
        echo '<h1>Directories:</h1><form method="post">';
        $size = strlen($cont);
        switch ($i) {
            case 0:
            case 1:
                $ok = '<img src="img/ok.gif" />';
                foreach ($out as $path => $j) if ('_' == $path[0]) $out[$path] = sprintf(span_r, 'Dir must NOT exist on production!'); else {
                    if (is_file($fn = "$path/$file")) {
                        $fs = filesize($fn);
                        $out[$path] = "$ok - " . ($fs == $size ? "$fs bytes" : sprintf(span_r, "$fs bytes"));
                    } else {
                        $out[$path] = tag(sprintf(TPL_CHECKBOX . ' ', $path, '') . "$file file not exists", '', 'label');
                    }
                }
            break;
            case 2: echo "\n\n\n2do - check & fix all PHP files for code: defned('STRT... or eval(\$me... or die;";
            break;
        }
        Admin::out($out, false);
        echo '<br>' . js('var x=0;') . a('[un] check all', "javascript:$('#table input').prop('checked',x=!x)");
        echo pad() . hidden() . '<input type="submit" value="write checked" /></form>';

        return menu($i, ['index.htm', '.htaccess']);
    }


    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _database($i = null) {
        global $sky;

        if (is_file($fn = 'var/update.sql')) {
            $str = trim(file_get_contents($fn));
            ';' == substr($str, -1) or $str .= ';';
            preg_match_all("/(?>[^;']|(''|(?>'([^']|\\')*[^\\\]')))+;/ixU", $str, $m, PREG_SET_ORDER);
            array_walk($m, function(&$v) {
                $v = $v[0];
            });
            Admin::out($m, false);
        }
        return ' ' . a('Migrations', "?main=6&");
    }

    static function start() {
        global $sky;

        if ($sky->s_init_needed) {
            $js = WWW . 'm/' . basename(Root::js);
            file_exists($js) or file_put_contents($js, file_get_contents(Root::js)) or exit("Cannot save `$js`");
            Root::init_reset(sql('+select tmemo from memory where id=5'));
            SKY::s('init_needed', null); # run once only
        }
    }

    static function init_reset($code) {
        if (!DEV)
            return;

        foreach (explode("\n", unl($code)) as $line) {
            if ($line) list($key, $val) = explode(' ', $line, 2); else continue;
            switch ($key) {
                case 'htaccess':
                    foreach (explode(' ', $line) as $dir)
                        if ($dir && file_exists($dir) && !file_exists($file = "$dir/.htaccess"))
                            file_put_contents($file, "deny from all\n");
                break;
                case 'index':
                    foreach (explode(' ', $line) as $dir)
                        if ($dir && file_exists($dir) && !file_exists($file = "$dir/index.htm"))
                            file_put_contents($file, "<html><body>Forbidden folder</body></html>\n");
                break;
                case 'php':
                    if (is_numeric($val)) {
                        $php = sqlf('+select tmemo from $_memory where id=%d union select 0 as tmemo', $val)
                            and sqlf('delete from $_memory where id=%d', $val)
                            and eval($php);
                    } elseif (is_file($val)) {
                        require $val;//req
                    }
                break;
            }
        }
    }

    static function dummy_txt($chars = 0) {
        $s = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua';
        $ary = explode(' ', $s);
        for ($j = 0, $str = '', $cnt = rand(2, 10); $chars ? true : $j < $cnt; $j++) {
            $str .= $chars ? $ary[rand(0, count($ary) - 1)] . ' ' : "<p>$s</p>";
            if ($chars && strlen($str) > $chars) return substr($str, 0, $chars);
        }
        return $str;
    }

    static function dummy_gen($table, $size = null) {
        $ary = [];
        $txt = 0;
        foreach (sql('@explain $_' . ($table = preg_replace("/`/u", '', $table))) as $c => $r) {
            $ary[$c] = "return ";
            if ('auto_increment' == $r[4]) $ary[$c] .= "null;";
            elseif (preg_match("/^[a-z]*text$/i", $r[0])) $ary[$c] .= 'Root::dummy_txt();' and $txt = 1;
            elseif ('datetime' == $r[0]) $ary[$c] .= 'date(DATE_DT, time() - rand(0, 3600 * 24 * 7));';
            elseif (preg_match("/varchar\((\d+)\)/", $r[0], $m)) $ary[$c] .= "Root::dummy_txt($m[1] > 15 ? rand(9, $m[1] - 5) : $m[1]);";
            elseif ('YES' == $r[1]) $ary[$c] .= "null;";
            elseif (!is_null($r[3])) $ary[$c] .= "'$r[3]';";
            else $ary[$c] .= "rand(0, 9);";
        }
        if (is_null($size)) $size = $txt ? 300 : 5;
        for ($i = 0; $i < $size; $i++) {
            $ins = $ary;
            foreach ($ins as &$v) $v = eval($v);
            sql('insert into $_` @@', $table, $ins);
        }
    }
}
