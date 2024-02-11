<?php

class Schedule
{
    private $single_thread; # function popen() disabled by security reasons, exec all in one process
    private $max_exec_sec;
    private $is_on = true;
    private $amp = ''; # in the crontab you will write: * * * * * php /path-to/cron.php @
    private $task = 0; # run manually in the console without @ for stdout
    private $arg = 0;
    private $script = 'php ';
    private $start = 0;

    static $names = [];

    function __construct($max_exec_minutes = 10, $debug_level = 1, $single_thread = false) {
        global $argv, $sky;

        if (!$this->max_exec_sec = 60 * $max_exec_minutes)
            return;
        SQL::$dd_h = 'Console::dd_h';
        'WINNT' == PHP_OS or $this->script = PHP_BINDIR . '/php ';
        $this->script .= $argv[0] ?? 0;
        $this->single_thread = !function_exists('popen') || $single_thread;

        if (isset($argv[1])) {
            if ('@' == $argv[1][0]) {
                $this->amp = '@';
                $argv[1] = substr($argv[1], 1);
            }
            if (strlen($argv[1]))
                $this->arg = (int)$argv[1];
        } elseif ('cli' != PHP_SAPI) { # you can use: * * * * * curl http://addr/cron.php
            $this->amp = '@';
            $this->single_thread = SKY::$cli = true;
        }
        SKY::$debug = $debug_level;
        $sky->shutdown[] = [$this, 'shutdown'];
        if (!$this->arg && !$this->amp)
            echo "Multy-threads: " . ($this->single_thread ? "No\n" : "Yes\n");
    }

    static function log($msg) {
        static $cron;
        $cron ?? ($cron = new Schedule(0));
        $cron->database();
        $tpl = 'update $_memory set dt=' . SKY::$dd->f_dt() . ', tmemo=substr($cc(%s,%s,tmemo),1,10000) where id=6';
        $section = L::r(SKY::$cli ? 'console' : (SKY::$section ?: 'front'));
        sqlf($tpl, date(DATE_DT) . " [$section] ", html("$msg\n"));
    }

    function __get($name) {
        global $sky;
        $sky->memory();
        $ary =& SKY::$mem['n'][3];
        return $ary[substr($name, 2)];
    }

    function __set($name, $value) {
        global $sky;
        if ('n_' == substr($name, 0, 2)) {
            $sky->memory();
            SKY::n(substr($name, 2), $value);
        }
    }

    function http_tests() {
        //2do
    }

    function visitors() {
        $this->sql('delete from visitors where (dt_l + interval $. day < now()) and uid is null', $this->n_clear_nc);
    }

    function mail_error() {
        $this->database();
        list ($dt, $err) = sqlf('-select dt, tmemo from $_memory where id=4');
        if (!$dt)
            return;
        sqlf('update $_memory set dt=null where id=4');
        return Rare::mail(pre($err), 'Error on production');
    }

    function bot_google($ip) { # see https://support.google.com/webmasters/answer/80553
        $host = gethostbyaddr($ip);
        if (!in_array($domain, ['googlebot.com', 'google.com']))
            return false;
        return $ip == gethostbyname($host);
    }

    function database($rule = true) {
        global $sky;
        if ($rule && null === SKY::$dd)
            $sky->open();
        SQL::$dd = SKY::$dd;
    }

    function turn($func) {
        $this->is_on = $func();
        return $this;
    }

    function at($schedule, $sky_open = true, $func = null) {
        # Minutes Hours Days Months WeekDays-0=sunday  *   or  12   or  */3   or   1,2,3
        $this->task++;
        if (!$this->is_on)
            return $this;

        if (is_callable($sky_open))
            $func = $sky_open;

        if (!$this->arg) {
            if ($this->ok($schedule)) {
                $this->start = microtime(true);
                if ($this->single_thread) {
                    $this->database($sky_open);
                    $sec = time();
                    $func($this->task);
                    if (is_file($fn = "var/cron/task_$this->task"))
                        unlink($fn);
                    $sec = time() - $sec;
                    if ($sec > $this->max_exec_sec)
                        $this->write("Exec time: $sec seconds", "single/$this->task", true);
                } else {
                    Console::thread("$this->script $this->amp$this->task 2>&1", $this->task);
                }
            }
        } elseif ($this->task == $this->arg) {
            $this->database($sky_open);
            $func($this->task);
        }
        return $this;
    }

