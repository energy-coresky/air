<?php

/*
 * $user->id $user->vid $user->pid $user->gid $user->auth
 */

//////////////////////////////////////////////////////////////////////////
class USER
{
    const BANNED = 1;
    #const IP_CHANGED = 2;
    const NO_ANY_C = 4;
    const NO_PRETTY = 8;
    const UA_CHANGED = 16;
    const NO_C = 32;
    const NO_JS = 64;
    const IS_BOT = 128;
    const NOT_TAILED = 1048576;
    const POINT_LG = 1;
    const POINT_M = 2;

    public $row = [];
    public $root = 0;
    public $cookie_srv; # set to .exampl.net when use en.m.exampl.net domain

    protected $pretty;

    function __get($name) {
        if ('_' == $name[1] && in_array($char = $name[0], ['v', 'u']))
            return SKY::$char(substr($name, 2));
        return array_key_exists($name, $this->row) ? $this->row[$name] : '';
    }

    function __set($name, $value) {
        if ('_' == $name[1] && in_array($char = $name[0], ['v', 'u']))
            return SKY::$char(substr($name, 2), $value);
        $this->row[$name] = $value;
    }

    function __call($name, $args) {
        if (!in_array($name, ['login', 'logout', 'register', 'oauth2']))
            throw new Error("Method USER::$name not exists");
        if (!$this->pretty)
            throw new Error('Cookie not set or not pretty');
        return call_user_func_array(['Rare', $name], $args);
    }

    function __construct($cookie_srv = '') {
        global $sky;

        $this->cookie_srv = $cookie_srv;
        $ua = preg_replace("|[\r\n\t]+|", ' ', $_SERVER['HTTP_USER_AGENT'] ?? '');
        define('IS_BOT', (int)!preg_match("#firefox/|msie \d|opera/|safari/|chrome/#i", $ua));
        $hash = md5($sky->ip . $ua . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        $this->pretty = preg_match("/^\w{23}$/", $cookie = (string)($_COOKIE[$sky->s_c_name] ?? ''));
        $dd = SKY::$dd;
        $now = $dd->f_dt();

        if ($this->row = sql('~select *, id as vid, 0 as user_id, !! from $_visitors where $$', [
                '$1' => qp($dd->f_dt(false, '-', '$.', 'minute') . ' > dt_l as visit', $sky->s_visit),
                $now . ' <= ' . $dd->f_dt('dt_l', '+', 1, 'second') . ' as flood',
                $now . ' > ' . $dd->f_dt('dt_l', '+', 1, 'hour') . ' as banend',
            ], $this->pretty ? qp('sky=$+', $cookie) : qp('hash=$+', $hash))) {

            $this->uid
                AND $row = sqlf('~select *, id as user_id from $_users where id=%d', abs($this->uid))
                AND $this->row = $row + $this->row;

            $_ = $this->flags & ~self::NOT_TAILED; # reset flag "not tailed" each click
            $_COOKIE ? ($_ &= ~self::NO_ANY_C) : ($_ |= self::NO_ANY_C);
            $this->row['v'] = SKY::ghost('v', $this->row['vmemo'], ['vmemo' => "update \$_visitors set @@ where id=$this->vid"]);

            $ary = [
                '!dt_l'      => $now,
                '!clk_total' => 'clk_total+1',
                '!clk_visit' => $this->visit ? 1 : 'clk_visit+1',
                'uri' => URI,
            ];

       #     if ($_POST || $this->banend)
        #        $ary['!clk_flood'] = $this->flood ? 'clk_flood+1' : 0;

            if ($this->pretty = $this->pretty && $this->sky === $cookie) {
                null === $this->hash or $ary['hash'] = null;
                $_ &= ~self::NO_PRETTY & ~self::NO_C;
            } else {
                $this->visit or $this->cookize($this->sky); # if not new visit
                $_ |= self::NO_PRETTY | (1 == $this->clk_total ? self::NO_C : 0);
            }
            if ($this->visit && $this->uid && !$this->v_mem)
                $ary['uid'] = $this->row['user_id'] = 0;
            if ($this->row['id'] = $this->row['user_id'] = $this->pretty ? (int)$this->user_id : 0) {
                $this->row['auth'] = $this->uid < 0 ? 2 : 1;
                if (1 == $this->pid) {
                    $this->root = 1;
                    if ($sky->s_trace_root && 2 == $this->auth)
                        SKY::$debug = $this->root = 2;
                }
            } else {
                $this->row['pid'] = $this->row['auth'] = 0;
            }
            $sky->auth = $this->row['auth'];
            if ($this->visit) $ary += [
                '!dt_v'      => $now,
                '!cnt_visit' => 'cnt_visit+1',
                'sky' => $this->cookize(),
            ];
            if ($sky->ip != $this->ip) {
                $ary['ip'] = $this->ip = $sky->ip;
            }
            if ($sky->eref) {
                $ary['ref'] = $sky->eref;
            }
            if (HEAVEN::J_FLY == $sky->fly) {
                $_ &= ~self::NO_JS;
            } elseif (1 == $this->clk_total) { # second click
                $_ |= self::NO_JS;
            }
            if ($ua != $this->v_ua) {
                $this->v_ua = $ua;
                $_ |= self::UA_CHANGED | (IS_BOT ? self::IS_BOT : 0);
            }

            SKY::v(null, $ary + ['flags' => $_]);

            $this->row['u'] = $this->id ? SKY::ghost('u', $this->row['umemo'], ['umemo' => "update \$_users set @@ where id=$this->id"]) : [];

            if (2 == $this->auth && $sky->is_front && Admin::section($sky->lref))
                $this->u_uri_admin = $sky->lref;

        } else {
            if (INPUT_POST == $sky->method)
                $sky->error_no = 12;
            $ary = [
                'sky'   => $this->cookize(),
                'hash'  => $hash,
                'ip'    => $sky->ip,
                'ref'   => $sky->eref,
                'uri'   => URI,
                'flags' => (IS_BOT ? self::IS_BOT : 0) | ($_COOKIE ? 0 : self::NO_ANY_C) | ($this->pretty ? 0 : self::NO_PRETTY),
            ];
            $lg = $this->get_lg();
            $this->row = $ary + [
                'vid' => 0,
                'pid' => 0,
                'user_id' => 0,
                'id' => 0,
                'v' => SKY::ghost('v', "ua $ua\nlg_0 $lg\nlg $lg\ncsrf " . strand(7), $ary + [
                    '!dt_0'  => $now,
                    '!dt_l'  => $now,
                    '!dt_v'  => $now,
                    'vmemo'  => $sky->error_no ? '' : 'insert into $_visitors @@',
                ], 7),
            ];
        }
        $sky->csrf = $this->v_csrf;

        if (START_TS - $sky->s_online_ts > 60) {
            $query = '+select 1 + count(1) from $_visitors where ' . $now . ' < ' . $dd->f_dt('dt_l', '+', '%d', 'minute') . ' and id<>%d';
            $sky->s_online = sqlf($query, $sky->s_visit, $this->vid);
            $sky->s_online_ts = START_TS;
        }

        if (DEBUG > 2)
            trace($this->row, '$user->row');
    }

    function get_lg() {
        global $sky;

        $locale = Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? DEFAULT_LG); # safe for \n & other
        trace($locale, 'Best locale'); //2do: check all HTTP_ACCEPT_LANGUAGE locales list to match best in multilangs apps
        return !$sky->langs ? $locale : Locale::lookup($sky->langs, $locale, true, in_array('en', $sky->langs) ? 'en' : DEFAULT_LG);
    }

