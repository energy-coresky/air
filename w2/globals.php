<?php

class Globals
{
    private static $functions = []; // 2do: detect SKY::$vars collisions
    private static $constants = [];
    private static $classes = [];
    private static $definitions;

    private $all;
    private $all_lc;
    private $interfaces;
    private $traits;
    private $path;
    private $json = [];
    private $pos;

    static $uses = [];
    static $cnt = [0, 0, 0, 0, 0, 0, 'tot' => [0, 0, 0], 8 => 0];

    public $_2;
    public $parts = [3 => 'Interfaces', 'Traits', 'Enums', 1 => 'Functions', 2 => 'Constants', 0 => 'Classes'];
    public $name = '';
    public $list = [];
    public $act = null;
    public $also_ns = [];

    function __construct($path = '.') {
        self::$definitions = (PHP::$data ?: PHP::ini_once())->definitions;
        global $sky, $argv;
        $this->path = $path;
        $sky->k_gr = $this;
        //$this->_2 = CLI ? ($argv[2] ?? '') : $sky->_2;
        $this->_2 = CLI ? ('def') : $sky->_2; # 2do cli user code usage
    }

    static function instance() {
        static $glb;
        return $glb ?? ($glb = new self);
    }

    static function file($fn, $code) { # used by earth/mvc/m_air.php
        $glb = new self;
        $glb->parse($fn, $code);
        return self::$definitions;
    }

    static function def($dir, $sect = 'CLASS') {
        $glb = new self($dir);
        return array_keys($glb->view_def()[$sect]);
    }

    function c_run($name = '') {
        global $sky;
        $this->name = $name;
        if (!CLI && !$sky->fly)
            return ['c3' => $this->_2 == 'ext' && !$name];
        SKY::d('gr_start', "run=$this->_2");
        if (CLI)
            $this->act = '';
        $marker = $name ? 'name' : $this->_2;
        $html = view("_glob.$marker", 'def' == $this->_2 ? $this->view_def() : $this->view_use());
        if (CLI)
            return print("Unchecked definitions: " . self::$cnt[5]);
        if ($name)
            return json(['html' => $html, 'menu' => count($this->list)]);
        json(['html' => $html, 'menu' => view('_glob.xmenu', [
            'defs' => self::$definitions,
            'cnt' => self::$cnt,
            'mand' => count($mand = array_diff(explode(' ', SKY::i('gr_extns')), explode(' ', SKY::i('gr_nmand')), Root::$core)),
            'color' => function ($ext) use (&$mand) {
                return in_array($ext, $mand) ? 'col0' : 'col9';
            },
        ])]);
    }

    function c_settings() {
        SKY::d('gr_start', 'settings');
        return [
            'ary' => $this->dirs(false, $dirs, $exclude),
            'dirs' => $dirs,
            'continue' => function ($dir) use ($exclude) {
                static $red = false;
                if (in_array($dir, $exclude)) {
                    $red = $dir;
                    return 0;
                }
                return $red && $red == substr($dir, 0, strlen($red));
            },
            'used' => array_diff(explode(' ', SKY::i('gr_extns')), explode(' ', SKY::i('gr_nmand')), Root::$core),
        ];
    }

    function c_html_def() {
        return venus\globals::def();
    }

    function c_html_use() {
        return venus\globals::use();
    }

    function c_chk() {
        $this->exclude_dirs();
        SKY::i('gr_' . $this->_2, 'true' == $_GET[$this->_2] ? 1 : 0);
        return true;
    }

    function c_files() {
        $this->exclude_dirs();
        SKY::i('gr_files', preg_replace("/\s+/s", ' ', $_POST['s']));
        return true;
    }

    function c_setup() {
        $this->exclude_dirs($_POST['dir'] ?? false, $_POST['act'] ?? false);
        return $this->c_settings();
    }

    function c_nmand($extns) {
        if ($x = !$extns) {
            $extns = self::extensions();
            $this->exclude_dirs();
            $s = $_POST['s'];
        }
        $nmand = explode(' ', SKY::i('gr_nmand'));
        if ($x) {
            ($offset = array_search($s, $nmand)) !== false ? array_splice($nmand, $offset, 1) : ($nmand[] = $s);
            SKY::i('gr_nmand', implode(' ', $nmand));
        }
        return [
            'extns' => $extns,
            'cnt_used' => count($used = explode(' ', SKY::i('gr_extns'))),
            'class' => function($ext, &$cnt) use (&$used, &$nmand) {
                isset($cnt) or $cnt = 0;
                if (in_array($ext, Root::$core))
                    return 'gr-core';
                if (in_array($ext, $nmand))
                    return 'gr-nmand';
                if (!in_array($ext, $used))
                    return '';
                $cnt++;
                return 'gr-used';
            },
        ];
    }

    function c_progress() {
        SKY::$debug = 0;
        [$val, $max] = sqlf('-select imemo, cmemo from $_memory where id=11');
        json(['max' => (int)$max, 'val' => (int)$val]);
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
        Plan::mem_p('report.nap', Boot::auto($nap));
        return $this->view_def();
    }

    function c_save() {
        Plan::mem_p("report_" . substr(NOW, 0, 10) . '.html', $_POST['html']);
        return $this->view_def();
    }

    function c_back() {
        return $this->view_def();
    }

