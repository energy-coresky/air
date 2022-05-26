<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class xForm
{
    public $cfg;
    public $ary;

    function __construct($cfg, $ary) {
        isset($cfg['opt']) or $cfg['opt'] = 0; # default option
        $this->cfg = $cfg;
        $this->ary = $ary;
    }
}

class Form
{
    private $dm = [
        '+' => 'this field cannot be empty',
        '-' => 'enter valid E-mail',
        '~' => 'enter valid phone number',
        '.' => 'enter login',
        '*' => 'enter password',
        '#' => 'enter numbers', #   '/' => 'RegExp', # '|' => 'Function', key 0 => MODIFY KEYS
    ];
    private $re = [
        '-' => RE_EMAIL,
        '~' => RE_PHONE,
        '.' => RE_LOGIN,
        '*' => RE_PASSW,
        '#' => '/^\d+$/',
    ];
    private $tag = [
        'method' => 'post',
        'enctype' => 'multipart/form-data',
        'id' => 'f1',
    ];
    const OPT_TABLE = 1;
    const OPT_DIV   = 2;

    private $form;
    private $post = [];
    private $ary = []; # collect filter's WHERE rules
    public $row = [];
    private $js = [];
    private $mk = false;
    private $validation = false;
    private $spec = false;
    private $cv_hash = []; # cv - conditional validation
    private $cv_flag = false;
    static $hidden = 0;
    static $cls_repeat = '';
    private $repeat = [];

    static function A($row, $form) { # no validation
        $me = new Form($form);
        return $me->tag($me->draw_form($row, $me->mk));
    }

    static function X(...$in) {
        $cfg = count($in) > 1 ? array_shift($in) : [];
        array_walk($in, function(&$ary) {
            is_array($ary) or $ary = [$ary];
        });
        return new xForm($cfg, $in);
    }

    function def($row = [], $as_string = true) { # default processing
        $html = $this->draw_form($row, $this->mk); # this one should be running first then ->js()
        return $as_string ? $this->js() . $this->tag($html) : [$this->js(), $this->tag($html)];
    }

    function validate() {
        $this->validation = true;
        $this->cv_prepare($this->js, $this->mk);
        $this->walk($this->form);
        return $this->post;
    }

    function __construct($form, $tag = []) {
        global $user;
        $form += ['_csrf' => [$user->v_csrf, 'hidden']];
        $this->set_new($form, $this->mk);
        $this->tag = $tag + $this->tag;
    }

    function js() {
        $s = '{' . array_join($this->js, function($k, $v) {
            $v = is_string($v) ? "'$v'" : sprintf("['$v[0]', %s]", '/' == @$v[1][0] ? $v[1] : "'$v[1]'");
            return "'$k':$v";
        }, ",\n") . '}';
        $repeat = $this->repeat_js();
        $js = "$(function() {\nsky.f.set('#{$this->tag['id']}', $s, $repeat);\n});";
        return tag('', "id=\"{$this->tag['id']}-message\"") . js($js);
    }

    function tag($html) {
        $etc = array_join($this->tag, function($k, $v) {
            return !$k ? trim($v) : $k . '="' . $v . '"';
        }, ' ');
        return tag($html, $etc, 'form');
    }

    function input($type, $val, $etc = '') {
        return sprintf('<input type="%s" value="%s"%s /> ', $type, html($val), $etc ? ' ' . trim($etc) : '');
    }

    private function set_new($form, &$mk) {
        $_mk = [];
        if (isset($form[-1][0])) {
            $mk = $_mk = $form[-1][0];
            unset($form[-1][0]);
        }
        if (isset($form[-1])) {
            $this->js = $form[-1] + $this->js;
            if ($this->validation)
                $this->cv_prepare($form[-1], $_mk);
            unset($form[-1]);
        }
        $this->form = $form;
    }

    private function cv_prepare($ary, $mk) {
        foreach ($ary as $k => $v) {
            if (is_array($v) && '/' != $k[0]) {
                $k = $this->modify_key($mk, $k);
                if ($_POST[$k]) continue;
                $this->cv_hash[$v[0]] = $v[1];
            }
        }
    }
    
