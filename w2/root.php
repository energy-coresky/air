<?php

# For Licence and Disclaimer of this code, see https://coresky.net/license

class Root
{
    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _overview($i) {
        global $sky;
        $menu = ['Summary', 'Objects', 'Functions', 'Variables', 'Constants'];
        $top = menu($i, $menu);
        switch ($menu[$i]) {
            case 'Summary':
                $_exec = function_exists('shell_exec') ? 'shell_exec' : function ($arg) {
                    return $arg;
                };

                $ltime = PHP_OS == 'WINNT' ? preg_replace("@[\r\n\t ]+@", ' ', $_exec('date /t & time /t')) : $_exec('date'); # 2do - MAC PC
                $utime = PHP_OS == 'WINNT' ? preg_replace("@^.*?([\d /\.:]{10,}( [A|P]M)?).*$@s", '$1', $_exec('net stats srv')) : $_exec('uptime');
                $db = SKY::$dd->info();
                echo Admin::out([
                    'Default site title' => $sky->s_title,
                    'Primary configuration' => sprintf('DEV = %d, DEBUG = %d, ENC = %s, PHPDIR = %s', DEV, DEBUG, ENC, PHP_BINDIR),
                    'System' => PHP_OS == 'WINNT' ? 'WINNT' : $_exec('uname -a'),
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
//                    'Cron layer last tick:' => (new Schedule)->n_cron_dt,
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

            case 'Objects':
            case 'Variables':
                $out = [];
                $list_vars = 3 == $i;
                $_out = get_defined_vars();
                array_splice($_out, 0, 5);
                $_out = array_filter($_out, function($v) use ($list_vars) {
                    return (bool)($list_vars ^ is_object($v));
                });
      //trace(get_defined_vars(),'xxx');
                foreach ($_out as $k => $v) if (!$list_vars) {
                    $methods = get_class_methods($v);
                    $vars = get_object_vars($v);
                    $v = '<pre>' . implode("</b>()\n", array_values($methods)) . "\n--\n$" . implode("\n$", array_keys($vars));
                    $out[$k] = "$v</pre>";
                } else {
                    is_string($v) or is_int($v) or $v = print_r($v, true);
                    $v = html($v);
                    if (strlen($v) > 500) $v = substr($v, 0, 500) . sprintf(span_r, ' cutted...');
                    $out[$k] = $v;
                }
                echo Debug::table($out);
                break;

            case 'Functions':
                $out = get_defined_functions();
                echo Admin::out(print_r($out['user'], true));
                break;

            case 'Constants':
                $list = array_keys($ary = get_defined_constants(true));
                $_GET['c'] = isset($_GET['c']) ? intval($_GET['c']) : array_search('user', $list);
                echo Admin::out($ary[$list[$_GET['c']]]);
                $top .= sprintf('<form>%s<select name="c" id="sm-select">%s</select></form>', hidden(['main' => 0, 'id' => 4]), option($_GET['c'], $list));
            case '':
                break;
        }
        return $top;
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
                ['', [['<br>See also ' . a('DEV instance', '_dev') . ' settings', 'li']]],
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
                'cache_act' => ['Hard cache active', 'radio', ['No', 'Yes']],
                'cache_sec' => ['Default TTL, seconds', '', '', 300],
                'red_label' => ['Red label', 'radio', ['Off', 'On'], 1],
                ['', [['<b><u>The Jet compiller</u></b>', 'li']]],
                'jet_cact' => ['Jet cache active', 'radio', ['No', 'Yes'], 1],
                'jet_swap' => ['Swap @inc & @require commands', 'checkbox'],
                'jet_prod' => ['Recompile Jet-files on production when edit tpls', 'checkbox'],
        #       'jet_0php' => ['Allow native PHP', 'checkbox'],
        #       'jet_1php' => ['Allow @php Jet command', 'checkbox'],
            '</fieldset>',
        ];
    }

    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _config($i) {
        global $sky;
        $menu = ['System', 'Admin', 'Cron', '/etc/'];
        $etc = 3 == $i;
        $edit = $etc ? isset($_GET['fn']) : !isset($_GET['show']);
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
                    $v .= $size > 1024 ? "- $size bytes" : a('edit', "?main=2&id=3&fn=" . substr($k, 1 + strlen($path)));
                });
            }
        } else {
            $ary = [['s' => 3], ['a' => 8], ['n' => 9]];
            $sid = $ary[$i];
            $char = key($sid);
            $sky->memory();
            $ary = SKY::$mem[$char][3];
            list ($imemo, $dt) = sql('-select imemo, dt from $_memory where id=$.', current($sid));
            $TOP .= pad() . "imemo=$imemo, dt=$dt" . pad() . a($edit ? 'Show' : 'Edit', "?main=2&id=$i&" . ($edit ? 'show' : 'edit'));
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
            isset($_POST['fn']) ? jump('?main=2&id=3') : jump(URI);
        }

        if (!$edit) {
            echo Admin::out($ary, !$etc);
            if ($etc)
                echo '<br>' . a('Write new file', '?main=2&id=3&fn');
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

    # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    static function _cache($i = null) {
        global $sky;

        $menu = ['Hard cache', 'Jet cache', 'Gate cache', 'Extra cache', 'Static cache', 'Drop ALL cache'];
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
            case 5: Admin::drop_all_cache(); jump('?main=4');
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
}


