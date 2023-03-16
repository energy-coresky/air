<?php

class Usage
{
    protected static $functions = [];
    protected static $constants = [];
    protected static $classes = [];

    protected $path;
    protected $json = [];

    static $extns = [];
    static $cnt = [0, 0, 0, 0, 0, 0, 'tot' => [0, 0, 0]];

    public $_2;
    public $parts = [3 => 'Interfaces', 'Traits', 1 => 'Functions', 2 => 'Constants', 0 => 'Classes'];

    function __construct($path = '.') {
        global $sky, $argv;
        defined('T_NAME_QUALIFIED') or define('T_NAME_QUALIFIED', 314);
        $this->path = $path;
        $sky->k_gr = $this;
        $this->_2 = CLI ? $argv[2] : $sky->_2;
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
            $p =& self::$extns[$extn][$extn || !$y ? $x : $y];
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

    function walk_files($proc) {
        $dirs = Rare::walk_dirs($this->path, $this->exclude_dirs());
        if ($flag = DIR_M != DIR_S && '.' == $this->path)
            $dirs = array_merge($dirs, Rare::walk_dirs(DIR_S . '/w2'));
        foreach ($dirs as $dir) {
            self::$cnt[1]++;
            foreach (Rare::list_path($dir, 'is_file') as $fn) {
                $ary = explode('.', $fn);
                $file_ext = end($ary);
                if (in_array($file_ext, ['php']))
                    call_user_func($proc, $fn);
            }
        }
        if ($flag) {
            call_user_func($proc, DIR_S . '/heaven.php');
            call_user_func($proc, DIR_S . '/sky.php');
        }
    }

    function _use() {// see vendor/sebastian/recursion-context/tests/ContextTest.php^101 Exception::class
        $extns = self::extensions();
        self::$extns = array_combine($extns, array_pad([], count($extns), [[], [], []])); # classes, funcs, consts
        self::$extns += ['' => [[], [], [], [], []]]; # classes, funcs, consts, interfaces, traits
        $this->walk_files([$this, 'parse_use']);
        $nap = Plan::mem_rq('report.nap');

        return [
            'show_emp' => 0,
            'e_usage' => [
                'max_i' => -1, // infinite
                'row_c' => function($ext, $evar = false) use ($nap) {
                    static $p, $i, $j = 0, $defs = 0, $ary = [];
                    if ($evar) {
                        is_int($i = $ext) ? ($ext = '') : ($i = 0);
                        $p = self::$extns[$ext];
                        '' !== $ext or 0 !== $defs or $defs = json_decode(Plan::mem_gq('gr_def.json') ?: '[]');
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
                        self::$cnt['tot'][0] += ($c0 = count($p[0]));
                        self::$cnt['tot'][1] += ($c1 = count($p[1]));
                        self::$cnt['tot'][2] += ($c2 = count($p[2]));
                        self::$cnt['ext'][$ext] = "$c0/$c1/$c2";
                        return false;
                    }
                    $name = key($ary);
                    $np = array_shift($ary);
                    if ($user) {
                        $lc = strtolower($name);
                        $this->json[$i][] = 2 == $i ? $name : $lc;
                        if (!$defs || !in_array($lc, $defs[$i > 2 ? 0 : $i])) {
                            $err = 'Definition not found';
                            self::$cnt[3]++;
                        } else {
                            self::$cnt[2]++;
                        }
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
            'after' => function($e, &$cnt) {
                $cnt = self::$cnt;
                if ('ext' == $this->_2) {
                    SKY::i('gr_extns', implode(' ', array_keys(self::$cnt['ext'])));
                } else {
                    ksort($this->json);
                    Plan::mem_p('gr_use.json', json_encode($this->json, JSON_PRETTY_PRINT));
                    self::$cnt[4] = array_sum(array_map('count', self::$extns['']));
                    self::$cnt[5] = self::$cnt[4] - $cnt[2] - $cnt[3];
                }
                return 1 + $e->key();
            },
        ];
    }
}
