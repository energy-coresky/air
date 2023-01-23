<?php

//////////////////////////////////////////////////////////////////////////
class HEAVEN extends SKY
{
    const J_ACT = 1;
    const U_ACT = 2;

    public $jump = false;
    //public $requests = [''];
    public $methods = ['POST', 'GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'TRACE', 'CONNECT'];
    public $method;
    public $re;
    public $sname = [];
    public $fn_extra = '';
    public $profiles = ['Anonymous', 'Root'];
    public $admins = 1; # root only has admin access or list pids in array
    public $lg = false; # no languages initialy
    public $has_public = true; # web-site or CRM
    public $page_p = 'p';
    public $show_pdaxt = false;
    public $fly = 0;
    public $surl_orig = '';
    public $surl = [];

    function __get($name) {
        if ('_' == $name[0] && 2 == strlen($name) && is_num($name[1])) {
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

    function __construct() {
        parent::__construct();

        header('Content-Type: text/html; charset=' . ENC);
        $this->method = array_search($_SERVER['REQUEST_METHOD'], $this->methods);
        if (false === $this->method)
            throw new Error('Unknown request method');

        define('PATH', preg_replace("|[^/]*$|", '', $_SERVER['SCRIPT_NAME']));
        define('URI', (string)substr($_SERVER['REQUEST_URI'], strlen(PATH))); # (string) required!
        define('SNAME', $_SERVER['SERVER_NAME']);
        define('PORT', 80 == $_SERVER['SERVER_PORT'] ? '' : ':' . $_SERVER['SERVER_PORT']);
    }

    private function extra_file($sname) {
        if (!EXTRA || 1 != $this->method) # GET only
            return $this->extra = 0;
        $this->fn_extra = "$sname[1]$sname[2]$sname[3]" . str_replace('/', '.', PATH . URI) . '.php';

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
        $pref_lg_m = '(www\.|[a-z]{2}\.)?(m\.)?';
        if (!preg_match("/^$pref_lg_m(.+)$/", SNAME, $this->sname))
            exit('sname');
/*        $this->extra = EXTRA;
        if ($fh = $this->extra_file($this->sname)) {
            for(; ob_get_level(); ob_end_clean());
            fpassthru($fh);
            fclose($fh);
            exit;
        }*/
        define('PROTO', @$_SERVER['HTTPS'] ? 'https' : 'http');
        define('DOMAIN', $this->sname[3]);

        parent::load(); # database connection start from here

        $this->fly = 'xmlhttprequest' == strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') ? HEAVEN::U_ACT : 0;
        # use fetch with a_.. actions
        $this->is_front = true;
        $this->origin = $_SERVER['HTTP_ORIGIN'] ?? false;
        $this->orientation = 0;
        if (isset($_SERVER['HTTP_X_ORIENTATION'])) {
            in_array($wo = (int)$_SERVER['HTTP_X_ORIENTATION'], [0, 1, 2]) or $wo = 0;
            $this->orientation = $wo;
        }
        if (DEV && DEV::$static) {
            $s = substr($this->s_statp, 0, -1) + 1;
            $this->s_statp = $s > 9999 ? '1000p' : $s . 'p';
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $this->re = "~^https?://$pref_lg_m" . preg_quote(DOMAIN . PATH);
        $this->lref = preg_match("$this->re(.*)$~", $referer, $m) ? $m[3] : false;
        $this->eref = !$m ? $referer : false;

        if ('' !== URI) { # not main page
            $this->surl = explode('/', $this->surl_orig = explode('?', URI)[0]);
            $cnt_s = count($this->surl);
            if (1 == $cnt_s && '' === $this->surl[0]) {
                $this->surl = [];
                $cnt_s = 0;
                if ($this->fly && 'AJAX' == key($_GET)) {
                    $this->fly = HEAVEN::J_ACT;
                    if ('adm' == $_GET['AJAX'])
                        $this->surl = ['adm'];
                    array_shift($_GET);
                }
            }
            common_c::rewrite_h($cnt_s, $this->surl);
        }

        if (SKY::$debug)
            $this->gpc = Debug::gpc();

        //if (12 == $this->error_no && !$this->fly && '' !== URI) // 2do:check for api calls
        //    jump();
        //if ($this->error_no && '_exception' != URI)
        //    throw new Exception(11);

        SKY::$vars = [
            'k_list' => 'list' == $this->_1 || in_array($this->page_p, [$this->_1, $this->_2, $this->_3]),
        ];
//        if (1 == $this->extra)
            //is_file($fn = 'var/extra.txt') && in_array($this->fn_extra, array_map('trim', file($fn))) or $this->extra = 2;
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

    function tail_x($plus = '', $stdout = '') {
        if ($plus) # if not tailed correctly
            trace($this->except ? $this->except['title'] : 'Unexpected Exit', true, 3);
        $this->ghost or $this->tail_ghost();
        for ($i = 0; ob_get_level(); $i++, $stdout .= ob_get_clean()); # grab if this is unexpected break
        if ($flag = !headers_sent() && HEAVEN::J_ACT == $this->fly) {
            http_response_code(200);
            $etc = is_array($this->ca_path) ? $this->ca_path : [];
            if ($this->except && 11 == $this->except['code'])
                $etc = ['ky' => $this->error_no];
            if (!$etc && $this->error_no > 100) {
                $etc = ['err_no' => $this->error_no, 'soft' => (int)('' === $plus)];
                if ($plus) {
                    $tracing = SKY::$debug ? $this->errors . $this->tracing($plus, true) : '';
                    $this->errors = view('_std.404', ['tracing' => $tracing]);
                } else {
                    $this->errors = $stdout;
                }
            }
            $flag = $etc || SKY::$debug && $this->errors;
        }
        if (DEV)
            $this->was_warning ? Plan::cache_p('sky_xw', 1) : Plan::cache_dq('sky_xw');
        if ($flag) {
            if (!$etc) {
                $plus or $this->errors .= $this->check_other();
                $this->errors .= "<h1>Stdout, depth=$i</h1><pre>" . html(mb_substr($stdout, 0, 100)) . '</pre>';
            }
            $out = json(['catch_error' => $this->errors] + $etc, true);
            $out or $out = json_encode(['catch_error' => '<h1>See X-tracing</h1>']);/////////////////////////
            $stdout = $out;
        }
        if (SKY::$debug && !isset($tracing)) {
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

        if (SKY::$debug) { # render tracing in the layout
            trace(array_keys(SKY::$mem), 'KEYS of: SKY::$mem');
            if (SKY::ERR_SHOW == $this->was_error && !headers_sent())
                http_response_code(503); # Service Unavailable
            if ($trace_single) {
                $this->tracing('', true); # write X-tracing
            } else {
                $out = '<h1>Tracing</h1>' . tag($this->tracing(), 'id="trace"', 'pre');
                $out_x = tag('', 'id="trace-x" style="display:none"');
                if ($this->errors) {
                    $out = $this->check_other() . $this->errors . $out;
                    echo tag($out, 'id="trace-t" style="display:none"') . $out_x . js("sky.err_t=1");
                } else {
                    global $user;
                    if (!$this->is_front && (DEV || 1 == $user->pid)) {
                        echo a('see tracing', ['sky.trace()']) . ' + ' . a('X', ['sky.trace(1)']);
                    }
                    echo $this->was_error == SKY::ERR_SHOW
                        ? $this->check_other() . $out . js("$('div.error')[0].scrollIntoView();")
                        : tag($out, 'id="trace-t" style="display:none"') . $out_x;
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
        if ($this->fly)
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
        if (SKY::$debug) {
            $tracing .= $h1 . $this->errors . tag($this->tracing($plus, true), 'id="trace"', 'pre');
            $tracing .= "<h1>Stdout</h1><pre>$stdout</pre>";
        }

        $this->k_static = [[], [], []]; # skip app css and js files
        $vars = MVC::jet('__std.exception');
        $vars += [
            'no' => $no,
            'tracing' => $tracing,
            'error' => $error ?? '',
        ];
        view(false, Plan::$parsed_fn, $vars);
    }

    function tracing($plus = '', $trace_x = false) {
        if (SKY::$debug) {
            if ($this->trans_coll)
                Language::translate($this->trans_coll);
            $uri = $this->methods[$this->method] . ' ' . URI;
            $tracing = 'PATH: ' . PATH . html("\nURI: $uri\n\$sky->lref: $this->lref") . "\n\$sky->fly: $this->fly";
            $tracing .= "\n\$sky->k_type: $this->k_type\nSURL: " . html("$this->surl_orig -> " . implode('/', $this->surl));
            $this->tracing = $tracing . "\n\n" . $this->tracing;
            if ($this->fn_extra)
                $this->tracing = "EXTRA: $this->fn_extra\n$this->tracing";
            if (SKY::$debug > 1) {
                flush();
                $plus .= 'Request headers:'  . html(substr(print_r(getallheaders(), true), 7, -2));
                $plus .= 'Response headers:' . html(substr(print_r(apache_response_headers(), true), 7, -2));
            }
        }
        $plus = parent::tracing($plus, $trace_x);
        return preg_replace('/^(' . preg_quote(DIR, '/') . '.*)$/m', '<span>$1</span>', $plus);
    }

    function shutdown() {
        chdir(DIR); # restore dir!
        Plan::$ware = 'main';
        $dd = SQL::$dd = SKY::$dd; # set main database driver if SKY loaded
        if (!$this->tailed) { # possible result of `die` function
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
                if ($dd)
                    sqlf('update $_memory set dt=' . $dd->f_dt() . ', tmemo=substr(' . $dd->f_cc('%s', 'tmemo') . ',1,10000) where id=11', $str . html(URI) . "\n");
            }
        }
        parent::shutdown();
    }

    function tail_ghost($alt = false) {
        global $user;

        if ($alt && isset($user) && $user->jump_alt && !headers_sent() && !$this->fly)
            jump($this->alt_jump = true, 302, false); # no exit, alt jump (special case)
        parent::tail_ghost();
        if ($this->jump && SKY::$debug) {
            #$plus = $this->tracing("JUMP[$user->jump_n]: $this->jump\n\n", !$user->jump_n);
            #if ($user->jump_n)
            #    sqlf('update $_memory set tmemo=' . SKY::$dd->f_cc('tmemo', '%s') . ' where id=1', "\n----\n$plus");
            $plus = $this->tracing("JUMP[]: $this->jump\n\n", true);
  #          if (1)
          #      sqlf('update $_memory set tmemo=' . SKY::$dd->f_cc('tmemo', '%s') . ' where id=1', "\n----\n$plus");
        }
    }
}


function jump($uri = '', $code = 302, $exit = true) {
    global $sky, $user;

    if ($ut = isset($user))
        $sky->alt_jump ? ($uri = $user->jump_alt) : ($user->jump_path[] = '. ' . LINK . URI);
    $alt = [];
    if (is_array($uri)) # jump to the next link if an error 404 or exception
        $uri = ($alt = $uri) ? array_shift($alt) : '';
    $sky->is_front or '' === $uri or '?' != $uri[0] or $uri = "adm$uri";
    $sky->jump = preg_match("@^https?://@i", $uri) ? $uri : LINK . $uri;
    if ($ut) {
        $user->jump_path[] = "! $sky->jump";
        if ($user->jump_n > 7) {
            $user->jump_alt = []; # no more jumps
            if ($exit && !$sky->alt_jump)
                throw new Error('22 Too much jumps');
            trace("Too much jumps:\n" . implode("\n", $user->jump_path), true);
            return;
        }
        array_unshift($alt, $user->jump_n, $user->jump_path);
        $user->v_jump_alt = serialize($alt);
    }
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
            is_string($cnt) or $cnt = (string)(SQL::$dd);
            $cnt = SQL::$dd->_rows_count($cnt);//////////////////
        }
    }
    $sky->is_front or $throw = false;
    $e = function ($no) use ($throw, $cnt, $sky) {
        if ($throw && (404 != $no || $sky->s_error_404))
            throw new Exception("pagination error $no");
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

    return '</li><li>' == $by ? sprintf('<ul class="%s"><li>%s</li></ul>', $class, implode($by, $ary)) : implode($by, $ary);
}

function json($in, $return = false, $off_layout = true) {
    if ($off_layout)
        MVC::$layout = '';
    header('Content-Type: application/json; charset=' . ENC);
    $out = json_encode($in, $return ? JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR : 0); # second const from PHP 5.5
    if ($err = json_last_error())
        trace('json error: ' . $err, true, 1);
    if (DEV && !$return)
        DEV::ed_var(['$$' => $in], MVC::instance(-1)->no);
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

function api($addr, $in) {
    $response = file_get_contents($addr, false, stream_context_create(['http' => [
        'method' => "POST",
        'header' => "Content-Type: application/json; charset=UTF-8\r\n",
        'content' => json_encode($in),
    ]]));
    return $response ? unjson($response, true) : false;
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

function js($x = '') {
    global $sky;

    if (is_string($x))
        return "<script>$x</script>";
    $pf = '?' . ($sky->s_statp ?: '1000p');
    $js = '';
    foreach ($x as $src)
        $js .= '<script src="' . ('~' == $src[0] ? PATH . substr($src, 2) . $pf : $src) . '"></script>';
    return $js;
}

function css($x = '') {
    global $sky;

    if (is_string($x))
        return '<style>' . $x . '</style>';
    $pf = '?' . ($sky->s_statp ?: '1000p');
    $css = '';
    foreach ($x as $src)
        $css .= '<link rel="stylesheet" href="' . ('~' == $src[0] ? PATH . substr($src, 2) . $pf : $src) . '" />';
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
            $val = isset($user) ? $user->v_csrf : 0;
        }
        $out .= sprintf(TPL_HIDDEN, $name, html($val));
    }
    return $out;
}
