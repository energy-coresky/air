<?php

function pad($n = 3) {
    return str_repeat(' &nbsp;', $n);
    //return str_pad($str, $n, 0, STR_PAD_LEFT); pad00($str, $n = 2)
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
            case 'a':
            case 's': return $sky->$name;
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

    static function out($out, $is_html = true, $c2 = '30%') { # check Admin::out() for XSS
        if (is_array($out)) {
            echo th(0 === $is_html ? ['','',''] : ['', 'NAME', 'VALUE'], 'id="table"');
            $i = 0;
            foreach ($out as $k => $v) {
                is_string($v) or is_int($v) or $v = print_r($v, true);
                if ($is_html)
                    $v = html($v);
                echo td([[1 + $i, 'style="width:5%"'], [$k, 'style="width:' . $c2 . '"'], $v], eval(zebra));
            }
            echo '</table>';
        } else {
            echo pre($out, 'id="pre-out"');
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

    static function menu($sky) {
        $files = array_map(function ($v) {
            return substr(basename($v), 1, -4);
        }, glob('admin/_*.php'));
        $files = array_merge(array_splice($files, array_search('main', $files), 1), $files);
        $buttons = implode("\t", array_map('ucfirst', $files));
        $root_access = ceil(count($files) / 7) . "\t" . implode("\t", array_keys($files));
        SKY::a('menu', serialize([-2 => $uris = implode("\t", $files), -1 => $buttons, $uris, $root_access]));
        $sky->is_front or jump('?main=0');
    }

    static function access() {
        global $sky, $user;

        list(, $tmemo) = sqlf('-select imemo, tmemo from $_memory where id=10');
        SKY::ghost('a', $tmemo, 'update $_memory set dt=now(), tmemo=%s where id=10');
        $menu = SKY::a('menu') or self::menu($sky);
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
        $sky->is_front = false;
        if (2 != $user->auth)
            return true;

        $sky->_list = 'list' == $sky->_1 || in_array($sky->page_p, [$sky->_1, $sky->_2, $sky->_3]);

        $me = new Admin;
        trace(self::$adm);
        $uri = '' === $sky->_0 ? self::$adm['first_page'] : URI;
        
        if (HEAVEN::J_FLY == $sky->fly) {
            $pos = array_search($sky->_0, $me->files);
            
        } else foreach ($me->uris as $p => $u) {
            if ($u === substr($uri, 4, strlen($u))) {////////
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
        
        if (!$me->_file && $sky->fly)
            throw new Error('admin ajax, no file: ' . $file);
        $me->_title = $me->_file ? $me->names[$pos] : 'File not found';
        return $me;
    }

    static function pages($ipp, $cnt = false, $ipl = 7) {
        $limit = $ipp;
        $p = pagination($limit, $cnt, 'page');
        if ($p->cnt <= $ipp)
            return [0, 'Pages: 1, Items: 1..' . $p->cnt, $p->cnt];
        $y = '<li%s><a href="%s">%s</a></li>';
        $html = 'Pages: <ul class="pagination">';
        foreach (($p->ary)($ipl) as $n)
            $html .= $n ? sprintf($y, $n == $p->current ? ' class="active"' : '', $p->url($n), $n) : '<li>..</li>';
        return [$limit, "$html</ul>, Items: " . $p->item[0] . '..' . $p->item[0] . " of $p->cnt", $p->cnt];
    }

    function process($delete = false) {
        global $sky;
        if ('list' == $sky->_type)
            return qp('select * from $_ ');
        if ('show' == $sky->_type || 'edit' == $sky->_type && !$_POST)
            return qp('select * from $_ where id=$.', $_GET['id']);
        if ('delete' == $sky->_type) {
            $cnt = sql('delete from $_ where id=$.', $_GET['id']);
            if ($delete)
                $delete($cnt);
            jump(me);
        }
        if (!$_POST)
            return;
        if ('new' == $sky->_type)
            (new Rare)->insert(substr(me, 1));
        if ('edit' == $sky->_type)
            (new Rare)->update(substr(me, 1), $_GET['id']);
        jump(me);
    }

    static function drop_all_cache() {
        global $sky;
        $result = 1;
        foreach (['cache', 'gate', 'jet'] as $plan)
            $result &= call_user_func(['Plan', "{$plan}_da"], '*');
        if (SKY::$dd) {
            $s = (int)substr($sky->s_statp, 0, -1) + 1;
            $sky->s_statp = $s > 9999 ? '1000p' : $s . 'p';
        }
        return $result;
    }

    static function warm_all() {
        //2do: sky_plan, gate, jet, svg, assets
    }
}
