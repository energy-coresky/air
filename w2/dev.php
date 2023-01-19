<?php

function e() {
    global $sky;
    $sky->error_no = 1;
    $sky->error_title = 'Gate error - <span class="gate-err"></span>';
    trace('Gate error', true, 1);
}

class DEV
{
    static $static = false;
    static $sqls = [['##', 'Time', 'Query']];
    static $vars = [['##', 'Name', 'Value']];

    static function init() {
        if (DEV && !CLI && SKY::$dd) {
            global $sky;

            $sky->dev = new DEV;
            $vars = Plan::mem_gq('dev_vars.txt');
            SKY::ghost('d', $vars, function ($s) {
                Plan::mem_p(['main', 'dev_vars.txt'], $s);
            });
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
            if ($list = $sky->d_files) {
                $saved = array_explode($list, '#', ',');
                if (count($saved) != count($saved = array_intersect_key($saved, $files))) # deleted file(s)
                    DEV::$static = true;
                $files = $saved + $files;
            }
            foreach ($files as $one => &$_mt) {
                if ($_mt != ($mt = filemtime($one))) {
                    $_mt = $mt;
                    DEV::$static = true;
                }
            }
            if (DEV::$static)
                SKY::d('files', array_join($files, '#', ','));
        }
    }

    static function trace() {
        global $sky;
        $style = 'style="width:100%; display:table; background-color:#fff; color:#000"';
        $avar = [1 => 'from Globals', 'from Jet Templates'];
        $classes = Root::get_classes()[1];
        sort($classes);
        $dev_data = [
            'classes' => $classes,
        ];
        $out = tag(json_encode($dev_data), 'class="dev-data" style="display:none"');

        if ($sky->d_sql)
            $out .= tag('<h2>SQL queries:</h2>' . Debug::table(DEV::$sqls), $style);
        if ($i = $sky->d_var)
            $out .= tag("<h2>Variables $avar[$i]:</h2>" . Debug::table(DEV::$vars), $style);
        return $out;
    }
    
    static function ed_var($in) {
        $i = 1;
        DEV::$vars[] = [['<hr>', 'colspan="3"']];
        foreach ($in as $k => $v) {
            $is_obj = is_object($v);
            $is_arr = is_array($v);
            is_string($v) or is_int($v) or $v = @var_export($v, true);
            $v = html($v);
            strlen($v) <= 500 or $v = substr($v, 0, 500) . sprintf(span_r, ' cutted...');
            if ($is_obj || $is_arr) {
                $v = tag($is_obj ? 'Object' : 'Array', '', 'b') . ' '
                    . a('>>>', 'javascript:;', 'onclick="sky.toggle(this)" style="font-family:monospace" title="expand / collapse"')
                    . tag($v, 'style="display:none"');
            }
            DEV::$vars[] = [$i++, "\$$k", $v];
        }
    }

    static function ed_sql($sql, $ts, $depth, $err) {
        static $i = 1;
        $j = 0;
        list ($file, $line) = array_values(debug_backtrace()[$depth]);
        $bg = 'background:' . ($err ? '#f77' : '#7f7;');
        $table = tag(html($sql), "style=\"font:normal 120% monospace;$bg\"", 'span');

        if (!in_array(strtolower(substr($sql, 0, 6)), ['delete', 'update', 'replac', 'insert'])) {
            SQL::$dd->query("explain extended $sql", $q);
            if (false !== $q) {
                $table .= "\n<u><i>EXPLAIN EXTENDED ..</i></u>";
                while ($row = SQL::$dd->one($q, 'A')) {
                    $j++ or $table .= th(array_keys($row), 'cellspacing="0" cellpadding="3"');
                    $table .= td(array_values($row));
                }
                $table .= '</table>';
                //SQL::$dd->free($q);
            }
        }
        DEV::$sqls[] = [$i++, sprintf('%01.3f sec', $ts), "$file:$line:\n$table"];
    }

    ///////////////////////////////////// GATE UTILITY /////////////////////////////////////
    static function post_data($me) {
         isset($_POST['args']) && $me->argc($_POST['args']);//////////
        SKY::d('sg_prod', (int)isset($_POST['production']));
        $addr = $pfs = [];
        $to =& $addr;
        if (isset($_POST['key'])) {
            foreach ($_POST['key'] as $i => $key) {
                if ($i == $_POST['cnt-addr'])
                    $to =& $pfs;
                if ('' === ($val = trim($_POST['val'][$i])) && '' === trim($key))
                    continue;
                $to[] = [$_POST['kname'][$i], trim($key), $_POST['vname'][$i], $val, (int)$_POST['chk'][$i]];
            }
        }
        $method = isset($_POST['method']) ? $_POST['method'] : [];
        foreach($method as &$v)
            $v = (int)$v;
        $flag = isset($_POST['flag']) ? array_sum($_POST['flag']) : 0;
        return [$flag, $method, $addr, $pfs];
    }

