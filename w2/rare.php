<?php

# This file included from "sky" console script

class Rare
{
    static public $u_svn_skips = ['.git', '.svn'];

    static function list_path($dir, $func = '', $skip = [], $up = false) {
        if ('/' === $dir) // ????
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
        if ('/' === $dir) // ????
            $dir = '.';
        if (!file_exists($dir) || in_array($dir, $skip))
            return [];
        $list = [$dir];
        foreach (self::list_path($dir, 'is_dir', $skip) as $path)
            $list = array_merge($list, self::walk_dirs($path, $skip));
        return $list;
    }

    static function mail($message, $ary = [], $subject = '', $to = '') {
        if (is_string($ary)) {
            $to = $subject;
            $ary = ['subject' => trim($ary)];
        }
        return common_c::mail_h(trim($message), $ary, trim($subject), trim($to));
    }

    static function val(String $in) {
        $out = $type = $x = '';
        $space = true;
        $bool = fn($s) => 'true' == $s || 'false' == $s;
        foreach (token_get_all("<?php " . trim($in)) as $v) {
            [$k, $v] = is_array($v) ? $v : [0, $v];
            if ($space && in_array($k, [T_OPEN_TAG, T_COMMENT, T_DOC_COMMENT]))
                continue;
            $space = T_WHITESPACE == $k or $type or $type = '{' == $v || '[' == $v ? 1 : 2;
            if (1 == $type) {
                if (in_array($v, [',', ':', '{', '[', ']', '}'])) {
                    if ($x && "'" == $x[0]) {
                        $x = '"' . substr($x, 1, -1) . '"';
                    } elseif ($x && '"' != $x[0] && !is_numeric($x) && !$bool($x) && 'null' !== $x) {
                        $x = '"' . $x . '"';
                    }
                    $out .= $x . $v;
                    $x = '';
                } elseif (!$space) {
                    $x .= $v;
                }
                continue;
            }
            $out .= $v;
        }
        if (!$type || 'null' === $out)
            return null;
        $out = trim($out);
        if (1 == $type) {
            $out = json_decode($out, true);
            if (json_last_error())
                throw new Error('Yaml error 2');
            return $out;
        }
        if ('"' == $out[0] || "'" == $out[0])
            return substr($out, 1, -1);
        if (is_numeric($out))
            return is_num($out) ? (int)$out : (float)$out;
        return $bool($out) ? 'true' === $out : $out;
    }

    static function yaml(String $in) {
        $ary = [];
        $p = ['' => &$ary];
        foreach (explode("\n", unl($in)) as $s) {
            if ('' === trim($s) || '#' == substr(trim($s), 0, 1))
                continue;
            if (!preg_match("/^(\s*)(\-|[^\s:]+:)(| .*)$/", $s, $match))
                throw new Error('Yaml error 1');
            [, $indent, $k, $v] = $match;
            $indent = str_replace("\t", '    ', $indent); # tab is 4 space
            $v = self::val($v);

            if ('-' == $k[0]) {
                $_2 = array_key_last($p[$_1 = array_key_last($p)]);
                $p[$_1][$_2][] = $v;
            } else {
                $k = substr($k, 0, -1);
                if (isset($p[$indent])) {
                    $p[$indent][$k] = $v;
                    array_splice($p, 1 + array_flip(array_keys($p))[$indent]);
                } else {
                    $_2 = array_key_last($p[$_1 = array_key_last($p)]);
                    $p[$_1][$_2][$k] = $v;
                    $p[$indent] =& $p[$_1][$_2];
                }
            }
        }
        return $ary;
    }

    static function split(String $in, $b = ';', $sql_comment = true) {
        $out = [];
        $s = $rest = '';
        foreach (token_get_all("<?php " . trim($in, "\n\r \t$b")) as $i => $v) {
            if (!$i)
                continue;
            is_array($v) or $v = [0, $v];
            if (T_DEC == $v[0] && $sql_comment || $rest) {
                $rest .= '' === $rest ? '#' : $v[1]; # replace "--" to "#"
                continue;
            }
            if (!$v[0] && $v[1] === $b) {
                '' === ($s = trim($s)) or $out[] = $s;
                $s = '';
            } else {
                $s .= $v[1];
            }
        }
        if ($rest) {
            $rest = self::split($rest, $b);
            $rest[0] = ltrim($s) . '--' . substr($rest[0], 1);
            return array_merge($out, $rest);
        } elseif ('' !== ($s = trim($s))) {
            $out[] = $s;
        }
        return $out;
    }