    private function modify_key($mk, $key) {
        if ($mk && $key) {
            $just_add = !isset($mk[1]);
            return $just_add ? $mk[0] . $key : preg_replace($mk[0], $mk[1], $key);
        }
        return $key;
    }

    private function proc_char($char, $name, $mk) {
        $name = $this->modify_key($mk, $n = $name);

        if ($this->validation) {
            if ('[]' == substr($name, -2)) {
                $vals = $_POST[$_n = substr($name, 0, -2)];
                $this->post[$_n] = $vals;
            } else {
                $vals = [$_POST[$name]];
                $this->post[$name] = $_POST[$name];
            }
            if (!$this->cv_flag && isset($this->cv_hash[$name]))
                $this->cv_flag = $name;
            foreach ($vals as $v) {
                if ($this->cv_flag)
                    continue;
                if ('+' == $char) {
                    if ('' === trim($v))
                        throw new Exception(404);
                    continue;
                }
                $re = '/' == $char ? $this->js[$n][1] : $this->re[$char];
                if (!preg_match($re, $v))
                    throw new Exception(404);
            }
            if ($this->cv_flag && $name == $this->cv_hash[$this->cv_flag])
                $this->cv_flag = false;
        } elseif ('/' == $char) {
            if ($mk) {
                $this->js[$name] = $this->js[$n];
                unset($this->js[$n]);
            }
        } else {
            if (!isset($this->js[$name]))
                $this->js[$name] = $char;
            if (!is_array(@$this->js[$char])) { # push default message
                $mess = isset($this->js[$char]) ? $this->js[$char] : $this->dm[$char];
                $this->js[$char] = [$mess, '+' == $char ? '' : $this->re[$char]];
            }
        }
    }

    private function proc_x($xf, $mk = []) {
        $cnt = count($ary = $xf->ary);
        $is_tbl = $xf->cfg['opt'] & self::OPT_TABLE || $cnt > 2;
        $is_div = $xf->cfg['opt'] & self::OPT_DIV;

        foreach ($ary as $i => &$one) {
            $_mk = $mk;
            $this->set_new($one, $_mk);
            $one = $this->draw_form([], $_mk, false);
            if (isset($xf->cfg[$i])) $one = [$one, $xf->cfg[$i]];
        }

        $etc = isset($xf->cfg['etc']) ? $xf->cfg['etc'] : 'style="width:100%"';

        if ($is_tbl) {
            $out = tag(td($ary), $etc, 'table');
            return $is_div ? tag($out, '') : $out;
        }
        if (1 == $cnt) {
            return $is_div ? tag($ary[0], $etc) : $ary[0];
        }
        $out = sprintf(TPL_FORM, $ary[0], $ary[1]); # else std tpl
        return $is_div ? tag($out, $etc) : $out;
    }

    private function walk($form, $mk = [], $func = false, $depth = 0) {
        $out = '';
        $rs = $depth % 2;
        foreach ($form as $name => $ary) {
            $char = false;

            if ($ary instanceof xForm) {
                $out .= $this->proc_x($ary, $mk);
                continue;
                
            } elseif (!is_array($ary)) {
                if (is_int($name)) {
                    $out .= $ary;
                    continue;
                }
                $ary = [$ary, 'hidden'];
                if ($this->validation) {
                    $this->post[$name] = $_POST[$name];
                    continue;
                }

            } elseif (is_int($name)) {
                $ar0 = is_array($ary[0]); # no left side
                if ($ar0 || is_array($ary[1])) { # horiz layout (row style) !
                    $lft = ($ary[0] instanceof xForm) ? $this->proc_x($ary[0], $mk) : ($ar0 ? '' : $ary[0]);
                    $rgt = $this->walk($ar0 ? $ary[0] : $ary[1], $mk, $func, 1 + $depth);
                    if (!$this->validation) {
                        if ($rs) {
                            $out .= tag($rgt, 'class="elm" style="width:100%"', 'li');
                        } else {
                            $rgt = tag($rgt, 'class="row"', 'ul');
                            $out .= $ar0 ? $rgt : sprintf(TPL_FORM, $lft, $rgt);
                        }
                    }
                    continue;
                }
                $name = '';

            } elseif (false !== strpos('+-~.*#/|', $name[0])) {
                $this->proc_char($char = $name[0], $name = substr($name, 1), $mk);
            } elseif ($this->validation) {
                if (isset($_POST[$name]))
                $this->post[$name] = $_POST[$name]; # collect form fields only !
            }

            if ($func) { # draw html
                if ($ary[0] instanceof xForm) {
                    $ary[0] = $this->proc_x($ary[0], $mk);
                }
                if (is_string($res = $func($this->modify_key($mk, $name), $ary, $rs))) {
                    $out .= $res;
                    continue;
                }
                $p2 = $char ? '%s' . tag('', 'class="red"', 'span') : '%s';
                if ($rs) {
                    $cls = in_array(@$ary[1], ['radio', 'checkbox']) ? 'elm-chk' : 'elm';
                    $tpl = ($ary[0] == '' ? '%s' : '<li class="desc">%s</li>') . tag($p2, "class=\"$cls\"", 'li');
                } else {
                    $tpl = sprintf(TPL_FORM, '%s', $p2);
                }
                $out .= sprintf($tpl, $res[0], $res[1]);
            }
        }
        return $out;
    }

