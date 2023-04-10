<?php

class Rewrite
{
    static $map;
    //static $code = '';

    static function lib(&$map, &$list = null) {
        $lib = explode("\n~\n", unl(view('_img.rewrites', [])));
        $list = [];
        array_walk($lib, function (&$v) use (&$list) {
            list ($name, $samp, $v) = explode("\n", $v, 3);
            list ($name, $is_dev) = explode(" ", $name);
            $list[] = $name;
            $v = [$name, $v, $is_dev, $samp];
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
        self::vars(false);
    }

    static function put(&$n) {
        if (isset($_POST['php'])) {     # save
            self::$map[$n] = [$_POST['n'], $_POST['php'], $_POST['x'], $_POST['u']];
            Plan::cache_p('_test.php', '<?php ' . $_POST['php']);
            $line = exec('php -l ' . Plan::cache_t('_test.php'));
            if ('No syntax errors' != substr($line, 0, 16))
                return false;
        } elseif (isset($_POST['d'])) { # delete
            array_splice(self::$map, $n, 1);
            $n < count(self::$map) or $n--;
        } elseif (isset($_POST['m'])) { # move
            $R = array_splice(self::$map, $n, 1);
            array_splice(self::$map, $n = $_POST['m'], 0, $R);
        } elseif (isset($_POST['a'])) { # insert from lib
            $R = self::lib(self::$map)[$_POST['f']];
            array_splice(self::$map, $n = 1 + $_POST['a'], 0, [$R]);
        }
        return Plan::_p('rewrite.php', Plan::auto(self::$map));
    }

    static function test($uri) {
        global $sky;
        $ary = parse_url($uri);
        isset($ary['query']) ? parse_str($ary['query'], $_GET) : ($_GET = []);
        $sky->surl = [];
        if (isset($ary['path']))
            $sky->surl = explode('/', $ary['path']);
        $cnt = count($sky->surl);
        SKY::$plans['main']['rewrite']($cnt, $sky->surl, $uri, $sky);
        return implode('/', $sky->surl) . ($_GET ? '?' . urldecode(http_build_query($_GET)) : '');
        //$rewritten == $uri ? "/$uri" : '';
    }

    static function cmp($uri, &$rw) {
        if ($uri === $rw)
            return true;
        if ($rw && '=' === $rw[-1])
            $rw = substr($rw, 0, -1);
        return $uri === $rw;
    }

    static function input(&$ary) {
        usort($ary, function ($a, $b) {
            if (in_array($a->func[0], ['e', 'd']))
                return -1;
            if (in_array($b->func[0], ['e', 'd']))
                return 1;
            return strcmp($a->func, $b->func);
        });
        foreach ($ary as $row) {
            $uri = substr(strip_tags($row->url), 1);
            $rw = self::test($uri);
            if (self::cmp($uri, $rw)) {
                $row->input = true;
            } else {
                $rw2 = self::test($rw);
                $row->input = self::cmp($uri, $rw2) ? "/$rw" : '';
            }
            if ($row->var) {//'()' != $row->pars
                $pars = [];
                $re = '';
                foreach (explode(', ', substr($row->pars, 1, -1)) as $i => $var) {
                    $pars["{{$i}}"] = '{' . substr($var, 1) . '}';
                    if (isset($row->var[$i]))
                        $re .= L::r(' ~ ') . tag($row->var[$i][0], 'style="font-family:monospace;color:"', 'span');
                }
                $row->url = strtr($row->url, $pars);
                true === $row->input or $row->input = strtr($row->input, $pars);
                $row->url .= $re;
            }
            $row->pars = tag($row->pars, 'style="font-family:monospace"', 'span');
        }
    }

    static function highlight(&$v) {
        $v[4] = str_replace('&lt;?php<br /><br />', '', highlight_string("<?php\n\n$v[1]", true));
    }
}
