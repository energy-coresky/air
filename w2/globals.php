<?php

class Globals
{
    private $exts = ['php'];//, 'jet'
    private $ext;
    private $dirs_skip = ['_arch', 'vendor', 'main/lng', 'var/upload'];
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

    private $keywords = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue',
        'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
        'endwhile', 'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include',
        'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private', 'protected',
        'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor',
        'match', 'readonly', 'fn', 'yield', // 'yield from'
    ];
    private $predefined_constants = ['__class__', '__dir__', '__file__', '__function__', '__line__', '__method__', '__namespace__', '__trait__'];

    private $functions;
    private $constants = [];

    private $interfaces;
    private $classes;
    private $traits;
    private $vars;

    private $all;
    private $all_lc;

    function __construct() {
    }

    function parse_html($fn, $line_start, $str) {
        //2do warm jet-cache
        //$this->parse($fn, $line_start, $str);
    }

    function parse($fn, $line_start = 1, $str = false) {
        $php = $str ? $str : file_get_contents($fn);
        $line = $line_start;
        $braces = $glob_list = 0;
        $place = 'GLOB';
        $vars = [];
        $out = $p1 = $p2 = '';
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
                        ++$braces;
                        break;
                    case T_EVAL:
                        $this->push('EVAL', $str);
                        break;
                    case T_GLOBAL:
                        $glob_list = 1;
                        break;
                    case T_STRING: case T_NAME_QUALIFIED:
                        if (in_array($p1, [T_CLASS, T_INTERFACE, T_TRAIT, T_NAMESPACE])) {
                            $this->push($place = substr(token_name($p1), 2), $str);
                        } elseif ('GLOB' == $place) {
                            if (T_FUNCTION == $p1)
                                $this->push($place = 'FUNCTION', $str);
                            if (T_CONST == $p1)
                                $this->push('CONST', $str);
                        }
                        $id = token_name($id) . " $str";
                        break;
                    case T_CONSTANT_ENCAPSED_STRING:
                        if ('(' == $p1 && 'T_STRING define' == $p2)
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
                case '{':
                    ++$braces;
                    break;
                case '}':
                    if (!--$braces) {
                        $place = 'GLOB';
                        $vars = [];
                    }
                    break;
                case ';':
                    $glob_list = 0;    
                    break;
                case '=': # =& also work
                    $rule = T_DOUBLE_COLON != $p2 && is_array($p1) && T_VARIABLE == $p1[0];
                    if ($rule && ('GLOB' == $place || in_array($p1[1], $vars)))
                        $this->push('VAR', $p1[1]);
                    break;
            }

            $p2 = $p1;
            $p1 = $id;
#            $out .= (is_array($id) ? implode(' ', $id) : (is_int($id) ? token_name($id) : $id))." [$braces][$line]\n";
        }
#'main/z.php'!=$fn or print "\n\n$out\n\n";
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
                    $assign($place, $ident, 'Do not use $GLOBALS');
                } else {
                    $assign($place, $ident);
                }
                break;
            case 'FUNCTION':
                $is_lc ? $assign($place, $ident, "Identifier in usage") : $assign($place, $ident);
                break;
            case 'CONST':
                $is_lc ? $assign($place, $ident, "Identifier in usage") : $assign($place, $ident);
                break;
            case 'DEFINE':
                $is_lc ? $assign($place, $ident, "Identifier in usage") : $assign($place, $ident);
                break;
            case 'EVAL':
                $assign($place, $ident, 'Dangerous code');
                break;
        }

        $this->all_lc[$lc] = $is_lc ? 1 + $this->all_lc[$lc] : 1;
        return '';
    }

    function c_dirs() {
        defined('T_NAME_QUALIFIED') or define('T_NAME_QUALIFIED', 314);
        $functions = get_defined_functions();
        $this->functions = array_flip($functions['internal']);
        foreach (get_defined_constants(true) as $k => $v) {
            if ('user' == $k)
                continue;
            $this->constants += $v;
        }
        //require __DIR__ . '/internals.php'; collect maximal list? 2do: save it to DB with links to docs

        $this->interfaces = array_flip(get_declared_interfaces());
        $this->classes = array_flip(get_declared_classes());
        $this->traits = array_flip(get_declared_traits());
        $this->vars = []; //array_flip(array_keys(get_defined_vars())); // [functions] => 1    [v] => 1    [k] => 1

        $all = $this->functions + $this->constants + $this->interfaces + $this->classes + $this->traits;
        $this->all = array_fill_keys(array_keys($all), 0);
        $this->all_lc = array_change_key_case($this->all);

        $dirs = Rare::walk_dirs('.', $this->dirs_skip);
        if ('c:/web/air' == DIR_S)
            $dirs = array_merge($dirs, Rare::walk_dirs(DIR_S . '/w2'));

        foreach ($dirs as $dir) {
            foreach (Rare::list_path($dir, 'is_file') as $fn) {
                list (,$this->ext) = explode('.', $fn) + [1 => ''];
                if (in_array($this->ext, $this->exts))
                    $this->parse($fn);
            }
        }
        if ('c:/web/air' == DIR_S) {
            $this->parse(DIR_S . '/heaven.php');
            $this->parse(DIR_S . '/sky.php');
        }

        foreach ($this->definitions as &$definition)
      #      natcasesort($definition);
            ksort($definition);

        return [
            'defs' => $this->definitions,
            'e_idents' => [
                'row_c' => function($in, $evar = false) {
                    static $ary, $gt;
                    if ($evar)
                        $gt = count($ary = $in) > 1;
                    if ($evar || !$ary)
                        return false;
                    $c = explode(' ', array_shift($ary), 3);
                    return [
                        'class' => $c[2] || $gt ? 'redy' : 'norm',
                        'pos' => "$c[0]^$c[1]",
                        'desc' => $gt ? 'Duplicated definition' : ($c[2] ? $c[2] : ''),
                    ];
                },
            ],
			'modules' => implode(' ', get_loaded_extensions()),
        ];
    }

    function c_save() {
        echo file_put_contents('var/report.html', $_POST['html']) ? 'Saved' : 'Error';
    }
}