    static function compile($class, $func) {
        $me = Gate::instance();
        json([
            'code' => $me->view_code(DEV::post_data($me), $class, $func),
            'url'  => PROTO . '://' . $me->url,
        ]);
    }

    static function atime() { # search ctrl with last access time
        $glob = Plan::_b('mvc/c_*.php');
        if ($fn = Plan::_t('mvc/default_c.php'))
            array_unshift($glob, $fn);
        $max = $c = 0;
        foreach ($glob as $fn) {
            $stat = stat($fn);
            if ($max < $stat['atime']) {
                $max = $stat['atime'];
                $c = basename($fn, '.php');
            }
        }
        return $c;
    }

    static function auto($v, $more = '') {
        return "<?php\n\n# this is auto generated file, do not edit\n$more\nreturn " . var_export($v, true) . ";\n";
    }

    static function save($class, $func = false, $ary = false) {
        $cfg =& SKY::$plans['main']['ctrl'];
        if ('main' != ($cfg[substr($class, 2)] ?? 'main'))
            Gate::$ware = $cfg[substr($class, 2)];
        $sky_gate = Gate::load_array();
        if (!$func) { # delete controller
            unset($sky_gate[$class]);
        } elseif (!$ary) { # delete action
            unset($sky_gate[$class][$func]);
        } else { # update, add
            $sky_gate[$class][$func] = true === $ary ? DEV::post_data(Gate::instance()) : $ary;
        }
        Plan::gate_dq($class . '.php'); # clear cache
        Plan::_p([Gate::$ware, 'gate.php'], DEV::auto($sky_gate));
    }

    static function cshow() {
        Gate::$cshow = SKY::d('sg_cshow');
        if (isset($_POST['s']))
            SKY::d('sg_cshow', Gate::$cshow = $_POST['s']);
        return Gate::$cshow;
    }

    static function gate($class, $func = null, $is_edit = true) {
        $cfg =& SKY::$plans['main']['ctrl'];
        Gate::$ware = $cfg[substr($class, 2)] ?? 'main';
        $ary = Gate::load_array($class);
        $me = Gate::instance();
        $src = Plan::_t([Gate::$ware, $fn = "mvc/$class.php"]) ? $me->parse($fn) : [];
        if ($diff = array_diff_key($ary, $src))
            $src = $diff + $src;
        if ($has_func = is_string($func)) {
            $me->argc(is_array($src[$func]) ? '' : $src[$func]);
            $src = [$func => $src[$func]];
        }
        $edit = $has_func && $is_edit;
        $return = [
            'row_c' => function($row = false) use (&$src, $ary, $me, $class, $edit) {
                if ($row && $row->__i && !next($src) || !$src)
                    return false;
                $name = key($src);
                $delete = is_array($pars = current($src));
                if (Gate::$cshow && !$delete)
                    $me->argc($pars);
                $is_j = in_array($name, ['empty_j', 'default_j']) || 'j_' == substr($name, 0, 2);
                $ary = isset($ary[$name]) ? $ary[$name] : [];
                list ($flag, $meth, $addr, $pfs) = $ary + [0, [], [], []];
                $vars = [
                    'func' => $name,
                    'delete' => $delete,
                    'pars' => $delete ? '' : $pars,
                    'code' => $edit || Gate::$cshow ? $me->view_code($ary, $class, $name) : false,
                    'error' => $delete ? 'Function not found' : '',
                    'url' => $me->url,
                ];
                if (Gate::$cshow)
                    return $vars;
                return $vars + [
                    'c1' => DEV::view_c1($flag, $edit, $meth, $is_j),
                    'c2' => DEV::view_c23($flag, $edit, $addr, 1),
                    'c3' => DEV::view_c23($flag, $edit, $pfs, 0),
                    'prod' => SKY::d('sg_prod') ? ' checked' : '',
                ];
            },
        ];
        return $has_func ? ['row' => (object)($return['row_c']())] : $return;
    }

