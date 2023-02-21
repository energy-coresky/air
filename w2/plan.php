<?php

function e() {
    $top = MVC::instance();
    DEV && !SKY::s('gate_404') ? DEV::e($top) : $top->set(404);
}

class Plan
{
    private static $connections = [];
    private static $defaults = [
        'view' => ['path' => DIR_M . '/mvc/view'],
        'cache' => ['path' => 'var/cache'],
        'jet' => ['path' => 'var/jet'],
        'gate' => ['path' => 'var/gate'],
        'mem' => ['path' => 'var/mem'],
        'sql' => ['path' => 'var/sql'], #2do
    ];

    static $wares = ['main'];
    static $ware = 'main';
    static $view = 'main';
    static $gate = 'main';
    static $parsed_fn;

    static $z_error = false;
    static $see_also = [];
    static $var_path = ['', '?', [], '']; # var_name, property, array's-path

    /*
        path   => required!
        driver => 'file' by default with '' (empty name) connection
        pref   => '' by default
        ttl    => -1 (infinity) by default
        dsn    => '' by default
        use    => '' by default ( => 'plan_name') - use selected connection
    */

    static function set($ware) {
        $prev = Plan::$ware;
        Plan::$ware = $ware;
        return $prev;
    }

    static function has_class($class) {
        return isset(SKY::$plans['main']['class'][$class]);
    }

    static function auto($v, $more = '') {
        return "<?php\n\n# this is auto generated file, do not edit\n$more\nreturn " . var_export($v, true) . ";\n";
    }

    static function vendor($class = false) {
        static $vendor = false;
        if ($class && $vendor) {
            call_user_func($vendor, $class);
        } elseif (!$class && !$vendor) {
            require 'vendor/autoload.php';
            if (2 != count($ary = spl_autoload_functions()))
                throw new Error('Vendor autoload');
            spl_autoload_unregister($vendor = 'Plan' === $ary[0][0] ? $ary[1] : $ary[0]);
        }
    }

    static function _g($a0, $w2 = false) {
        $std = is_array($a0) ? $a0[1] : $a0;
        return $w2 ? file_get_contents(DIR_S . "/$std") : Plan::__callStatic('_g', [$a0]);
    }

    static function view_($op, $a0) { # g or m
        list ($ware, $a0) = is_array($a0) ? $a0 : [Plan::$ware, $a0];
        if (!preg_match($re = "/^\w+\.jet$/", $a0))
            throw new Error("Jet: file `$a0` do not match $re");
        if ('_' == ($a0[1] ?? '') && '_' == $a0[0]) {
            $a0 = DIR_S . '/w2/' . $a0;
            return 'g' == $op ? file_get_contents($a0) : stat($a0)['mtime'];
        }
        if ('main' != $ware && !Plan::view_t([$ware, $a0]))
            return Plan::__callStatic('view_' . $op, [['main', $a0]]);
        return Plan::__callStatic('view_' . $op, [[$ware, $a0]]);
    }

