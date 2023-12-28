<?php

class Plan
{
    static $ware = 'main';
    static $view = 'main';
    static $z_error = false;
    static $see_also = [];
    static $var_path = ['', '?', [], '']; # var_name, property, array's-path
    static $pngdir = ''; # for DEV

    static function set($ware, $func = false) {
        $prev = self::$ware;
        self::$ware = $ware;
        if ($func) {
            $return = $func($prev);
            self::$ware = $prev;
        }
        return $func ? $return : $prev;
    }

    static function has($in, $class = true) {
        return $class ? isset(SKY::$plans['main']['class'][$in]) : isset(SKY::$plans[$in]);
    }

    static function auto($v, $more = '') {
        $array = var_export($v, true);
        if (!is_string($more))
            $more = call_user_func_array($more, [&$array]);
        return "<?php\n\n# this is auto generated file, do not edit\n$more\nreturn $array;\n";
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
        return $w2 ? file_get_contents(DIR_S . "/$std") : self::__callStatic('_g', [$a0]);
    }

    static function view_($op, $a0) { # g or m
        list ($ware, $a0) = is_array($a0) ? $a0 : [self::$ware, $a0];
        if (!preg_match($re = "/^\w+\.jet$/", $a0))
            throw new Error("Jet: file `$a0` do not match $re");
        if ('_' == ($a0[1] ?? '') && '_' == $a0[0]) {
            $a0 = DIR_S . '/w2/' . $a0;
            return 'g' == $op ? file_get_contents($a0) : stat($a0)['mtime'];
        }
        if ('main' != $ware && !self::view_t([$ware, $a0]))
            return self::__callStatic('view_' . $op, [['main', $a0]]);
        return self::__callStatic('view_' . $op, [[$ware, $a0]]);
    }

    static function __callStatic($func, $arg) {
        static $old_ware = false;
        static $old_obj = false;
        list ($pn, $op) = explode('_', $func);
        $pn or $pn = 'app';
        list ($ware, $a0) = is_array($arg[0]) ? $arg[0] + [1 => null] : [self::$ware, $arg[0]];

        if ('view' == $pn && 'main' == $ware && !is_array($arg[0]))
            $ware = self::$view;

        if ($old_ware == $ware && $old_obj->pn == $pn) {
            $obj = $old_obj;
        } else {
            $obj = (object)self::open($pn, $ware);
            $obj->pn = $pn;
            $old_ware = $ware;
            $old_obj = $obj;
        }
        if ($obj->dc->setup($obj, 'q' === ($op[1] ?? 0) ? $a0 : false))
            return $arg[1] ?? ('rq' == $op ? [] : ('gq' == $op ? '' : 0));

        switch ($op) {
            case 'obj':
                return null === $a0 ? $obj : $obj->$a0;
            case 'b':
                return $obj->dc->glob($a0); # mask
            case 'a':
                return $obj->dc->append($a0, $arg[1]);
            case 'p':
                return $obj->dc->put($a0, $arg[1]);
            case 's':
                return $obj->dc->set($a0, $arg[1]);
            case 'gq':
            case 'g':
                return $obj->dc->get($a0);
            case 't':
                return $obj->dc->test($a0); # if OK return fullname
            case 'mq':
            case 'm':
                return $obj->dc->mtime($a0);
            case 'rq':
            case 'r':
                return $obj->dc->run($a0, $arg[1] ?? false);
            case 'da':
            case 'dq':
            case 'd':
                if (in_array($pn, ['view', 'mem', 'app']))
                    throw new Error("Failed when Plan::{$pn}_$op(..)");
                return 'da' == $op ? $obj->dc->drop_all($a0) : $obj->dc->drop($a0);
            case 'autoload':
                trace("autoload($a0)");
                if (strpos($a0, '\\')) {
                    if (2 != count($x = explode("\\", $a0)) || !isset(SKY::$plans[$x[0]]))
                        return self::vendor($a0);
                    $dir = 1 == strlen($x[1]) || '_' != $x[1][1] ? 'w3' : 'mvc';
                    return self::_rq([$x[0], "$dir/$x[1].php"]) || self::vendor($a0);
                }
                $low = strtolower($a0);
                $classes = SKY::$plans['main']['class'] ?? [];
                if (in_array(substr($a0, 0, 2), ['m_', 't_'])) {
                    if (is_file($fn = $obj->path . "/mvc/$a0.php"))
                        return require $fn;
                    if ('main' != $ware && ($fn = self::_t(['main', "mvc/$a0.php"])))
                        return require $fn;
                    return self::vendor($a0);
                } elseif (isset($classes[$a0])) {
                    return self::_r([$classes[$a0], "w3/$low.php"]);
                }
                $fn = DIR_S . '/w2/' . $low . '.php';
                return is_file($fn) ? require $fn : self::_rq("w3/$low.php") || self::vendor($a0);
            default:
                throw new Error("self::$func(..) - method not exists");
        }
    }