    static function set_null($ary, $table) {
        $emp = false;
        foreach ($ary as $v) {
            if ($emp = '' === $v)
                break;
        }
        if ($emp) {
            $d = sql('@explain $_`', $table);
            array_walk($ary, function(&$v, $k) use ($d) {
                if ('' === $v && isset($d[$k]) && 'YES' == $d[$k][1] && null === $d[$k][3])
                    $v = null;
            });
        }
        return $ary;
    }

    static function filter_pfx($pfx, $and_cut = true) {
        $len = strlen($pfx);
        $ary = array_filter($_POST, function($k) use ($pfx, $len) {
            return $pfx == substr($k, 0, $len);
        }, ARRAY_FILTER_USE_KEY);
        if (!$and_cut)
            return $ary;
        $keys = array_map(function($k) use ($len) {
            return substr($k, $len);
        }, array_keys($ary));
        return array_combine($keys, array_values($ary));
    }

    const TPL_CHK = '<li class="elm-chk"><label><ul class="row"><li class="elm">%s</li><li class="desc-chk">%s</li></ul></label></li>';

    function draw_form($row = [], $mk = [], $add_post = true) {
        if ($this->spec)
            return $this->{"draw_$this->spec"}($row, $mk, $add_post);
        if (is_numeric($row))
            $row = sql('~select * from $_ where id=$.', $row);
        $this->row = $row + $this->row;
        if ($add_post)
            $this->row = $_POST + $this->row;
        $row =& $this->row;

        return $this->walk($this->form, $mk, function($name, $ary, $rs) use ($row) {
            $val = $ary[0];
            if ($n = $name) { # assign!
                $name = " name=\"$name\"";
                if ('[]' == substr($n, -2))
                    $n = substr($n, 0, -2);
            }

            switch (@$ary[1]) {
                case 'hidden':
                    return $this->input('hidden', isset($row[$n]) ? $row[$n] : $val, $name);
                case 'submit': case 'button': case 'image': case 'reset':
                    $el = $this->input($ary[1], $val, @$ary[2] ? $ary[2] . $name : $name);
                    return sprintf($rs ? '%s%s' : TPL_FORM, '', $el);
                case 'textarea': case 'textarea_rs':
                    $s = isset($row[$n]) ? $row[$n] : @$ary[3];
                    $el = tag(html($s), isset($ary[2]) ? $ary[2] . $name : $name, 'textarea');
                    if ($rs || 'textarea_rs' == $ary[1])
                        return [$val, $el];
                    return tag(($val ? "$val<br>" : '') . $el, '', 'p');
                case 'chk':
                    $etc = (isset($row[$n]) ? $row[$n] : @$ary[3]) ? ' checked' : '';
                    $el = $this->input('checkbox', '', $etc . ' onclick="$(this).prev().val(this.checked?1:0)"');
                    return ['', tag($this->input('hidden', $etc ? 1 : 0, $name) . $el . $val, '', 'label') . ' &nbsp; &nbsp;'];
                case 'checkbox':
                    $etc = ((isset($row[$n]) ? $row[$n] : @$ary[3]) ? ' checked' : '') . (@$ary[2] ? $ary[2] . $name : $name);
                    $el = $this->input('checkbox', isset($ary[4]) ? $ary[4] : 1, $etc);
                    return ['', tag($el . $val, '', 'label') . ' &nbsp; &nbsp;'];
                case 'radio':
                    $el = '';
                    foreach ($ary[2] as $v => $desc) {
                        $etc = ($v == (isset($row[$n]) ? $row[$n] : @$ary[3]) ? ' checked' : '') . (@$ary[4] ? " $ary[4]$name" : $name);
                        $el .= sprintf(self::TPL_CHK, $this->input('radio', $v, $etc), $desc);
                    }
                    return [$val, tag($el, 'class="row"', 'ul')];
                case 'select':
                    $el = tag(option(isset($row[$n]) ? $row[$n] : @$ary[4], $ary[2]), (isset($ary[3]) ? $ary[3] : '') . $name, 'select');
                    return [$val, $el];
                case 'img':
                case 'doc':
                    if (isset($ary[3])) {
                        $id_name = $ary[3];
                        $id = $ary[2];
                    } else {
                        $id_name = isset($row[$n . '_name']) ? $row[$n . '_name'] : '';
                        $id = isset($row[$n]) ? $row[$n] : '';
                    }
                    return [$val, $this->files($id, $name, 'img' == $ary[1], $id_name)];
                case 'custom':
                    return [$val, call_user_func($ary[2], $this, $n)];
                case 'ni': # no item, format purpose
                    return [$val, (isset($row[$n]) ? $row[$n] : @$ary[2]) . "&nbsp;"];
                case 'li': # list item, format purpose
                    return tag($val, isset($ary[2]) ? $ary[2] : 'class="elm-chk"', 'li');
                default: # other inputs
                    $type = isset($ary[1]) && $ary[1] ? $ary[1] : 'text';
                    $v = isset($ary[3]) ? $ary[3] : '';
                    if (isset($row[$n])) {
                        if (is_array($v = $row[$n])) {
                            $v = array_shift($row[$n]);
                            $this->repeat[self::$cls_repeat][$n] = $row[$n];
                        }
                    }
                    $el = $this->input($type, $v, @$ary[2] ? $ary[2] . $name : $name);
                    return [$val, $el];
            }
        });
    }

