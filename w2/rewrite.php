<?php

class Rewrite
{
    static $map;
    static $test = [];

    static function lib(&$map, &$list = null) {
        $fp = fopen(__FILE__, 'r');
        fseek($fp, __COMPILER_HALT_OFFSET__);
        $lib = explode("\n~\n", trim(unl(stream_get_contents($fp))));
        fclose($fp);
        $list = [];
        array_walk($lib, function (&$v) use (&$list) {
            list ($name, $test, $v) = explode("\n", $v, 3);
            list ($name, $is_dev) = explode(" ", $name);
            $list[] = $name;
            $v = [$name, $v, $is_dev, $test];
        });
        $map or $map = array_slice($lib, 0, 3);
        return $lib;
    }

    static function vars($restore) {
        global $sky;
        static $get, $surl;
        if ($restore) {
            $sky->surl = $surl;
            $_GET = $get;
        } else {
            $surl = $sky->surl;
            $get = $_GET;
        }
    }

    static function get(&$lib, &$map, &$list) {
        $map = Plan::_rq('rewrite.php');
        $lib = self::lib($map, $list, true);
        self::$map =& $map;
        self::vars(false);//return;
        foreach ($map as $rw) {
            if ('' === $rw[3])
                continue;
            self::$test[$rw[3]] = '/' . self::test($rw[3]);
        }
        trace(self::$test);
    }

    static function put(&$n) {
        list ($mode, $x, $y) = explode(' ', $_POST['mode'], 3);
        switch ($mode) {
            case 'save':
                self::$map[$n = $x] = [$_POST['n'], $_POST['php'], $_POST['x'], $_POST['u']];
                Plan::cache_p('_test.php', '<?php ' . $_POST['php']);
                $line = exec('php -l ' . Plan::cache_t('_test.php'));
                if ('No syntax errors' != substr($line, 0, 16))
                    return false;
                break;
            case 'move':
                $R = array_splice(self::$map, $x, 1);
                array_splice(self::$map, $n = $y, 0, $R);
                break;
            case 'drop':
                array_splice(self::$map, $n = $x, 1);
                $n < count(self::$map) or $n--;
                break;
            case 'add': # insert from lib
                $R = self::lib(self::$map)[$y];
                array_splice(self::$map, $n = 1 + $x, 0, [$R]);
                break;
        }
        $_POST = []; # erase form data from POST
        return Plan::_p('rewrite.php', Plan::auto(self::$map));
    }

    static function highlight(&$v) {
        $v[4] = str_replace('&lt;?php<br /><br />', '', highlight_string("<?php\n\n$v[1]", true));
    }

    static function test($uri, $code = false) {
        global $sky;
        if ($uri && '/' == $uri[0])
            $uri = substr($uri, 1);
        $ary = parse_url($uri);
        isset($ary['query']) ? parse_str($ary['query'], $_GET) : ($_GET = []);
        $sky->surl = [];
        if (isset($ary['path']))
            $sky->surl = explode('/', $ary['path']);
        $cnt = count($sky->surl);
        if ($code) {
            $surl =& $sky->surl;
            eval($code);
        } else {
            SKY::$plans['main']['rewrite']($cnt, $sky->surl, $uri, $sky);
        }
        return implode('/', $sky->surl) . ($_GET ? '?' . urldecode(http_build_query($_GET)) : '');
    }

    static function cmp($uri, &$rw) {
        if ($uri === $rw)
            return true;
        if ($rw && '=' === $rw[-1])
            $rw = substr($rw, 0, -1);
        return $uri === $rw;
    }

    static function chk_tests($act, $ctrl) {
        $pfx = substr($act, 0, 2);
        if ('a_' == $pfx || 'j_' == $pfx)
            $act = substr($act, 2);
        //trace($act, $ctrl);
        $ext = [];
        if ('*' == $ctrl) {
            foreach (self::$test as $in => $out) {
                if (preg_match("|^/$act|", $out))
                    $ext[] = $in;
            }
        }
        return $ext ? implode('<br>', $ext) : false;
    }

