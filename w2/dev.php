<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

function e() {
    global $sky;
    $sky->error_no = 1;
    $sky->error_title = 'Gate error - <span class="gate-err"></span>';
    trace('Gate error', true, 1);
}

class DEV
{
    const js = 'http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js';
    static $cfg = [];
    static $ini = 'var/cfg_dev.ini';
    static $static = false;
    static $cfg_default = "files = \nvar = 2\nsql = 1\nstatic = pub\nconst = 0\nclass = 0\ncron = 0";

    static function start() {
        global $sky;

        if ($sky->s_init_needed) {
            $js = (DESIGN ? DESIGN : WWW) . 'pub/' . basename(DEV::js);
            file_exists($js) or file_put_contents($js, file_get_contents(DEV::js)) or exit("Cannot save `$js`");
            DEV::init_reset(sql('+select tmemo from memory where id=5'));
            SKY::s('init_needed', null); # run once only
        }
    }

    static function init() {
        if (DEV && !CLI) {
            DEV::$cfg = is_file(DEV::$ini) ? parse_ini_file(DEV::$ini) : parse_ini_string(DEV::$cfg_default);

            $stat = explode(',', DEV::$cfg['static']);
            $stat = array_map(function ($v) {
                return '/' == $v[0] || strpos($v, ':') ? $v : WWW . $v;
            }, $stat);

            $files = [];
            foreach ($stat as $one) {
                if ('' !== $one && is_dir($one)) {
                    $files += array_flip(glob("$one/*.css"));
                    $files += array_flip(glob("$one/*.js"));
                } elseif (is_file($one)) {
                    $files[$one] = 0;
                }
            }
            if (isset(DEV::$cfg['files']) && DEV::$cfg['files']) {
                $saved = array_explode(DEV::$cfg['files'], '#', ',');
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
                DEV::save(['files' => array_join($files, '#', ',')]);
        }
    }

    static function cfg($name) {
        return isset(DEV::$cfg[$name]) ? DEV::$cfg[$name] : '';
    }

    static function save($ary) {
        DEV::$cfg = $ary + DEV::$cfg;
        file_put_contents(DEV::$ini, "\n; this is auto-generated file\n\n" . array_join(DEV::$cfg, ' = '));
    }

    static function form() {
        $br = '<br>' . str_repeat(' &nbsp;', 7) .'other CONSTANTS see in the ' . a('Admin section', 'adm?main=0&id=4');
        return [ # visitor data
            'var' => ['Show Vars in the tracing', 'radio', ['none', 'from Globals', 'from Templates']],
            'sql' => ['Show SQLs in the tracing', 'checkbox'],
            'const' => ['Show user-defined global CONSTANTs in the tracing,' . $br, 'checkbox'],
            'class' => ['Show CLASSEs', 'checkbox'],
            'cron'  => ['Run cron when click on DEV instance', 'checkbox'],
            ['', [['See also ' . a('Admin\'s configuration', 'adm?main=2') . ' settings', 'li']]],
            Form::X([], '<hr>'),
            ['Check static files for changes (file or path to *.js & *.css files), example: `pub,C:/web/air/assets`', 'li'],
            'static' => ['', '', 'size="50"'],
            'trans' => ['Language class mode', 'radio', ['manual edit', 'auto-detect items', 'translation api ON']],
            ['Save', 'submit'],
        ];
    }

    static $sqls = [['##', 'Time', 'Query']];
    static $vars = [['##', 'Name', 'Value']];
    static $consts = [['##', 'Name', 'Value']];
    static $classes = [['##', 'Name', 'Value']];

    static function trace() {
        global $sky;
        $style = 'style="width:100%; display:table; background-color:#fff; color:#000"';
        $avar = [1 => 'from Globals', 'from Jet Templates'];
        $out = '';

        if (DEV::cfg('sql'))
            $out .= tag('<h2>SQL queries:</h2>' . Debug::table(DEV::$sqls), $style);
        if ($i = DEV::cfg('var'))
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
        global $sky;

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
                        require $val;
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
            elseif (preg_match("/^[a-z]*text$/i", $r[0])) $ary[$c] .= 'DEV::dummy_txt();' and $txt = 1;
            elseif ('datetime' == $r[0]) $ary[$c] .= 'date(DATE_DT, time() - rand(0, 3600 * 24 * 7));';
            elseif (preg_match("/varchar\((\d+)\)/", $r[0], $m)) $ary[$c] .= "DEV::dummy_txt($m[1] > 15 ? rand(9, $m[1] - 5) : $m[1]);";
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

    ///////////////////////////////////// GATE UTILITY /////////////////////////////////////
    static function post_data($me) {
        global $sky;
         isset($_POST['args']) && $me->argc($_POST['args']);//////////
        $sky->n_sg_prod = isset($_POST['production']);
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
        $glob = glob('main/app/c_*.php');
        if (is_file($fn = 'main/app/default_c.php'))
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

    static function save_gate($class, $func = false, $ary = false) {
        $sky_gate = Gate::load_array();
        if (is_array($func)) { # save virtuals
            $found = [];
            foreach ($sky_gate as $k => $v)
                $class === $v and $found[] = $k;
            foreach ($found as $v)
                unset($sky_gate[$v]);
            foreach ($func as $v)
                '' !== $v and $sky_gate["c_$v"] = $class;
        } elseif (!$func) { # delete controller
            unset($sky_gate[$class]);
        } elseif (!$ary) { # delete action
            unset($sky_gate[$class][$func]);
        } else { # update, add
            $sky_gate[$class][$func] = true === $ary ? DEV::post_data(Gate::instance()) : $ary;
        }
        if (is_file($fn = "var/gate/$class.php"))//--delete virtuals
            unlink($fn);
        $comment = '# this is autogenerated file, do not edit';
        $file = "<?php\n\n$comment\n\n\$sky_gate = " . var_export($sky_gate, true) . ";\n\n";
        file_put_contents(Gate::ARRAY, $file);
    }

    static function cshow() {
        global $sky;
        Gate::$cshow = $sky->n_sg_cshow;
        if (isset($_POST['s']))
            Gate::$cshow = $sky->n_sg_cshow = (int)('true' == $_POST['s']);
        return Gate::$cshow;
    }

    static function gate($class, $func = null, $is_edit = true) {
        list($real, $ary) = Gate::load_array($class); # virt class return real
        if ($real != $class)
            throw new Error("Cannot open virtual controller `$class`");
        $me = Gate::instance();
        $src = is_file($fn = "main/app/$class.php") ? $me->parse($fn) : [];
        if ($diff = array_diff_key($ary, $src))
            $src = $diff + $src;
        if ($has_func = is_string($func)) {
            $me->argc(is_array($src[$func]) ? '' : $src[$func]);
            $src = [$func => $src[$func]];
        }
        $edit = $has_func && $is_edit;
        $return = [
            'row_c' => function($row = false) use (&$src, $ary, $me, $class, $edit) {
                global $sky;
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
                    'prod' => $sky->n_sg_prod ? ' checked' : '',
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

    ///////////////////////////////////// DEV UTILITY /////////////////////////////////////
    static function run($page, $v) {
        $page or $page = 'overview';
        MVC::body("_std.$page");
        return (array)(new DEV)->{"c_$page"}($v);
    }

    function c_view($x) {
        $cr = [1 => 1, 2 => 15, 3 => 16];
        if ($_POST)
            SQL::open('_')->sqlf('update memory set tmemo=%s where id=1', $_POST['t0']);
        $trace = $x
            ? sqlf('+select tmemo from $_memory where id=%d', $cr[$x])
            : SQL::open('_')->sqlf('+select tmemo from memory where id=1');
        $top = $header = '';
        $nv = $_GET['nv'] ?? 0;
        for ($list = [], $i = 0; preg_match("/(TOP|SUB)\-VIEW: (\S+) (\S+)(.*)/s", $trace, $m); $i++) {
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

        $layout = '>Layout not used</div>';
        $body = '>Body template not used</div>';
        $sl = $sb = ';background:pink"';
        if ($list) {
            $tpl = $list[$nv][2];
            if ('^' != $tpl) {
                list ($lay, $bod) = explode('^', $tpl);
                if ($lay) {
                    $sl = '"';
                    $fn = "y_$lay.jet";
                    $layout = ">Layout: $fn</div>" . Display::jet("view/$fn") . '<br>';
                }
                if ($bod) {
                    $sb = '"';
                    $bod = explode('.', $bod);
                    $fn = "_$bod[0].jet";
                    $bod = $fn . (($marker = $bod[1] ?? '') ? ", marker: $marker" : '');
                    $body = ">Body: $bod</div>" . Display::jet("view/$fn", $marker) . '<br>';
                }
            }
        }
        return [
            'list' => $list,
            'nv' => $nv,
            'layout' => '<div class="other-task" style="position:sticky; top:0px' . $sl . $layout,
            'body' => '<div class="other-task" style="position:sticky; top:42px' . $sb . $body,
            'trace_x' => "_x$x",
            'top' => $top,
            'header' => $header,
        ];
    }

    function c_overview() {
        $form = DEV::form();
        if ($_POST) {
            foreach ($form as $k => $v)
                is_int($k) or isset($_POST[$k]) or $_POST[$k] = 0;
            DEV::save($_POST);
        }
        $this->_y = ['page' => 'dev'];
        return ['form' => Form::A(DEV::$cfg, $form)];
    }

    function c_system() {
    }

    function c_documentation() {
        
    }
}