    function files($id, $name, $is_img, $id_name) {
        $em = tag('drag file (or click) here', '', 'span')
            . tag('', 'value="0" style="display:none"', 'progress');
        if ($id) {
            $del = '<a href="javascript:;" class="delete-%s" onclick="sky.file_delete(this, ' . $id . ')">[X]</a>';
            $in = $is_img
                ? '<img style="position:absolute" src="file?id' . $id . '"/>' . sprintf($del, 'img')
                : tag($id_name, 'class="doc-file-name"') . sprintf($del, 'doc');
        } else {
            $in = $em;
        }
        return tag($in, $is_img ? 'class="imgs"' : 'class="files"')
            . '<input type="file" style="opacity:0; position:fixed; top:-100em" />'
            . $this->input('hidden', $id, $name)
            . tag($em, 'style="display:none"');
    }

    function draw_table($row = [], $mk = [], $add_post = true) {
        $this->row = $row + $this->row;
        $row =& $this->row;

        return $this->walk($this->form, $mk, function($name, $ary, $rs) use ($row) {
            $val = $ary[0];
            switch (@$ary[1]) {
                case 'hidden': case 'button':
                    return '';
                case 'select': case 'radio':
                    $v = '&nbsp;';
                    if (isset($ary[2][$row[$name]])) {
                        $cls = $row[$name] ? '' : ' hide-elms';
                        $v = tag($ary[2][$row[$name]], 'class="concl' . $cls . '"', 'span');
                    }
                    return [$val, $v];
                case 'checkbox':
                    $yes = isset($row[$name]) ? $row[$name] : @$ary[3];
                    //isset($row[$name]) && isset($ary[2][$row[$name]]) && $ary[2][$row[$name]];
                    return $yes ? [tag($val, 'class="concl"', 'span'), ''] : '';
                case 'ni':
                    return [$val, $ary[2]];
                case 'img':
                    $pic_id = $row[$name] ? $row[$name] : 0;
                    return [$val, $pic_id ? '<img src="file?id' . $pic_id . '"/>' : ''];
                case 'doc':
                    return [$val, @$ary[3]];
                case 'custom':
                    return [$val, call_user_func($ary[2], $this, $name)];
                default:
                    $v = isset($row[$name]) ? $row[$name] : @$ary[3];
                    return [$val, tag($v ? html($v) : '&nbsp;', 'class="concl"', 'span')];
            //      return [$val, isset($row[$name]) && $row[$name] ? html($row[$name]) : '&nbsp;'];
            }
        });
    }

