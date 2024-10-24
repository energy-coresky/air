<?php

class Usage
{
    protected static $functions = []; // 2do: detect SKY::$vars collisions
    protected static $constants = [];
    protected static $classes = [];
    protected static $definitions;

    protected $path;
    protected $json = [];
    protected $pos;

    static $uses = [];
    static $cnt = [0, 0, 0, 0, 0, 0, 'tot' => [0, 0, 0], 8 => 0];

    public $_2;
    public $parts = [3 => 'Interfaces', 'Traits', 'Enums', 1 => 'Functions', 2 => 'Constants', 0 => 'Classes'];
    public $name = '';
    public $list = [];
    public $act = null;

    function __construct($path = '.') {
        self::$definitions = (PHP::$data ?: PHP::ini_once())->definitions;
        global $sky, $argv;
        $this->path = $path;
        $sky->k_gr = $this;
        //$this->_2 = CLI ? ($argv[2] ?? '') : $sky->_2;
        $this->_2 = CLI ? ('def') : $sky->_2; # 2do cli user code usage
    }

    function parse_use($fn) {
        $use = function ($x, &$ary, &$name, $y = 0) {
            if ($abs = '\\' === $name[0]) {
                $name = substr($name, 1);
            } elseif (strpos($name, '\\') && $this->ns) {
                [$pfx, $name] = explode('\\', $name, 2);
            }
            if ($ok = !$abs && isset($this->use[$x][$pfx ?? $name]))
                $name = isset($pfx) ? $this->use[$x][$pfx] . "\\$name" : $this->use[$x][$name];
            $extn = $ary[2 == $x ? $name : strtolower($name)] ?? '';
            $abs or $ok or '' !== $extn or $name = $this->ns . $name;
            $p =& self::$uses[$extn][$extn || !$y ? $x : $y];
            if ($this->name) {
                if ($this->name == $name)
                    $this->list[] = $this->pos;
            } elseif (isset($p[$name])) {
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
        $code = file_get_contents($fn);
        $line = $ok = 1;
        $this->use = [[], [], []]; # cls fun const
        $this->ns = $p1 = $name = $pos = $quot = '';
        $clist = [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST];
        $slist = ['self', 'parent', 'static'];
        foreach (token_get_all(unl($code)) as $token) {
            $id = $token;
            $this->pos = [$fn, $line];
            if (is_array($token)) {
                [$id, $str] = $token;
                $line += substr_count($str, "\n");
                switch ($id) {
                    case T_STRING: case T_NS_SEPARATOR:
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
                    } elseif ('USE' === $pos) {
                        if ($curly) {
                            $use(0, self::$classes, $name, 4); # traits via comma
                        } else {
                            $ary = explode('\\', $name);
                            $this->use[$usn][end($ary)] = $name; # use ns name
                            $name = '';
                        }
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
$name && $use(0, self::$classes, $name, 4); # traits
                        } else {
                            $ary = explode('\\', $name);
                            $this->use[$usn][end($ary)] = $name; # use ns name
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
        foreach ($extns as $one) {
            $rn = new ReflectionExtension($one);
            $list = $rn->getClassNames();
            self::$classes += array_change_key_case(array_combine($list, array_pad([], count($list), $one)));
            self::$functions += array_change_key_case(array_map(fn() => $one, $rn->getFunctions()));
            if (isset($const[$one])) {
                if ('Core' === $one)
                    $const[$one] += array_flip(PHP::$data->spec); # special_const like STDIN
                self::$constants += array_map(fn() => $one, $const[$one]);
            }
        }
        return $extns;
    }

    function exclude_dirs($dir = false, $act = false) {
        static $dirs;
        if (null === $dirs) {
            $tmemo = sqlf('+select tmemo from $_memory where id=11');
            SKY::ghost('i', $tmemo, 'update $_memory set dt=$now, tmemo=%s where id=11');
            false === $act or SKY::i('gr_act', $act);
            null !== $this->act or $this->act = SKY::i('gr_act');
            $dirs = explode(' ', SKY::i('gr_dirs' . $this->act));
        }
        if ($dir) {
            ($offset = array_search($dir, $dirs)) !== false ? array_splice($dirs, $offset, 1) : ($dirs[] = $dir);
            SKY::i('gr_dirs' . $this->act, implode(' ', $dirs));
        }
        return $dirs;
    }

    function dirs($is_proc, &$dirs, &$exclude = null) {
        $exclude = $this->exclude_dirs();
        $ary = $is_proc ? $exclude : [];
        $len = strlen($root = realpath(DIR));
        $c1 = count($dirs = Rare::walk_dirs($this->path, $ary));
        if ('.' != $this->path)
            return;
        if ($r1 = substr(realpath(DIR_S), 0, $len) != $root)
            $dirs = array_merge($dirs, Rare::walk_dirs(DIR_S, $ary));
        $c2 = count($dirs);
        $sw = SKY::d('second_wares');
        $d2 = [];
        foreach (SKY::$plans as $plan => $cfg) {
            if (substr(realpath($cfg['app']['path']), 0, $len) == $root
                || !SKY::i('gr_sdw') && in_array($cfg['app']['type'], ['dev', 'pr-dev'])) continue;
            $d2 = array_merge($d2, Rare::walk_dirs("$sw/$plan", $ary));
        }
        $dirs = array_merge($dirs, $d2);
        return [$c1 - 1, !$r1, $c2 - 1, !$d2];
    }

    function walk_files($proc) {
        $this->dirs(true, $dirs);
        self::$cnt[1] = count($dirs);
        $ts = microtime(true);
        $imemo = 0;
        foreach ($dirs as $dir) {
            foreach (Rare::list_path($dir, 'is_file') as $fn) {
                $ary = explode('.', $fn);
                $file_ext = end($ary);
                if (in_array($file_ext, ['php']))
                    call_user_func($proc, $fn);
            }
            self::$cnt[8]++;
            if (microtime(true) - $ts > 0.1) {
                sqlf('update $_memory set imemo=%d, cmemo=%d where id=11', $imemo = self::$cnt[8], self::$cnt[1]);
                $ts = microtime(true);
            }
        }
        if ($imemo != self::$cnt[8])
            sqlf('update $_memory set imemo=%d, cmemo=%d where id=11', self::$cnt[8], self::$cnt[1]);
        if ('.' != $this->path || !($files = SKY::i('gr_files')))
            return;
        foreach (explode(' ', $files) as $fn)
            is_file($fn) && call_user_func($proc, $fn);
    }

    function _use() {// see vendor/sebastian/recursion-context/tests/ContextTest.php^101 Exception::class
        $extns = self::extensions();
        self::$uses = array_combine($extns, array_pad([], count($extns), [[], [], []])); # classes, funcs, consts
        self::$uses += ['' => [[], [], [], [], [], []]]; # classes, funcs, consts, interfaces, traits, enums
        $this->walk_files([$this, 'parse']);// parse_use
        $nap = Plan::mem_rq('report.nap');

        return [
            'show_emp' => SKY::i('gr_snu'),
            'e_usage' => [
                'max_i' => -1, // infinite
                'row_c' => function($ext, $evar = false) use ($nap) {
                    static $p, $i, $j = 0, $defs = 0, $ary = [];
                    if ($evar) {
                        is_int($i = $ext) ? ($ext = '') : ($i = 0);
                        $p = self::$uses[$ext];
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
                    self::$cnt[4] = array_sum(array_map('count', self::$uses['']));
                    self::$cnt[5] = self::$cnt[4] - $cnt[2] - $cnt[3];
                }
                return 1 + $e->key();
            },
            'sup' => function($ext, $_used) {
                static $nmand;
                if (in_array($ext, Root::$core))
                    return tag('core', 'style="color:red"', 'sup');
                if (null === $nmand)
                    $nmand = explode(' ', SKY::i('gr_nmand'));
                if (in_array($ext, $nmand))
                    return tag('not mandatory', 'style="color:magenta"', 'sup');
                return $_used ? tag('used', 'style="color:#777"', 'sup') : '';
            },
        ];
    }

    function ns($ns) {
        if ($this->name == $ns) {
            $this->list[] = $this->pos;
        } elseif (!isset($this->also_ns[$ns])) {
            $this->d_add('NAMESPACE', $ns);
            $this->also_ns[$ns] = 0;
        } else {
            $this->also_ns[$ns]++;
        }
    }

    function d_add($key, $ident) {
        $place =& self::$definitions[$key];
        $place[$ident][] = implode(' ', $this->pos).' ';
    }

    //function u_add($x, &$ary, &$name, $y = 0) {
    function u_add($y, $php, $prev) {
        $name = $php->get_real($y, $exact);
        if (T_CLASS == $y->rank) {
            $if = T_IMPLEMENTS == $prev;
            $i = $if || T_USE == $prev ? ($if ? 3 : 4) : 0; # 5 - enums
            $j = 0;
            $p0 =& self::$classes;
        } elseif (T_CONST == $y->rank) {
            $j = $i = 2;
            $p0 =& self::$constants;
        } else {
            $j = $i = 1;
            $p0 =& self::$functions;
        }
        $extn = $p0[2 == $i ? $name : strtolower($name)] ?? '';

        $p =& self::$uses[$extn][$extn ? $j : $i];
        if ($this->name) { # show code snapshots
            if ($this->name == $name)
                $this->list[] = $this->pos;
        } elseif (isset($p[$name])) { # use again
            $p[$name][1]++;
            if ($this->pos[0] != $p[$name][0][0])
                $p[$name][2]++;
            $p[$name][0] = $this->pos;
        } else { # new usage
            $p[$name] = [$this->pos, 0, 0];
        }
    }

    function parse($fn, $code = false) {
        self::$cnt[0]++;
        $php = new PHP($code ?: file_get_contents($fn));
        $skip_rank = fn($rank) => in_array($rank, ['NAMESPACE', 'CLASS-CONST', 'METHOD']);
        $glob_list = $define = false;
        $vars = [];
        foreach ($php->rank() as $prev => $y) {
            $this->pos = [$fn, $y->line];
            $ns = '' === $php->ns ? '' : "$php->ns\\";
            switch ($y->tok) {
                case T_EVAL:
                    static $n = 1;
                    $this->d_add('EVAL', $n++ . ".$fn $y->line");
                    break;
                case T_GLOBAL:
                    $glob_list = true;
                    $vars = [];
                    break;
                case T_STRING:
                    if ($y->is_def) {
                        $skip_rank($y->rank) or $this->d_add($y->rank, $ns . $y->str);
                    } elseif (in_array($y->rank, [T_CONST, T_FUNCTION, T_CLASS])) {
                        $this->u_add($y, $php, $prev);
                    }
                    break;
                case T_VARIABLE:
                    if ($glob_list)
                        $vars[] = $y->str;
                    $rule = '$GLOBALS' == $y->str || '=' === $y->next && (/*!$ns &&*/ !$php->pos || in_array($y->str, $vars));
                    if ($rule && T_DOUBLE_COLON != $prev) # =& also work
                        $this->d_add('VAR', $y->str);
                    break;
                case 0:
                    if (';' == $y->str)
                        $glob_list = false;
                    if ($define && T_CONSTANT_ENCAPSED_STRING === $y->next) {
                        $this->pos[1] = $define;
                        $this->d_add('DEFINE', substr($y->new->str, 1, -1));
                    }
                    break;
            }
            $define = T_FUNCTION === $y->rank && 'define' == $y->str ? $y->line : false;
            //$this->u_add($y, $php); start new NS
            if (T_NAMESPACE == $prev)
                $this->ns($php->ns);
        }
        //$this->u_add($y, $php); file finished
    }
}