    function flag($flag = 0, $val = null, $char = 'v') {
        $storage =& SKY::$mem[$char][2]['flags'];
        if ($val == null)
            return $flag ? $storage & $flag : $storage;
        SKY::$char(null, ['flags' => $val ? $flag | $storage : ~$flag & $storage]);
    }

    private function cookize($cookie = false) {
        global $sky;

        if (!$cookie)
            for ($i = 0; sqlf('+select count(1) from $_visitors where sky=%s', $cookie = strand()); )
                if (++$i > 99)
                    throw new Error(1);
        trace("name, PATH, srv = $sky->s_c_name, " . PATH . ", $this->cookie_srv", 'SET-COOKIE');
        if (!setcookie($sky->s_c_name, $cookie, ceil(START_TS + I_YEAR * 3), PATH, $this->cookie_srv, false))
            throw new Error(2);
        return $cookie;
    }

    function deny(...$in) {
        return !call_user_func_array([$this, 'allow'], $in);
    }

    function allow(...$in) {
        if (1 == $this->pid) # superuser can full access
            return true;
        $in or $in = [[]];
        return in_array($this->pid, is_array($in[0]) ? $in[0] : $in);
    }

    function guard_origin($skip = []) {
    //      if ($sky->origin && $sky->origin != $link)          throw new Exception('Origin not allowed');
    }

    function guard_csrf($skip = []) {
        global $sky;
        if (INPUT_POST != $sky->method || in_array($sky->_0, $skip) || $sky->error_no)
            return;
        $csrf = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? false;
        unset($_POST['_csrf']);
        if ($csrf !== $this->v_csrf)
            throw new Hacker('CSRF protection');

        trace('CSRF is OK');
    }
}
