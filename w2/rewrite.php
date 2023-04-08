<?php

class Rewrite
{
    static $map;

    static function lib(&$map, &$list = null) {
        $lib = explode("\n~\n", unl(view('_img.rewrites', [])));
        $list = [];
        array_walk($lib, function (&$v) use (&$list) {
            list ($name, $samp, $v) = explode("\n", $v, 3);
            list ($name, $is_dev) = explode(" ", $name);
            $list[] = $name;
            $v = [$name, $v, $is_dev, $samp];
        });
        $map or $map = array_slice($lib, 0, 5);
        return $lib;
    }

    static function get(&$lib, &$map, &$list) {
        $map = Plan::_rq('rewrite.php');
        $lib = self::lib($map, $list);
        self::$map =& $map;
    }

    static function put(&$n) {
        if (isset($_POST['php'])) {     # save
            self::$map[$n] = [$_POST['n'], $_POST['php'], $_POST['x'], $_POST['u']];
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
        Plan::_p('rewrite.php', Plan::auto(self::$map));
    }

    static function highlight(&$v) {
        $v[4] = str_replace('&lt;?php<br /><br />', '', highlight_string("<?php\n\n$v[1]", true));
    }
}
