<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

function pad($n = 3) {
    return str_repeat(' &nbsp;', $n);
}

function pad00($str, $n = 2) {
    return str_pad($str, $n, 0, STR_PAD_LEFT);
}

class Admin
{
    static $adm = ['first_page' => 'auth'];
    static $menu = [];
    private $_file = '';
    const button = '<a href="?%2$s" class="admin-btn%3$s">%1$s</a>';

    function __get($name) {
        global $sky;
        if ($name && '_' == $name[1]) switch($name[0]) {
            case 'a': return SKY::$mem['a'][3][substr($name, 2)];
            case 's': return SKY::$mem['s'][3][substr($name, 2)];
            case 't': SQL::onduty(substr($name, 2));
            case 'm': return MVC::instance()->$name;
        }
        return self::$adm[$name];
    }

    function __set($name, $value) {
        if ('a_' == substr($name, 0, 2)) {
            SKY::a(substr($name, 2), $value);
        } elseif ('y_' == substr($name, 0, 2)) {
            SKY::$reg['_y'][$name] = $value;
        } else {
            self::$adm[$name] = $value;
        }
    }

    function use_front($name) {
        MVC::instance();
        return view($name, true); # can be controller name or callback function
    }

    function get_file() {
        return $this->_file;
    }

    static function section($url, $re = false) {
        global $sky;
        $re = $re ? "($re|adm(\?.*)?)$~" : "adm(\?.*)?$~";
        return preg_match("~$re", $url, $m) ? $m : false; // $sky->re . 
    }

    static function out($out, $is_html = true) { # check Admin::out() for XSS
        if (is_array($out)) {
            echo th(0 === $is_html ? ['','',''] : ['', 'NAME', 'VALUE'], 'id="table"');
            $i = 0;
            foreach ($out as $k => $v) {
                is_string($v) or is_int($v) or $v = print_r($v, true);
                if ($is_html) $v = html($v);
                echo td([1 + $i, [$k, 'style="min-width:100px"'], $v], eval(zebra));
            }
            echo '</table>';
        } else {
            echo tag($out, 'id="pre-out"', 'pre');
        }
    }

    static function top_menu($pid) {
        global $user;
        if ($user->pid != $pid) {
            $pos = array_search('main', self::$adm['files']);
            if (false === $pos || !in_array($pos, self::$adm['cr']))
                return;
        }
        $ary = isset(self::$menu[$pid]) ? explode("\t", self::$menu[$pid]) : [];
        self::$adm['rows'] = $ary ? array_shift($ary) : 0;
        self::$adm['cr'] = $ary;
    }

    static function access() {
        global $sky, $user;

        list(, $tmemo) = sqlf('-select imemo, tmemo from $_memory where id=8');
        SKY::ghost('a', $tmemo, 'update $_memory set dt=now(), tmemo=%s where id=8');
        $menu = SKY::$mem['a'][3]['menu'] or Rare::root_menu($sky);
        self::$menu = unserialize($menu);
        self::$adm = [
            'files' => explode("\t", self::$menu[-2]),
            'names' => explode("\t", self::$menu[-1]),
            'uris' => explode("\t", self::$menu[0]),
        ];
        self::top_menu($user->pid);
        if (!self::$adm['rows'] || !self::$adm['cr'])
            return false;

        $sky->show_pdaxt = true;

        self::$adm['first_page'] = 'adm?' . self::$adm['uris'][current(self::$adm['cr'])];
        $sky->is_front = $sky->extra = false;
        if (2 != $user->auth)
            return true;

        $me = new Admin;
        trace(self::$adm);
        
        if (1 == $sky->ajax) {
            $pos = array_search($sky->_0, $me->files);
            
        } else foreach ($me->uris as $p => $u) {
            if ($u === substr(URI, 4, strlen($u))) {////////
                $pos = $p;
                break;
            }
        }
        if (!is_int($pos) || !in_array($pos, $me->cr))
            throw new Error('Admin file not found or access denied');
        
        $fn = $me->files[$pos];
        if (is_file($file = "admin/_$fn.php")) {
            SQL::onduty($fn);
            $me->_file = $file;
        }
        
        if (!$me->_file && $sky->ajax)
            throw new Error('admin ajax, no file: ' . $file);
        $me->_title = $me->_file ? $me->names[$pos] : 'File not found';
        return $me;
    }

    static function pages($ipp, $cnt = null, $ipl = 7, $throw = false) {
        list($limit, $pages, $cnt) = pagination($ipp, $cnt, $ipl, null, $throw);
        if (!$pages) return [0, 'Pages: 1', $cnt];
        $html = '';
        $tpl = '<li%s><a href="%s">%s</a></li>';
        $br = $pages->br[0] != 1 || $pages->br[1] != $pages->last;
        if ($br) $html .= sprintf($tpl . $tpl, '', $pages->a_first, '&laquo;', '', $pages->a_prev, '&lsaquo;');
        $html .= $pages->left . sprintf($tpl, ' class="active"', $pages->a_current, $pages->current) . $pages->right;
        if ($br) $html .= sprintf($tpl . $tpl, '', $pages->a_next, '&rsaquo;', '', $pages->a_last, '&raquo;');
        return [$limit, "Pages: <ul class=\"pagination\">$html</ul>", $cnt];
    }

    function process($delete = false) {
        global $sky;
        if ('list' == $sky->k_type)
            return qp('select * from $_ ');
        if ('show' == $sky->k_type || 'edit' == $sky->k_type && !$_POST)
            return qp('select * from $_ where id=$.', $_GET['id']);
        if ('delete' == $sky->k_type) {
            $cnt = sql('delete from $_ where id=$.', $_GET['id']);
            if ($delete)
                $delete($cnt);
            jump(me);
        }
        if (!$_POST)
            return;
        if ('new' == $sky->k_type)
            (new Rare)->insert(substr(me, 1));
        if ('edit' == $sky->k_type)
            (new Rare)->update(substr(me, 1), $_GET['id']);
        jump(me);
    }

    static function drop_all_cache() { // 2do Plans!
        global $sky;
        $sky->s_contr = '';
        $dirs = ['var/cache', 'var/gate', 'var/jet', 'var/extra'];
        $result = 1;
        foreach ($dirs as $dir) {
            foreach (glob("$dir/*.php") as $fn)
                $result &= (int)unlink($fn);
        }
        return $result;
    }

    static function warm_jet($is_prod) {
        //2do
    }
}