    static function __callStatic($func, $arg) {
        static $old_ware = false;
        static $old_obj = false;
        list ($pn, $op) = explode('_', $func);
        $pn or $pn = 'app';
        list ($ware, $a0) = is_array($arg[0]) ? $arg[0] + [1 => 1] : [Plan::$ware, $arg[0]];

        if ('view' == $pn && 'main' == $ware && !is_array($arg[0]))
            $ware = Plan::$view;

        if ($old_ware == $ware && $old_obj->pn == $pn) {
            $obj = $old_obj;
        } else {
            $obj = (object)Plan::open($pn, $ware);
            $obj->pn = $pn;
            $old_ware = $ware;
            $old_obj = $obj;
        }
        $obj->quiet = 'q' === ($op[1] ?? 0) ? $a0 : false;
        if ($obj->con->setup($obj))
            return $arg[1] ?? ('rq' == $op ? [] : ('gq' == $op ? '' : 0));
        switch ($op) {
            case 'obj':
                return $obj;
            case 'b':
                return $obj->con->glob($a0); # mask
            case 'a': # append
            case 'p':
                return $obj->con->put($a0, $arg[1], 'a' == $op);
            case 'gq':
            case 'g':
                return $obj->con->get($a0);
            case 'tp': # jet for view(..) func
                Plan::$parsed_fn = $obj->path . '/' . $a0;
            case 't':
                return $obj->con->test($a0); # if OK return fullname
            case 'mq':
            case 'm':
                return $obj->con->mtime($a0);
            case 'mf': # jet
                $s = $obj->con->get($a0);
                $line = substr($s, $n = strpos($s, "\n"), strpos($s, "\n", 2 + $n) - $n);
                return [$obj->con->mtime($a0), explode(' ', trim($line, " \r\n#"))];
            case 'rq':
            case 'r':
                return $obj->con->run($a0);
            case 'rr': # gate
                $recompile = $arg[1];
                return require $obj->path . '/' . $a0;
            case 'da':
            case 'dq':
            case 'd':
                if (in_array($pn, ['view', 'mem', 'app']))
                    throw new Error("Failed when Plan::{$pn}_$op(..)");
                return 'da' == $op ? $obj->con->drop_all($a0) : $obj->con->drop($a0);
            case 'autoload':
                trace("autoload($a0)");
                if (strpos($a0, '\\'))
                    return Plan::vendor($a0);
                $low = strtolower($a0);
                $cfg =& SKY::$plans['main']['class'];
                if (in_array(substr($a0, 0, 2), ['m_', 't_'])) {
                    if (is_file($fn = $obj->path . "/mvc/$a0.php"))
                        return require $fn;
                    if ('main' != $ware && ($fn = Plan::_t(['main', "mvc/$a0.php"])))
                        return require $fn;
                    return eval("class $a0 extends Model_$a0[0] {}");
                } elseif (isset($cfg[$a0])) {
                    return Plan::_r([$cfg[$a0], "w3/$low.php"]);
                }
                $fn = DIR_S . '/w2/' . $low . '.php';
                return is_file($fn) ? require $fn : Plan::_rq("w3/$low.php") || Plan::vendor($a0);
            default:
                throw new Error("Plan::$func(..) - method not exists");
        }
    }

    static function &open($pn, $ware = false) {
        $ware or $ware = Plan::$ware;

        if (!Plan::$connections) {
            require DIR_S . "/w2/dc_file.php";
            Plan::$connections[''] = new dc_file;
            $plans = SKY::$plans + Plan::$defaults;
            if (is_file($fn = $plans['cache']['path'] . '/' . 'sky_plan.php')) {
                SKY::$plans = require $fn;
            } else {
                SKY::$plans = $ctrl = [];
                $wares = is_file($fn = DIR_M . '/wares.php') ? (require $fn) : [];
                SKY::$plans['main'] = ['app' => ['path' => DIR_M], 'class' => []] + $plans;
                $cfg =& SKY::$plans['main']['class'];
                foreach ($wares as $key => $val) {
                    $conf = require ($path = $val['path']) . "/conf.php";
                    if ($val['type'] ?? 0)
                        $conf['app']['type'] = 'pr-dev';
                    if (!DEV && in_array($conf['app']['type'], ['dev', 'pr-dev']))
                        continue;
                    foreach ($val['class'] as $cls)
                        'c_' == substr($cls, 0, 2) ? ($ctrl[substr($cls, 2)] = $key) : ($cfg[$cls] = $key);
                    $ptr =& $conf['app'];
                    unset($ptr['require'], $ptr['class'], $ptr['databases'], $ptr['options']);
                    SKY::$plans[$key] = ['app' => ['path' => $path] + $conf['app']] + $conf;
                }

                $plans = SKY::$plans;
                $ctrl += Gate::controllers();
                SKY::$plans['main'] += ['ctrl' => $ctrl];
                $plans['main'] += ['ctrl' => $ctrl];
                Plan::cache_p('sky_plan.php', Plan::auto($plans)); # make dir & save file
            }
            $cfg =& SKY::$plans['main'][$pn];
            Plan::locale();
        } elseif (isset(SKY::$plans[$ware][$pn])) {
            $cfg =& SKY::$plans[$ware][$pn];
            if ($cfg['con'] ?? false)
                return $cfg;
        } else {
            $cfg =& SKY::$plans['main'][$pn];
            SKY::$plans[$ware][$pn] =& $cfg;
            if ($cfg['con'] ?? false)
                return $cfg;
        }
        if ($cfg['driver'] ?? false) {
            $class = 'dc_' . $cfg['driver'];
            Plan::$connections[$pn] = $cfg['con'] = new $class($cfg);
            unset($cfg['dsn']);
        } else {
            $cfg['con'] = Plan::$connections[$cfg['use'] ?? ''];
        }
        return $cfg;
    }

