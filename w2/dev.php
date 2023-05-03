<?php
#[\AllowDynamicProperties]
class DEV
{
    const repository = 'https://coresky.net/api';

    static function e($top) {
        global $sky;
        $sky->error_no = 71;
        $top->body = $sky->fly ? '' : '_std.e71';
        list ($class, $action) = explode('::', substr($top->hnd, 0, -2));
        $sky->ca_path = ['ctrl' => $class, 'func' => $action];
        trace(["Gate error in $top->hnd", 'Gate error'], true, 2);
    }

    static function init() {
        global $sky;

        $sky->dev = new DEV;
        $vars = Plan::mem_gq('dev_vars.txt');
        SKY::ghost('d', $vars, function ($s) {
            Plan::mem_p(['main', 'dev_vars.txt'], $s);
        });
        if ('' === $sky->d_crash_to)
            $sky->d_crash_to = 8;

        if (SKY::d('cron')) {
            if (!Plan::_t(['main', 'cron.php']))
                return trace('cron.php not found', true);
            $ts = SKY::d('cron_dev_ts') ?: 0;
            if (START_TS - $ts > 60) {
                SKY::d('cron_dev_ts', START_TS);
                exec('php ' . DIR . '/' . DIR_M . '/cron.php @ 2>&1');
            }
        }

        if (!$static = $sky->d_static)
            return;

        $stat = array_map(function ($v) {
            return '/' == $v[0] || strpos($v, ':') ? $v : WWW . $v;
        }, explode(',', $static));

        $files = [];
        foreach ($stat as $one) {
            if ('' !== $one && is_dir($one)) {
                $files += array_flip(glob("$one/*.css"));
                $files += array_flip(glob("$one/*.js"));
            } elseif (is_file($one)) {
                $files[$one] = 0;
            }
        }
        if ($list = $sky->d_statics) {
            $saved = array_explode($list, '#', ',');
            if (count($saved) != count($saved = array_intersect_key($saved, $files))) # deleted file(s)
                $sky->static_new = true;
            $files = $saved + $files;
        }
        foreach ($files as $one => &$_mt) {
            if ($_mt != ($mt = filemtime($one))) {
                $_mt = $mt;
                $sky->static_new = true;
            }
        }
        if ($sky->static_new)
            SKY::d('statics', array_join($files, '#', ','));
    }

    ///////////////////////////////////// DEV UTILITY /////////////////////////////////////
    function c_view($x) {
        global $sky;
        $cr = [1 => 1, 2 => 15, 3 => 16];
        if ($_POST)
            Plan::mem_p('dev_trace.txt', $_POST['t0']);
        $trace = $trc = $x
            ? sqlf('+select tmemo from $_memory where id=%d', $cr[$x])
            : Plan::mem_g('dev_trace.txt');
        preg_match("/^WARE: (\w+)/m", $trace, $m);
        $ware = $m[1] ?? 'main';
        $header = '';
        $nv = $_GET['nv'] ?? 0;
        for ($list = [], $i = 0; preg_match("/(TOP|SUB|BLK)\-VIEW: (\d+) (\S+) (\S+)(.*)/s", $trace, $m); $i++) {
            $m[1] = ucfirst(strtolower($m[1]));
            $title = "$m[1]-view:&nbsp;<b>$m[3]</b> &nbsp; Template: <b>" . ('^' == $m[4] ? 'no-tpl' : $m[4]) . "</b>";
            if ($nv == $i)
                $header = $title;
            $trace = $m[5];
            array_pop($m);
            array_shift($m);
            $list[] = $m + [7 => 'no-handle' == $m[2]];
        }
        $menu = ['Source templates', 'Parsed template', 'Master action'];

        $layout = '>Layout not used</div>';
        $body = '>Body template not used</div>';
        $sl = $sb = ';background:#eee"';
        $php = '';
        if (2 == $sky->_6) {
            $ctrl = explode('::', $list[$nv][2]);
            $fn = ($w2 = 'dev_c' == $ctrl[0]) ? "w2/dev_c.php" : "mvc/$ctrl[0].php";
            $php = '<div class="other-task" style="position:sticky; top:0px">Controller: ' . basename($fn)
                . ", action: $ctrl[1]</div>";
            $we = 'mvc/common_c.php' != $fn ? $ware : 'main';
            $php .= Display::php_method(Plan::_g([$we, $fn], $w2), substr($ctrl[1], 0, -2));
        } elseif (1 == $sky->_6) {
            $tpl = $list[$nv][3];
            list ($lay, $bod) = explode('^', $tpl);
            $fn = MVC::fn_parsed($lay, "_$bod");
            $php = '<div class="other-task" style="position:sticky; top:0px">Parsed: ';
            if (Plan::jet_t($fn)) {
                $php .= $fn . '</div>' . Display::php(Plan::jet_g($fn));
            } else {
                $php .= ' not found</div>';
            }
            
        } elseif ($list) {
            $tpl = $list[$nv][3];
            if ('^' != $tpl) {
                list ($lay, $bod) = explode('^', $tpl);
                if ($lay) {
                    $sl = '"';
                    $lay = explode('.', $lay);
                    $fn = '_' == $lay[0][0] ? "$lay[0].jet" : "y_$lay[0].jet";
                    $lay = $fn . (($marker = $lay[1] ?? '') ? ", marker: $marker" : '');
                    $layout = ">Layout: $lay</div>" . Display::jet(Plan::view_('g', [$ware, $fn]), $marker) . '<br>';
                    if ('' === $bod) {
                        $sb = '"';
                        $body = '>Body: used "echo" in controller</div><br>';
                    }
                }
                if ($bod) {
                    $sb = '"';
                    $bod = explode('.', $bod);
                    $fn = "_$bod[0].jet";
                    $bod = $fn . (($marker = $bod[1] ?? '') ? ", marker: $marker" : '');
                    $body = ">Body: $bod</div>" . Display::jet(Plan::view_('g', [$ware, $fn]), $marker) . '<br>';
                }
            }
        }
        return [
            'list_views' => $list,
            'list_menu' => $menu,
            'trace_x' => pre($trc, 'class="trace"'),
            'nv' => $nv,
            'y_tx' => $x,
            'header' => ('main' == $ware ? '' : '<span style="font-size:14px"><b>' . strtoupper($ware) . ":</b></span> ") . $header,
            // for src tpl
            'layout' => '<div class="other-task" style="position:sticky; top:0px' . $sl . $layout,
            'body' => '<div class="other-task" style="position:sticky; top:42px' . $sb . $body,
            // for parsed tpl
            'php' => $php,
        ];
    }

