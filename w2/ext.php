<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

function e() {
    global $sky;
    $sky->error_no = 1;
    $sky->error_title = 'Gate error - <span class="gate-err"></span>';
    trace('Gate error', true, 1);
}

class Ext
{
    const js = 'http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js';
    static $cfg = [];
    static $ini = 'var/cfg_dev.ini';
    static $static = false;
    static $cfg_default = "files = \nvar = 2\nsql = 1\nstatic = pub\nconst = 0\nclass = 0\ncron = 0";

    static function start() {
        global $sky;

        if ($sky->s_init_needed) {
            $js = (DESIGN ? DESIGN : WWW) . 'pub/' . basename(Ext::js);
            file_exists($js) or file_put_contents($js, file_get_contents(Ext::js)) or exit("Cannot save `$js`");
            Ext::init_reset(sql('+select tmemo from memory where id=5'));
            SKY::s('init_needed', null); # run once only
        }
    }

    static function init() {
        if (DEV && !CLI) {
            Ext::$cfg = is_file(Ext::$ini) ? parse_ini_file(Ext::$ini) : parse_ini_string(Ext::$cfg_default);

            $stat = explode(',', Ext::$cfg['static']);
            $files = [];
            foreach ($stat as $one) {
                if ('' !== $one && is_dir($path = WWW . $one)) {
                    $files += array_flip(glob("$path/*.css"));
                    $files += array_flip(glob("$path/*.js"));
                } elseif (is_file($path)) {
                    $files[$path] = 0;
                }
            }
            if (isset(Ext::$cfg['files']) && Ext::$cfg['files']) {
                $saved = array_explode(Ext::$cfg['files'], ':', ',');
                if (count($saved) != count($saved = array_intersect_key($saved, $files))) # deleted file(s)
                    Ext::$static = true;
                $files = $saved + $files;
            }
            foreach ($files as $one => &$_mt) {
                if ($_mt != ($mt = filemtime($one))) {
                    $_mt = $mt;
                    Ext::$static = true;
                }
            }
            if (Ext::$static)
                Ext::save(['files' => array_join($files, ':', ',')]);
        }
    }

    static function cfg($name) {
        return isset(Ext::$cfg[$name]) ? Ext::$cfg[$name] : '';
    }

    static function save($ary) {
        Ext::$cfg = $ary + Ext::$cfg;
        file_put_contents(Ext::$ini, "\n; this is auto-generated file\n\n" . array_join(Ext::$cfg, ' = '));
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
            ['Check static files for changes (file or path to *.js & *.css files), example: `pub`', 'li'],
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

        if (Ext::cfg('sql'))
            $out .= tag('<h2>SQL queries:</h2>' . Debug::table(Ext::$sqls), $style);
        if ($i = Ext::cfg('var'))
            $out .= tag("<h2>Variables $avar[$i]:</h2>" . Debug::table(Ext::$vars), $style);
        return $out;
    }
    
    static function ed_var($in) {
        $i = 1;
        Ext::$vars[] = [['<hr>', 'colspan="3"']];
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
            Ext::$vars[] = [$i++, "\$$k", $v];
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
        Ext::$sqls[] = [$i++, sprintf('%01.3f sec', $ts), "$file:$line:\n$table"];
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
            elseif (preg_match("/^[a-z]*text$/i", $r[0])) $ary[$c] .= 'Ext::dummy_txt();' and $txt = 1;
            elseif ('datetime' == $r[0]) $ary[$c] .= 'date(DATE_DT, time() - rand(0, 3600 * 24 * 7));';
            elseif (preg_match("/varchar\((\d+)\)/", $r[0], $m)) $ary[$c] .= "Ext::dummy_txt($m[1] > 15 ? rand(9, $m[1] - 5) : $m[1]);";
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

