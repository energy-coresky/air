<?php
#[\AllowDynamicProperties]
class Globals
{
    private $exts = ['php'];//, 'jet'
    private $ext;
    private $cnt = [0, 0];
    private $definitions = [
        'NAMESPACE' => [],
        'INTERFACE' => [],
        'TRAIT' => [],
        'VAR' => [],
        'FUNCTION' => [],
        'CONST' => [],
        'DEFINE' => [],
        'CLASS' => [],
        'EVAL' => [],
    ];
    static $ns = [];
    static $used_ext = [];
    static $cls2ext = [];

    private $keywords = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue',
        'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
        'endwhile', 'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include',
        'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private', 'protected',
        'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor',
        'match', 'readonly', 'fn', 'yield', // 'yield from'
    ];
    private $predefined_constants = ['__class__', '__dir__', '__file__', '__function__', '__line__', '__method__', '__namespace__', '__trait__'];

    private static $functions = [];
    private static $constants = [];

    private $interfaces;
    private $classes;
    private $traits;

    private $all;
    private $all_lc;

    private $path;

    static function ware($dir) {
        $glb = new Globals($dir);
        return array_keys($glb->c_report()['CLASS']);
    }

    function __construct($path = '.') {
        defined('T_NAME_QUALIFIED') or define('T_NAME_QUALIFIED', 314);
        $this->path = $path;
    }

    function parse_html($fn, $line_start, $str) {
        //2do warm jet-cache
        //$this->parse($fn, $line_start, $str);
    }

    function parse($fn, $line_start = 1) {
        $this->cnt[0]++;
        $php = file_get_contents($fn);
        $line = $line_start;
        $curly = $glob_list = $ns_kw = 0;
        $place = 'GLOB';
        $vars = [];
        $out = $p1 = $p2 = $ns = '';
        foreach (token_get_all(unl($php)) as $token) {
            $id = $token;
            $this->pos = "$fn $line";
            $line_start = $line;
            if (is_array($token)) {
                list($id, $str) = $token;
                $line += substr_count($str, "\n");
                switch ($id) {
                    case T_WHITESPACE: case T_COMMENT:
                        continue 2;
                    case T_CURLY_OPEN:
                        ++$curly;
                        break;
                    case T_EVAL:
                        static $n = 1;
                        $this->push('EVAL', $n++ . ".$this->pos");
                        break;
                    case T_GLOBAL:
                        $glob_list = true;
                        break;
                    case T_NAMESPACE:
                        $ns_kw = true;
                        $ns = '';
                        break;
                    case T_STRING: case T_NAME_QUALIFIED: case T_NS_SEPARATOR:
                        if ($ns_kw) {
                            $ns .= $str;
                        } elseif (in_array($p1, [T_CLASS, T_INTERFACE, T_TRAIT, ])) {
                            $this->push($place = substr(token_name($p1), 2), $ns . $str);
                        } elseif ('GLOB' == $place) {
                            if (T_FUNCTION === $p1 && T_USE != $p2 || T_FUNCTION === $p2 && '&' === $p1)
                                $this->push($place = 'FUNCTION', $ns . $str);
                            if (T_CONST === $p1 && T_USE != $p2)
                                $this->push('CONST', $ns . $str);
                        }
                        $id = [$id, $str];
                        break;
                    case T_CONSTANT_ENCAPSED_STRING:
                        if ('(' == $p1 && is_array($p2) && 'define' == $p2[1])
                            $this->push('DEFINE', substr($str, 1, -1));
                        break;
                    case T_VARIABLE:
                        if ($glob_list)
                            $vars[] = $str;
                        if ('$GLOBALS' == $str)
                            $this->push('VAR', $str);
                        $id = [$id, $str];
                        break;
                    case T_INLINE_HTML:
                        'jet' != $this->ext or $this->parse_html($fn, $line_start, $str);
                        break;
                }
            }

            switch ($id) {
                case '(': # def anonymous function
                    if ('GLOB' == $place && (T_FUNCTION === $p1 || T_FUNCTION === $p2 && '&' === $p1)) {
                        $place = 'FUNCTION';
                    }
                    break;
                case '}':
                    if (!--$curly) {
                        $place = 'GLOB';
                        $vars = [];
                    }
                    break;
                case '{':
                    ++$curly;
                    if (!$ns_kw) {
                        break;
                    } elseif ('' === $ns) {
                        $ns_kw = false;
                    }
                case ';':
                    $glob_list = false;
                    if ($ns_kw) {
                        $ns_kw = false;
                        if (!isset(self::$ns[$ns])) {
                            $this->push('NAMESPACE', $ns);
                            self::$ns[$ns] = 0;
                        } else {
                            self::$ns[$ns]++;
                        }
                        $ns .= '\\';
                    }
                    break;
                case '=': # =& also work
                    $rule = T_DOUBLE_COLON != $p2 && is_array($p1) && T_VARIABLE == $p1[0];
                    if ($rule && ('GLOB' == $place || in_array($p1[1], $vars)))
                        $this->push('VAR', $p1[1]);
                    break;
            }

            $p2 = $p1;
            $p1 = $id;
        }
    }

    function push($key, $ident) {
        $assign = function (&$place, $ident, $mess = '') {
            $place[$ident][] = "$this->pos $mess";
        };

        $place =& $this->definitions[$key];
        if (in_array($lc = strtolower($ident), $this->keywords))
            return $assign($place, $ident, 'Keyword override');
        if (in_array($lc, $this->predefined_constants))
            return $assign($place, $ident, 'Predefined constant override');

        $is_lc = isset($this->all_lc[$lc]);
        $is_nc = isset($this->all[$ident]);
        switch ($key) {
            case 'NAMESPACE':
                $assign($place, $ident);
                break;
            case 'INTERFACE':
                !$is_lc || interface_exists($ident, false) ? $assign($place, $ident) : $assign($place, $ident, 'Identifier in usage');
                break;
            case 'TRAIT':
                !$is_lc || trait_exists($ident, false) ? $assign($place, $ident) : $assign($place, $ident, 'Identifier in usage');
                break;
            case 'CLASS':
                !$is_lc || class_exists($ident, false) ? $assign($place, $ident) : $assign($place, $ident, 'Identifier in usage');
                break;
            case 'VAR':
                if ('$GLOBALS' == $ident) {
                    $assign($place, $ident, '-Do not use $GLOBALS');
                } else {
                    $assign($place, $ident);
                }
                break;
            case 'FUNCTION':
                isset(self::$functions[$lc]) ? $assign($place, $ident, "Internal function name used") : $assign($place, $ident);
                break;
            case 'CONST':
            case 'DEFINE':
                isset(self::$constants[$ident]) ? $assign($place, $ident, "Internal constant name used") : $assign($place, $ident);
                break;
            case 'EVAL':
                $assign($place, $ident, 'Dangerous code');
                break;
        }
        return '';
    }

    function exclude_dirs($dir = false) {
        static $dirs;
        if (null === $dirs) {
            $tmemo = sqlf('+select tmemo from $_memory where id=11');
            $mem = SKY::ghost('i', $tmemo, 'update $_memory set dt=$now, tmemo=%s where id=11');
            $dirs = isset($mem['gr_dirs']) ? explode(' ', $mem['gr_dirs']) : [];
        }
        if ($dir) {
            ($offset = array_search($dir, $dirs)) !== false ? array_splice($dirs, $offset, 1) : ($dirs[] = $dir);
            SKY::i('gr_dirs', implode(' ', $dirs));
        }
        trace($dirs);
        return $dirs;
    }

    function c_dirs() {
        $dirs = Rare::walk_dirs('.');
        if (DIR_M != DIR_S)
            $dirs = array_merge($dirs, Rare::walk_dirs(DIR_S . '/w2'));
        SKY::d('gr_start', 'dirs');
        return [
            'dirs' => $dirs,
            'continue' => function ($dir) {
                static $red = false, $dirs;
                is_array($dirs) or $dirs = $this->exclude_dirs();
                if (in_array($dir, $dirs)) {
                    $red = $dir;
                    return 0;
                }
                return $red && $red == substr($dir, 0, strlen($red));
            },
        ];
    }

    function c_skip() {
        $this->exclude_dirs($_POST['dir']);
        return $this->c_dirs();
    }

    function c_back() {
        return $this->c_report();
    }

    function usage($fn) {
        $assign = function ($x, &$ary, $name, $ns = '') {
            if ($abs = '\\' === $name[0])
                $name = substr($name, 1);
            $extn = $ary[$name] ?? '';
            if (isset($this->use[$x][$name])) {
                $name = $this->use[$x][$name];
                if (strpos($name, '\\'))
                    $extn = '';
            } elseif (!$abs && '' === $extn) {
                $name = $ns . $name;
            }
            $p =& self::$used_ext[$extn][$x];
            if (isset($p[$name])) {
                $p[$name][1]++;
                if ($this->pos[0] != $p[$name][0][0])
                    $p[$name][2]++;
                $p[$name][0] = $this->pos;
            } else {
                $p[$name] = [$this->pos, 0, 0];
            }
        };
        $this->cnt[0]++;
        $php = file_get_contents($fn);
        $line = 1;
        $this->use = [[], [], []]; # cls fun const
        $out = $p1 = $p2 = $p3 = $ns = $name = $pos = '';
        $clist = [T_OBJECT_OPERATOR, T_FUNCTION, T_DOUBLE_COLON, T_NEW];
        foreach (token_get_all(unl($php)) as $token) {
            $id = $token;
            $this->pos = [$fn, $line];
            if (is_array($token)) {
                list($id, $str) = $token;
                $line += substr_count($str, "\n");
                switch ($id) { // 2do extends & implements kw.. const ??
                    case T_WHITESPACE: case T_COMMENT:
                        continue 2;
                    case T_NAMESPACE:
                        $pos = 'ns';
                        $ns = '';
                        break;
                    case T_NEW:
                        $pos = 'new';
                        $name = '';
                        break;
                    case T_USE:
                        $pos = 'use';
                        $name = '';
                        $usn = 0;
                        break;
                    case T_FUNCTION:
                        $usn = 1;
                        break;
                    case T_CONST:
                        $usn = 2;
                        break;
                    case T_STRING: case T_NAME_QUALIFIED: case T_NS_SEPARATOR:
                        if ('ns' == $pos) {
                            $ns .= $str;
                        } elseif ('\\' === $str) {
                            $name .= '' === $name && is_array($p1) ? "$p1[1]\\" : '\\';
                        } elseif ($name || 'use' == $pos) {
                            $name .= $str;
                        }
                        $id = [$id, $str];
                        break;
                    case T_INLINE_HTML:
                        break;
                    case T_DOUBLE_COLON: # ::
                        if (is_array($p1) && T_STRING === $p1[0] && !in_array($p1[1], ['self', 'parent']))
                            $assign(0, self::$cls2ext, $name ?: $p1[1], $ns);
                        $name = '';
                        break;
                }
            }

            switch ($id) {
                case '(': # use function
                    if ('use' === $pos) {
                        $pos = '';
                    } elseif ('new' != $pos && is_array($p1) && T_STRING === $p1[0] && !in_array($p2, $clist) && !('&' == $p2 && T_FUNCTION == $p3)) {
                        $assign(1, self::$functions, $p1[1], $name ? substr($name, 1, -strlen($p1[1])) : $ns);
                        $name = '';
                    }
                    break;
                case '{':
                    if ('ns' == $pos) {
                        $pos = '';
                        '' === $ns or $ns .= '\\';
                    }
                    break;
                case ';':
                    if ('ns' == $pos) {
                        $pos = '';
                        $ns .= '\\';
                    } elseif ('use' === $pos) {
                        $this->use[$usn][$p1[1]] = T_AS === $p2 ? substr($name, 0, -strlen($p1[1])) : $name;
                        $pos = $name = '';
                    }
                    break;
            }

            if ('new' == $pos && T_NEW !== $id && !is_array($id)) {
                if (!in_array($id, [T_VARIABLE, T_STATIC]) && $name)
                    $assign(0, self::$cls2ext, $name);
                $pos = $name = '';
            }
            if ($name && T_AS !== $id && !is_array($id)) # reset const for ::
                $name = '';
            $p3 = $p2;
            $p2 = $p1;
            $p1 = $id;
        }
    }

    function c_usage() {
        SKY::d('gr_start', 'usage');
        $extns = self::extensions();
        if (isset($_POST['s']))
            SKY::d('gr_unused', $_POST['s']);
        //trace($extns);
        self::$used_ext = array_combine($extns, array_pad([], count($extns), [[], [], []])); # classes, funcs, consts
        self::$used_ext += ['' => [[], [], []]];
        $this->walk_files([$this, 'usage']);
        $total = [0, 0, 0];
        $used = [];
        return [
         //   'extns' => $extns,
            'show_unused' => SKY::d('gr_unused'),
            'func' => function ($ary, $key) use (&$total, &$used) {
                $used[] = $key;
                $total[0] += ($c0 = count($ary[0]));
                $total[1] += ($c1 = count($ary[1]));
                $total[2] += ($c2 = count($ary[2]));
                return "$c0/$c1/$c2";
            },
            'total' => function () use (&$total, &$used) {
                SKY::i('gr_extns', implode(' ', $used));
                return "$total[0]/$total[1]/$total[2]";
            },
        ];
    }

    static function extensions($simple = false) {
        $extns = get_loaded_extensions();
        natcasesort($extns);
        if ($simple)
            return $extns;
        $const = get_defined_constants(true);
        foreach ($extns as $n => $extn) {
            $rn = new ReflectionExtension($extn);
            $list = $rn->getClassNames();
            self::$cls2ext += array_combine($list, array_pad([], count($list), $extn));
            self::$functions += array_change_key_case(array_map(function() use ($extn) {
                return $extn;
            }, $rn->getFunctions()));
            if (isset($const[$extn])) {
                self::$constants += array_map(function() use ($extn) {
                    return $extn;
                }, $const[$extn]);
            }
        }
        return $extns;
    }

    function walk_files($proc) {
        $dirs = Rare::walk_dirs($this->path, $this->exclude_dirs());
        if ($flag = DIR_M != DIR_S && '.' == $this->path)
            $dirs = array_merge($dirs, Rare::walk_dirs(DIR_S . '/w2'));
        foreach ($dirs as $dir) {
            $this->cnt[1]++;
            foreach (Rare::list_path($dir, 'is_file') as $fn) {
                $ary = explode('.', $fn);
                $this->ext = end($ary);
                if (in_array($this->ext, $this->exts))
                    call_user_func($proc, $fn);
            }
        }
        if ($flag) {
            call_user_func($proc, DIR_S . '/heaven.php');
            call_user_func($proc, DIR_S . '/sky.php');
        }
    }

    function c_report() {
        SKY::d('gr_start', 'report');
        $extns = self::extensions();
        $this->interfaces = array_flip(get_declared_interfaces());
        $this->classes = array_flip(get_declared_classes());
        $this->traits = array_flip(get_declared_traits());
        //require __DIR__ . '/internals.php'; collect maximal list? 2do: save it to DB with links to docs

        $all = self::$constants + $this->interfaces + $this->classes + $this->traits;
        $this->all = array_fill_keys(array_keys($all), 0);
        $this->all_lc = array_change_key_case($this->all);

        $this->walk_files([$this, 'parse']);
        if ('.' != $this->path)
            return $this->definitions;
        foreach ($this->definitions as &$definition)
            uksort($definition, 'strcasecmp');

        $nap = Plan::mem_rq('report.nap');
        $cnts = [0, 0]; # no-problem, ok

        return [
            'defs' => $this->definitions,
            'cnts' => function($i) use (&$cnts) {
                return 2 == $i ? array_sum(array_map('count', $this->definitions)) - $cnts[0] - $cnts[1] : $cnts[$i];
            },
            'e_idents' => [
                'max_i' => -1, // infinite
                'row_c' => function($in, $evar = false) use ($nap, &$cnts) {
                    static $ary, $gt, $id, $num, $err_msg, $def_prev = '';
                    if ($evar) {
                        $gt = count($ary = $in[0]) > 1;
                        $id = $in[1];
                        if (isset($nap[$id]))
                            $cnts[1]++;
                        list ($def) = explode('.', $id);
                        if ($def_prev != $def)
                            $num = 1;
                        $def_prev = $def;
                        $err_msg = 'VAR' == $def ? 'Twice assigning' : 'Duplicated definition';
                    }
                    if ($evar || !$ary)
                        return false;

                    $c = explode(' ', array_shift($ary), 3);
                    $x = $c[2] ? $c[2] : ($gt ? $err_msg : '');
                    if ($x && $x[0] == '-')
                         $x = substr($x, 1);
                    $ok = isset($nap[$id]);
                    if (!$gt = $gt || $c[2])
                        $cnts[0]++;
                    return [
                        'class' => $gt ? ($ok ? 'bg-y' : 'bg-r') : 'norm', //bg-b
                        'pos' => "$c[0]^$c[1]",
                        'desc' => $x,
                        'nap' => $ok ? $nap[$id] : '',
                        'num' => $num++,
                    ];
                },
            ],
            'loaded' => $extns,
            'used' => explode(' ', SKY::i('gr_extns')),
            'cnt' => $this->cnt,
        ];
    }

    function c_saved() {
        return [];
    }

    function c_lint() {
        return [];
    }

    function c_code() {
        return [];
    }

    function c_form() {
        return ['ident' => $_POST['ident']];
    }

    function c_mark() {
        $nap = Plan::mem_rq('report.nap');
        $nap[$_POST['ident']] = $_POST['desc'];
        Plan::mem_p('report.nap', Plan::auto($nap));
        return $this->c_report();
    }

    function c_save() {
        Plan::mem_p("report_" . substr(NOW, 0, 10) . '.html', $_POST['html']);
        return $this->c_report();
    }
}