    function j_second_dir() {
        global $sky;
        $dir = trim($_POST['s'], " \t\r\n/");
        if (!is_dir($dir)) {
            print("Dir `$dir` not exists");
            return 801;
        }
        $sky->d_second_wares = $dir;
        echo 'OK';
    }

    function j_attach() {
        $wares = (array)Plan::_rq('wares.php');
        $dir = trim($_POST['s'], " \t\r\n/");
        if ('un' != ($mode = $_POST['mode'])) { # Install
            list ($type, $dir) = explode('.', $dir, 2);
            if (!is_file("$dir/conf.php")) {
                print("File `$dir/conf.php` not found");
                return 801;
            }
            $conf = require "$dir/conf.php";
            $required = explode(' ', $conf['app']['require'] ?? '');
            $name = basename($dir);
            $flags = explode(' ', $conf['app']['flags'] ?? '');
            if ('' == $required[0])
                array_shift($required);
            foreach ($required as $class) {
                if (!Plan::has($class)) {
                    if ($isv = is_dir('vendor'))
                        Plan::vendor();
                    if (!class_exists($class, $isv)) {
                        print("Class `$class` not found");
                        return 801;
                    }
                }
            }
            $cls = [];
            if ('prod' == $type && 1 == $mode) {
                $classes = Globals::def($dir);
                $more = '';
                if (in_array('Ware', $classes)) {
                    require "$dir/w3/ware.php";
                    $more = '<h2>Install options:</h2>' . (new Ware);
                }
                return [
                    'more' => $more,
                    'classes' => $classes,
                    'name' => $name,
                    'dir' => $dir,
                    'flags' => $flags,
                ];
            } else if (2 == $mode) {
                if (!$cls = $_POST['cls'] ?? []) {
                    print('Must select at least one class');
                    return 801;
                }
            }
            $wares[$name] = [
                'path' => $dir,
                'class' => $cls,
                'tune' => $_POST['tune'] ?? '',
            ];
            if (isset($_POST['dev']))
                $wares[$name] += ['type' => 'dev'];
        } else {
            unset($wares[strtolower($dir)]);
        }
        Plan::_p('wares.php', Plan::auto($wares));
        Plan::cache_d('sky_plan.php');
        echo 'OK';
    }

    function desc($path) {
        if (!is_file($fn = "$path/README.md"))
            return false;
        foreach (file($fn) as $line) {
            $line = trim($line);
            if (!$line || '#' == $line[0])
                continue;
            return $line;
        }
        return '-';
    }

    function wares($merge = false) {
        global $sky;
        $works = array_keys($installed = SKY::$plans);
        array_shift($installed);
        $wares = array_map('basename', glob('wares/*'));
        if ($sky->d_second_wares && is_dir($sky->d_second_wares))
            $wares = array_merge($wares, array_map('basename', glob("$sky->d_second_wares/*")));
        return $merge ? array_merge($wares, $works) : [$works, $wares, $installed];
    }