    function push($key, $ident) {
        /*
            case 'NAMESPACE':
            case 'INTERFACE': case 'TRAIT': case 'CLASS': case 'ENUM':
            case 'FUNCTION':
            case 'CONST':
            case 'DEFINE':
            case 'VAR':       case 'EVAL':*/
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

    function view_def() {
        $extns = self::extensions();
        $this->interfaces = array_flip(get_declared_interfaces());
        $classes = array_flip(get_declared_classes());
        $this->traits = array_flip(get_declared_traits());
        //2do: = Earth::php_names();

        $all = self::$constants + $this->interfaces + $classes + $this->traits;
        $this->all = array_fill_keys(array_keys($all), 0);
        $this->all_lc = array_change_key_case($this->all);

        $this->walk_files([$this, 'parse']);
        if ('.' != $this->path)
            return self::$definitions;
        foreach (self::$definitions as &$definition)
            uksort($definition, 'strcasecmp');

        $nap = Plan::mem_rq('report.nap');

        return $this->c_nmand($extns) + [
            'defs' => self::$definitions,
            'e_idents' => [
                'max_i' => -1, // infinite
                'row_c' => function($in, $evar = false) use ($nap) {
                    static $ary, $gt, $id, $num, $err_msg, $def_prev = '';
                    if ($evar) {
                        $gt = count($ary = $in[0]) > 1;
                        $id = $in[1];
                        if (isset($nap[$id]))
                            self::$cnt[3]++;
                        [$def, $ident] = explode('.', $id);
                        if (!in_array($def, ['NAMESPACE', 'VAR', 'EVAL'])) {
                            $z = 'FUNCTION' == $def ? 1 : (in_array($def, ['CONST', 'DEFINE']) ? 2 : 0);
                            $this->json[$z][] = strtolower($ident);
                        }
                        if ($def_prev != $def)
                            $num = 1;
                        $def_prev = $def;
                        $err_msg = 'VAR' == $def ? 'Twice assigning' : 'Duplicated definition';
                    }
                    if ($evar || !$ary)
                        return false;

                    $c = explode(' ', array_shift($ary), 3);
                    $x = $c[2] ?: ($gt ? $err_msg : '');
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
            'after' => function($e, &$cnt) {
                Plan::mem_p('gr_def.json', json_encode($this->json, JSON_PRETTY_PRINT));
                self::$cnt[4] = array_sum(array_map('count', self::$definitions));
                $cnt = self::$cnt;
                self::$cnt[5] = $cnt[4] - $cnt[2] - $cnt[3];
                return 1 + $e->key();
            },
        ];
    }

    function view_use() {
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
            $this->add_d('NAMESPACE', $ns);
            $this->also_ns[$ns] = 0;
        } else {
            $this->also_ns[$ns]++;
        }
    }

    function add_d($key, $ident) {
        $place =& self::$definitions[$key];
        $place[$ident][] = implode(' ', $this->pos).' ';
    }

    //function add_u($x, &$ary, &$name, $y = 0) {
    function add_u($y, $php, $prev) {
        $name = $php->get_real($y, $ns_name);
        if (T_CLASS == $y->rank) {
            $if = T_IMPLEMENTS == $prev;
            $i = $if || T_USE == $prev ? ($if ? 3 : 4) : 0; # 5 - enums
            $j = 0; // T_NEW exact class
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
        //if (PHP::$warning)
            
        $skip_rank = fn($rank) => in_array($rank, ['NAMESPACE', 'CLASS-CONST', 'METHOD']);
        $glob_list = $define = false;
        $vars = [];
        foreach ($php->rank() as $prev => $y) {
            $this->pos = [$fn, $y->line];
            $ns = '' === $php->ns ? '' : "$php->ns\\";
            switch ($y->tok) {
                case T_EVAL:
                    static $n = 1;
                    $this->add_d('EVAL', $n++ . ".$fn $y->line");
                    break;
                case T_GLOBAL:
                    $glob_list = true;
                    $vars = [];
                    break;
                case T_STRING:
                    if ($y->is_def) {
                        $skip_rank($y->rank) or $this->add_d($y->rank, $ns . $y->str);
                    } elseif (in_array($y->rank, [T_CONST, T_FUNCTION, T_CLASS])) {
                        $this->add_u($y, $php, $prev);
                    }
                    break;
                case T_VARIABLE:
                    if ($glob_list)
                        $vars[] = $y->str;
                    $rule = '$GLOBALS' == $y->str || '=' === $y->next && (/*!$ns &&*/ !$php->pos || in_array($y->str, $vars));
                    if ($rule && T_DOUBLE_COLON != $prev) # =& also work
                        $this->add_d('VAR', $y->str);
                    break;
                case 0:
                    if (';' == $y->str)
                        $glob_list = false;
                    if ($define && T_CONSTANT_ENCAPSED_STRING === $y->next) {
                        $this->pos[1] = $define;
                        $this->add_d('DEFINE', substr($y->new->str, 1, -1));
                    }
                    break;
            }
            $define = T_FUNCTION === $y->rank && 'define' == $y->str ? $y->line : false;
            //$this->add_u($y, $php); start new NS
            if (T_NAMESPACE == $prev)
                $this->ns($php->ns);
        }
        //$this->add_u($y, $php); file finished
    }
}
