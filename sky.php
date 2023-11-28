<?php

interface PARADISE {

}

class SKY implements PARADISE
{
    const ERR_DETECT = 1;
    const ERR_SHOW   = 3;
    const ERR_SUPPRESSED = 4;
    const CORE = '0.521 2023-11-28T13:44:05+02:00 energy';

    public $tracing = '';
    public $error_prod = '';
    public $error_no = 0;
    public $error_last = 0;
    public $was_error = 0;
    public $was_warning = 0;
    public $gpc = '';
    public $langs = [];
    public $shutdown = [];
    public $surl = [];

    static $plans = [];
    static $mem = [];
    static $reg = [];
    static $vars = [];
    static $databases = [];
    static $dd = null;
    static $cli;
    static $debug;
    static $errors = [0]; # cnt_error

    protected $ghost = false;
    protected $except = [];

    function __construct() {
        global $argv, $sky;
        $sky = $this;

        ob_get_level() && ob_end_clean();
        require DIR_S . '/w2/core.php';
        require DIR_S . '/w2/plan.php';
        SKY::$debug = DEBUG;
        ini_set('error_reporting', $this->log_error = -1);
        if (SKY::$cli = CLI)
            $this->gpc = '$argv = ' . html(var_export($argv, true));
        date_default_timezone_set(PHP_TZ);
        mb_internal_encoding(ENC);
        define('NOW', date(DATE_DT));
        srand(microtime(true) * 1e6);//srand((double)microtime() * 1e6);

        set_error_handler(function ($no, $message, $file, $line, $context = null) {
            $amp = '';
            if ($detect = error_reporting() & $no) {
                $this->error_last = $no;
                $this->was_error |= SKY::ERR_DETECT;
            } elseif (DEV) {
                static $show;
                if (null === $show)
                    $show = $this->d_err ? '@' : '';
                if ($detect = $amp = $show)
                    $this->error_last = $no;
                $this->was_error |= SKY::ERR_SUPPRESSED | ($show ? SKY::ERR_DETECT : 0);
            }
            if ($detect && (SKY::$debug || $this->log_error)) {
                $error = Plan::error_name($no) . $amp;
                trace(["PHP $error", "$error: $message"], true, $line, $file, $context);
            }
            return true;
        });

        set_exception_handler(function ($e) {
            preg_match("/^(\d{1,3}) ?(.*)$/", $mess = $e->getMessage(), $match);
            if ($stop = 'Stop' == ($class = get_class($e)))
                $this->tailed = "Thrown Stop($mess)\n"; # = 1
            $this->error_no = 'Hacker' == $class ? 11 : 51;
            $this->except['match'] = $match ?: [1 => 404, $mess ?: '?'];
            $this->except['title'] = $title = "Thrown $class($mess)";
            $noterror = $stop || 11 == $this->error_no or $title = [$title, $title];
            trace($title, !$noterror, $e->getLine(), $e->getFile(), $e->getTraceAsString());
        });

        spl_autoload_register('Plan::_autoload', true, true);
        register_shutdown_function([$this, 'shutdown']);
        Plan::open('cache'); # load composed SKY::$plans
        DEV && !CLI && DEV::init();
    }

    function open($msg = 'OK') {
        if (SKY::$dd || SKY::$dd === false)
            return SKY::$dd;

        SKY::$dd = false; # let stay false if thrown
        $this->memory(8, 's', SKY::$dd = SQL::open());
        $this->log_error = $this->s_log_error or SKY::$debug or ini_set('error_reporting', 0);
        $this->trace_cli = $this->s_trace_cli;

        if (DEV && !CLI && $this->static_new) {
            $s = substr($this->s_statp, 0, -1) + 1;
            $this->s_statp = $s > 9999 ? '1000p' : $s . 'p';
        }
        trace($msg, 'SKY OPENED', 1);
        return SKY::$dd;
    }