    static function bracket(String $in, $b = '(') {
        if ('' === $in || $b != $in[0])
            return '';
        $close = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];
        $s = '';
        $i = 0;
        if ('{' == $b)
            $in = 'function' . $in;
        foreach (token_get_all("<?php $in") as $v) {
            if (is_array($v)) {
                if (!$i && in_array($v[0], [T_OPEN_TAG, T_FUNCTION]))
                    continue;
                $v = $v[1];
            } elseif ($b == $v) {
                $i++;
            } elseif ($close[$b] == $v) {
                $i--;
            }
            $s .= $v;
            if (!$i)
                return $s;
        }
        return '';
    }

    static function strcut($str, $n = 100) {
        $z = mb_substr($str, 0, $n);
        return mb_strlen($str) > $n
            ? trim(mb_substr($z, 0, mb_strrpos($z, ' ', 0) - mb_strlen($z)), '.,?!') . '&nbsp;...'
            : $z;
    }

    static function optimize($in) {
        $ct = false; /* optimize `?><?php` in parsed templates */
        $buf = [];
        foreach (token_get_all($in) as $tn) {
            is_array($tn) or $tn = [0, $tn];
            if ($tn[0] == T_OPEN_TAG && $ct) {
                array_pop($buf);
                T_WHITESPACE != end($buf)[0] or array_pop($buf);
                $tn = [0, in_array(end($buf)[1], [':', ';']) ? "\n" : ";\n"];
            }
            $buf[] = $tn;
            $ct = T_CLOSE_TAG == $tn[0];
        }
        for ($str = ''; $buf; $str .= array_shift($buf)[1]);
        return $str;
    }

    static function cache($name = false, $func = '', $ttl = -3) {
        global $sky;
        static $cache = [];

        if ($name) { # the begin place
            if (is_numeric($func)) {
                $tmp = $ttl;
                $ttl = $func;
                $func = $tmp;
            }
            if (is_array($name)) {
                $plan = "Plan::$name[0]_";
                $fn = (isset($name[2]) ? "$name[2]/" : '') . "$name[1].php";
            } else {
                $plan = 'Plan::cache_';
                if (-2 == $ttl) # quiet delete file on ttl = -2
                    return Plan::cache_d('jet_' . (DEFAULT_LG ? $func . '_' : '') . "$name.php");
                $fn = 'jet_' . (DEFAULT_LG ? LG . '_' : '') . "$name.php";
            }
            if (-3 == $ttl && '' === ($ttl = $sky->s_cache_sec)) { # get ttl from SKY conf
                $sky->s_cache_act = 1;
                $sky->s_cache_sec = $ttl = 300; # 5 min
            }
            $mtime = ("{$plan}mq")($fn);
            $recompile = !$sky->s_cache_act || !$mtime || -1 != $ttl && ($mtime + $ttl < time());
            trace("$fn, " . ($recompile ? 'recompiled' : 'used cached'), 'CACHE');

            if (is_callable($func)) {
                $recompile
                    ? ("{$plan}p")($fn, $str = call_user_func($func))
                    : ($str = ("{$plan}g")($fn));
                return $str;
            } elseif ($recompile) {
                $cache[] = [$plan, $fn];
                ob_start();
                return true; # if (true) .. recompile Jet-cache-area ~if
            }
            ("{$plan}r")($fn); # echo to Jet-stdout
            return false; # if (false) .. no recompile ~if
            
        } else { # the end place in the template
            echo $str = ob_get_clean();
            list ($plan, $fn) = array_pop($cache);
            ("{$plan}p")($fn, $str);
        }
    }

    static function label($str) {
        $ary = [];
        $str = preg_replace_callback("/%(PHP|HTML)_([A-Z\d_]+)%/", function($m) use (&$ary) {
            if (isset($ary[$m[0]]))
                return $ary[$m[0]];
            if ('R_' == substr($m[2], 0, 2)) {
                if (DEV && SKY::d('red_label'))
                    return $ary[$m[0]] = tag($m[0], 'class="red_label"');
            }
            $label = strtolower('_' == $m[2][1] ? $m[2] : "x_$m[2]");
            if ('PHP' == $m[1]) {
                return $ary[$m[0]] = view($label, true);
            } elseif ($html = Plan::view_gq("$label.html")) {
                return $ary[$m[0]] = $html;
            }
            trace("LABEL: `$label` not found", true);
            return '';
        }, $str);
        trace(implode(', ', array_keys($ary)), 'LABEL', 1);
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
                    or ($k < 176) && ++$_866 # �, �, �, �, �, �, �, �, �, �, �, �, �, �, �. ������ ����� ���� 82% ��������
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
        $dd = SKY::$dd;
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
            if ($dd->sql('+select count(1) from $_users where email=$+ $$', $data['email'], $or))
                return 3;
            $data['access'] = strand(30);
            if (cfg('auth')->crypt)
                $data['passw'] = self::passwd($data['passw']);
            return $data + ['id' => $dd->sql('insert into $_users @@', $data)];

        # step 2 (e-mail confirmation)
        } elseif (is_num($user_id) && $user_id > 0 && preg_match("/^[\da-z]{23}$/i", $data)) {
            return $dd->sql('update $_users set state="act" where id=$. and access=$+', $user_id, $data);
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

        if ($r && (cfg('auth')->crypt ? $r['passw'] == self::passwd($paswd, $r['passw']) : $r['passw'] == $paswd)) {
            $user->row = $r + $user->row;
            if (!$user->auth) {
                $user->row['u'] = SKY::ghost('u', $user->row['umemo'], ['umemo' => "update \$_users set @@ where id=$user->id"]);
                $user->v_mem = (int)isset($_POST['mem']);
                $user->row['auth'] = 1;
            } else {
                $user->row['auth'] = 2;
            }
            SKY::v('emulate', null);
            $now = SQL::$dd->f_dt();
            $uid = 2 == $user->auth ? -$user->id : $user->id;
            SKY::v(null, ['!dt_l' => $now, 'uid' => $uid]);
            SKY::u(null, ['!dt_u' => $now]);
        }
        return $user->auth;
    }

    static function logout($to = '', $ary = []) {
        global $sky, $user;

        if (!$user->auth)
            throw new Hacker('Logout when auth=0');
        if ($ary)
            SKY::u(null, $ary);
        SKY::v(null, ['uid' => 2 == $user->auth ? $user->id : 0]);
        SKY::v('emulate', null);
        if (!$sky->fly && false !== $to)
            jump($to);
    }
}
