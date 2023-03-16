<?php
#[\AllowDynamicProperties]
class Globals extends Usage
{
    private $definitions = [
        'NAMESPACE' => [], 'INTERFACE' => [], 'TRAIT' => [], 'VAR' => [], 'FUNCTION' => [],
        'CONST' => [], 'DEFINE' => [], 'CLASS' => [], 'EVAL' => [],
    ];
    private $keywords = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue',
        'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
        'endwhile', 'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include',
        'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private', 'protected',
        'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor',
        'match', 'readonly', 'fn', 'yield', // 'yield from'
    ];
    private $predefined_const = ['__DIR__', '__FILE__', '__LINE__', '__FUNCTION__', '__CLASS__', '__METHOD__', '__NAMESPACE__', '__TRAIT__'];
    //private $special_const = ['STDIN', 'STDOUT', 'STDERR', '__COMPILER_HALT_OFFSET__'];

    private $all;
    private $all_lc;
    private $interfaces;
    private $traits;

    public $also_ns = [];

    function c_run() {
        global $sky;
        if (!CLI && !$sky->fly)
            return [];
        SKY::d('gr_start', "run=$this->_2");
        $html = view("_glob.$this->_2", 'def' == $this->_2 ? $this->_def() : $this->_use());
        if (CLI) {
            echo "Unchecked: " . parent::$cnt[5];
        } else {
            $menu = tag(view('_glob.xmenu', ['defs' => $this->definitions, 'cnt' => parent::$cnt]), 'style="position:sticky; top:42px"');
            json(['html' => $html, 'menu' => $menu]);
        }
    }

    static function ware($dir) {
        $glb = new Globals($dir);
        return array_keys($glb->_def()['CLASS']);
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

    function c_settings() {
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
        return $this->c_settings();
    }

    function c_back() {
        return $this->_def();
    }

    function push($key, $ident) {
        $assign = function (&$place, $ident, $mess = '') {
            $place[$ident][] = "$this->pos $mess";
        };

        $place =& $this->definitions[$key];
        if (in_array($lc = strtolower($ident), $this->keywords))
            return $assign($place, $ident, 'Keyword override');
        if (in_array($lc, $this->predefined_const))
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
                isset(parent::$functions[$lc]) ? $assign($place, $ident, "Internal function name used") : $assign($place, $ident);
                break;
            case 'CONST':
            case 'DEFINE':
                isset(parent::$constants[$ident]) ? $assign($place, $ident, "Internal constant name used") : $assign($place, $ident);
                break;
            case 'EVAL':
                $assign($place, $ident, 'Dangerous code');
                break;
        }
        return '';
    }

    function parse_def($fn, $line_start = 1) {
        parent::$cnt[0]++;
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
                        //'jet' != $this->ext or $this->parse_html($fn, $line_start, $str);
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
                        if (!isset($this->also_ns[$ns])) {
                            $this->push('NAMESPACE', $ns);
                            $this->also_ns[$ns] = 0;
                        } else {
                            $this->also_ns[$ns]++;
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

    function _def() {
        $extns = parent::extensions();
        $this->interfaces = array_flip(get_declared_interfaces());
        $classes = array_flip(get_declared_classes());
        $this->traits = array_flip(get_declared_traits());
        //require __DIR__ . '/internals.php'; collect maximal list? 2do: save it to DB with links to docs

        $all = parent::$constants + $this->interfaces + $classes + $this->traits;
        $this->all = array_fill_keys(array_keys($all), 0);
        $this->all_lc = array_change_key_case($this->all);

        $this->walk_files([$this, 'parse_def']);
        if ('.' != $this->path)
            return $this->definitions;
        foreach ($this->definitions as &$definition)
            uksort($definition, 'strcasecmp');

        $nap = Plan::mem_rq('report.nap');

        return [
            'extns' => $extns,
            'ext_used' => explode(' ', SKY::i('gr_extns')),
            'defs' => $this->definitions,
            'e_idents' => [
                'max_i' => -1, // infinite
                'row_c' => function($in, $evar = false) use ($nap) {
                    static $ary, $gt, $id, $num, $err_msg, $def_prev = '';
                    if ($evar) {
                        $gt = count($ary = $in[0]) > 1;
                        $id = $in[1];
                        if (isset($nap[$id]))
                            parent::$cnt[3]++;
                        list ($def, $ident) = explode('.', $id);
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
                    $x = $c[2] ? $c[2] : ($gt ? $err_msg : '');
                    if ($x && $x[0] == '-')
                         $x = substr($x, 1);
                    $ok = isset($nap[$id]);
                    if (!$gt = $gt || $c[2])
                        parent::$cnt[2]++;
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
                parent::$cnt[4] = array_sum(array_map('count', $this->definitions));
                $cnt = parent::$cnt;
                parent::$cnt[5] = $cnt[4] - $cnt[2] - $cnt[3];
                return 1 + $e->key();
            },
        ];
    }
}