    static function locale($lg = 'en') {
        if ('en' == $lg && setlocale(LC_ALL, 'en_US.utf8', 'en_US.UTF-8'))
            return 1;
        return $lg ? setlocale(LC_ALL, "$lg.utf8", "$lg.UTF-8", $lg) : setlocale(LC_ALL, 0);
    }

    static function check_other() {
        return SKY::$dd ? (string)SKY::$dd->check_other() : '';
    }

    static function gpc() {
        return "\$_GET: " . Plan::var($_GET) .
            "\n\$_POST: " . Plan::var($_POST) .
            "\n\$_FILES: " . Plan::var($_FILES) .
            "\n\$_COOKIE: " . Plan::var($_COOKIE) . "\n";
    }

    static function error_name($no) {
        $list = [
            E_ERROR => 'Fatal error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core fatal error',
            E_CORE_WARNING => 'Core warning',
            E_COMPILE_ERROR => 'Compile fatal error',
            E_COMPILE_WARNING => 'Compile warning',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable error',
            E_DEPRECATED => 'Deprecated',
        ];
        return $list[$no] ?? "ErrorNo_$no";
    }

    static function catch_error($func) {
        global $sky;
        $t = [$sky->was_error, SKY::$debug];
        SKY::$debug = $sky->was_error = 0;
        $r = [call_user_func($func), $e = $sky->was_error];
        list ($sky->was_error, SKY::$debug) = $t;
        $sky->was_error |= $e;
        return $r;
    }

    static function z_err($z_fly, $is_error = false, $drop = true) {
        static $msg = null;

        if (null !== $msg)
            return $msg; # empty string or msg
        $msg = Plan::cache_gq($addr = ['main', 'dev_z_err']);
        if (!SKY::$debug) # j_trace don't erase file
            return $msg;
        $z_fly = HEAVEN::Z_FLY == $z_fly;
        if ($msg) {
            if ($drop) {
                $z_fly or Plan::cache_dq($addr); # erase flash file
                SKY::$debug = false; # skip self tracing to show z-error's one
            }
        } elseif ($z_fly && $is_error) {
            $msg = tag("Z-error at " . NOW, 'class="z-err"', 'h1');
            $p =& SKY::$errors;
            if (isset($p[1]))
                $msg .= '<h1>' . $p[1][0] . '</h1><pre>' . $p[1][1] . '</pre>';
            if (isset($p[2]))
                $msg .= '<h1>' . $p[2][0] . '</h1><pre>' . $p[2][1] . '</pre>';
            Plan::cache_p($addr, $msg);
            Plan::$z_error = true;
        }
        return $msg;
    }

    // 2do suppressed: fix for php 8 https://www.php.net/manual/en/language.operators.errorcontrol.php
    static function epush($title, $desc, $context) {
        if ($context) {
            $desc .= "\n";
            foreach ($context as $k => $v)
                if (is_scalar($v) || is_null($v))
                    $desc .= "\$$k = " . html(var_export($v, 1)) . ";\n";
        }
        $p =& SKY::$errors;
        $p[isset($p[1]) ? 2 : 1] = ["#$p[0] $title", mb_substr($desc, 0, 1000)];
    }

    static function closure($fun) {
        $fun = new ReflectionFunction($fun);
        $file = file($fun->getFileName());
        $line = trim($file[$fun->getStartLine()]);
        return 'Plan::' == substr($line, 0, 6) ? $line : 'Extended Closure';
    }

