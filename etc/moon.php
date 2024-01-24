<?php

class Moon
{
    const chunk = 8192;

    private $conn = false;
    private $log_fh = false;
    private $moved = [];
    private $mode;

    public $dir;
    public $web;

    public $json = [
        'step' => [
            'func' => 'test',
            'fn' => '',
            'pos' => 0,
            'www' => '',
            'cnt' => 1,
            'tof' => 0,
            'toq' => 0,
            'ver' => '',
        ],
        'err' => '',
        'msg' => '',
        'success' => '',
    ];

    function __construct() {
        ob_get_level() or ob_start();
        ini_set('log_errors', 0);
        ini_set('display_errors', 1);
        ini_set('error_reporting', -1);
        set_error_handler(function ($no, $message, $file, $line) {
            if (error_reporting() & $no) {
                $error = "moon.php^$line $message";
                $_POST ? ($this->err = $error) : print($error);
            }
            return true;
        });
        register_shutdown_function(function () {
            $stdout = ob_get_clean();
            $error = '' === $stdout || !$_POST ? false : "Stdout: $stdout";
            $e = error_get_last();
            if ($e && $e['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR))
                $error = "moon.php^$e[line] $e[message]";
            if ($error)
                $_POST ? ($this->err = $error) : print($error);
            if ($this->err)
                $this->log($this->err);
            if ('cli' != PHP_SAPI)
                echo $_POST ? json_encode($this->json) : $stdout;
            if ($this->err && $this->moved) {
                ob_start();
                $this->log("Try move to initial state...");
                foreach($this->moved as $v)
                    $this->log("RENAME: $v[0] to $v[1] - " . (@rename($v[0], $v[1]) ? 'OK' : 'FAIL'));
                ob_get_clean();
            }
            if ($this->log_fh)
                fclose($this->log_fh);
        });
        $this->dir = $this->path(dirname(__DIR__));
        $this->web = basename(__DIR__);
        if ($_POST) {
            header('Content-Type: application/json; charset=UTF-8');
            $this->mode = $_POST['mode'] % 2;
            parse_str($_POST['step'], $step);
            key($step) ? ($this->step = $step) : ($this->_fn = current($step));
            for ($prev = $this->_func; $prev == $this->_func; ) {
                $this->{"_$this->_func"}();
                if ($this->err || $this->success || 'etc' == $this->_func)
                    break;
            }
            exit;
        }
    }

    function log($str, $append = true) {
        if (!$_POST['log'][0])
            return;
        if (!$this->log_fh) {
            is_file($fn = "$this->dir/$this->web/$this->_fn.txt") or $append = false;
            if (!$this->log_fh = @fopen($fn, $append ? 'ab' : 'wb'))
                return $this->err = "Cannot open `$fn` for writing";
        }
        if (!@fwrite($this->log_fh, "$str\n"))
            $this->err = "Cannot write LOG file";
    }

    function mv($oldname, $newname) {
        $result = @rename($oldname, $newname);
        if (true === $result) {
            array_unshift($this->moved, [$newname, $oldname]);
            $this->log("RENAME: $oldname to $newname");
        }
        return $result;
    }

    function __get($name) {
        return '_' == $name[0]
            ? $this->json['step'][substr($name, 1)]
            : $this->json[$name];
    }

    function __set($name, $value) {
        '_' == $name[0]
            ? ($this->json['step'][substr($name, 1)] = $value)
            : ($this->json[$name] = $value);
    }

    function path($str) {
        return str_replace('\\', '/', realpath($str));
    }

    function sql($query) {
        if (!$this->conn) {
            if (!$this->conn = mysqli_init())
                return $this->err = 'Failed mysqli_init()';
            $n = 'test' == $this->_func && isset($_POST['create']) ? null : trim($_POST['name']);
            $port = $_POST['port'] ? (int)$_POST['port'] : null;
            if (!@mysqli_real_connect($this->conn, $_POST['host'], $_POST['user'], $_POST['password'], $n, $port))
                return $this->err = mysqli_connect_error();
            if (!mysqli_set_charset($this->conn, 'utf8'))
                return $this->err = 'Cannot set utf8 charset';
        }
        $q = mysqli_query($this->conn, $query);
        if (mysqli_errno($this->conn)) {
            $this->err = mysqli_error($this->conn);
        } else {
            $this->log("SQL: " . substr(trim($query), 0, 60) . (strlen($query) > 60 ? '...' : ''));
        }
        return $q;
    }

    function _test() {
        date_default_timezone_set('Europe/Kiev');
        $this->log("Start at (Europe/Kiev) " . date('Y-m-d H:i:s'), !$_POST['log'][1]);
        $list = $this->walk_parent();
        if ($this->mode) {
            if ($d = $list['anew'][2])
                return $this->err = "Directory `anew` is not empty";
            if (false === $d) {
                if(!@mkdir("$this->dir/anew"))
                    return $this->err = "Cannot create `anew` directory";
                $this->log("MKDIR: $this->dir/anew");
            }
        } else foreach ($list as $fn => $v) {
            if (false === $v[1])
                return $this->err = "Parent or public directory is not empty";
        }

        if (isset($_POST['name'])) {
            $new = isset($_POST['create']);
            $q = $this->sql($new ? 'show databases' : 'show tables');
            if ($this->err)
                return;
            for ($ary = [], $n = trim($_POST['name']); $r = mysqli_fetch_row($q); $ary[] = $r[0]);
            if ($new && !in_array($n, $ary)) {
                $this->sql("create database `$n`");
                $this->msg = "Database `$n` created";
            } else {
                $this->msg = "Database `$n` exists";
                $new or $this->msg .= ', ' . count($ary) . ' tables inside';
            }
        } else {
            $this->msg = "Started ..";
        }
        if (!$this->err) {
            $this->_func = 'dir';
            $this->log($this->msg);
        }
    }

    function _dir() {
        [$head, $pos, $data] = $this->head($this->_fn);
        preg_match("/^A\^(\d+)\.(\d+)\.(\d+)\.\d+/", $head['ftrd'], $h);
        if (!preg_match("/^DIRS: (\d+)\n([^\n]+)\n/", $data, $m) || !$h)
            return $this->err = "File `$this->_fn` is corrupted";
        $www = $this->_www = $head['www'];
        $this->_ver = explode(' ', $head['compiled'], 2)[1];
        $this->msg = 'Total ' . count($dirs = explode(' ', $m[2])) . ' directories created';
        foreach ($dirs as $dir) {
            if ("$dir/" == $www) {
                if (!$this->mode)
                    continue;
                $dir = "$this->dir/anew/$this->web";
            } else {
                $path = $this->mode ? "$this->dir/anew" : $this->dir;
                $dir = substr($dir, 0, strlen($www)) == $www
                    ? "$path/$this->web/" . substr($dir, strlen($www))
                    : "$path/$dir";
            }
            if (file_exists($dir))
                return $this->err = "Directory `$dir` exists";
            if (!@mkdir($dir))
                return $this->err = "Cannot create directory `$dir`";
            $this->log("MKDIR: $dir");
        }
        $this->_func = 'file';
        $this->_pos = 2 + $pos + strlen($m[0]);
        $this->_tof = (int)$h[1];
        $this->_toq = 1 + $h[2] + $h[3];
    }

    function conf(&$data, $len) {
        $port = $_POST['port'] && 3306 != $_POST['port'] ? ":$_POST[port]" : '';
        $dsn = "dsn: '$_POST[name] $_POST[host]$port $_POST[user] $_POST[password]'";
        $data = substr($data, 0, $len);
        $data = preg_replace('/\bdsn: ""/', $dsn, $data);
        $data = preg_replace('/\bpref: ""/', 'pref: ' . ($_POST['prefix'] ?: '""'), $data);
    }

    function _file() {
        $read = fopen($this->_fn, 'rb');
        fseek($read, $this->_pos);
        $data = @fread($read, Moon::chunk);
        $err = function ($s, $w = false) use ($read) {
            fclose($read);
            if ($w)
                fclose($w);
            $this->err = $s;
        };
        if (!preg_match("/^FILE: (\S+) (\d+)\n/", $data, $m)) {
            if ("END:\n" !== $data)
                return $err("File `$this->_fn` is corrupted");
            $this->_pos = $this->_cnt = 0;
            if (!isset($_POST['name']))
                return $this->finish();
            $this->msg = "Execute SQLs...";
            $this->_func = 'sql'; # next step
        } else {
            $is_www = $this->_www == substr($m[1], 0, $len = strlen($this->_www));
            $path = $this->mode ? "$this->dir/anew" : $this->dir;
            $fn = $is_www ? "$path/$this->web/" . substr($m[1], $len) : "$path/$m[1]";
            if (file_exists($fn))
                return $err("File `$fn` already exists");
            if (false === ($write = @fopen($fn, 'wb')))
                return $err("Cannot open file `$fn` for writing");
            $len = strlen($data = substr($data, $len_m0 = strlen($m[0])));
            if ($conf = 'main/config.yaml' == $m[1] && isset($_POST['name']))
                $this->conf($data, $m[2]);
            if (false === @fwrite($write, $data, $conf ? strlen($data) : $m[2]))
                return $err("Error writing file `$fn`", $write);
            for (; $len < $m[2]; $len += $sz) {
                if (!$sz = @fwrite($write, @fread($read, Moon::chunk), $m[2] - $len))
                    return $err("Error writing file `$fn`", $write);
            }
            $this->log("FWRITE: $fn");
            $this->msg = (1 == $this->_cnt ? '' : '-') . "Written file $this->_cnt of $this->_tof";
            $this->_pos += 1 + $len_m0 + $m[2];
            $this->_cnt += 1;
            fclose($write);
        }
        fclose($read);
    }

    function finish($sql_fn = []) {
        $files = [$this->path(__FILE__), $this->path($this->_fn)] + $sql_fn;
        if ($_POST['log'][0])
            $files[] = $this->path($log = "$this->_fn.txt") . ' <a target="_blank" href="' . $log . '">open LOG</a>';
        $this->msg = 'Completed successfully';
        $this->success = "You may delete or move unnecessary files:<br>" . implode('<br>', $files);
        $this->success .= '<br><br><a href="' . substr($_SERVER['SCRIPT_NAME'], 0, -strlen('moon.php')) . '">open ' . "$this->_fn app</a>";
    }

    function _sql() {
        if (!$fn = $this->path($this->mode ? '../anew/var/app.sql' : '../var/app.sql'))
            return $this->err = "File `../var/app.sql` not found";
        $pref = $_POST['prefix'];
        if (false === ($read = @fopen($fn, 'rb')))
            return $this->err = "Cannot open file `$fn` for reading";
        $this->sql("set names utf8");
        fseek($read, $this->_pos);
        for ($len = $s = 0; $len < Moon::chunk; $len += strlen($data)) {
            if (false === ($data = fgets($read))) {
                $this->err = "Error reading `$fn` file";
                break;
            } elseif ($s = "-- END;\n" == $data) {
                break;
            }
            $this->_pos += strlen($data);
            if (preg_match("/^(create table|insert into|update|alter table) `(\w+)`/", $data, $m) && $pref)
                $data = "$m[1] `$pref$m[2]`" . substr($data, strlen($m[0]));
            if ($m && 'create table' === $m[1] && isset($_POST['drop']))
                $this->sql("DROP TABLE IF EXISTS `$pref$m[2]`");
            $this->sql($data);
            $this->_cnt += 1;
            $this->msg = "-Executed SQL query $this->_cnt of $this->_toq";
        }
        fclose($read);

        if ($s && !$len) {
            $this->finish([2 => $this->path($fn)]);
            $flag = false;
            $q = $this->sql("select tmemo from {$pref}memory where id=3");
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", mysqli_fetch_row($q)[0]));
            foreach ($lines as &$line) {
                [$k, $v] = explode(' ', $line, 2);
                if ($flag = 'version' == $k) {
                    $line = "version $this->_ver";
                    break;
                }
            }
            $flag or $lines[] = "version $this->_ver";
            $vars = mysqli_real_escape_string($this->conn, implode("\n", $lines));
            $this->sql("update {$pref}memory set tmemo='$vars' where id=3");
        }
    }

    function drop_dir($top) {
        $ary = self::walk_dirs($top);
        rsort($ary);
        foreach ($ary as $dir) {
            foreach (self::list_path($dir, 'is_file') as $file) {
                if (!@unlink($file))
                    return $this->err = "Cannot delete file `$file`";
                $this->log("UNLINK: $file");
            }
            if (!@rmdir($dir))
                return $this->err = "Error clearing `$top`";
            $this->log("RMDIR: $dir");
        }
    }

    function move($exf) { # $mode=2-aold2p 3-anew2p (0 1)
        $this->log($this->mode ? "MOVE ANEW TO PRODUCTION.." : "ROLLBACK..");
        $list = $this->walk_parent();
        if ($d = $list[$dst = $this->mode ? 'aold' : 'anew'][2])
            return $this->err = "Directory `$dst` is not empty";
        if (!$list[$src = $this->mode ? 'anew' : 'aold'][2])
            return $this->err = "Directory `$src` is empty or not exists";
        if (false === $d) {
            if (!@mkdir("$this->dir/$dst")) // @mkdir($dir, 0755)?
                return $this->err = "Cannot create `$dst` directory";
            $this->log("MKDIR: $this->dir/$dst");
        }
        foreach ($list as $fn => $v) { # move prod to anew/aold
            if ($fn === $this->web) {
                if (!@mkdir("$this->dir/$dst/$fn"))
                    return $this->err = "Cannot create `$dst/$fn` directory";
                $this->log("MKDIR: $this->dir/$dst/$fn");
            } elseif (!$v[1] && !in_array($fn, $exf)) {
                if (!$this->mv("$this->dir/$fn", "$this->dir/$dst/$fn"))
                    return $this->err = "Cannot move `$fn` to `$dst/$fn`";
            }
        }
        $ary = self::list_path("$this->dir/$src");
        $ary = array_merge($ary, self::list_path("$this->dir/$src/$this->web"));
        sort($ary);
        foreach ($ary as $fn) { # move anew/aold to prod
            $rel = substr($fn, 6 + strlen($this->dir));
            if ($rel == $this->web)
                continue;
            if (file_exists($prod = "$this->dir/$rel"))
                return $this->err = "Cannot move from `$src` to `$rel`, item exists";
            if (!$this->mv($fn, $prod))
                return $this->err = "Error moving from `$src` to `$rel`";
        }
        if (is_dir($fn = "$this->dir/$src/$this->web")) {
            if (!@rmdir($fn))
                return $this->err = "Cannot delete directory `$src/$this->web`";
            $this->log("RMDIR: $this->dir/$src/$this->web");
        }
        $this->success = $_POST['success'] or $this->success = 1;
        $this->msg = "Moved from `$src` to production successfully";
    }

    function _etc() { // _com= refresh|n|o|move
        $exf = 'refresh' == $this->_com ? $this->preset() : explode('&', urldecode($_POST['exf']));
        if (1 == strlen($this->_com))
            $this->drop_dir($this->dir . ('o' == $this->_com ? '/aold' : '/anew'));
        if ('move' == $this->_com)
            $this->move($exf);
        $tpl = '<input%s type="checkbox" name="exf" value="%s">';
        $this->pd = '';
        foreach ($this->walk_parent() as $fn => $v) {
            $ch = in_array($fn, $exf) ? ' checked' : '';
            $this->pd .= '<div>' . sprintf($tpl, $v[1] ? ' disabled checked' : $ch, $fn);
            $this->pd .= ($v[1] ? $v[1] : $v[0]) . '</div>';
        }
    }

    function preset() {
        [$head,, $data] = $this->head($this->_fn);
        preg_match("/^DIRS: (\d+)\n([^\n]+)\n/", $data, $m);
        $ln = strlen($x = $head['www']);
        $ary = explode(' ', "$m[2] $head[filep]");
        $www = basename(__DIR__);
        $dirs = array_map(fn($v) => $x == substr($v, 0, $ln) ? "$www/" . substr($v, $ln) : $v, $ary);
        $d1 = array_map(fn($v) => "$www/$v", self::list_path('.'));
        $d2 = array_map(fn($v) => substr($v, 3), self::list_path('..'));
        return array_diff(array_merge($d1, $d2), $dirs);
    }

    function walk_parent() {
        $ary = self::list_path($this->dir);
        in_array($dir = "$this->dir/anew", $ary) or $ary[] = $dir;
        in_array($dir = "$this->dir/aold", $ary) or $ary[] = $dir;
        $ary = array_merge($ary, self::list_path("$this->dir/$this->web"));
        sort($ary);
        $list = [];
        $t1 = '%s <i style="color:green;font-size:10px">%s</i>';
        array_walk($ary, function($one) use (&$list, $t1) {
            $one = substr($one, 1 + strlen($this->dir));
            $dir = is_dir("../$one") ? "<b>$one</b>" : $one;
            $spec = $nem = false;
            if ($one == $this->web)
                $spec = sprintf($t1, $dir, "(public directory)");
            if (basename($one) == 'moon.php')
                $spec = sprintf($t1, $dir, "(Moon script)");
            if (substr($one, -4) == '.sky')
                $spec = sprintf($t1, $dir, "(SKY Package)");
            if ($one == "$this->web/$this->_fn.txt")
                $spec = sprintf($t1, $dir, "(LOG file)");
            if ($one == 'aold' || $one == 'anew') {
                if (!file_exists("../$one")) {
                    $s = '<u style="color:red">directory not exists!</u>';
                } else {
                    $s = ($nem = count(self::list_path("../$one")))
                        ? '<u style="color:red">directory is not empty!</u>'
                        : '<u style="color:green">directory is empty</u>';
                    $s .= " <a href=\"javascript:;\" onclick=\"$$.etc('$one[1]')\">[DROP]</a>";
                }
                $spec = sprintf($t1, "<b>$one</b>", "") . " - $s";
            }
            $list[$one] = [$dir, $spec, $nem];
        });
        return $list;
    }

    function head($fn) {
        $read = fopen($fn, 'rb');
        $data = fread($read, Moon::chunk);
        fclose($read);
        $head = ['fn' => $fn, '_' => [0, 0, 0]];
        $pos = strpos($data, "\n\n");
        $ary = false === $pos ? ['corr 1'] : explode("\n", substr($data, 0, $pos));
        foreach ($ary as $line) {
            [$k, $v] = explode(' ', $line, 2);
            if ($k == 'mod_required')
                $v = explode(' ', $v);
            $head[$k] = $v;
            if ($k == 'ftrd' && preg_match("/^A\^(\d+)\.(\d+)\.(\d+)\.(\d+)/", $v, $m))
                $head['_'] = [$m[1], $m[2] + $m[3], $m[4]];
                
                
        }
        return [$head, $pos, substr($data, 2 + $pos)];
    }

    function table($fn) {
        $dec = [
            'type' => 'Package type',
            'fn' => 'Filename',
            'mod_required' => 'Required PHP modules',
            'desc' => 'Description',
            'version' => 'Versions',
            'compiled' => 'TZ, compiled TS, CS-ver, APP-ver',
            'ftrd' => 'Added files, tables, rows dirs (FTRD)',
        ];
        echo '<table width="100%" style="background:silver;margin-top:20px">';
        [$head] = $this->head($fn);
        $tpl = ' - <a style="font:bold 15px monospace" href="javascript:;" onclick="$$.select(%s)">Install</a>';
        $tpl .= function_exists('shell_exec')
            ? ' or.. <a style="" href="javascript:;" onclick="$$.cli(%s)">Run in console</a>'
            : ' ..cannot use console for %s';
        foreach ($head as $k => $v) {
            if (in_array($k, ['www', 'corr', '_']))
                continue;
            if ('fn' == $k) {
                $_ = $head['_'];
                $v .= isset($head['corr'])
                    ? ' - <span style="color:red">file is corrupted</span>'
                    : sprintf($tpl, "['$v',$_[0],$_[1],$_[2]]", "'$v'");
            } if ('type' == $k) {
                $v .= ", filesize: " . filesize($fn);
            } elseif ('mod_required' == $k) {
                $all = get_loaded_extensions();
                foreach ($v as &$one)
                    $one = sprintf('<span style="background:%s">%s</span>', in_array($one, $all) ? '#bfb' : '#fbb', $one);
                $v = implode(', ', $v);
            }
            printf('<tr><td width="30%%">%s</td><td>%s</td></tr>', $dec[$k], $v);
        }
        echo '</table>';
    }

    function packages() {
        if ($list = glob('*.sky')) {
            foreach ($list as $fn)
                $this->table($fn);
        }
        if ($lst2 = glob('*.sky.bz2')) {
            foreach ($lst2 as $fn) {
                echo $fn . ' - <a href="javascript:;" onclick="$$.unpk(\'' . $fn . '\')">unpack</a><br>';
            }
        }
        if (!$list && !$lst2)
            echo '<h2 style="color:red">No package files found!</h2>';
        printf('<br>PHP_VERSION: %s, shell_exec: %s', PHP_VERSION, function_exists('shell_exec') ? 'OK' : 'FAILED');
    }

    static function list_path($dir, $func = '', $skip = [], $up = false) {
        if ('/' === $dir)
            $dir = '.';
        if (!is_dir($dir))
            return [];
        $list = $up ? ['..'] : [];
        if ($dh = opendir($dir)) {
            while ($name = readdir($dh)) {
                if ($name == '.' || $name == '..')
                    continue;
                $path = $dir == '.' ? $name : "$dir/$name";
                if (!$func || $func($path)) {
                    if (in_array($path, $skip))
                        continue;
                    $list[] = $path;
                }
            }
            closedir($dh);
        }
        return $list;
    }

    static function walk_dirs($dir, $skip = []) {
        if ('/' === $dir)
            $dir = '.';
        if (!file_exists($dir))
            return [];
        $list = [$dir];
        foreach (self::list_path($dir, 'is_dir', $skip) as $path)
            $list = array_merge($list, self::walk_dirs($path, $skip));
        return $list;
    }
}

if (isset($_POST['cli'])) {
    shell_exec("php moon.php $_POST[cli] -");
    exit;
}

if (isset($_POST['unpk'])) {
    $bz = bzopen($fn = $_POST['unpk'], 'r');
    $wr = fopen(substr($fn, 0, -4), 'w');
    while(!feof($bz)) {
        $str = bzread($bz);
        fwrite($wr, $str);
    }
    bzclose($bz);
    unlink($fn);
    exit;
}

$moon = new Moon;

if ('cli' == PHP_SAPI):
    ob_end_flush();
    if (version_compare(PHP_VERSION, '7.4.0') <= 0) {
        echo "\nPHP version required >= 7.4.0\n";
        exit;
    }
    if (1 == $argc) {
        echo "Usage: php moon.php packagename.sky [user:password@databasehost:3306/databasename:pref_]\n";
        echo "  dsn example: root:@localhost/testdb\nor.. use web-interface\n";
        exit;
    }
    $moon->mode = 0;
    $moon->_fn = $argv[1];
    $_POST = ['log' => '00'];
    if (3 == $argc && '-' != $argv[2]) {
        if (!preg_match("~^(\w+):([^@]*)@([^:/]+):?(\d+|)/(\w+):?(\w+|)$~", $argv[2], $m))
            exit("\nDSN don't match\n");
        $_POST += [
            'user' => $m[1],
            'password' => $m[2],
            'host' => $m[3],
            'port' => $m[4] ?: 3306,
            'name' => $m[5],
            'prefix' => $m[6],
        ];
    }
    while (true) {
        $moon->{"_$moon->_func"}();
        if ($moon->json['err'] || $moon->success)
            break;
        if ($moon->json['msg'])
            echo $moon->json['msg'] . "\r";
    }
    if ($moon->json['err'])
        echo "\n" . $moon->json['err'] . "\n";

    if ($moon->success && ($argc < 3 || '-' != $argv[2])) {
        echo "\nsuccess\n";
        system("php ../main/sky s");
    }
    exit;



else: ?><!doctype html>
<html>
<head><title>Install SKY applications</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<link href="favicon.ico" rel="shortcut icon" type="image/x-icon" />
<script>
var $$ = {
    s1: [
        'install to the production<sup>1</sup>',
        'install to the `anew` directory first<sup>2</sup>',
        'move from `aold` to production<sup>3</sup> (rollback)',
        'move from `anew` to production<sup>4</sup>'
    ],
    s2: [
        "<sup>1</sup> This is usual installation. Files extracts from the\npackage to there working places (production).\n"
            + "Parent and public directory must be empty",
        "<sup>2</sup> Package files extract to the `anew` directory, then old\ninstallation moves to the `aold` directory, "
            + "then new installation\nfrom `anew` directory moves to the production.\n"
            + "Moving is a quick operation, used PHP function rename().\n"
            + "Check parent directory before start: assume all \n"
            + "<b>UNchecked files</b> and directories will moved to `aold`.",
        "<sup>3</sup> SKY-package file don't used. This is something like\nrollback. Files and directories from "
            + "production moves to the `anew`\ndirectory, then files from `aold` firectory moves to the production\n"
            + "Database will untouched.",
        "<sup>4</sup> After rollback: you can move the new installation\nto the production again",
    ],
    form_html: '',
    mode: 2,
    type: function(v) {
        var div = $('#p2');
        if (v > 1) {
            v -= 2;
            div.find('span:eq(0)').html($$.s1[v]);
            div.find('span:eq(1)').html($$.s1[v + 1]);
            $$.mode += v ? 2 : -2;
            div.find('button').html($$.mode > 1 || !$$.fd[2] ? 'Start' : 'Next');
        } else if ($$.mode % 2 && !v) {
            $$.mode -= 1;
        } else if (0 == $$.mode % 2 && v) {
            $$.mode += 1;
        }
        var a = '<a href="javascript:;" onclick="$(this).parent().hide()" style="float:right">hide [X]</a>';
        div.find('pre:eq(1)').html(a + $$.s2[$$.mode]);
    },
    fd: [], // [fn,f,tr,d]
    select: function(fd) {
        $$.fd = fd;
        $('#page div:eq(0)').hide().next().show();
        $('.fn').html(fd[0].toUpperCase());
        $('input[name=step]').val('0=' + fd[0]);
        if (fd[2])
            $('#f3').prepend($$.form_html);
        $('input[name=prefix], input[name=name]').keyup(function() {
            $(this).parents('dl').css('background', $(this).val() ? '' : 'pink');
        });
        $$.type(2);
        $$.etc('refresh');
    },
    stop: true,
    button: null,
    response: function(r, msg) {
        r.err || $('.r').remove();
        if (r.err) {
            $$.stop = true;
            $($$.button).html('Continue');
            $('#log').append('<div class="r">' + r.err + '</div>');
        } else if ('-' == r.msg.charAt(0)) {
            $('#log div.y:last').html(r.msg.substr(1));
        } else if (msg) {
            var cls = r.success ? 'g' : 'y';
            $('#log').append('<div class="' + cls + '">' + r.msg + '</div>');
            if (r.success && r.success != 1 && $$.mode != 1)
                $('#log').append('<div class="w">' + r.success + '</div>');
        }
    },
    postfields: function(v) {
        var log = $('input[name=log]').is(':checked') ? '1' : '0';
            log += $('input[name=reset]').is(':checked') ? '1' : '0';
        if (v)
            log += '&' + $('#f3').serialize();
        return 'mode=' + $$.mode + '&log=' + log;
    },
    etc: function(com, success, el) { // refresh n o move
        if (1 == com.length && !confirm('Are you sure? Drop ' + ('n' == com ? 'anew' : 'aold') + ' directory?'))
            return;
        if ('move' == com && $$.mode < 2)
            return $$.fd[2] ? $('#p2').hide().next().show() : $$.run(el);
        var q = {
            step: $.param({func:'etc', com:com, fn:$$.fd[0]}),
            exf: $('#parent').serialize().replace(/exf=/g, ''),
            success: success
        };
        $.post('moon.php', $.param(q) + '&' + $$.postfields(0), function(r) {
            $$.response(r, 'move' == com);
            $('#parent').html(r.pd);
            $$.parent_div();
            if (success)
                $$.mode = 1;
        });
    },
    parent_div: function(x) {
        $('#parent div').each(function() {
            var inp = $(this).find('input'), ch = inp.is(':checked');
            $(this).css({
                background:ch ? '' : '#ff9',
                borderBottom:'1px solid silver'
            });
            x || inp.is(':disabled') || $(this).click(function() {
                ch = inp[0].checked = !ch;
                $$.parent_div(1);
            });
        });
    },
    unpk: function(fn) {
        $.post('moon.php', {unpk:fn}, function(r) {
            location.href = 'moon.php';
        });
    },
    cli: function(fn) {
        $.post('moon.php', {cli:fn}, function(r) {
            location.href = location.href.substr(0, location.href.length - 8);
        });
    },
    run: function(el) {
        if (el) { // button clicked
            $(el).html(($$.stop = !$$.stop) ? 'Continue' : 'Pause');
            $$.button = el;
        }
        $$.stop || $.post('moon.php', $$.postfields(1), function(r) {
            $$.response(r, true);
            $('input[name=step]').val($.param(r.step));
            r.success || $$.run();
            if (r.success && $$.mode) {
                $$.mode = 3;
                $$.etc('move', r.success);
                $($$.button).hide();
            }
        });
    }
};
$(function() {
    var div = $('#foo').next();
    $$.form_html = div.html();
    $('#p2').html(div.next().html());
    div.next().remove();
    div.remove();
});
</script>
<style>
#page { margin:8px auto 0 auto; width:600px; padding:5px 100px; border-bottom:2px solid lightblue; min-height:calc(100vh - 55px) }
#foo { margin:0 auto;width:790px; font-size:14px; padding:5px; background:white; text-align:center; }
#log { clear:both; margin-top:20px; }
.r, .g, .y, .w { padding:7px; margin-top:5px; border-bottom:2px solid silver; }
.r { background:pink } .g { background:#bfb } .y { background:#ff9 }
a, h1 { color: #3d7098; }
h1 { font-size: 25px; margin-top: 30px; border-bottom: 4px solid #3d7098; }
table, td, #page { background: white; font-family: arial, verdana; font-size: 90%; }
table, td { padding:5px; }
a:hover { text-decoration: none; color: white; background-color: #3d7098; }
dl { width: 100%; margin: 5px 0; }
dt { float: left; text-align: right; width: 35%; padding-right: 5px; }
dd { text-align: left; margin-left: 35%; }
#parent { margin:15px 0; display:none }
.fl { float:left;position:absolute; }
</style>
</head>
<body style="margin:0; display:inline-block; width:100%; background:lightblue;">
<div id="page">
    <h1 onclick="location.href='moon.php'">Install <span class="fn">SKY applications</span></h1>
    <div><?php $moon->packages() ?></div>
    <div style="display:none">
        <div id="p2"></div>
        <div style="display:none">
            <a href="javascript:;" onclick="$('#p2').show().next().hide()" class="fl">&lt;&lt;&lt;back</a>
            <form id="f3"><input type="hidden" name="step"></form>
            <dl><dt>&nbsp;</dt><dd><button onclick="$$.run(this)">Start</button></dd></dl>
        </div>
        <div id="log"></div>
    </div>
</div>
<div id="foo">Moon is the installer for all <a href="https://coresky.net/">SKY</a> applications</div>

<div>
<dl><dt>Database host:</dt><dd><input name="host" value="localhost" size="12">
    &nbsp; Port: <input name="port" size="4"> <small>empty for 3306</small>
</dd></dl>
<dl style="background:pink"><dt>Database name:</dt><dd><input name="name" size="12">
    <label><input name="create" type="checkbox" checked> create if not exists</label><br>
    <label><input name="drop" type="checkbox"> drop tables if exists before creation</label>
    </dd></dl>
<dl style="background:pink"><dt>Add prefix for tables:
    </dt><dd><input name="prefix" size="12"> <small>example: <b>ab_</b></small></dd></dl>
<dl><dt>Database user:</dt><dd><input name="user" value="root" size="12">
    &nbsp; Password: <input name="password" size="12">
</dd></dl>
</div>

<div>
<pre style="background:#ff9; padding:10px; font-size:15px">
<a href="javascript:;" onclick="$(this).parent().hide()" style="float:right">hide [X]</a>The current directory is:
 <b><?php echo "$moon->dir/$moon->web" ?></b> (public)
This install script creates multiple directories in the
<u>parent directory</u> <a href="javascript:;" onclick="$$.etc('refresh')"><b><?php echo $moon->dir ?></b></a>, for example:
 <b><?php echo "$moon->dir/main" ?></b></pre>
<fieldset><legend>Select installation type:</legend>
    <label><input type="checkbox" checked onchange="$$.type(this.checked ? 2 : 4)">
    use `<b class="fn"></b>` package for new installation</label><br>
    <label><input name="type" type="radio" onclick="$$.type(0)" checked> <span></span></label> &nbsp;
    <label><input name="type" type="radio" onclick="$$.type(1)"> <span></span></label><hr>
    <label><input name="log" type="checkbox" checked> write installation log file</label> &nbsp;
    <label><input name="reset" type="checkbox"> reset log from start</label><br><br>
    <button onclick="$$.etc('move','',this)"></button>
    <pre style="background:#ff9; padding:10px; font-size:15px"></pre>
</fieldset>
<fieldset style="margin:10px 0"><legend>Parent directory contain now:</legend>
    <a href="javascript:;" onclick="$(this).next().toggle()" style="float:right">collapse/expand</a>
    <form id="parent"></form>
</fieldset>
</div>
</body>
</html><?php endif;