    static function external(&$ary, $ctrl) {
        usort($ary, function ($a, $b) {
            if (in_array($a->func[0], ['e', 'd']))
                return -1;
            if (in_array($b->func[0], ['e', 'd']))
                return 1;
            return strcmp($a->func, $b->func);
        });
        $trait = ['crash', 'test_crash', 'etc/{0}/{1}', '?init='];
        foreach ($ary as $row) {
            $uri = substr(strip_tags($row->uri), 1);
            $rw = self::test($uri);
            $row->trait = false;
            if ($row->ext = self::chk_tests($row->func, $ctrl)) {
                
            } else {
                if (self::cmp($uri, $rw)) { # no rewrite
                    $row->ext = $row->uri;
                    $row->uri = true;
                } else { # test reverse uri
                    $rw2 = self::test($rw);
                    $row->ext = self::cmp($uri, $rw2) ? "/$rw" : '';
                    if ('*' == $ctrl && in_array($uri, $trait)) {
                        $row->trait = "Do not rewrite URI!";
                    }
                }
            }
            $row->re = '';
            if ($row->var) {//'()' != $row->pars
                $pars = [];
                foreach (explode(', ', substr($row->pars, 1, -1)) as $i => $var) {
                    $ns = "<r>?</r>";
                    if (isset($row->var[$i])) {
                        $row->re .= L::r(' ~ ') . tag($row->var[$i][0], 'style="font-family:monospace;color:"', 'span');
                        $row->var[$i][2] or $ns = '';
                    }
                    $pars["{{$i}}"] = '{' . substr($var, 1) . $ns . '}';
                }
                $row->ext = strtr($row->ext, $pars);
                true === $row->uri or $row->uri = strtr($row->uri, $pars);
            }
            $row->pars = tag($row->pars, 'style="font-family:monospace"', 'span');
        }
    }
}

__halt_compiler();

Main-page 0
/
$main = '' === $uri;
if ($main || 'main' === $uri)
    return $surl = $main ? ['main'] : [];
~
Dev-ends 1

if ('_' == $sky->_0[0])
    return;
~
Assets-coresky 1
/m/{0}
if ($cnt && 'etc' == $surl[0])
    return $surl[0] = '-';
if ($cnt && 'm' == $surl[0])
    return $surl[0] = 'etc';
~
Assets-wares 1
/w/{1}/{0}
if (3 == $cnt && 'w' == $surl[0]) {
    array_shift($surl);
    $surl[2] = $surl[0];
    return $surl[0] = 'etc';
}
~
Robots.txt 0
/robots.txt
if ('robots.txt' == $uri)
    return array_unshift($surl, 'etc');
~
Swap-1-2 0
/0/2/1
if ($cnt > 2) {
    $tmp = $surl[1];
    $surl[1] = $surl[2];
    $surl[2] = $tmp;
}
~
Additive 0

if ($cnt && 'x' == $surl[0])
    $surl[0] = 'ctrl';
~
Pagination 0
/ctrl/page-2/action
if ($cnt > 2 && preg_match("/^page\-\d+$/", $surl[1])) {
    common_c::$page = (int)substr($surl[1], 5);
    array_splice($surl, 1, 1);
    $cnt--;
}
~
Url-html 0
/c/a/page.html
$ext = 'html';
if ($cnt) {
    $a = explode('.', $p =& $surl[$cnt - 1]);
    $p = 2 == count($a) && $ext == $a[1] ? $a[0] : "$p.$ext";
}
~
Url-lang 0
/en/c/a
# take language from surl (semantic url)
common_c::langs_h();
if ($cnt && in_array($surl[0], $sky->langs)) {
    common_c::$lg = array_shift($surl);
    $cnt--;
}
~
Terminator 0

$lst = ['adm', 'api', 'crash', 'test_crash'];
if ($cnt && in_array($surl[0], $lst))
    return;
~
Reverse 0
/dash-surl/a
$rw = ['dash-surl' => 'nodash',];
if ($cnt) {
    $rw += array_flip($rw);
    $p =& $surl[0];
    empty($rw[$p]) or $p = $rw[$p];
}