    static function view_c1($flag, $edit, $meth, $is_j) {
        global $sky;

        $flags = [
            Gate::HAS_T3 => 'Address has semantic part',
            Gate::RAW_INPUT => 'Use raw body input',
            Gate::AUTHORIZED => 'User must be authorized',
            Gate::OBJ_ADDR => 'Return address as object',
            Gate::OBJ_PFS => 'Return postfields as object',
        ];
        $out = '';
        if ($is_j)
            $meth = [0];
        $skip = !$edit || $is_j;
        foreach ($sky->methods as $k => $v) {
            $ok = in_array($k, $meth);
            if ($skip && !$ok)
                continue;
            $input = sprintf('<input type="checkbox" name="method[]" value="%d"%s/>', $k, $ok ? ' checked' : '');
            $col = $k ? (1 == $k ? '#0f0' : '#aaf') : 'pink';
            $attr = sprintf('cx="%s" style="background:%s"', $col, $ok ? $col : '#ddd');
            $out .= ($out ? ' ' : '') . tag($skip ? $v : "$input$v", $attr, 'label');
        }
        $out .= sprintf('<div style="width:100%%%s">', $edit ? '' : ';min-height:50px');
        foreach ($flags as $k => $v) {
            if ($is_j && $k & Gate::HAS_T3)
                continue;
            $ok = (bool)($flag & $k);
            $input = sprintf('<input type="checkbox" name="flag[]" value="%d"%s%s/>', $k, $ok ? ' checked' : '', $edit ? '' : ' disabled');
            $attr = sprintf('style="color:%s%s"', $ok ? '#111' : '#777', $ok ? ';font-weight:bold' : '');
            $chk = tag("$input$v", $attr, 'label');
            if ($ok || $edit)
                $out .= "$chk<br>";
        }
        $out .= '</div>';
        return $out;
    }

    static function ary_c23($is_addr = 1, $v = []) {
        $v += ['', '', '', '', 0];
        return [
            'kname' => $v[0],
            'key' => $v[1],
            'vname' => $v[2],
            'val' => $v[3],
            'isaddr' => $is_addr,
            'chk' => $v[4],
        ];
    }

    static function view_c23($flag, $edit, $ary, $is_addr) {
        $out = '';
        if ($edit) {
            foreach ($ary as $v) {
                trace($v, 'x0');
                $out .= view('c23_2edit', DEV::ary_c23($is_addr, $v));
            }
            if ($is_addr)
                $out .= hidden('cnt-addr', count($ary));
            $out .= a('add parameter', 'javascript:;', 'onclick="sky.g.tpl(this,' . $is_addr . ')"');
        } else {
            foreach ($ary as $v) {
                trace($v,'x1');
                $v += ['', '', '', '', 0];
                $re_val = !preg_match("/^\w*$/", $v[3]);
                $val = $re_val ? "/^$v[3]$/" . ($v[2] ? " ($v[2])" : '') : $v[3];
                $re_key = !preg_match("/^\w*$/", $v[1]);
                $key = $re_key ? "/^$v[1]$/" . ($v[0] ? " ($v[0])" : '') : $v[1];
                $out .= view('c23_view', [
                    'data' => "$key => $val",
                    'isaddr' => $is_addr,
                    'ns' => $v[4] ? 'ns&nbsp;' : '',
                ]);
            }
        }
        return $out;
    }

