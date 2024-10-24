<?php

#[\AllowDynamicProperties]
class Globals extends Usage
{
    private $all;
    private $all_lc;
    private $interfaces;
    private $traits;

    public $also_ns = [];

    static function instance() {
        static $glb;
        return $glb ?? ($glb = new self);
    }

    static function file($fn, $code) { # used by earth/mvc/m_air.php
        $glb = new Globals;
        $glb->parse($fn, $code);
        return self::$definitions;
    }

    static function def($dir, $sect = 'CLASS') {
        $glb = new Globals($dir);
        return array_keys($glb->_def()[$sect]);
    }

    function c_html_def() {
        return venus\globals::def();
    }

    function c_html_use() {
        return venus\globals::use();
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
        $html = view("_glob.$marker", 'def' == $this->_2 ? $this->_def() : $this->_use());
        if (CLI)
            return print("Unchecked definitions: " . parent::$cnt[5]);
        if ($name)
            return json(['html' => $html, 'menu' => count($this->list)]);
        json(['html' => $html, 'menu' => view('_glob.xmenu', [
            'defs' => self::$definitions,
            'cnt' => parent::$cnt,
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
            $extns = parent::extensions();
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

    function parse_html($fn, $line_start, $str) {
        //2do warm jet-cache
        //$this->parse($fn, $line_start, $str);
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
        return $this->_def();
    }

    function c_save() {
        Plan::mem_p("report_" . substr(NOW, 0, 10) . '.html', $_POST['html']);
        return $this->_def();
    }

    function c_back() {
        return $this->_def();
    }

    function push($key, $ident) {
        $place =& self::$definitions[$key];
        $place[$ident][] = implode(' ', $this->pos) . ' ';
return;

        $assign = function (&$place, $ident, $mess = '') {
            $place[$ident][] = $this->pos[0] . ' ' . $this->pos[1] . " $mess";
        };

        $place =& self::$definitions[$key];
        if (in_array($lc = strtolower($ident), PHP::$data->keywords))
            return $assign($place, $ident, 'Keyword override');
        if (in_array($lc, PHP::$data->const)) # predefined_const
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
            case 'ENUM':
                !$is_lc || enum_exists($ident, false) ? $assign($place, $ident) : $assign($place, $ident, 'Identifier in usage');
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
        //return '';
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
                            parent::$cnt[3]++;
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
                parent::$cnt[4] = array_sum(array_map('count', self::$definitions));
                $cnt = parent::$cnt;
                parent::$cnt[5] = $cnt[4] - $cnt[2] - $cnt[3];
                return 1 + $e->key();
            },
        ];
    }
}
