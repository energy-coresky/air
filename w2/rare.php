<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class Rare
{
    static public $u_svn_skips = ['.git', '.svn'];

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
                    if (in_array($name, self::$u_svn_skips) || in_array($path, $skip))
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

    static function bracket(String $in, $b = '(') {
        if ('' === $in || $b != $in[0])
            return '';
        $close = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];
        $s = '';
        $i = 0;
        foreach (token_get_all("<?php $in") as $v) {
            if (is_array($v)) {
                $v = $v[1];
            } elseif ($b == $v) {
                $i++;
            } elseif ($close[$b] == $v) {
                $i--;
            }
            $s .= $v;
            if (!$i && '<?php ' != $s)
                return substr($s, 6);
        }
        return '';
    }

    static function cache($name = '', $func = null, $ttl = 0) {
        global $sky;
        
        if ($name) { # the begin place
            $file = 'var/cache/_' . (DEFAULT_LG ? '%s_' : '') . "$name.php";
            if (is_numeric($func))
                $ttl = $func;
            if (-1 == $ttl) # delete on ttl = -1
                return @unlink(DEFAULT_LG ? sprintf($file, $func) : $file);
            $fn = DEFAULT_LG ? sprintf($file, LG) : $file;
            $ttl or $ttl = SKY::$mem['s'][3]['cache_sec'];
            $recompile = true;
            if ($sky->s_cache_act && is_file($fn)) {
                $s = stat($fn);
                $s['mtime'] + $ttl < time() or $recompile = false;
            }

            trace("CACHE: $fn, " . ($recompile ? 'recompiled' : 'used cached'));

            if (is_callable($func)) {
                $recompile
                    ? file_put_contents($fn, $str = call_user_func($func, $name, true))
                    : ($str = file_get_contents($fn));
                return $str;
            } elseif ($recompile) {
                MVC::$cache_filename = $fn;
                ob_start();
                return true;
            }
            require $fn;
            return false;
            
        } else { # the end place in the template
            file_put_contents(MVC::$cache_filename, $str = ob_get_clean());
            echo $str;
        }
    }

    static function label($str) {
        $ary = [];
        $str = preg_replace_callback("/%(PHP|HTML)_([A-Z\d_]+)%/", function($m) use (&$ary) {
            global $sky;

            if (isset($ary[$m[0]]))
                return $ary[$m[0]];
            if ('R_' == substr($m[2], 0, 2)) {
                if (DEV && $sky->s_red_label)
                    return $ary[$m[0]] = tag($m[0], 'class="red_label"');
            }
            $label = strtolower('_' == $m[2][1] ? $m[2] : "x_$m[2]");
            if ('PHP' == $m[1]) {
                return $ary[$m[0]] = view($label, true);
            } elseif (is_file($fn = MVC::fn_tpl($label))) {
                return $ary[$m[0]] = file_get_contents($fn);
            }
            trace("LABEL: file `$fn` not found", true);
            return '';
        }, $str);
        trace('LABEL: ' . implode(', ', array_keys($ary)), 0, 1);
        return $str;
    }

    // return  # utf-8 cp1251 cp1252 koi8 cp866  or ?
    static function enc_detect($string) {
        if (is_bool($len = mb_strlen($string)))
            return '?';
        if ($len > 1e4)
            $string = mb_substr($string, 0, $len = 1e4);
        if (preg_match("//u", $string))
            return 'utf8';
        $range = function ($k) {
            return $k >= 0x41 && $k <= 0x5A || $k >= 0x61 && $k <= 0x7A;
        };
        $_51 = $_52 = $i8 = $_866 = $_e = $_r = 0;
        for ($prev = $i = 0; $i < $len; $i++) {
            $k = ord($string[$i]);
            $next = $i + 1 < $len ? ord($string[$i + 1]) : 0;
            $eng = $range($k) && ++$_e;
            if ($rus = $k > 127) {
                $_r++;
                ($range($prev) || $range($next)) && ++$_52
                    or ($k < 176) && ++$_866 # о, е, а, и, н, т, с, р, в, л, к, м, д, п, у. ƒанные буквы дают 82% покрыти€
                    or in_array($k, [224, 192, 232, 200, 241, 209, 194, 226]) && ++$_51
                    or in_array($k, [225, 193, 233, 201, 243, 211, 247, 215]) && ++$i8;
            }
            $prev = $k;
        }
        if ($_e / $len > 0.3 && $_52 > 10)
            return 'cp1252';
        if ($_866 / $_r > 0.3)
            return 'cp866';
        return $_51 >= $i8 ? 'cp1251' : 'koi8';
    }

    static function root_menu($sky) {
        $files = array_map(function ($v) {
            return substr(basename($v), 1, -4);
        }, glob('admin/_*.php'));
        $files = array_merge(array_splice($files, array_search('main', $files), 1), $files);
        $buttons = implode("\t", array_map('ucfirst', $files));
        $root_access = ceil(count($files) / 7) . "\t" . implode("\t", array_keys($files));
        SKY::a('menu', serialize([-2 => $uris = implode("\t", $files), -1 => $buttons, $uris, $root_access]));
        $sky->is_front or jump('?main=3');
    }

    private $err = [];
    private $ai_name;

    function insert($table, $set = []) {
        $ary = $this->values($table, $set);
        return $this->err ? false : sql('insert into $_` @@', $table, $ary);
    }

    function update($table, $id, $set = []) {
        $ary = $this->values($table, $set);
        return $this->err ? false : sql('update $_` set @@ where $`=$.', $table, $ary, $this->ai_name, $id);
    }

    function replace($table, $id = 0, $set = []) {
        return $id ? $this->update($table, $id, $set) : $this->insert($table, $set);
    }

    function get_errors($by = '<br>') {
        return implode($by, $this->err);
    }

    protected function values($table, $set) {
        $ary = [];
        $in_set = $set;
        if (true === $set) $set = [];
        if ($q = sql('explain $_`', $table)) {
            for ($struct = []; $r = $q->one('R'); ) $struct[] = $r[0] . ('datetime' == $r[1] ? '.N' : ('auto_increment' == $r[5] ? '.A' : ''));
        } else {
            return $this->err[''] = "Table `$table` absent";
        }
        foreach ($struct as $col) {
            in_array($x = substr($col, -2), ['.A', '.N']) and $col = substr($col, 0, -2);
            '.A' == $x and $this->ai_name = $col;
            $vall = true === $in_set && isset($_POST[$col]);
            if ('.N' == $x && !$vall) {
                if (!isset($set[$col])) $ary["!$col"] = 'now()'; # datetime column, set to now() as default
                elseif (false === $set[$col]) continue; # dont touch datetime column if set to false
            }
            if ($vall || isset($set[$col]) && is_array($set[$col])) { # validation
                if ($vall) $set[$col] = [".+", ucfirst($col) . ' can\'t be empty'];
                $val = isset($set[$col][2]) ? $set[$col][2] : trim($_POST[$col]);
                $rule = $set[$col][0];
                $err = false;
                if (!$set[$col][1]) {
                    $err = call_user_func($rule, $val);
                } elseif (is_array($val)) {
                    foreach($val as $c) preg_match("~$rule~", $c) or $err = $set[$col][1];
                } else preg_match("~$rule~", $val) or $err = $set[$col][1];

                if (false === $err) $ary[$col] = isset($set[$col][2]) ? $set[$col][2] : $_POST[$col];
                else $this->err[$col] = $err;

            }
            elseif (isset($set[$col])) $ary[$col] = $set[$col];
            elseif (isset($_POST[$col])) $ary[$col] = $_POST[$col];
        }
        return $ary;
    }

    static function oauth2($via, $func = false) {
        global $sky, $user;

        if (!$data = Oauth2::run($via))
            return 1;
        if (!isset($data->email))
            return 4;
        if (!preg_match(RE_EMAIL, $data->email))
            return 2;
        if ($row = sql('>select * from $_users where email=$+ limit 1', $data->email)) {
            if ('blk' == $row->state)
                return 3; # blocked acc
            if ('del' == $row->state)
                $user->v_xlogged = 3; # login when acc. was deleted
            $ary = ['!dt_u' => 'now()', 'state' => 'act'];
            if ($func)
                $ary += $func($row->id, $row, $data);
            sql('update $_users set @@ where id=$.', $ary, $user_id = $row->id);
        } else {
            $user->v_xlogged = 2; # login (+register) via oauth2
            $ary = ['lang' => $user->v_lg, 'register_via' => $via];
            if (isset($data->gender))
                $ary += ['gender' => $data->gender];
            if (isset($data->link))
                $ary += ["link_$via" => $data->link];
            SKY::x($ary);
            $ary = [
                'state' => 'act',
                '!pid' => 6,
                'access' => strand(30),
                'email' => $data->email,
                'passw' => '',
                '!dt_r' => 'now()',
                'umemo' => SKY::x(),
            ];
            if (isset($data->name))
                $ary += ['uname' => $data->name];
            $user_id = sql('insert into $_users @@', $ary);
            if ($func)
                $func($user_id, false, $data);
        }
        SKY::v(null, ['uid' => $user_id]);
        return [$user_id, $row ? 'login' : 'register']; # OK
    }

    static function passwd($str, $salt = 0) {
        $salt or $salt = '$1$' . strand(8) . '$';
        return crypt($str, $salt);
    }

    static function register($data, $user_id = 0) {
        if (!$user_id && is_array($data)) { # step 1, insert
            $or = qp();
            if (isset($data['login'])) {
                if (!preg_match(RE_LOGIN, $login = strtolower($data['login'])))
                    return 4;
                if (isset($data['reserved_names']) && in_array($login, $data['reserved_names']))
                    return 2;
                unset($data['reserved_names']);
                $or = qp('or login=$+', $login);
            }
            if (!preg_match(RE_PASSW, $data['passw']) || !preg_match(RE_EMAIL, $data['email']))
                return 4;
            if (sql('+select count(1) from $_users where email=$+ $$', $data['email'], $or))
                return 3;
            $data['access'] = strand(30);
            if (PASS_CRYPT)
                $data['passw'] = self::passwd($data['passw']);
            return $data + ['id' => sql('insert into $_users @@', $data)];

        # step 2 (e-mail confirmation)
        } elseif (is_numeric($user_id) && $user_id > 0 && preg_match("/^[\da-z]{23}$/i", $data)) {
            return sql('update $_users set state="act" where id=$. and access=$+', $user_id, $data);
        }
        return 0;
    }

    static function login($js_chk = true) {
        global $sky, $user;

        !$user->auth or 1 == $user->auth && !$sky->is_front or die;
        isset($_POST['login']) && isset($_POST['password']) or die;
        $match_email = preg_match(RE_EMAIL, $login = strtolower($_POST['login']));
        if (!$match_email && !preg_match(RE_LOGIN, $login) || !preg_match(RE_PASSW, $paswd = $_POST['password'])) {
            $js_chk && die;
            return false;
        }
        $r = sql('~select * from $_users where state="act" and !! = $+', $match_email ? 'email' : 'login', $login);

        if ($r && (PASS_CRYPT ? $r['passw'] == self::passwd($paswd, $r['passw']) : $r['passw'] == $paswd)) {
            $user->row = $r + $user->row;
            if (!$user->auth) {
                $user->row['u'] = SKY::ghost('u', $user->row['umemo'], ['umemo' => "update \$_users set @@ where id=$user->id"]);
                $user->v_mem = (int)isset($_POST['mem']);
                $user->row['auth'] = 1;
            } else {
                $user->row['auth'] = 2;
            }
            $now = SQL::$dd->f_dt();
            $uid = 2 == $user->auth ? -$user->id : $user->id;
            SKY::v(null, ['!dt_l' => $now, 'uid' => $uid]);
            SKY::u(null, ['!dt_u' => $now]);
        }
        return $user->auth;
    }

    static function logout($ary = []) {
        global $sky, $user;

        if ($user->auth) {
            if ($ary)
                SKY::u(null, $ary);
            SKY::v(null, ['uid' => $sky->is_front ? 0 : $user->id]);
        } elseif (!$sky->ajax) {
            throw new Exception('logout when auth=0');
        }
        $to = $sky->is_front ? $sky->lref : $user->u_uri_front;
        if ($sky->ajax)
            return !$to ? LINK : $to;
        jump([$to, '']);
    }
}