    static function xload(&$name) {
        static $instances = [];
        $n0 = substr($name, 1);
        if (isset($instances[self::$ware][$name = 'm' . $n0]))
            return $instances[self::$ware][$name];
        if (isset($instances[self::$ware][$name = 't' . $n0]))
            return $instances[self::$ware][$name];
        self::_rq("mvc/x$n0.php");
        if (class_exists($model = self::$ware . "\\t$n0", false))
            return $instances[self::$ware][$name] = new $model;
        $model = self::$ware . "\\m$n0";
        return $instances[self::$ware][$name = 'm' . $n0] = new $model;
    }

    static function wares($fn, &$ctrl, &$class) {
        $ymls = [];
        foreach (require $fn as $ware => $plan) {
            $conf = require ($path = $plan['path']) . "/conf.php";
            if ($plan['type'] ?? false)
                $conf['app']['type'] = 'pr-dev';
            if ($plan['options'] ?? false)
                $conf['app']['options'] = $plan['options'];
            if (!DEV && in_array($conf['app']['type'], ['dev', 'pr-dev']))
                continue;
            foreach ($plan['class'] as $cls) {
                $df = 'default_c' == $cls;
                if ($df || 'c_' == substr($cls, 0, 2)) {
                    $x = $df ? '*' : substr($cls, 2);
                    $ctrl[$plan['tune'] ? "$plan[tune]/$x" : $x] = $ware;
                } else {
                    $class[$cls] = $ware;
                }
            }
            if ($plan['tune'])
                $ctrl["$plan[tune]/*"] = $ware;
            $app =& $conf['app'];
            unset($yml, $app['require'], $app['class'], $app['databases']);
            if ($cfg = self::yml($yml, "$path/conf.yml"))
                $app['cfg'] = $cfg;
            if ($yml)
                $ymls[$ware] = $yml;
            SKY::$plans[$ware] = ['app' => ['path' => $path] + $conf['app']] + $conf;
        }
        return $ymls;
    }

    static function rewrite(&$in) {
        $code = "\n";
        foreach (self::_rq('rewrite.php') as $rw)
            !DEV && $rw[2] or $code .= $rw[1] . "\n";
        $in = explode("'',", $in, 2);
        $in = "$in[0]function(\$cnt, &\$surl, \$uri, \$sky) {{$code}},$in[1]";
        return '';
    }