    function ok($schedule = '-') {
        static $now = false, $ary = [];

        if (!$now) {
            $now = getdate();
            $now = [$now['minutes'], $now['hours'], $now['mday'], $now['mon'], $now['wday']];
        }
        '' !== $schedule or $schedule = '-';
        if (preg_match("/^[a-z]/i", $schedule)) {
            self::$names[$this->task] = $schedule;
            $ary or $ary = strbang(unl(trim(Plan::_gq('cron.times'))));
            if (!isset($ary[$schedule])) {
                $ary[$schedule] = '-';
                Plan::_p('cron.times', array_join($ary));
            }
            $schedule = $ary[$schedule];
        }

        if ($flock = '!' == $schedule[0]) {
            $schedule = trim(substr($schedule, 1));
            if (is_file($fn = "var/cron/task_$this->task")) {
                $sec = time() - filemtime($fn);
                if ($sec > $this->max_exec_sec)
                    $this->write("Exec time: $sec seconds", basename($fn), true);
                return false;
            }
        }
        if ('-' == $schedule[0])
            return false;
        if ('+' != $schedule[0]) {
            $in = preg_split("/\s+/", $schedule);
            for ($i = 0; $i < 5; $i++) {
                if (!isset($in[$i]) || $in[$i] === '*' || $in[$i] === '')
                    continue;

                if (isset($in[$i][1]) && $in[$i][1] == '/') {
                    if ($now[$i] % substr($in[$i], 2) == 0)
                        continue;
                    return false;
                }
                $ret = false;
                foreach (explode(',', $in[$i]) as $d) {
                    if ($d == $now[$i])
                        $ret = true;
                }
                if (!$ret)
                    return false;
            }
        }
        if ($flock)
            file_put_contents($fn, 'Start at ' . date(DATE_DT));
        return true;
    }

    function sql(...$in) {
        $this->database();
        $sql = (string)call_user_func_array('qp', $in);
        $n = sql(SQL::NO_PARSE + 1, $sql);
        $this->write(sprintf("<= %s <= %s", is_array($n) ? count($n) : $n, $sql));
        return $n;
    }

    function write($str, $task = 0, $is_error = false) {
        $task or $task = $this->task;
        if (isset(self::$names[$task]))
            $task .= '.' . self::$names[$task];

        $date = date(DATE_DT);
        $this->database($this->amp);
        if (!SKY::$dd) {
            echo "$date [$task] $str\n";
            return;
        }
        $tpl = 'update $_memory set dt=' . SKY::$dd->f_dt() . ', tmemo=substr($cc(%s,%s,tmemo),1,10000) where id=%d';
        $time = sprintf("$date [$task] %01.3f sec", microtime(true) - $this->start);
        if ($is_error) {
            sqlf($tpl, "$time\n", html("$str\n\n"), 4);
        } else {
            sqlf($tpl, "$time ", html("$str\n"), 6);
        }
    }

    function shutdown() {
        $sec = microtime(true) - START_TS;

        if ($this->arg) { # popen process
            if (is_file($fn = "var/cron/task_$this->arg"))
                unlink($fn);
            if ($sec > $this->max_exec_sec)
                $this->write("Exec time: $sec seconds", $this->arg, true);
        }

        if ($this->arg || $this->single_thread) {
            $tpl = '%s, execution time: %01.3f sec, SQL nums in cron tasks: %d';
            if (SKY::$dd)
                $this->n_cron_dt = sprintf($tpl, NOW, $sec, SQL::$query_num);
            return;
        }

        Console::thread(function($str, $id, $is_error) {
            static $stdout = [];
            if ($is_error)
                return $this->write($str, 'ALL', true);
            isset($stdout[$id]) or $stdout[$id] = '';
            if (false !== $str)
                return $stdout[$id] .= $str;
            if (isset($stdout[$id]) && '' !== $stdout[$id])
                $this->write($stdout[$id], $id);
        });
    }
}
