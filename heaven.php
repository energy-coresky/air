<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license
# This file designed for 'front' and 'admin' enter points, but not for 'cron'
# Filename: unique

//////////////////////////////////////////////////////////////////////////
class HEAVEN extends SKY
{
    private $referer = [];

    public $jump = false;
    public $methods = ['POST', 'GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'TRACE', 'CONNECT'];
    public $method = false;
    public $re;
    public $sname = [];
    public $fn_extra = '';
    public $profiles = ['Anonymous', 'Root'];
    public $admins = 1; # root only has admin access or list pids in array
    public $lg = false; # no languages initialy
    public $has_public = true; # web-site or CRM
    public $adm_able = false;
    public $ajax = 0;
    public $surl = [];

    function __get($name) {
        if (2 == strlen($name) && '_' == $name[0] && is_num($name[1])) {
            $v = (int)$name[1];
            if ('' === URI)
                return $v ? '' : 'main';
            $cnt = count($this->surl);
            if ($v < $cnt && $this->is_front)
                return $this->surl[$v];
            !$this->is_front or $v -= $cnt;
            if (($i = floor($v / 2)) >= count($_GET))
                return '';
            $key = key($v < 2 ? $_GET : array_slice($_GET, $i, 1, true));
            return $v % 2 ? $_GET[$key] : $key;
        }
        return parent::__get($name);
    }

    private function extra_file($sn) {
        if (!EXTRA || 1 != $this->method) # GET only
            return $this->extra = 0;
        $this->fn_extra = "$sn[1]$sn[2]$sn[3]" . str_replace('/', '.', PATH . URI) . '.php';

        if (is_file($fn = "var/extra/$this->fn_extra") && ($fh = @fopen($fn, 'r'))) {
            $ts = fgets($fh);
            if ("0\n" === $ts || START_TS < (int)$ts) # check TTL
                return $fh;
            fclose($fh);
            @unlink($fn);
            $this->extra = 1; # set adjust mode
        }
        return false;
    }

    function load() {
        header('Content-Type: text/html; charset=' . ENC);
        $this->method = array_search($_SERVER['REQUEST_METHOD'], $this->methods);
        if (false === $this->method)
            throw new Err('Unknown method');

        define('PATH', preg_replace("|[^/]*$|", '', $_SERVER['SCRIPT_NAME']));
        define('URI', (string)substr($_SERVER['REQUEST_URI'], strlen(PATH))); # (string) required!
        define('SNAME', $_SERVER['SERVER_NAME']);

        $re = DEFAULT_LG == 'ru' ? '(www\.|[a-z]{2}\.)?(m\.)?' : '(www\.|[q]{2}\.)?(m\.)?';
        if (!preg_match("/^$re([a-z\d\-\.]+\.([a-z\d]{2,5}))$/", SNAME, $this->sname))
            exit('sname');
        $this->extra = EXTRA;
        if ($fh = $this->extra_file($this->sname)) {
            for(; ob_get_level(); ob_end_clean());
            fpassthru($fh);
            fclose($fh);
            exit;
        }
        define('PROTO', @$_SERVER['HTTPS'] ? 'https' : 'http');
        define('DOMAIN', $this->sname[3]);

        $hook = parent::load(); # database connection start from here

        $this->ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest' == strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) ? 2 : 0;
        $this->is_front = true;
        $this->origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : false;
        $this->orientation = 0;
        if (isset($_SERVER['HTTP_X_ORIENTATION'])) {
            in_array($wo = (int)$_SERVER['HTTP_X_ORIENTATION'], [0, 1, 2]) or $wo = 0;
            $this->orientation = $wo;
        }
        if (DEV && Ext::$static) {
            $s = (int)$this->s_statp + 1;
            $this->s_statp = preg_match("/^\d{4}$/", $s) ? $s . 'p' : '1000p';
        }

        $referer = @$_SERVER['HTTP_REFERER'];
        $this->re = "~^https?://$re" . preg_quote(DOMAIN . PATH);
        $this->lref = preg_match("$this->re(.*)$~", $referer, $m) ? $m[3] : false;
        $this->eref = !$m ? $referer : false;
        $ajax_adm = false;

        if ('' !== URI) { # not main page
            $this->surl = explode('/', explode('?', URI)[0]);
            $hook->h_rewrite($cnt_s = count($this->surl));
            if (1 == $cnt_s && '' === $this->surl[0]) {
                $this->surl = [];
                if ('AJAX' == key($_GET) && $this->ajax) {
                    $this->ajax = 1; # j_ template
                    $ajax_adm = 'adm' == $_GET['AJAX'];
                    array_shift($_GET);
                }
            }
        }

        if ($this->debug)
            $this->gpc = Debug::gpc();

        global $user;
        $user = new USER;
        $hook->h_load();
        if (12 == $this->error_no && !$this->ajax && '' !== URI) // 2do:check for api calls
            jump();
        if ($this->error_no && '_exception' != URI)
            throw new Exception(11);

        $adm = $this->admins && $user->allow($this->admins) ? Admin::access($ajax_adm) : false;
        $type = in_array($this->_1, ['list', 'edit', 'new']);
        $fn = ($this->style ? "$this->style/" : '') . ($this->is_mobile ? 'mobile' : 'desktop');
        $tz = !$user->vid || '' === $user->v_tz ? "''" : (float)('' === $user->u_tz ? $user->v_tz : $user->u_tz);

        SKY::$vars = [
            'k_tkd' => [$this->s_title, $this->s_keywords, $this->s_description],
            'k_type' => $type = $type ? $this->_1 : ($this->_1 && 'p' != $this->_1 ? 'show' : 'list'),
            'k_list' => 'list' == $type,
            'k_static' => [[], ["~/$fn.js"], ["~/$fn.css"]], # default app meta_tags, js, css files
            'k_js'  => "sky.tz=$tz; sky.is_debug=$this->debug; var addr='" . LINK . "';",
        ];
        if (1 == $this->extra)
            is_file($fn = 'var/extra.txt') && in_array($this->fn_extra, array_map('trim', file($fn))) or $this->extra = 2;

        return $adm;
    }

    function log($mode, $data) {
        if (!in_array($this->s_test_mode, [$mode, 'all']))
            return;
        sqlf('update $_memory set dt=' . SKY::$dd('dt') . ', tmemo=substr(' . SKY::$dd->f_cc('%s', 'tmemo') . ',1,15000) where id=10', date(DATE_DT) . " $mode $data\n");
    }

    function qs($url) {
        return preg_match("$this->re(.*)$~", $url, $m) ? $m[3] : false;
    }

    function flag($flag = 0, $val = null, $char = 'v') {
        $storage =& SKY::$mem[$char][2]['flags'];
        if ($val == null)
            return $flag ? $storage & $flag : $storage;
        SKY::$char(null, ['flags' => $val ? $flag | $storage : ~$flag & $storage]);
    }

    function csrf($skip = []) {
        if (INPUT_POST != $this->method || in_array($this->_0, $skip) || $this->error_no)
            return false;
        $csrf = 1;
        if (isset($_POST['_csrf'])) {
            $csrf = $_POST['_csrf'];
            unset($_POST['_csrf']);
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        return $csrf;
    }

    function h_load() { # default processing
        global $user;

        if ($this->is_mobile = $this->sname[2]) {
            $user->v_mobi or SKY::v('mobi', 1);
        } else {
            !$user->v_mobi or 'www.' == $this->sname[1] or SKY::v('mobi');
        }
        if (DEFAULT_LG) {
            define('LG', !$this->sname[1] ? ($user->id && $user->u_lang ? $user->u_lang : $user->v_lg) : substr(SNAME, 0, 2));
            if (LG != $user->v_lg && !$this->sname[2])
                $user->v_lg = LG;
        }
        $link = PROTO . '://' . (DEFAULT_LG ? LG . '.' : '') . ($this->is_mobile ? 'm.' : '') . DOMAIN;
        define('LINK', $link . PATH);

        if ($csrf = $this->csrf()) {
            if (1 == $csrf || $csrf != $user->v_csrf)
                throw new Exception('CSRF protection');
            if ($this->origin && $this->origin != $link)
                throw new Exception('Origin not allowed');
            trace('CSRF checked OK');
        }
    }

    function h_rewrite($cnt) {
        if (1 == $cnt && 'robots.txt' == $this->surl[0] && !$_GET) {
            $this->surl = [''];
            $_GET = ['_etc' => 'robots.txt'];
        }
    }

    function tail_x($plus = '', $stdout = '') {
        if ($plus) # if not tailed correctly
            trace($this->except ? $this->except['title'] : 'Unexpected Exit', true, 3);
        $this->ghost or $this->tail_ghost();
        for ($i = 0; ob_get_level(); $i++, $stdout .= ob_get_clean()); # grab if this is unexpected break
        if ($flag = !headers_sent() && 1 === $this->ajax) { # CSN-AJAX template only
            $etc = is_array($this->ca_path) ? $this->ca_path : [];
            if ($this->except && 11 == $this->except['code'])
                $etc = ['ky' => $this->error_no];
            if (!$etc && $this->error_no > 100) {
                $etc = ['err_no' => $this->error_no, 'soft' => (int)('' === $plus)];
                if ($plus) {
                    $tracing = $this->debug ? $this->errors . $this->tracing($plus, true) : '';
                    $this->errors = view('_std.404', ['d_tracing' => $tracing]);
                } else {
                    $this->errors = $stdout;
                }
            }
            $flag = $etc || $this->debug && $this->errors;
        }
        if ($flag) {
            if (!$etc) {
                $plus or $this->errors .= $this->check_other();
                $this->errors .= "<h1>Stdout, depth=$i</h1>" . html($stdout);
            }
            $out = json(['catch_error' => $this->errors] + $etc, true);
            $out or $out = json_encode(['catch_error' => '<h1>See X-tracing</h1>']);/////////////////////////
            $stdout = $out;
        }
        if ($this->debug && !isset($tracing)) {
            trace(mb_substr($stdout, 0, 100), 'STDOUT up to 100 chars:');
            $this->tracing($plus, true); # write X-tracing
        }
        echo $stdout; # send to browser
        $this->tailed = true;
        exit;
    }

    function tail() {
        $trace_single = $this->s_trace_single;
        $this->ghost or $this->tail_ghost();///////2do:  show an errors for FILE REQUESTS!

        if ($this->debug) { # render tracing in the layout
            trace(array_keys(SKY::$mem), 'KEYS of: SKY::$mem');
            if (SKY::ERR_SHOW == $this->was_error && !headers_sent())
                http_response_code(503); # Service Unavailable
            if ($trace_single) {
                $this->tracing('', true); # write X-tracing
            } else {
                if ($this->errors) {
                    $out = tag($this->check_other() . $this->errors, '');
                    $out .= '<h1>Tracing</h1>' . tag($this->tracing(), 'id="trace"', 'pre');
                    echo tag($out, 'id="err-bottom"');
                } else {
                    global $user;
                    if (!$this->is_front && (DEV || 1 == $user->pid)) {
                        echo a('see tracing', ['sky.trace()']) . ' + ' . a('X', ['sky.trace(1)']);
                    }
                    echo $this->was_error == self::ERR_SHOW
                        ? $this->check_other() . tag($this->tracing(), 'id="trace"', 'pre') . js("$('div.error')[0].scrollIntoView();")
                        : tag($this->tracing(), 'style="display:none" id="trace"', 'pre');
                }
            }
        }
        $this->tailed = true;
    }

    protected function tail_force($plus) {
        global $user;

        $hs = headers_sent();
        if (!$hs && $this->jump)
            exit;
        MVC::instance();
        if ($this->ajax)
            $this->tail_x($plus); # exit here

        $no = $ky = $this->error_no;
        if ($this->except) {
            if ($no = $this->except['code']) {
                if ($this->except['mess'])
                    $error = $this->except['mess']; # used inside <h1> in __std.exception
            } elseif ('Stop' != $this->except['name']) {
                $no = 404;
            }
            $h1 = '';
        } else {
            $no = $this->s_error_403 ? 403 : 404;
            $h1 = '<h1>Unexpected Exit</h1>';
        }
        $this->k_refresh = false;
        $tracing = '';
        if ($hs) {
            $tracing = '--></script></style>';
            if (DEV) {
                $tracing .= tag('Headers sent', '', 'h1');
            } else {
        //      $tracing .= js('document.location.href="' . ($user->jump_alt ? LINK : '_exception') . '"');
                $tracing .= js('document.location.href="' . PATH . "_exception?$no\"");
                $this->k_refresh = '0!' . PATH . "_exception?$no";
            }
        } elseif ($no) {
            http_response_code($no > 99 ? $no : 404);
        }
        for ($stdout = ''; ob_get_level(); $stdout .= html(ob_get_clean()));
        if ($this->debug) {
            $tracing .= $h1 . $this->errors . tag($this->tracing($plus, true), 'id="trace"', 'pre');
            $tracing .= "<h1>Stdout</h1><pre>$stdout</pre>";
        }
        $this->k_static = [[], [], []]; # skip app css and js files
        $this->in_tpl = true;
        require MVC::recompile('', '__std.exception');
    }

    function tracing($plus = '', $trace_x = false) {
        if ($this->debug) {
            if ($this->trans_coll)
                Language::translate($this->trans_coll);
            
            $this->tracing = 'PATH: ' . PATH . html("\nURI: " . URI . "\n\$sky->lref: $this->lref") . "\n\$sky->ajax: $this->ajax"
                . "\n\$sky->k_type: $this->k_type\n\$sky->surl: " . ($this->surl ? print_r($this->surl,1) : '[]') . "\n\n$this->tracing";
            if ($this->fn_extra)
                $this->tracing = "EXTRA: $this->fn_extra\n$this->tracing";
            if ($this->debug > 1) {
                flush();
                $plus .= 'Request headers:'  . html(substr(print_r(getallheaders(), true), 7, -2));
                $plus .= 'Response headers:' . html(substr(print_r(apache_response_headers(), true), 7, -2));
            }
        }
        $plus = parent::tracing($plus, $trace_x);
        if (DEV && !$trace_x)
            $plus .= Ext::trace();
        return preg_replace('/^(' . preg_quote(DIR, '/') . '.*)$/m', '<span>$1</span>', $plus);
    }

    function shutdown() {
        chdir(DIR); # restore dir!
        $dd = SQL::$dd = SKY::$dd; # main database driver
        if (!$this->tailed) {
            global $user;
            if (isset($user)) {
                $this->flag(USER::NOT_TAILED, 1);
                if (!$this->except || 'Exception' == $this->except['name']) { # DAH result
                    SKY::v(null, ['!clk_flood' => 'clk_flood+9']);
                    if ($user->clk_flood > 19 && !DEV)
                        $this->flag(USER::BANNED, 1); # hard ban
                }
            }
            $crash = $this->except ? $this->except['crash'] : !$this->s_quiet_eerr;
            if ($crash && $this->s_crash_log) {
                $str = NOW . ' ';
                if (isset($user))
                    $str .= a('v' . $user->vid, "?visitors=" . ($user->vid ? "vid$user->vid" : "ip$user->ip")) . ' ';
                if (INPUT_POST == $this->method)
                    $str .= 'POST ';
                sqlf('update $_memory set dt=' . $dd->f_dt() . ', tmemo=substr(' . $dd->f_cc('%s', 'tmemo') . ',1,10000) where id=11', $str . html(URI) . "\n");
            }
        }
        parent::shutdown();
    }

    function tail_ghost($alt = false) {
        global $user;

        if ($alt && isset($user) && $user->jump_alt && !headers_sent() && !$this->ajax)
            jump($this->alt_jump = true, 302, false); # no exit, alt jump (special case)
        parent::tail_ghost();
        if ($this->jump && $this->debug) {
            $plus = $this->tracing("JUMP[$user->jump_n]: $this->jump\n\n", !$user->jump_n);
            if ($user->jump_n)
                sqlf('update $_memory set tmemo=' . SKY::$dd->f_cc('tmemo', '%s') . ' where id=1', "\n----\n$plus");
        }
    }
}


function jump($uri = '', $code = 302, $exit = true) {
    global $sky, $user;

    $sky->alt_jump ? ($uri = $user->jump_alt) : ($user->jump_path[] = '. ' . LINK . URI);
    $alt = [];
    if (is_array($uri)) # jump to the next link if an error 404 or exception
        $uri = ($alt = $uri) ? array_shift($alt) : '';
    $sky->is_front or '' === $uri or '?' != $uri[0] or $uri = "adm$uri";
    $sky->jump = preg_match("@^https?://@i", $uri) ? $uri : LINK . $uri;
    $user->jump_path[] = "! $sky->jump";
    if ($user->jump_n > 7) {
        $user->jump_alt = []; # no more jumps
        if ($exit && !$sky->alt_jump)
            throw new Err('22 Too much jumps');
        trace("Too much jumps:\n" . implode("\n", $user->jump_path), true);
        return;
    }
    array_unshift($alt, $user->jump_n, $user->jump_path);
    $user->v_jump_alt = serialize($alt);
    header("Location: $sky->jump", true, $code);
    $sky->tailed = true;
    if ($exit)
        exit;
}

function pagination($ipp, $cnt = null, $ipl = 5, $current = null, $throw = true) {
    global $sky;
    $page = $sky->page_p or $page = 'p'; #   

    if (!is_numeric($cnt)) {
        if ($cnt instanceof SQL) {
            $cnt = sql('+select count(1) $$', $cnt);
        } else {
            is_string($cnt) or $cnt = SQL::onduty();
            $cnt = SQL::$dd->_rows_count($cnt);//////////////////
        }
    }
    $sky->is_front or $throw = false;
    $e = function ($no) use ($throw, $cnt, $sky) {
        if ($throw && (404 != $no || $sky->s_error_404)) throw new Exception("pagination error $no");
        return [0, $no, $cnt];
    };
    if ($cnt <= $ipp) {
        if (isset($_GET[$page]) && $sky->is_front) return $e(1);
        return [0, false, $cnt]; # pagination don't required
    }
    $br = [1, $last = floor($cnt / $ipp) + ($cnt % $ipp ? 1 : 0)]; # start, end
    if ($addr = !is_numeric($current)) {
        $current = 0;
        if (preg_match("/^(.*?)(\?|&)$page=([^&]+)(.*)$/", URI, $m)) {
            if (!is_numeric($m[3]) || $m[3] < 1) return $e(2);
            $current = $m[3] - 1;
            if ($current >= $last) {
                if ($sky->is_front) return $e(404); else $current = $last ? $last - 1 : 0;
            }
            $m[4] = trim($m[4], '&');
            $func = function ($i) use ($m, $page) {
                if (1 == $i) return '' === $m[4] ? ('' === $m[1] ? LINK : $m[1]) : $m[1] . $m[2] . $m[4];
                return html("$m[1]$m[2]$page=$i") . ('' === $m[4] ? '' : "&amp;$m[4]");
            };
        } else {
            if (isset($_GET[$page])) return $e(3);
            $func = function ($i) use ($page) {
                return 1 == $i ? html(URI) : html(URI) . (strpos(URI, '?') === false ? '?' : '&amp;') . "$page=$i";
            };
        }
    }
    if ($last > $ipl) {
        $br = [$start = $current - floor($ipl / 2) + 1, $start + $ipl - 1];
        if ($br[0] < 1) {
            $br = [1, $ipl];
        } elseif($br[1] > $last) {
            $br = [$last - $ipl + 1, $last];
        }
    }
    $ps = [
        'current' => $current + 1,
        'last' => $last,
        'br' => $br,
    ];
    if (!$addr) return [
        $current * $ipp,
        (object)($ps + ['middle' => range($br[0], $br[1])]),
        $cnt,
    ];
    $ps += [
        'a_current' => $func($current + 1),
        'a_prev' => $func($current ? $current : 1),
        'a_next' => $func($current + 1 == $last ? $last : $current + 2),
        'a_first' => $func(1),
        'a_last' => $func($last),
    ];
    for ($i = $br[0], $ps['left'] = ''; $i <= $current; $i++)
        $ps['left'] .= '<li><a href="' . $func($i) . "\">$i</a></li>";
    for (++$i, $ps['right'] = ''; $i <= $br[1]; $i++)
        $ps['right'] .= '<li><a href="' . $func($i) . "\">$i</a></li>";
    return [$current * $ipp, (object)$ps, $cnt];
}

function menu($act, $ary, $tpl = '', $by = '</li><li>', $class = 'menu') {
    global $sky;

    if (!$tpl) {
        $tpl = defined('TPL_MENU') ? TPL_MENU : '?%s';
        $by = ' &nbsp; ';
    }
    if (count($sky->surl) > 1)
        $tpl = PATH . $tpl;
    array_walk($ary, function(&$v, $k) use ($tpl, $act) {
        $ok = $act == explode('?', $k)[0];
        $v = sprintf('<a%s href="' . $tpl . '">%s</a>', $ok ? ' class="active"' : '', $k, $v);
    });

    #if (isset($ary[$act]))
    #   $ary[$act] = '<a class="active"' . substr($ary[$act], 2);

    return '</li><li>' == $by ? sprintf('<ul class="%s"><li>%s</li></ul>', $class, implode($by, $ary)) : implode($by, $ary);
}

function json($in, $return = false, $off_layout = true) {
    if ($off_layout)
        MVC::$layout = '';
    header('Content-Type: application/json; charset=' . ENC);
    defined('JSON_PARTIAL_OUTPUT_ON_ERROR') or define('JSON_PARTIAL_OUTPUT_ON_ERROR', 0); # SKY work from PHP 5.4 this const from PHP 5.5
    $out = json_encode($in, $return ? JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR : 0);
    false === $out ? trace('json error: ' . json_last_error(), true, 1) : trace($in, 'json()', 1);
    return $return ? $out : print($out);
}

function unjson($in, $assoc = false) {
    $out = json_decode($in, $assoc);
    if ($err = json_last_error()) {
        strlen($in) < 1000 or $in = substr($in, 0, 1000) . ' ...';
        trace("unjson() error=$err, in=$in", true);
    }
    return $out;
}

function option($selected, $table, $order = 'id') {
    if (is_string($table)) {
        $q = sql('select * from $_` order by $`', $table, $order);
        for ($table = ['' => '---']; $r = $q->one('R'); $table[$r[0]] = $r[1]);
    }
    $out = '';
    foreach ($table as $k => $v)
        $out .= tag($v, sprintf('value="%s"%s', $k, (string)$selected == $k ? ' selected' : ''), 'option');
    return $out;
}

function radio($name, $checked, $ary, $by = ' ') {
    $tpl = '<label><input type="radio" name="%s" value="%s"%s /> %s</label>%s';
    $out = '';
    foreach ($ary as $k => $v)
        $out .= sprintf($tpl, $name, $k, (string)$checked == $k ? ' checked' : '', $v, $by);
    return $out;
}

function a($anchor, $href = null, $x = '') {
    if (is_array($href)) {
        $x .= ' onclick="' . $href[0] . '"';
        $href = 'javascript:;';
    }
    $href or $href = $href === null ? "http://$anchor/" : LINK;
    return sprintf('<a href="%s"%s>%s</a>', $href, $x ? ' ' . trim($x) : '', $anchor);
}

function js($x = '', $p2 = true) {
    global $sky;

    if (is_string($x))
        return "<script>$x</script>";
    $pref = $sky->surl ? PATH : '';
    $js = '';
    foreach ($x as $src)
        $js .= '<script src="' . ('~' == $src[0] ? $pref . $sky->s_statp . substr($src, 1) : $src) . '"></script>';
    return $js;
}

function css($x = '', $p2 = 'screen') {
    global $sky;

    if (is_string($x))
        return '<style>' . $x . ($x && $p2 ? '</style>' : '');
    $pref = $sky->surl ? PATH : '';
    $css = '';
    foreach ($x as $src)
        $css .= '<link rel="stylesheet" href="' . ('~' == $src[0] ? $pref . $sky->s_statp . substr($src, 1) : $src) . '" />';
    return $css;
}

function tag($in, $x = 'class="fr"', $tag = 'div') {
    if (is_scalar($in))
        return "<$tag" . ($x ? ' ' : '') . trim($x) . ">$in</$tag>";
    $tag = '';
    foreach ($in as $k => $v)
        $tag .= sprintf(TPL_META, $k, $v);
    return $tag;
}

function th($ary, $x = '') {
    $tr = $x ? "<table $x><tr>\n" : '<tr>';
    foreach ($ary as $k => $v)
        $tr .= is_int($k) ? "<th>$v</th>\n" : "<th $v>$k</th>\n";
    return "\n$tr</tr>\n";
}

function td($ary, $x = '') {
    $tr = $x ? "<tr $x>\n" : "<tr>\n";
    foreach ($ary as $v)
        $tr .= is_array($v) ? "<td $v[1]>$v[0]</td>\n" : "<td>$v</td>\n";
    return "\n$tr</tr>\n";
}

function hidden($set = '_csrf', $r = 0) {
    is_array($set) or $set = [$set => $r];
    $out = '';
    foreach ($set as $name => $val) {
        if (!is_string($name)) {
            $name = $val;
            $val = isset($r[$name]) ? $r[$name] : '';
        } elseif ('_csrf' == $name && 0 === $r) {
            global $user;
            $val = $user->v_csrf;
        }
        $out .= sprintf(TPL_HIDDEN, $name, html($val));
    }
    return $out;
}