    function memory($id = 9, $char = 'n', $dd = null) {
        if (!isset(SKY::$mem[$char])) {
            $dd or $dd = $this->open("OK, first char is `$char`");
            if (!$dd)
                return '';
            list($dt, $imemo, $tmemo) = $dd->sqlf('-select dt, imemo, tmemo from $_memory where id=' . $id);
            SKY::ghost($char, $tmemo, 'update $_memory set dt=$now, tmemo=%s where id=' . $id, 0, $dd);
        }
        return SKY::$mem[$char][3];
    }

    function __get($name) {
        if ('_' == $name[0] && 2 == strlen($name) && is_num($name[1])) {
            $v = (int)$name[1];
            $cnt = count($this->surl);
            if ($v < $cnt)
                return $this->surl[$v];
            $w = floor(($v -= $cnt) / 2);
            if ($w >= count($_GET))
                return '';
            $ary = array_slice($_GET, $w, 1, true);
            return $v % 2 ? pos($ary) : key($ary);
        } elseif ('_' === ($name[1] ?? '')) {
            if ('k' == $name[0])
                return array_key_exists($name, SKY::$vars) ? SKY::$vars[$name] : '';
            if (!isset(SKY::$mem[$char = $name[0]]))
                return '';
            return SKY::$mem[$char][3][substr($name, 2)] ?? '';
        }
        return array_key_exists($name, SKY::$reg) ? SKY::$reg[$name] : '';
    }

    function __set($name, $value) {
        $xx = substr($name, 0, 2);
        if ('k_' == $xx) {
            SKY::$vars[$name] = $value;
        } elseif ('_' == ($xx[1] ?? '')) {
            SKY::$mem[$char = $xx[0]][0] |= 1; # set flag
            SKY::$mem[$char][3][substr($name, 2)] = $value; # (string) ?
        } else {
            SKY::$reg[$name] = $value;
        }
    }

    function __call($char, $args) {
        SKY::__callStatic($char, $args);
    }

    static function __callStatic($char, $args) {
        if (1 != strlen($char))
            return trace("Method SKY::$char not found", true, 1);
        $exists = isset(SKY::$mem[$char]);
        if (!$args)
            return $exists;
        $exists or SKY::$mem[$char] = [0, null, $args[1] ?? '', [], SKY::$dd];
        $x =& SKY::$mem[$char];
        if (is_array($k = $args[0])) {  # s - system conf
            $x[3] = $k + $x[3];         # a - conf for root-admin section
            return $x[0] |= 1;          # n - cron conf
        }                               # u,v - user, visitor (session)
        if (1 == count($args))          # i,j - used in Language class
            return $x[3][$k] ?? '';     # d - development conf
        $v = $args[1];
        if (is_null($k)) {
            if ($exists)
                if (is_array($v)) $x[2] = $v + $x[2]; else unset($x[2][$v]);
            return $x[0] |= 2;
        }
        if (is_null($v)) {
            unset($x[3][$k]);
        } else {
            $x[3][$k] = $v;
        }
        return $x[0] |= 1;
    }

    static function &ghost($char, $packed, $tpl = '', $flag = 0, $dd = null) {
        SKY::$mem[$char] = [$flag, $flag & 4 ? null : $packed, $tpl, [], $dd ?? SKY::$dd];
        if (SKY::$debug && $tpl)
            trace(is_array($tpl) ? end($tpl) : (DEV && $tpl instanceof Closure ? Debug::closure($tpl) : $tpl), 'GHOST', 1);
        if ($packed) foreach (explode("\n", unl($packed)) as $v) {
            list($k, $v) = explode(' ', $v, 2);
            SKY::$mem[$char][3][$k] = escape($v, true);
        }
        return SKY::$mem[$char][3];
    }

    static function sql($char, $return = true) {
        $x =& SKY::$mem[$char];
        if ('s' == $char && !$x[1]) # protect if SQL select failed
            return;
        $flag = $x[0];
        $x[0] = 0; # reset flags
        if ($f1 = $flag & 1) { # sky-memory
            $new = array_join($x[3], function($k, $v) {
                return $k . ' ' . escape($v);
            });
            $new === $x[1] ? ($f1 = 0) : ($x[1] = $new);
            if ($x[2] instanceof Closure)
                return $f1 ? $x[2]($new) : null;
        }
        if (is_array($x[2])) { # type 2
            if ($flag & 2 | $f1) {
                $query = end($x[2]);
                $key = key($x[2]);
                if ($f1) {
                    $x[2][$key] = $new;
                } else {
                    unset($x[2][$key]);
                }
                if ($query && $x[2])
                    return $return ? $x[4]->qp($query, $x[2]) : $x[4]->sql($query, $x[2]);
                return $x[2];
            }
        } elseif ($f1) {
            return $return ? $new : $x[4]->sqlf($x[2], $new);
        }
    }