    static function &open($pn, $ware = false) {
        static $connections = [];
        $ware or $ware = self::$ware;

        $new_dc = function (&$cfg) use (&$connections, $pn) {
            $class = 'dc_' . $cfg['driver'];
            require DIR_S . "/w2/$class.php";
            $connections[$pn] = new $class($cfg);
            unset($cfg['dsn']);
            return $connections[$pn];
        };

        if ($connections) {
            $set = isset(SKY::$plans[$ware][$pn]);
            $cfg =& SKY::$plans[$set ? $ware : 'main'][$pn];
            $set or SKY::$plans[$ware][$pn] =& $cfg;
            isset($cfg['dc']) or $cfg['dc'] = isset($cfg['driver']) ? $new_dc($cfg) : $connections[$cfg['use'] ?? ''];
        } else {
            require DIR_S . "/w2/dc_file.php";
            require DIR_S . "/w2/rare.php";
            $dc = $connections[''] = $connections['cache'] = new dc_file;
            $plans = SKY::$plans + [
                'view' => ['path' => DIR_M . '/mvc/view'],
                'cache' => ['path' => 'var/cache'],
                'gate' => ['path' => 'var/gate'],
                'jet' => ['path' => 'var/jet'],
                'mem' => ['path' => 'var/mem'],
            ];
            $cfg = $plans['cache'];
            if (isset($cfg['driver']))
                $dc = $new_dc($cfg);
            $dc->setup((object)$cfg);
            if ($dc->test('sky_plan.php')) {
                SKY::$plans = $dc->run('sky_plan.php');
            } else {
                SKY::$plans = $ctrl = [];
                $app = ['path' => DIR_M, 'cfg' => self::yml($ymls, DIR_M . '/conf.yml')];
                SKY::$plans['main'] = ['rewrite' => '', 'app' => $app, 'class' => []] + $plans;
                $ymls = ['main' => $ymls];
                if (is_file($fn = DIR_M . '/wares.php'))
                    $ymls += self::wares($fn, $ctrl, SKY::$plans['main']['class']);
                $plans = SKY::$plans;
                $plans['main'] += ['ctrl' => $ctrl + Debug::controllers('main')];
                SKY::$plans['main']['cache']['dc'] = $dc;
                self::cache_s('sky_plan.php', self::auto($plans, ['Plan', 'rewrite']));
                SKY::$plans = self::cache_r('sky_plan.php');
                foreach ($ymls as $ware => $yml)
                    self::yml($yml, $ware);
            }
            SKY::$plans['main']['cache']['dc'] = $dc;
            self::locale();
        }
        return $cfg;
    }

    static function locale($lg = 'en') {
        if ('en' == $lg && setlocale(LC_ALL, 'en_US.utf8', 'en_US.UTF-8'))
            return 1;
        return $lg ? setlocale(LC_ALL, "$lg.utf8", "$lg.UTF-8", $lg) : setlocale(LC_ALL, 0);
    }

    static function yml(&$name, $ware = 'main') {
        if (null === $name) {
            $name = Rare::yaml(is_file($ware) ? file_get_contents($ware) : '');
            return $name['plan'] ?? [];
        } elseif (is_array($name)) {
            foreach ($name as $key => $val) {
                if ('plan' != $key && is_array($val))
                    self::cache_s(['main', "cfg_{$ware}_$key.php"], self::auto($val));
            }
        } else {
            $yml = Rare::yaml(self::_g([$ware, 'conf.yml']))[$name];
            if (is_string($yml)) {
                $ext = explode('.', $yml);
                switch (end($ext)) {
                    case 'php': return self::_r([$ware, $yml]);
                    case 'yml':
                    case 'yaml': return Rare::yaml(self::_g([$ware, $yml]));
                    case 'json': return json_decode(self::_g([$ware, $yml]), true);
                    case 'default': return strbang(unl(self::_g([$ware, $yml])));
                }
            }
            return $yml;
        }
    }

    static function &cfg($name) {
        static $cache = [];

        [$ware, $name] = is_array($name) ? $name + ['main', 'plan'] : [self::$ware, $name];
        
        if ('plan' == $name) {
            $p =& SKY::$plans[$ware]['app']['cfg'];
        } else {
            $p =& $cache[$ware][$name];
            if (null === $p) {
                $p = self::cache_rq($addr = ['main', "cfg_{$ware}_$name.php"])
                    or self::cache_s($addr, self::auto($p = self::yml($name, $ware)));
            }
        }
        return $p;
    }