    static function ctrl() {
        return array_filter(SKY::$plans['main']['ctrl'], function ($v) {
            return $v != 'main';
        });
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
        $top = $header = '';
        $nv = $_GET['nv'] ?? 0;
        for ($list = [], $i = 0; preg_match("/(TOP|SUB|BLK)\-VIEW: (\S+) (\S+)(.*)/s", $trace, $m); $i++) {
            $m[1] = ucfirst(strtolower($m[1]));
            $title = "$m[1]-view:&nbsp;<b>$m[2]</b> &nbsp; Template: <b>" . ('^' == $m[3] ? 'no-tpl' : $m[3]) . "</b>";
            if ('Top' == $m[1])
                $top = $title;
            if ($nv == $i)
                $header = $title;
            $trace = $m[4];
            array_pop($m);
            array_shift($m);
            $list[] = $m;
        }
        $list_sqls = preg_match("/([\.\d]+ sec), SQL queries: (\d+)/s", $trace, $m) ? $m[2] : '?';
        $time = $m ? $m[1] : '??';
        $list_classes = '?';
        if (preg_match('~<div class="dev-data" [^>]+>(.*?)</div>~s', $trace, $m)) {
            $list_classes = json_decode(trim($m[1]), true);
            
            $list_classes = is_array($list_classes) ? count($list_classes['classes']) : '??';
        }

        $menu = ['Source templates', 'Parsed template', 'Master action'];

        $layout = '>Layout not used</div>';
        $body = '>Body template not used</div>';
        $sl = $sb = ';background:#eee"';
        $php = '';
        if (2 == $sky->_6) {
            $ctrl = explode('::', $list[$nv][1]);
            $fn = ($w2 = 'standard_c' == $ctrl[0]) ? "w2/standard_c.php" : "mvc/$ctrl[0].php";
            $php = '<div class="other-task" style="position:sticky; top:0px">Controller: ' . basename($fn)
                . ", action: $ctrl[1]</div>";
            $we = 'mvc/common_c.php' != $fn ? $ware : 'main';
            $php .= Display::php_method(Plan::_g([$we, $fn], $w2), substr($ctrl[1], 0, -2));
        } elseif (1 == $sky->_6) {
            $tpl = $list[$nv][2];
            list ($lay, $bod) = explode('^', $tpl);
            $fn = MVC::fn_parsed($lay, "_$bod");
            $php = '<div class="other-task" style="position:sticky; top:0px">Parsed: ';
            if (Plan::jet_t($fn)) {
                $php .= $fn . '</div>' . Display::php(Plan::jet_g($fn));
            } else {
                $php .= ' not found</div>';
            }
            
        } elseif ($list) {
            $tpl = $list[$nv][2];
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
            'err_ajax' => tag($trc, 'id="trace"', 'pre'),
            //'list_sqls' => $list_sqls,
            //'list_classes' => $list_classes,
            'bottom' => [count($list), $list_sqls, $list_classes],
            'time' => $time,
            'nv' => $nv,
            'y_tx' => $x,
            'top' => $top,
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
        if (!is_dir($dir))
            return print("Dir `$dir` not exists");
        $sky->d_second_wares = $dir;
        echo 'OK';
    }

    function j_attach() {
        global $sky;
        $wares = (array)Plan::_rq('wares.php');
        $dir = trim($_POST['s'], " \t\r\n/");
        if ('un' != ($mode = $_POST['mode'])) { # Install
            list ($type, $dir) = explode('.', $dir);
            if (!is_file("$dir/conf.php"))
                return print("File `$dir/conf.php` not found");

            require "$dir/conf.php";
            $required = explode(' ', $plans['app']['require'] ?? '');
            if ('' == $required[0])
                array_shift($required);
            foreach ($required as $class) {
                if (!class_exists($class, false) && !isset(SKY::$plans['main']['class'][$class]))
                    return print("Class `$class` not found");
            }
            $name = basename($dir);
            $cls = [];
            if ('prod' == $type && 1 == $mode) {
                $classes = Globals::ware($dir);
                foreach ($classes as $one)
                    $wares[$name]['class'][] = $one;
                return [
                    'classes' => $classes,
                    'name' => $name,
                    'dir' => $dir,
                ];
            } else if (2 == $mode) {
                if (!$cls = $_POST['cls'] ?? [])
                    return print('Must select at least one class');
            }
            $wares[$name] = ['path' => $dir, 'class' => $cls];
            if (isset($_POST['dev']))
                $wares[$name] += ['type' => 'dev'];
        } else {
            unset($wares[strtolower($dir)]);
        }
        Plan::_p('wares.php', DEV::auto($wares));
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
                    require "$path/conf.php";
                    return [
                        'name' => ucfirst($ware),
                        'type' => $plans['app']['type'],
                        'path' => $path,
                        'desc' => $this->desc($path),
                    ];
                },
            ],
        ];
    }

    const repository = 'https://coresky.net/api';

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

    function c_main($n) {
        $this->_y = ['page' => 'dev'];
        if ('INFO_' == substr($n, 0, 5) && phpinfo(constant($n)))
            throw new Stop;
        return $n ? ['h4' => Root::$h4[$n]] : Root::dev();
    }

    function j_drop() {
        global $sky;
        if (!DEV)
            return;
        list ($char, $id) = explode('.', $_POST['cid']);
        $sky->memory($id, $char);
        //call_user_func_array("SKY::$char", [$_POST['v'], null]);
        trace("SKY:: $char $_POST[v], null");
        SKY::$char($_POST['v'], null);
    }

    function j_pp() {
        SKY::d('pp', (int)$_POST['pp']);
    }

    function j_reflect() {
        $name = explode(' ', $_POST['n'], 3);
        $name = 'e' == ($type = $_POST['t']) ? $name[1] : $name[0];
        echo Display::reflect($name, $type);
    }
}