    static function show_table($row, $form) {
        $me = new Form($form);
        $me->spec = 'table';
        return $me->draw_table($row);
    }

    function draw_ary($row = [], $mk = [], $return_ary = true) {
        $this->walk($this->form, $mk, function($name, $ary, $rs) {
            if (in_array($name, $this->exclude))
                return '';
            isset($ary[1]) && $ary[1] or $ary[1] = 'text';
            if (!isset($_POST[$name]))
                throw new Error("Form::draw_ary err: not set \$_POST[$name]");
            $val = $_POST[$name];
            if ('' !== $val) switch ($ary[1]) {
                case 'select':
                    $this->ary += ["$this->pfx$name=" => $val];
                    break;
                case 'text':
                    $this->ary += ["$this->pfx$name like " => "%$val%"];
                    break;
            }
            return '';
        });
        return $return_ary ? $this->ary : '';
    }

    static function filter_ary($form, $pfx, $exclude = []) {
        $me = new Form($form);
        $me->spec = 'ary';
        $me->pfx = $pfx;
        $me->exclude = $exclude + [-11 => '', -12 => '_csrf'];
        return $me->draw_ary();
    }

    static function hide() {
        if (self::$hidden++ % 2)
            return '</ul></li>';
        return '<li class="elm-hide"><ul>';
    }

    function repeat_js() {
        if (!$this->repeat)
            return '[]';
        array_walk($this->repeat, function(&$v, $cls_repeat) {
            $cnt = count(current($v));
            for ($a = [], $i = 0; $i < $cnt; $i++) {
                $b = [];
                foreach ($v as $name => $ary)
                    $b[] = "'$name':" . var_export($ary[$i], true);
                $a[] = "'$cls_repeat',{" . implode(',', $b) . "}";
            }
            $v = implode(",", $a);
        });
        return '[' . implode(",", $this->repeat) . ']';
    }

    static function repeat_val($inp) {
        $cnt = -1;
        $post = [];
        foreach ($inp as $name) { # validate
            if (!isset($_POST[$name]) || !is_array($_POST[$name]) || $cnt > -1 && $cnt != count($_POST[$name])) {
                return array_combine($inp, array_pad([], count($inp), []));
            } else {
                $post[$name] = array_values($_POST[$name]); # 100% 0...n indexes
            }
            $cnt > -1 or $cnt = count($post[$name]);
        }
        $out = array_combine($inp, array_pad([], count($inp), []));
        for ($i = 0; $i < $cnt; $i++) {
            $ok = true;
            foreach ($post as $name => $ary)
                if ('' === trim($ary[$i]))
                    $ok = false;
            if ($ok) foreach ($post as $name => $ary)
                $out[$name][] = trim($ary[$i]);
        }
        return $out;
    }

    static function repeat_begin($class) {
        return '<div class="' . (self::$cls_repeat = $class) . '">';
    }

    static function repeat_end($ancor) {
        $etc = 'id="' . self::$cls_repeat . '" style="position:relative; left:200px"';
        return '</div>' . a($ancor, ["sky.f.plus('" . self::$cls_repeat . "')"], $etc);
    }
}
