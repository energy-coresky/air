<?php
#[\AllowDynamicProperties]
class Globals
{
    private $exts = ['php'];//, 'jet'
    private $ext;
    private $definitions = [
        'NAMESPACE' => [], 'INTERFACE' => [], 'TRAIT' => [], 'VAR' => [], 'FUNCTION' => [],
        'CONST' => [], 'DEFINE' => [], 'CLASS' => [], 'EVAL' => [],
    ];
    static $also_ns = [];
    static $used_ext = [];

    private $keywords = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue',
        'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
        'endwhile', 'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include',
        'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private', 'protected',
        'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor',
        'match', 'readonly', 'fn', 'yield', // 'yield from'
    ];
    private $predefined_constants = ['__class__', '__dir__', '__file__', '__function__', '__line__', '__method__', '__namespace__', '__trait__'];

    static $total = [0, 0, 0];
    static $cnt = [0, 0, 0, 0, 0];

    private static $functions = [];
    private static $constants = [];
    private static $classes = [];

    private $interfaces;
    private $traits;

    private $all;
    private $all_lc;

    private $path;

    static function ware($dir) {
        $glb = new Globals($dir);
        return array_keys($glb->_def()['CLASS']);
    }

    function __construct($path = '.') {
        defined('T_NAME_QUALIFIED') or define('T_NAME_QUALIFIED', 314);
        $this->path = $path;
    }

    function parse_html($fn, $line_start, $str) {
        //2do warm jet-cache
        //$this->parse_def($fn, $line_start, $str);
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
        return $this->_def();
    }

    function c_save() {
        Plan::mem_p("report_" . substr(NOW, 0, 10) . '.html', $_POST['html']);
        return $this->_def();
    }

    function parse_def($fn, $line_start = 1) {
        self::$cnt[0]++;
        $php = file_get_contents($fn);
        $line = $line_start;
        $curly = $glob_list = $ns_kw = 0;
        $place = 'GLOB';
        $vars = [];
        $p1 = $p2 = $ns = '';
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
                        if (!isset(self::$also_ns[$ns])) {
                            $this->push('NAMESPACE', $ns);
                            self::$also_ns[$ns] = 0;
                        } else {
                            self::$also_ns[$ns]++;
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

    function parse_use($fn) {
        $use = function ($x, &$ary, &$name, $y = 0) {
            if ($abs = '\\' === $name[0]) {
                $name = substr($name, 1);
            } elseif (strpos($name, '\\') && $this->ns) {
                list ($pfx, $name) = explode('\\', $name, 2);
            }
            if ($ok = !$abs && isset($this->use[$x][$pfx ?? $name]))
                $name = isset($pfx) ? $this->use[$x][$pfx] . "\\$name" : $this->use[$x][$name];
            $extn = $ary[2 == $x ? $name : strtolower($name)] ?? '';
            $abs or $ok or '' !== $extn or $name = $this->ns . $name;
            $p =& self::$used_ext[$extn][$extn || !$y ? $x : $y];
            if (isset($p[$name])) {
                $p[$name][1]++;
                if ($this->pos[0] != $p[$name][0][0])
                    $p[$name][2]++;
                $p[$name][0] = $this->pos;
            } else {
                $p[$name] = [$this->pos, 0, 0];
            }
            $name = '';
        };
        self::$cnt[$curly = 0]++;
        $php = file_get_contents($fn);
        $line = $ok = 1;
        $this->use = [[], [], []]; # cls fun const
        $this->ns = $p1 = $name = $pos = $quot = '';
        $clist = [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST];
        $slist = ['self', 'parent', 'static'];
        foreach (token_get_all(unl($php)) as $token) {
            $id = $token;
            $this->pos = [$fn, $line];
            if (is_array($token)) {
                list($id, $str) = $token;
                $line += substr_count($str, "\n");
                switch ($id) {
                    case T_STRING: case T_NS_SEPARATOR:  //case T_NAME_QUALIFIED:
                        if (T_AS === $p1 && $name) {
                            $this->use[$usn][$str] = $name;
                            $name = '';
                        } else {
                            'NAM' == $pos ? ($this->ns .= $str) : ($name .= $str);
                        }
                    case T_WHITESPACE: case T_COMMENT:
                        continue 2;
                    case T_VARIABLE: case T_STATIC:
                        $pos = '';
                        break;
                    case T_CURLY_OPEN:
                        ++$curly;
                        break;
                    case T_NAMESPACE:
                        $this->ns = '';
                    case T_IMPLEMENTS:
                        if ('EXT' === $pos)
                            $use(0, self::$classes, $name);
                    case T_CLASS: case T_INTERFACE: case T_TRAIT:
                    case T_EXTENDS: case T_INSTANCEOF:
                    case T_USE: case T_NEW:
                        $pos = substr(token_name($id), 2, 3);
                        $name = '';
                        $usn = 0;
                        break;
                    case T_FUNCTION:
                        $usn = 1;
                        break;
                    case T_CONST:
                        $usn = 2;
                        break;
                    case T_DOUBLE_COLON:
                        if ($name && !in_array($name, $slist))
                            $use(0, self::$classes, $name);
                        $name = '';
                        break;
                }
            }
            $prev = $pos;
            switch ($id) {//T_INLINE_HTML
case '&':
                    continue 2;
                case '(':
                    if ('USE' === $pos) { # use in the Closure
                        $pos = '';
                    } elseif (!$pos && $name && !in_array($p1, $clist)) {
                        $use(1, self::$functions, $name);
                    }
                    break;
                case '}':
                    --$curly;
                    break;
                case '{':
                    ++$curly;
                    if ('EXT' === $pos) {
                        $use(0, self::$classes, $name); # extends class
                    } elseif ('IMP' === $pos) {
                        $use(0, self::$classes, $name, 3); # interfaces
                    } elseif ('NAM' === $pos && $this->ns) {
                        $this->ns .= '\\';
                    }
                    $pos = '';
                    break;
                case ',':
                    if ('IMP' === $pos) {
                        $use(0, self::$classes, $name, 3); # interfaces via comma
                    } elseif ('USE' === $pos && $curly) {
                        $use(0, self::$classes, $name, 4); # traits via comma
                    } else {
                        $pos = '';
                    }
                    break;
                case '"':
                    $quot = !$quot;
                    break;
                case ';':
                    if ('NAM' === $pos) {
                        $this->ns .= '\\';
                    } elseif ('USE' === $pos) {
                        if ($curly) {
                            $use(0, self::$classes, $name, 4); # traits
                        } else {
                            $ary = explode('\\', $name);
                            $this->use[$usn][end($ary)] = $name;
                        }
                    }
                    $pos = $name = '';
                    break;
            }

            if ($name && (T_AS !== $id || 'USE' !== $pos)) { # constants
                if ('NEW' === $pos) { # new cls
                    if (!in_array($id, [T_VARIABLE, ]) && !in_array($name, $slist))
                        $use(0, self::$classes, $name);
                } elseif (!$prev && !$quot && !in_array($p1, $clist) && !in_array($id, ['{', T_VARIABLE, T_ELLIPSIS])
                    && !in_array(strtolower($name), ['true', 'false', 'null', 'bool', 'void', 'string'])) {
                    $use(2, self::$constants, $name);
                }
                $pos = $name = '';
            }
            $p1 = $id;
        }
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
        return $this->_def();
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
            self::$classes += array_change_key_case(array_combine($list, array_pad([], count($list), $extn)));
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
            self::$cnt[1]++;
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

    function _use($show_ext = true) {// see vendor/sebastian/recursion-context/tests/ContextTest.php^101 Exception::class
        global $sky;
        $sky->k_parts = [3 => 'Interfaces', 'Traits', 1 => 'Functions', 2 => 'Constants', 0 => 'Classes'];
        $extns = self::extensions();
        self::$used_ext = array_combine($extns, array_pad([], count($extns), [[], [], []])); # classes, funcs, consts
        self::$used_ext += ['' => [[], [], [], [], []]]; # classes, funcs, consts, interfaces, traits
        $this->walk_files([$this, 'parse_use']);
        $json = [];
        return [
            'show_emp' => 0,
            'e_usage' => [
                'max_i' => -1, // infinite
                'row_c' => function($ext, $evar = false) {
                    static $p, $i, $j = 0, $defs = 0, $ary = [];
                    if ($evar) {
                        is_int($i = $ext) ? ($ext = '') : ($i = 0);
                        $p = self::$used_ext[$ext];
                        '' !== $ext or 0 !== $defs or $defs = json_decode(Plan::mem_gq('definitions.json') ?: '[]');
                        if (0 !== $defs)
                            $ary = $p[$i] and uksort($ary, 'strcasecmp');
                    }
                    $user = 0 !== $defs;
                    $chd = $err = '';
                    for (; !$ary; $i++)
                        if ($i > 2 || $user) {
                            return false;
                        } elseif ($ary = $p[$chd = $i]) {
                            if ($evar)
                                $j = $evar->key() + 1;
                            uksort($ary, 'strcasecmp');
                        }
                    if ($evar) {
                        self::$total[0] += ($c0 = count($p[0]));
                        self::$total[1] += ($c1 = count($p[1]));
                        self::$total[2] += ($c2 = count($p[2]));
                        self::$total[$ext] = "$c0/$c1/$c2";
                        return false;
                    }
                    $name = key($ary);
                    $np = array_shift($ary);
                    if ($user && (!$defs || !in_array(strtolower($name), $defs[$i > 2 ? 0 : $i]))) {
                        $err = 'Definition not found';
                        self::$cnt[3]++;
                    } else {
                        self::$cnt[2]++;
                    }
                    return [
                        'chd' => $j == $ext->__i ? $i - 1 : $chd,
                        'name' => $name,
                        'pos' => $np[0][0] . '^' . $np[0][1],
                        'usage' => $np[1],
                        'files' => $np[2],
                        'err' => $err,
                        'class' => $err ? ($err ? 'bg-r' : 'bg-y') : 'norm', //bg-b
                        'nap' => '',
                    ];
                },
            ],
            'after' => function($e, &$cnt) use (&$json, $show_ext, $sky) {
                $cnt = self::$cnt;
                if ($show_ext) {
                    SKY::i('gr_extns', implode(' ', array_keys($sky->k_tot = self::$total)));
                } else {
                    //Plan::mem_p('usage.json', json_encode($json, JSON_PRETTY_PRINT));
                    self::$cnt[4] = array_sum(array_map('count', self::$used_ext['']));
                    self::$cnt[5] = self::$cnt[4] - $cnt[2] - $cnt[3];
                }
                return 1 + $e->key();
            },
        ];
    }

    function _def() {
        $extns = self::extensions();
        $this->interfaces = array_flip(get_declared_interfaces());
        $classes = array_flip(get_declared_classes());
        $this->traits = array_flip(get_declared_traits());
        //require __DIR__ . '/internals.php'; collect maximal list? 2do: save it to DB with links to docs

        $all = self::$constants + $this->interfaces + $classes + $this->traits;
        $this->all = array_fill_keys(array_keys($all), 0);
        $this->all_lc = array_change_key_case($this->all);

        $this->walk_files([$this, 'parse_def']);
        if ('.' != $this->path)
            return $this->definitions;
        foreach ($this->definitions as &$definition)
            uksort($definition, 'strcasecmp');

        $nap = Plan::mem_rq('report.nap');
        $json = [];

        return [
            'ext_loaded' => $extns,
            'ext_used' => explode(' ', SKY::i('gr_extns')),
            'defs' => $this->definitions,
            'e_idents' => [
                'max_i' => -1, // infinite
                'row_c' => function($in, $evar = false) use ($nap, &$json) {
                    static $ary, $gt, $id, $num, $err_msg, $def_prev = '';
                    if ($evar) {
                        $gt = count($ary = $in[0]) > 1;
                        $id = $in[1];
                        if (isset($nap[$id]))
                            self::$cnt[3]++;
                        list ($def, $ident) = explode('.', $id);
                        if (!in_array($def, ['NAMESPACE', 'VAR', 'EVAL'])) {
                            $z = 'FUNCTION' == $def ? 1 : (in_array($def, ['CONST', 'DEFINE']) ? 2 : 0);
                            $json[$z][] = strtolower($ident);
                        }
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
                        self::$cnt[2]++;
                    return [
                        'class' => $gt ? ($ok ? 'bg-y' : 'bg-r') : 'norm', //bg-b
                        'pos' => "$c[0]^$c[1]",
                        'desc' => $x,
                        'nap' => $ok ? $nap[$id] : '',
                        'num' => $num++,
                    ];
                },
            ],
            'after' => function($e, &$cnt) use (&$json) {
                Plan::mem_p('definitions.json', json_encode($json, JSON_PRETTY_PRINT));
                self::$cnt[4] = array_sum(array_map('count', $this->definitions));
                $cnt = self::$cnt;
                self::$cnt[5] = $cnt[4] - $cnt[2] - $cnt[3];
                return 1 + $e->key();
            },
        ];
    }

    function c_run() {
        global $sky;
        if (!$sky->fly)
            return [];
        SKY::d('gr_start', "run=$sky->_2");
        $html = view("_glob.$sky->_2", 'def' == $sky->_2 ? $this->_def() : $this->_use('ext' == $sky->_2));
        $menu = ['defs' => $this->definitions, 'cnt' => self::$cnt];
        json([
            'html' => $html,
            'menu' => tag(view('_glob.xmenu', $menu), 'style="position:sticky; top:42px"'),
        ]);
    }
}
