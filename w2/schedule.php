<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class Schedule
{
    private $single_thread = false; # POpen Disabled by security reasons, exec all in one process
    private $started = 0;
    private $arg = -1;
    private $amp = '';
    private $i = 0;
    private $func   = [];
    private $handle = [];
    private $stdout = [];
    private $now    = [];
    private $php    = 'php';
    const TPL = '%s, execution time: %01.3f sec, SQL nums in cron tasks: %d';
    const UPD = 'update $_memory set tmemo=substr(concat(%s,%s,tmemo),1,10000) where id=%d';
    public $dt;
    public $imemo;

    function &load() {
        global $sky;
        $sky->loaded or $sky->load();
        if (!isset(SKY::$mem['n'])) {
            list($this->dt, $this->imemo, $tmemo) = sqlf('-select dt, imemo, tmemo from $_memory where id=9');
            SKY::ghost('n', $tmemo, 'update $_memory set dt=$now, tmemo=%s where id=9');
        }
        return SKY::$mem['n'][3];
    }

    function __get($name) {
        $ary = $this->load();
        if ('www' == $name) {
            $ary = explode('~', $ary['www']);
            return DEV ? $ary[0] . '/' : $ary[1] . '/';
        }
        return $ary[substr($name, 2)];
    }

    function __set($name, $value) {
        if ('n_' == substr($name, 0, 2)) {
            $this->load();
            SKY::n(substr($name, 2), $value);
        }
    }

    function __construct($debug_level = 1, $single_thread = false) {
        global $argv, $sky;

        'WINNT' == PHP_OS or $this->php = PHP_BINDIR . '/php';
        $this->single_thread = !function_exists('popen') || $single_thread;

        if (isset($argv[1])) {
            if ('@' == $argv[1][0]) {
                $this->amp = '@';
                $argv[1] = substr($argv[1], 1);
            }
            if (strlen($argv[1]))
                $this->arg = $argv[1];
        } elseif ('cli' != PHP_SAPI) { # you can use: * * * * * curl http://addr/cron.php
            $this->amp = '@';
            $this->single_thread = $sky->cli = true;
        }
        $sky->debug = $debug_level;
        if (-1 == $this->arg)
            $sky->shutdown[] = $this;
    }

    function mail_error() {
        global $sky;
        $sky->loaded or $sky->load();

        list ($dt, $err) = sqlf('-select dt, tmemo from $_memory where id=4');
        if (!$dt)
            return;
        sqlf('update $_memory set dt=null where id=4');
        $mail = new Mail;
        $mail->add_html("<pre>$err</pre>");
        return $mail->send($sky->s_email, 'error on production', 'nr@' . _PUBLIC);
    }

    function bot_google($ip) { # see https://support.google.com/webmasters/answer/80553
        $host = gethostbyaddr($ip);
        if (!in_array($domain, ['googlebot.com', 'google.com']))
            return false;
        return $ip == gethostbyname($host);
    }

    function at($schedule, $load_sky = true, $func = null) {
        # Minutes Hours Days Months WeekDays-0=sunday  *   or  12   or  */3   or   1,2,3
        global $argv, $sky;
        if (is_callable($load_sky))
            $func = $load_sky;

        if (-1 == $this->arg) {
            if ($this->ok($schedule)) {
                if ($this->single_thread) {
                    if ($load_sky && !$sky->loaded)
                        $sky->load();
                    $func();
                } else {
                    $this->handle[$this->i] = popen("$this->php $argv[0] $this->amp$this->i 2>&1", 'r');
                    $this->stdout[$this->i] = '';
                }
                $this->started++;
            }
        } elseif ($this->i == $this->arg) {
            if ($load_sky && !$sky->loaded)
                $sky->load();
            $func();
            if ($sky->loaded)
                $this->n_cron_dt = sprintf(self::TPL, NOW, microtime(true) - START_TS, SQL::$query_num);
        }
        $this->i++;
        return $this;
    }

    function ok($schedule = '') {
        if (!$this->now) {
            $ary = getdate();
            $this->now = [$ary['minutes'], $ary['hours'], $ary['mday'], $ary['mon'], $ary['wday']];
        }
        if ('-' == @$schedule[0])
            return false;
        if ('+' == @$schedule[0])
            return true;

        $in = preg_split("/\s+/", $schedule);
        for ($i = 0; $i < 5; $i++) {
            if (!isset($in[$i]) || $in[$i] === '*' || $in[$i] === '')
                continue;

            if (isset($in[$i][1]) && $in[$i][1] == '/') {
                if ($this->now[$i] % substr($in[$i], 2) == 0)
                    continue;
                return false;
            }
            $ret = false;
            foreach (explode(',', $in[$i]) as $d) {
                if ($d == $this->now[$i])
                    $ret = true;
            }
            if (!$ret)
                return false;
        }
        return true;
    }

    function sql() {
        global $sky;
        $sky->loaded or $sky->load();
        $start = microtime(true);
        $sql = (string)call_user_func_array('qp', func_get_args());
        $n = sql(SQL::NO_PARSE + 1, $sql);
        $this->write(sprintf("%01.3f sec <= %s <= %s", microtime(true) - $start, is_array($n) ? count($n) : $n, $sql));
        return $n;
    }

    function write($str, $task = -1, $is_error = false) {
        global $sky;
        if ($unk = -1 == $task)
            $task = $this->single_thread ? $this->i : $this->arg;
        $date = date(DATE_DT);
        if (!$this->amp || !$sky->loaded) {
            echo $unk ? "$str\n" : "$date [$task] $str\n";
            return;
        }
        if ($is_error) {
            $time = sprintf("$date %01.3fs - cron task #$task:", microtime(true) - START_TS);
            sqlf(self::UPD, sprintf(span_b, "<b>$time</b>\n"), html("$str\n\n"), 4);
        } else {
            sqlf(self::UPD, "$date [$task] ", html("$str\n"), 2);
        }
    }

    function shutdown() { # 2do: resolve if a task longer then 60 sec
        $write = $except = NULL;
        $finished = $time = 0;
        while ($this->handle) {
            $read = [];
            foreach ($this->handle as &$one)
                $read[] =& $one;
            $cnt = stream_select($read, $write, $except, 0, $time);
            if (false === $cnt) {
                $this->write("Error stream_select()", 'ALL', true);
                foreach ($this->handle as &$one)
                    pclose($one);
                break;
            } elseif ($cnt) {
                foreach ($read as &$hdl) {
                    $str = fread($hdl, 2096);
                    if ('' !== $str)
                        foreach ($this->handle as $i => &$one)
                            if ($one == $hdl)
                                $this->stdout[$i] .= $str;
                }
                $time = 0; # 0.1 sec
            } else {
                $time += 5e5; # 0.5 sec
                if ($time > 3e6)
                    $time = 3e6; # 3 sec
            }
            $eof = [];
            foreach ($this->handle as $i => &$one)
                $eof[$i] =& $one;

            foreach ($eof as $i => &$hdl) {
                if (feof($hdl)) {
                    if ('' !== $this->stdout[$i])
                        $this->write($this->stdout[$i], $i, true);
                    pclose($this->handle[$i]);
                    $finished++;
                    unset($this->handle[$i]);
                }
            }
            if ($this->started == $finished)
                break; # the end
        }

        global $sky;// $sky->was_error=1;
        $sky->was_error or $sky->debug = false;
        if ($this->started && $this->single_thread && $sky->loaded)
            $this->n_cron_dt = sprintf(self::TPL, NOW, microtime(true) - START_TS, SQL::$query_num);
    }
}