    static function check_other() {
        return SKY::$dd ? (string)SKY::$dd->check_other() : '';
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

    static function z_err($z_fly, $is_error = false, $drop = true) {
        static $msg = null;

        if (null !== $msg)
            return $msg; # empty string or msg
        $msg = self::cache_gq($addr = ['main', 'dev_z_err']);
        if (!SKY::$debug) # j_trace don't erase file
            return $msg;
        $z_fly = HEAVEN::Z_FLY == $z_fly;
        if ($msg) {
            if ($drop) {
                $z_fly or self::cache_dq($addr); # erase flash file
                SKY::$debug = false; # skip self tracing to show z-error's one
            }
        } elseif ($z_fly && $is_error) {
            $msg = tag("Z-error at " . NOW, 'class="z-err"', 'h1');
            $p =& SKY::$errors;
            if (isset($p[1]))
                $msg .= '<h1>' . $p[1][0] . '</h1><pre>' . $p[1][1] . '</pre>';
            if (isset($p[2]))
                $msg .= '<h1>' . $p[2][0] . '</h1><pre>' . $p[2][1] . '</pre>';
            self::cache_p($addr, $msg);
            self::$z_error = true;
        }
        return $msg;
    }

    // 2do suppressed: fix for php 8 https://www.php.net/manual/en/language.operators.errorcontrol.php
    static function epush($title, $desc, $context) {
        $vars = '';
        if ($context) {
            $vars = "\n";
            foreach ($context as $k => $v)
                $vars .= "\$$k = " . self::var($v) . ";\n";
        }
        $p =& SKY::$errors;
        $p[isset($p[1]) ? 2 : 1] = ["#$p[0] $title", $desc . $vars];
    }

    static function var($var, $add = '', $quote = true, $var_name = false) { # tune var_export for display in html
        if ($var_name)
            self::$var_path = [$var_name, '?', [], is_object($var) ? get_class($var) : ''];
        switch ($type = gettype($var)) {
            case 'unknown type':
            case 'resource':
                return L::m($type); // for php8 - get_debug_type($var) class@anonymous stdClass
            case 'object':
                $cls = get_class($var);
                if ($quote) {
                    $p =& self::$var_path;
                    self::$see_also[$cls] = [$p[':' == $p[1][0] ? 3 : 0] . $p[1] . implode('', $p[2]) => $var];
                    return L::m("Object $cls");
                }
                $s = self::object($var, 'c', 2);
                if ("{\n  \n}" == $s)
                    return L::y("Object $cls") . ' - ' . L::m('no properties');
                return tag($s, 'c="' . $cls . '"', 'o');
            case 'array':
                $var = self::array($var, $add);
                if ($quote)
                    return $var;
                return strlen($var) < 50 ? L::y('Array ') . $var : "<r>$var</r>";
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
                return $gt ? html($var) . L::g('&nbsp;cutted..') : html($var);
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
            self::$var_path[2][$pad] = '[' . (is_int($k) ? $k : "'$k'") . ']';
            $v = is_array($v) ? self::array($v, $add, 1 + $pad) : self::var($v, $add);
            if (is_string($k)) {
                $ary[] = "'" . html($k) . "' =&gt; $v";
            } else {
                $ary[] = $i !== $k ? "$k =&gt; $v" : $v;
                $i = $k < 0 ? 0 : 1 + $k;
            }
            if (($len += strlen($v)) > 220) {
                $ary[] = L::g('cutted..');
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
                    ? L::r('err')
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
                    if ($skip) {
                        $val = L::g('see below..');
                    } else try {
                        $val = @$p->getValue($obj);
                        $val = self::var($val, '  ');
                    } catch (Throwable $e) {
                        //$val = sprintf(span_m, $e->getMessage());
                        $val = L::m($e->getMessage());
                    }
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
                    . (($rt = $v->getReturnType()) ? ': ' . $rt->getName() : '');// from php8.1 getTentativeReturnType()
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
            return $obj ? "$out\n}" : pre("$out\n}", '');
        }
    }
}

class L {
    static function __callStatic($func, $arg) {
        return "<$func>$arg[0]</$func>"; # colors: RGZMY, tags:abipqsu
    }
}
