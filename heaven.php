<?php

//////////////////////////////////////////////////////////////////////////
class HEAVEN extends SKY
{
    const J_FLY = 1;
    const Z_FLY = 2;

    public $fly = 0;
    public $surl_orig = '';
    public $surl = [];
    public $jump = false; # INPUT_POST=0 INPUT_GET=1
    public $methods = ['POST', 'GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'TRACE', 'CONNECT'];
    public $method;
    public $auth = false;
    public $profiles = ['Anonymous', 'Root'];
    public $admins = 1; # root only has admin access or list pids in array
    public $has_public = true; # web-site or CRM
    public $page_p = 'p';
    public $show_pdaxt = false;

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
        global $sky;
        $sky = $this;

        $this->method = array_search($_SERVER['REQUEST_METHOD'], $this->methods);
        false !== $this->method or exit('request method');
        define('PROTO', isset($_SERVER['HTTPS']) ? 'https' : 'http');
        define('DOMAIN', $_SERVER['SERVER_NAME']);
        define('PORT', 80 == $_SERVER['SERVER_PORT'] ? '' : ':' . $_SERVER['SERVER_PORT']);
        define('PATH', preg_replace("|[^/]*$|", '', $_SERVER['SCRIPT_NAME']));
        define('LINK', PROTO . '://' . DOMAIN . PORT . PATH);
        define('URI', (string)substr($_SERVER['REQUEST_URI'], strlen(PATH))); # (string) required!
        header('Content-Type: text/html; charset=UTF-8');

        if (EXTRA && INPUT_GET == $this->method) {
            $fn = "var/extra/" . DOMAIN . urlencode(URI) . '.html';
            if (is_file($fn) && ($fh = @fopen($fn, 'r'))) {
                for(; ob_get_level(); ob_end_clean());
                fpassthru($fh);
                fclose($fh);
                exit;
            }
        }

        parent::__construct(); # 9/7 classes at that point on DEV/Prod

        $this->ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->fly = 'xmlhttprequest' == strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') ? HEAVEN::Z_FLY : 0;
        # use fetch with a_.. actions
        $this->is_front = true;
        $this->origin = $_SERVER['HTTP_ORIGIN'] ?? false;
        $this->orientation = 0;
        if (isset($_SERVER['HTTP_X_ORIENTATION'])) {
            in_array($wo = (int)$_SERVER['HTTP_X_ORIENTATION'], [0, 1, 2]) or $wo = 0;
            $this->orientation = $wo;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $this->lref = preg_match('~^' . PROTO . '://' . preg_quote(DOMAIN . PORT . PATH) . '(.*)$~', $referer, $m) ? $m[1] : false;
        $this->eref = !$m ? $referer : false;

        if (SKY::$debug)
            $this->gpc = Plan::gpc(); # original input

        require DIR_S . '/w2/mvc.php';
        Plan::app_r('mvc/common_c.php');
        MVC::$cc = new common_c;
        $mvc = new MVC;
        $cnt = 0;
        if ('' !== URI) { # not main page
            $this->surl = explode('/', $this->surl_orig = explode('?', URI)[0]);
            if (false !== strpos($this->surl_orig, '//')) {
                SKY::$debug && $this->open();
                throw new Hacker('403 Twice slash');
            }
            $cnt = count($this->surl);
            if (1 == $cnt && '' === $this->surl[0]) {
                $this->surl = [];
                $cnt = 0;
                if ($this->fly && 'AJAX' === key($_GET)) {// && INPUT_POST == $this->method
                    $mvc->return = $this->fly = HEAVEN::J_FLY;
                    if ('adm' === $_GET['AJAX'])
                        $this->surl = ['adm'];//$cnt = 1; !
                    array_shift($_GET);
                }
            }
        }
        common_c::rewrite_h($cnt, $this->surl);
        $mvc->top(); # 16/14 classes at that point on DEV/Prod
    }

    function domain(&$match_lg = null) {
        if (!preg_match("/^([a-z]{2}\.)?(m\.)?(.*)$/", DOMAIN, $m))
            throw new Error('User domain match');
        $match_lg = in_array($m[1] = substr($m[1], 0, 2), $this->langs);
        $this->is_mobile = (bool)$m[2];
        return ['.' . $m[3]] + $m;
    }

    static function tail_t() {
        global $sky;
        # let ghost's SQLs will in the tracing
        $sky->ghost or $sky->tail_ghost();

        if (SKY::$debug) { # render tracing in the layout
            $z_err = DEV ? Plan::z_err($sky->fly) : '';
            echo tag('<h1>Tracing</h1>' . tag($sky->tracing('', false), 'class="trace"', 'pre'), 'id="trace-t" style="display:none"');
            echo tag($z_err, 'id="trace-x" x="' . ($z_err ? 1 : 0) . '" style="display:none"');
            if (DEV && (SKY::$errors[0] || $z_err))
                echo js('sky.err_t = 1');
        }
        $sky->tailed = true;
    }

    function tail_x($exit, $stdout = '') {
        # let ghost's SQLs will in the tracing
        $this->ghost or $this->tail_ghost();
        # grab OB if unexpected break done
        for ($depth = 0, $x = ''; ob_get_level(); $depth++, $x .= ob_get_clean());
        $stdout = $x . $stdout;

        if ($hs = headers_sent())
            $this->fly = HEAVEN::Z_FLY;
        $msg = DEV ? Plan::z_err($this->fly, $this->was_error & SKY::ERR_SHOW) : '';//ERR_DETECT

        if (HEAVEN::J_FLY == $this->fly) {
            http_response_code(200);
            $ary = false;
            if ($msg) {
                $ary = ['err_no' => 1];
            } elseif ($this->ca_path) {
                $ary = ['err_no' => 1] + $this->ca_path;
            } elseif ($this->error_no > 99) {
                $ary = ['err_no' => $this->error_no, 'exit' => $exit];
                $msg = $exit ? view("_std._$this->error_no", $ary + ['stdout' => $stdout]) : $stdout;
            } elseif (DEV && SKY::$debug && SKY::$errors[0]) {
                $ary = ['err_no' => 1];
                $msg = $exit ? '' : Plan::check_other();
                $out = '' === $stdout ? sprintf(span_m, 'EMPTY STRING') : html(mb_substr($stdout, 0, 500));
                $msg .= "<h1>Stdout, depth=$depth</h1><pre>$out</pre>";
            }
            if ($ary) {
                if (!$stdout = json($ary + ['catch_error' => $msg], true))
                    $stdout = json_encode(['err_no' => $this->error_no, 'exit' => $exit, 'catch_error' => '<h1>See X-tracing</h1>']);
            }
        } elseif ($exit && !$hs) {
            http_response_code($exit);
        }
#        if (DEV)
 #           $this->was_warning ? Plan::cache_p('sky_xw', 1) : Plan::cache_dq('sky_xw');
        if (SKY::$debug) {
            trace(mb_substr($stdout, 0, 100), 'STDOUT up to 100 chars');
            $this->tracing("Exit with $exit.$this->error_no\n"); # write X-tracing
        }
        echo $stdout; # send to browser

        if ($exit)
            SQL::close(); # end of script
        $this->tailed = true;
        exit;
    }

    function shutdown($web = false) {
        global $user;
        $toggle = false; # always on PROD
        if (!$this->tailed) {
            if (isset($user))
                $user->flag(USER::NOT_TAILED, 1);
            if (DEV && !$this->fly)
                SKY::d('tracing_toggle', $toggle = 1 - (int)$this->d_tracing_toggle);
                # PHP bug: SKY::d('tracing_toggle') always return NULL from here
        }

        parent::shutdown(function ($err, $func) use ($user, $toggle) {
            $is_x = DEV ? !Plan::z_err($this->fly, $this->was_error & SKY::ERR_SHOW, $this->fly || $this->tailed) : true; //ERR_DETECT

            if ($this->tailed) {
                if (SKY::$debug && is_string($this->tailed))
                    $this->tracing($this->tailed);
            } else {
                $title = $h1 = $this->except['title'] ?? ($err ?: 'die');
                if (!$exit = $func($err)) { # for exit, die. sky->open =OK after error
                    trace([$h1 = 'Unexpected Exit', $h1], true, 3);
                    $this->error_no = 12;
                    $exit = $this->s_error_403 ? 403 : 404; # exit err code
                }
                # save to Crash log
                if (($dd = SKY::$dd) && $this->s_log_crash) {
                    $str = NOW . "[$this->error_no] $_SERVER[REQUEST_METHOD] (" . html(URI) . ') ' . $title . ', ';
                    $str .= isset($user) ? a('v' . $user->vid, "?visitors=" . ($user->vid ? "vid$user->vid" : "ip$user->ip")) : $this->ip;
                    sqlf('update $_memory set dt=' . $dd->f_dt() . ', tmemo=substr(' . $dd->f_cc('%s', 'tmemo') . ',1,10000) where id=5', "$str\n");
                }

                $hs = headers_sent();
                if (!$hs && 12 == $this->error_no && $this->s_empty_die) # tracing not saved!
                    return http_response_code($exit); # no response

                if ($this->fly)
                    $this->tail_x($exit); # exit at the end
                # else crash for $sky->fly == 0

                $http_code = $exit > 99 ? $exit : 404; # http code
                $redirect = '';
                $this->_refresh = false;

                if ($hs) { # try redirect
                    $redirect = '--></script></style>';
                    $to = PATH . ($this->eview ? '_crash?' : 'crash?') . $http_code;
                    if (DEV) {
                        $redirect .= tag('Headers sent, redirect to: ' . a($to, $to) . ". Wait $this->d_crash_to sec", '', 'h1');
                        $this->_refresh = "$this->d_crash_to!$to";
                    } else {
                        $redirect .= js("document.location.href='$to'");
                        $this->_refresh = "0!$to";
                    }
                } else {
                    http_response_code($http_code);# 503 -Service Unavailable
                    if (Plan::mem_t('error.html')) {
                        if ($is_x && SKY::$debug)
                            $this->tracing("Fatal exit with $exit.$this->error_no\n");
                        Plan::mem_r('error.html');
                        SQL::close();
                        exit;
                    }
                }

                for ($stdout = $tracing = ''; ob_get_level(); $stdout .= html(ob_get_clean()));
                if (SKY::$debug) {
                    if ('__dev.layout' == MVC::$layout)
                        $toggle = true; # for dev-tools
                    if (!$is_x) {
                        $toggle = true; # Z-err on DEV only
                        $tracing .= "<h1>See Z-error first</h1>" . js('sky.err_t = 1');
                        $this->_refresh = false;
                        if ($hs)
                            $redirect = '--></script></style>'; # cancel redirect
                    } // SKY::$errors ??
                    $tracing .= "<h1>$h1</h1>" . pre($this->tracing("Fatal exit with $exit.$this->error_no\n", $is_x));
                    $tracing .= "<h1>Stdout</h1><pre>$stdout</pre>";
                }
                $this->_static = false; # skip app css and js files
                $vars = MVC::jet('__std.crash');
                $vars += [
                    'redirect' => $redirect,
                    'no' => $http_code,
                    'tracing' => $toggle ? $tracing : '',
                ];
                view(false, Plan::$parsed_fn, $vars);
            }
        });
        SQL::close();
    }

    function tracing($top = '', $is_x = true) {
        if (SKY::$debug) {
            trace(implode(', ', array_keys(SKY::$mem)), 'CHARS of Ghost');
            if ($this->trans_coll)
                Language::translate($this->trans_coll);
            $rewritten = implode('/', $this->surl) . ($_GET ? '?' . urldecode(http_build_query($_GET)) : '');
            $uri = $this->methods[$this->method] . ' ' . URI . " --> $rewritten\n\$sky->lref: $this->lref";
            $this->tracing = 'PATH: ' . PATH . html("\nADDR: $uri") . "\n\$sky->fly: $this->fly\n\n" . $this->tracing;

            if (SKY::$debug > 1) {
                $top .= 'Request headers:'  . html(substr(print_r(Util::request_headers(), true), 7, -2));
                $top .= 'Response headers:' . html(substr(print_r(Util::response_headers(), true), 7, -2));
            }
        }
        return parent::tracing($top, $is_x);
    }
}


function jump($uri = '', $code = 302, $exit = true) {
    global $sky;

    if ($sky->fly)
        throw new Error('Jump when $sky->fly');
    $sky->fly = HEAVEN::Z_FLY;
    if (strpos($uri, '://')) {
        $sky->jump = $uri;
    } else {
        $sky->is_front or '' === $uri or '?' != $uri[0] or $uri = "adm$uri";
        $sky->jump = LINK . $uri;
    }

    header("Location: $sky->jump", true, $code);
    $sky->tailed = "JUMP: $sky->jump\n";
    if ($exit)
        exit;
}

function trace($var, $is_error = false, $line = 0, $file = '', $context = false) {
    global $sky;

    if ($err = true === $is_error) {
        $sky->was_error |= SKY::ERR_DETECT;
        if (null === SKY::$dd)
            $sky->open(is_array($var) ? $var[1] : $var);
        if (SKY::$debug && SKY::$errors[0]++ > 49) {
            return;
            //throw new Error("500 Internal SKY error");
        }
    }
    if (SKY::$debug || $sky->log_error && $err) {
        if ($err && is_array($var))
            list ($title, $var) = $var;

        $var = is_string($var) ? html($var) : Plan::var($var, '', false, '?');

        $is_warning = 'WARNING' === $is_error;
        if (is_string($is_error)) {
            $var = "$is_error: $var";
            $is_error = false;
        }
        if (!$file) {
            $depth = 1 + $line;
            $db = debug_backtrace();
            list ($file, $line) = array_values($db[$line]);
            if (is_array($line)) { # file-line don't supported
                list ($file, $line) = array_values($db[$depth - 2]);
                $fln = sprintf(span_r, "<span>$file^$line</span>");
            }
        }
        isset($fln) or $fln = "<span>$file^$line</span>";
        $mgs = "$fln\n$var";// . html($var);
        if ($err) {
            $sky->was_error |= SKY::ERR_SHOW;
            if (SKY::$cli)
                echo "\n$file^$line\n$var\n\n";//2do striptags
            if ($sky->log_error) { # collect error log
                $type = SKY::$cli ? 'console' : ($sky->is_front ? 'front' : 'admin');
                $sky->error_prod .= sprintf(span_r, '<b>' . NOW . ' - ' . $type . '</b>');
                if (!SKY::$cli)
                    $sky->error_prod .= ' ' . $sky->methods[$sky->method] . ' uri: ' . html(URI);
                $sky->error_prod .= "\n$mgs\n\n";
            }
        }
        if (!SKY::$debug)
            return; # else collect tracing
        if ($err) {
            $sky->tracing .= "$fln\n" . '<div class="error">' . "$var</div>\n";//html() . 
            if (!DEV)
                return;
            if (is_string($context)) {
                $backtrace = html($context);
                $context = false;
            } else {
                ob_start();
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $backtrace = html(ob_get_clean());
            }
            Plan::epush($title ?? 'User Error', "$mgs\n\n$backtrace", $context);
        } elseif ($is_warning) {
            $sky->was_warning = 1;
            $sky->tracing .= "$fln\n" . '<div class="warning">' . "$var</div>\n";//html() . 
        } else {
            $sky->tracing .= "$mgs\n\n";
        }
    }
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
        if ($throw && (404 != $no))
            throw new Hacker("pagination error $no");
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
        Util::vars(['$$' => $in], MVC::instance(-1)->no);
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

function get($addr, $headers = '') {
    $response = file_get_contents($addr, false, stream_context_create(['http' => [
        'method' => "GET",
        'header' => "User-Agent: Coresky\r\n$headers",
    ]]));
    return $response ? unjson($response, true) : false;
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

function pre($in, $x = 'class="code"') {
    return tag($in, $x, 'pre');
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
            global $sky;
            $val = $sky->csrf;
        }
        $out .= sprintf(TPL_HIDDEN, $name, html($val));
    }
    return $out;
}