    static function var($var, $add = '', $quote = true, $var_name = false) { # tune var_export for display in html
        if ($var_name)
            self::$var_path = [$var_name, '?', [], is_object($var) ? get_class($var) : ''];
        switch ($type = gettype($var)) {
            case 'unknown type':
            case 'resource':
                return sprintf(span_m, $type); // for php8 - get_debug_type($var) class@anonymous stdClass
            case 'object':
                $cls = get_class($var);
                if ($quote) {
                    $p =& self::$var_path;
                    self::$see_also[$cls] = [$p[':' == $p[1][0] ? 3 : 0] . $p[1] . implode('', $p[2]) => $var];
                    return sprintf(span_m, "Object $cls");
                }
                $s = self::object($var, 'c', 2);
                if ("{\n  \n}" == $s)
                    return sprintf(span_y, "Object $cls") . ' - ' . sprintf(span_m, 'no properties');
                return tag($s, 'c="' . $cls . '"', 'o');
            case 'array':
                $var = self::array($var, $add);
                if ($quote)
                    return $var;
                return strlen($var) < 50 ? sprintf(span_y, 'Array ') . $var : "<r>$var</r>";
            case 'string':
                if ($gt = mb_strlen($var) > 50) // 100
                    $var = mb_substr($var, 0, 50);
                if ($quote) {
                    if (false === strpbrk($var, "\r\n\t'")) {
                        $var = var_export($var, true);
                        if ($gt)
                            $var = substr($var, 0, -1);
                    } else {
                        $ary = ["\t" => "\\t", "\r" => "\\r", "\n" => "\\n", '"' => '\\"'];
                        $var = '"' . strtr($var, $ary) . ($gt ? '' : '"');
                    }
                }
                return $gt ? html($var) . sprintf(span_g, '&nbsp;cutted..') : html($var);
            default: # boolean integer double NULL
                return var_export($var, true);
        }
    }

    static function array($var, $add = '', $pad = 0) {
        static $len;
        $pad or $len = 0;
        if (!$var)
            return '[]';
        $i = 0;
        $ary = [];
        foreach ($var as $k => $v) {
            self::$var_path[2][$pad] = '[' . (is_int($k) ? $k : "'$k'") . ']'; /// $k = 11'22
            $v = is_array($v) ? self::array($v, $add, 1 + $pad) : self::var($v, $add);
            if (is_string($k)) {
                $ary[] = "'" . html($k) . "' =&gt; $v"; /// $k = 11'22
            } else {
                $ary[] = $i !== $k ? "$k =&gt; $v" : $v;
                $i = $k < 0 ? 0 : 1 + $k;
            }
            if (($len += strlen($v)) > 220) {
                $ary[] = sprintf(span_g, 'cutted..');
                break;
            }
        }
        if (!$pad)
            self::$var_path[2] = [];
        $pad = str_pad('', $pad * 2, ' ');
        $s = implode(', ', $ary);
        if (strlen($s) < 77)
            return "[$s]";
        return "[\n$add  $pad" . implode(",\n$add  $pad", $ary) . "\n$add$pad]";
    }