    static function lang($lg, $page = false) {
        define('LG', $lg);
        SKY::$reg['trans_late'] = Plan::_r(['main', "lng/$lg.php"]);
        if (SKY::$reg['lg_page'] = $page)
            SKY::$reg['trans_late'] += Plan::_r(['main', "lng/{$lg}_$page.php"]);
        if (DEV)
            SKY::$reg['trans_coll'] = [];
    }

    static function date($in, $hm = true) {
        if (!$in)
            return '';
        if (is_string($in))
            $in = strtotime($in);
        return date(SKY::s('date_format') ?: 'd.m.Y' . ($hm ? ' H:i' : ''), $in);
    }

    function log($mode, $data) {
        if (!SKY::$dd || !in_array(SKY::s('test_mode'), [$mode, 'all']))
            return;
        $new = date(DATE_DT) . " $mode $data\n";
        SKY::$dd->sqlf('update $_memory set dt=' . SKY::$dd->f_dt() . ', tmemo=substr(' . SKY::$dd->f_cc('%s', 'tmemo') . ',1,15000) where id=7', $new);
    }

    static function version() : array {
        $core = explode(' ', SKY::CORE);      # timestamp, CS-ver,   APP-ver,  APP-name
        $app = explode(' ', self::s('version')) + [time(), $core[0], '0.0001', 'APP'];
        $len = strlen(substr($app[2], 1 + strpos($app[2], '.')));
        $app[4] = "$app[3].SKY.";
        $app[3] = ($len < 3 ? '' : ($len < 4 ? 'βῆτα.' : 'ἄλφα.')) . "$app[2].$app[3].SKY.";
        return [
            'core' => $core,
            'app' => $app,
        ];
    }

    function tail_ghost() {
        $this->ghost = true;
        foreach (SKY::$mem as $char => &$v)
            $v[0] && $v[2] && SKY::sql($char, false);
    }

    function shutdown($web = false) {
        chdir(DIR);
        Plan::$ware = Plan::$view = 'main';
        $dd = SQL::$dd = SKY::$dd; # set main database driver if opened

        foreach ($this->shutdown as $func)
            call_user_func($func);

        $this->ghost or $this->tail_ghost(); # run tail_ghost() if !$this->tailed

        $e = error_get_last();
        $err = false;
        if ($e && $e['type'] != $this->error_last) {
            $error = Plan::error_name($e['type']);
            trace(["PHP $error", $err = "$error: $e[message]"], true, $e['line'], $e['file']);
            $this->error_no = 52;
        }
        $code = function ($err) : int {
            if ($this->except)
                return (int)$this->except['match'][1];
            return $err ? 500 : 0; # when "Compile fatal error" for example (before PHP 8)
        };

        if ($dd && $this->error_prod && $this->log_error) # write error log
            sqlf('update $_memory set dt=' . $dd->f_dt() . ', tmemo=substr(' . $dd->f_cc('%s', 'tmemo') . ', 1, 5000) where id=4', $this->error_prod);

        if ($web)
            return $web($err, $code);
        # CLI
        $hnd = $this->shutdown ? get_class($this->shutdown[0][0]) : 'Console';
        trace("0 $hnd ^", 'TOP-VIEW', 1);
        if (DEV)
            Debug::vars(['sky' => $this]);
        if ($this->was_error & SKY::ERR_SHOW || $this->trace_cli)
            $this->tracing("$hnd\n");
        SQL::close();
        exit($code($err));
    }