    function c_ware() {
        list ($works, $wares, $installed) = $this->wares();
        $dir = array_diff($wares, $works);
        $wares = Plan::_rq('wares.php');
        return [
            'app' => SKY::version()['app'][4],
            'e_installed' => [
                'row_c' => function ($row) use (&$installed, &$wares) {
                    $name = $installed ? key($installed) : 0;
                    if (!$ware = array_shift($installed))
                        return false;
                    return [
                        'name' => ucfirst($name),
                        'type' => $ware['app']['type'],
                        'class' => $wares[$name]['class'],
                        'cnt' => count($wares[$name]['class']),
                        'desc' => $this->desc(Plan::_obj([$name])->path),
                    ];
                },
            ],
            'e_dir' => [
                'row_c' => function ($row) use (&$dir) {
                    global $sky;
                    if (!$ware = array_shift($dir))
                        return false;
                    $path = is_dir($d = "wares/$ware") ? $d : "$sky->d_second_wares/$ware";
                    if (!is_dir($path))
                        return true;
                    $conf = require "$path/conf.php";
                    return [
                        'name' => ucfirst($ware),
                        'type' => $conf['app']['type'],
                        'path' => $path,
                        'desc' => $this->desc($path),
                    ];
                },
            ],
        ];
    }

    function j_download() {
        $name = strtolower($_POST['n']);
        is_dir('wares') or mkdir('wares');
        if (!class_exists('ZipArchive', false)) {
            echo 'class ZipArchive not exists';
            return;
        }
        $zip = file_get_contents(DEV::repository . "?get=$name.zip");
        file_put_contents($fn = "wares/$name.zip", $zip);

        $zip = new ZipArchive;
        if ($ok = $zip->open($fn) === true) {
            mkdir($dir = "wares/$name");
            $zip->extractTo("$dir/");
            $zip->close();
            unlink($fn);
        }
        echo $ok ? 'OK' : 'Error in ZIP archive';
    }

    function j_inet() {
        $inet = @api(DEV::repository, ['search']);
        if ('OK' != @$inet['result']) {
            echo '<h1>Error in remote call</h1>';
            return;
        }
        $inet = $inet['wares'];
        $wares = $this->wares(true);
        //echo '<pre>';print_r($inet);echo '</pre>';
        return [
            'bg_ware' => '#e0e7fe',
            'e_inet' => [
                'row_c' => function ($row) use (&$inet, &$wares) {
                    $name = $inet ? key($inet) : 0;
                    if (!$ware = array_shift($inet))
                        return false;
                    if (in_array($name, $wares))
                        return true;
                    return [
                        'name' => ucfirst($name),
                        'type' => $ware['type'],
                        'class' => $cls = ($ware['classes'] ?? 0) ? explode(' ', $ware['classes']) : [],
                        'cnt' => !$cls ? 0 : count($cls),
                        'desc' => $ware['desc'],
                    ];
                },
            ],
        ];
    }

    function wares_menu() {
        $menu = [];
        foreach (SKY::$plans as $ware => $cfg) {
            if (($cfg['app']['type'] ?? '') == 'dev')
                $menu['_' . $ware] = ucfirst($ware);
        }
        return $menu;
    }

    function j_drop() {
        global $sky;
        if (!DEV)
            return;
        list ($char, $id) = explode('.', $_POST['cid']);
        $sky->memory($id, $char);
        trace("SKY:: $char $_POST[v], null");
        SKY::$char($_POST['v'], null);
    }

    function j_pp() {
        SKY::d('pp', (int)$_POST['pp']);
    }

    function j_reflect() {
        $name = explode(' ', $_POST['n'], 3);
        $name = 'e' == ($type = $_POST['t']) ? $name[1] : $name[0];
        echo Plan::object($name, $type, SKY::d('pp'));
    }

    function c_main($n) {
        $this->_y = ['page' => 'dev'];
        if ('INFO_' == substr($n, 0, 5) && phpinfo(constant($n)))
            throw new Stop;
        if ($n)
            return ['n' => $n];

        $val = explode(' ', SKY::s('version')) + ['', '', '0', 'APP'];
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
        if (isset($_POST['app'])) {
            SKY::s('version', time() . ' ' . SKY::version()['core'][0] . " $_POST[ver] $_POST[app]");
        } elseif ($_POST) {
            $ary = $_POST + SKY::$mem['d'][3];
            ksort($ary);
            SKY::d($ary);
        }
        return [
            'form1' => Form::A(array_combine($key, $val), $form1),
            'form2' => Form::A(SKY::$mem['d'][3], [
                'dev' => ['Set debug=0 for DEV-tools', 'chk'],
                'err' => ['Show suppressed PHP errors', 'chk'],
                'cron'  => ['Run cron when click on DEV instance', 'chk'],
                'lgt' => ['SkyLang table name', '', 'size="25"'],
                'manual' => ['PHP manual language', 'select', $phpman],
                'se' => ['Search engine tpl', '', 'size="50"'],
                'nopopup'  => ['No dev-tools on soft 404', 'chk'],
                'crash_to'  => ['Crash-redirect timeout, sec', 'number', '', 8],
                Form::X([], '<hr>'),
                ['Check static files for changes (file or path to *.js & *.css files), example: `m,C:/web/air/assets`', 'li'],
                'static' => ['', '', 'size="50"'],
                'etc'  => ['Turn ON tracing for default_c::a_etc()', 'chk'],
                'red_label' => ['Red label', 'radio', ['Off', 'On']],
                'jet_cache' => ['Jet cache', 'radio', ['Off', 'On']],
                ['Save', 'submit'],
            ]),
        ];
    }
}