    static function object($name, $type, $pp = 0) {
        $params = function ($func) {
            return array_map(function ($p) use ($func) {
                $one = $p->hasType() ? ($p->allowsNull() ? '?' : '') . $p->getType()->getName() . ' ' : '';
                if ($var = $p->isVariadic())
                    $one .= '...';
                $one .= ($p->isPassedByReference() ? '&' : '') . '$' . $p->getName();
                if ($var || !$p->isOptional())
                    return $one;
                return "$one = " . ($func->isInternal() && PHP_VERSION_ID < 80000
                    ? sprintf(span_r, 'err')
                    : ($p->isDefaultValueConstant() ? $p->getDefaultValueConstantName() : self::var($p->getDefaultValue())));
            }, $func->getParameters());
        };

        if ('f' == $type) { # function
            $fnc = new ReflectionFunction($name);
            return "<pre>function $fnc->name(" . implode(', ', $params($fnc)) . ')'
                . (($rt = $fnc->getReturnType()) ? ": " . $rt->getName() : '') . '</pre>';

        } elseif ('e' == $type) { # extensions
            $ext = new ReflectionExtension($name);
            return $ext->info();

        } else { # class, interface, trait
            $modifiers = function ($obj) {
                $m = Reflection::getModifierNames(~ReflectionMethod::IS_PUBLIC & $obj->getModifiers());
                return $m ? implode(' ', $m) . ' ' : '';
            };
            $obj = is_object($name) ? $name : false;
            $cls = $obj ? new ReflectionObject($obj) : new ReflectionClass($name);
            $name = ('t' == $type ? 'trait' : ('i' == $type ? 'interface' : 'class')) . " $cls->name";
            if ($x = $cls->getParentClass())
                $name .= " extends " . $x->getName();
            if ($x = $cls->getInterfaceNames())
                $name .= ' implements ' . implode(', ', $x);
            $name = 2 == $pp ? '' : "$name\n";

            $data = $obj ? [] : array_map(function ($v, $k) {
                return "const $k = " . self::var($v, '  ');
            }, $c = $cls->getConstants(), array_keys($c));
            $props = $cls->getProperties($pp ? null : ReflectionProperty::IS_PUBLIC);
            $defs = $obj ? [] : $cls->getDefaultProperties();
            global $sky;
            $data = array_merge($data, array_map(function ($p) use ($defs, $obj, $sky) {
                $one = $p->getName();
                $m = $p->getModifiers();
                if ($obj) {
                    $skip = $obj instanceof SKY && in_array($one, ['mem', 'reg', 'vars']);
                    if ($pp = $p->isPrivate() || $p->isProtected())
                        $p->setAccessible(true);
                    $arrow = ReflectionProperty::IS_STATIC & $m ? '::$' : '-&gt;';
                    $mods = '';
                    if ($m &= ~ReflectionProperty::IS_STATIC & ~ReflectionProperty::IS_PUBLIC)
                        $mods = ' (' . implode(' ', Reflection::getModifierNames($m)) . ')';
                    self::$var_path[1] = $arrow . $one;
                    $val = $skip ? sprintf(span_g, 'see below..') : self::var(@$p->getValue($obj), '  ');
                    $one = "$arrow$one$mods = $val";
                    if ($pp)
                        $p->setAccessible(false);
                } else {
                    if (null !== $defs[$one] && $p->isDefault())
                        $one .= " = " . self::var($defs[$one], '  ');
                    ReflectionProperty::IS_PUBLIC == $m or $m &= ~ReflectionProperty::IS_PUBLIC;
                    $one = implode(' ', Reflection::getModifierNames($m)) . " \$$one";
                }
                return $one;
            }, $props));
            sort($data);

            $funcs = 2 == $pp ? [] : array_map(function ($v) use ($params, $modifiers) {
                return $modifiers($v) . 'function ' . ($v->returnsReference() ? '&' : '')
                    . $v->name . '(' . implode(', ', $params($v)) . ')'
                    . (($rt = $v->getReturnType()) ? ': ' . $rt->getName() : '');
            }, $cls->getMethods(1 == $pp ? null : ReflectionMethod::IS_PUBLIC));
            sort($funcs);

            $traits = implode(', ', $cls->getTraitNames());
            $data = array_merge($traits ? ['use ' . $traits] : [], $data, $data && $funcs ? [''] : [], $funcs);
            $out = $modifiers($cls) . "$name{\n  " . implode("\n  ", $data);
            if ($obj && $obj instanceof SKY) {
                ksort(SKY::$mem);
                foreach (SKY::$mem as $char => $ary) {
                    $out .= "\n\n  ghost-$char:" . (is_array($ary[2]) ? ' <u>type-2</u>' : '') . ($ary[3] ? ' <u>sky-memory</u>' : '');
                    ksort($ary[3]);
                    foreach ($ary[3] as $k => $v)
                        $out .= "\n  -&gt;{$char}_$k = " . self::var($v);
                }
                $out .= "\n\n  SKY::\$reg:";
                ksort(SKY::$reg);
                foreach (SKY::$reg as $k => $v) {
                    self::$var_path[1] = "-&gt;$k";
                    $out .= "\n  -&gt;$k = " . self::var($v);
                }
                $out .= "\n\n  SKY::\$vars:";
                ksort(SKY::$vars);
                foreach (SKY::$vars as $k => $v) {
                    self::$var_path[1] = "-&gt;$k";
                    $out .= "\n  -&gt;$k = " . self::var($v);
                }
            }
            return $obj ? "$out\n}" : tag("$out\n}", '', 'pre');
        }
    }
}

class SVG {
    static $size = [16, 16];
    static $fill = "currentColor";
    private $name;
    private $pack;

    function __construct($c, $a = false) { # new image from array
        $this->name = $a ? $a : $c;
        $this->pack = 'svg_list_' . ($a ? $c : 0) . '.pack';
    }

    function __toString() { # compile image
        global $sky;
        $tpl = unl(Plan::view_g([$sky->d_last_ware ?: 'main', $this->pack]));
        preg_match("/\n:$this->name(| [^\n]+)\n(.+?)\n:/s", $tpl, $m);
        return sprintf('<svg %s xmlns="http://www.w3.org/2000/svg">%s</svg>', $m[1], $m[2]);
    }
}