    function tracing($top = '', $is_x = true) {
        $data = DEV ? Debug::data() : '';
        $top .= "\nDIR: " . DIR . "\n$this->tracing$this->gpc";
        $top .= sprintf("\n---\n%s: script execution time: %01.3f sec, SQL queries: " . SQL::$query_num, NOW, microtime(true) - START_TS) . $data;
        if ($is_x && SKY::$dd) {
            if (DEV)
                SKY::$dd->_xtrace();
            SKY::$dd->sqlf('update $_memory set tmemo=%s where id=1', $top);
        }
        if (Plan::$z_error)
            Plan::cache_a(['main', 'dev_z_err'], pre($top, 'class="trace"'));
        return $top;
    }
}

//////////////////////////////////////////////////////////////////////////
class HEAVEN extends SKY
{
    const J_FLY = 1;
    const Z_FLY = 2;

    public $fly = 0;
    public $surl_orig = '';
    public $jump = false; # INPUT_POST=0 INPUT_GET=1 0..8
    public $methods = ['POST', 'GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'TRACE', 'CONNECT'];
    public $method;
    public $auth = false;
    public $profiles = ['Anonymous', 'Root'];
    public $admins = 1; # root only has admin access or list pids in array
    public $has_public = true; # web-site or CRM
    public $pagination = 'p';
    public $show_pdaxt = false;

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

        if (EXTRA && 1 == $this->method) { # INPUT_GET
            $fn = "var/extra/" . DOMAIN . urlencode(URI) . '.html';
            if (is_file($fn) && ($fh = @fopen($fn, 'r'))) {
                for (; ob_get_level(); ob_end_clean());
                fpassthru($fh);
                fclose($fh);
                exit;
            }
        }

        parent::__construct(); # 9/7 classes at that point on DEV/Prod

        $this->ip = $_SERVER['REMOTE_ADDR'] ?? '';
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
            $this->gpc = Debug::gpc(); # original input

        require DIR_S . '/w2/mvc.php';
        Plan::app_r('mvc/common_c.php');
        MVC::$cc = new common_c;
        $mvc = new MVC;
        $this->fly = 'xmlhttprequest' == strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') ? HEAVEN::Z_FLY : 0;
        $this->is_front = true;
        $cnt = 0;
        if ('' !== URI) { # not main page
            $this->surl = explode('/', $this->surl_orig = explode('?', URI)[0]);
            if (false !== strpos($this->surl_orig, '//')) {
                SKY::$debug && $this->open();
                throw new Hacker('403 Twice slash');
            }
            $cnt = count($this->surl);
            if (1 == $cnt && '' === $this->surl[0]) {
                $cnt = 0;
                $this->surl = [];
                if (0 == $this->method && ($jact = $_SERVER['HTTP_X_ACTION_J'] ?? false)) { // INPUT_POST 2do: delete the checks from gate
                    'main' == $jact or common_c::$tune = $jact;
                    $mvc->return = $this->fly = HEAVEN::J_FLY;
                }
            }
        }
        common_c::rewrite_h($cnt, $this->surl, URI, $this);
        $mvc->top(); # 16/14 classes at that point on DEV/Prod
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
                $out = '' === $stdout ? L::m('EMPTY STRING') : html(mb_substr($stdout, 0, 500));
                $msg .= "<h1>Stdout, depth=$depth</h1><pre>$out</pre>";
            }
            if ($ary) {
                if (!$stdout = json($ary + ['catch_error' => $msg], true))
                    $stdout = json_encode(['err_no' => $this->error_no, 'exit' => $exit, 'catch_error' => '<h1>See X-tracing</h1>']);
            }
        } elseif ($exit && !$hs) {
            http_response_code($exit);
        }

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
                $fn = MVC::jet('__std.crash', '', $vars);
                $vars->data['_vars'] += [
                    'redirect' => $redirect,
                    'no' => $http_code,
                    'tracing' => $toggle ? $tracing : '',
                ];
                Plan::jet_r($fn, $vars);
            }
        });
        SQL::close();
    }

    function tracing($top = '', $is_x = true) {
        if (SKY::$debug)
            Debug::tracing($top);
        return parent::tracing($top, $is_x);
    }
}
