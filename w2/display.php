<?php

class Display
{
    static $me = false;
    const lay_l = '<table cellpadding="0" cellspacing="0" style="width:100%"><tr><td class="tdlnum code" style="width:10px">';
    const lay_m = '</td><td style="padding-left:1px;vertical-align:top">';
    const lay_r = '</td></tr></table>';

    static function jet($fn, $marker = '', $no_lines = false) {
        $s = function ($s, $c) {
            return '<span style="color:' . $c . '">' . html($s) . '</span>';
        };
        $in = $fn;
        $lnum = $inm = '';
        $ary = [""];
        $yellow = $blue = [];

        foreach (explode("\n", unl($in)) as $i => $line) {
            $lnum .= str_pad(1 + $i, 3, 0, STR_PAD_LEFT) . "<br>";
            $cnt = count($ary) - 1;
            if (preg_match("/^#\.([\.\w]+)(.*)$/", $line, $m)) {
                $line = $s('#.', '#090') . $s($m[1], 'red') . $s($m[2], '#b45309');
                $line = '<div class="code" style="background:#e7ebf2;">' . "$line</div>";
                $ary[] = [$line, explode('.', $m[1])];
                $ary[] = "";
                if (in_array($marker, explode('.', $m[1])))
                    $inm = !$inm;
                if ($inm)
                    $yellow[] = 2 + $cnt;
            } else {
                $ary[$cnt] .= "$line\n";
                
                if ($inm && preg_match("/(#use|@use|@inc|@block)\(\.([a-z\d_]+)/", $line, $m))
                    $blue[] = $m[2];
            }
        }
        $fu = function ($v) use ($s) {
            $out = '';
            while (preg_match('/^(.*?)(~|@|){([{!\-])(.*?)([\-!}])}(.*)$/s', $v, $m)) {
                $out .= html($m[1]);
                $v = $m[6];
                if ('@' == $m[2])
                    $out .= html("@{"."$m[3]$m[4]$m[5]"."}");
                elseif ('{' == $m[3])
                    $out .= $s($m[2] . "{{".$m[4]."}}", '#fff; background:#b45309');
                elseif ('!' == $m[3])
                    $out .= $s($m[2] . "{!".$m[4]."!}", '#fff; background:#777');
                else
                    $out .= $s($m[2] . "{-".$m[4]."-}", '#b45309'); # Jet comment
            }
            return $out . html($v);
        };
        $mname = [];
        foreach ($ary as $i => &$v) {
            $pp = ['if', 'elseif', 'else', 'end', 'use'];
            if (is_array($v)) {
                $new = array_diff($v[1], $mname);
                $mname = array_merge(array_diff($mname, $v[1]), $new);
                $v = $v[0];
            } else {
                $out = '';
                while (preg_match('/^(.*?)(~|@|#)([a-z]+)(.*)$/s', $v, $m)) {
                    $out .= $fu($m[1]);
                    $v = substr($m[4], strlen($br = Rare::bracket($m[4])));
                    $out .= $s($m[2] . $m[3], '#' == $m[2] ? (in_array($m[3], $pp) ? '#090' : '') : '#00f');
                    if ($br && '`' != $br[1] && in_array($m[3], ['inc', 'use', 'block']))
                        $out .= $s($br, 'red');
                    elseif ($br)
                        $out .= $s($br, '#00b;font-weight:bold');
                }
                $v = $out . $fu($v);
                if (in_array($i, $yellow) || '' === $marker) {
                    $v = '<div class="code" style="background:#ffd;">' . "$v</div>";
                } elseif (array_intersect($mname, $blue)) {
                    $v = '<div class="code" style="background:#eff;">' . "$v</div>";//e0e7ff
                }
            }
        }
        $out = implode("", $ary);
        if ($out[-1] == "\n")
            $out = "\n" . $out;
        if ($no_lines)
            return '<pre style="margin:0;background:#ffd">' . $out . '</pre>';
        $table = self::lay_l . $lnum . self::lay_m . '<pre style="margin:0;width:100%">' . $out . '</pre>' . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function md($text) {
        $code = function ($text, $re) {
            return preg_replace_callback("@$re@s", function ($m) {
                if ('php' == $m[1])
                    return Display::php(unhtml($m[2]), '', true);
                return 'jet' == $m[1] ? Display::jet(unhtml($m[2]), '-', true) : tag(html($m[2]), '', 'pre');
            }, $text);
        };
        if (Plan::has_class('Parsedown')) {
            $md = new Parsedown;
            return $code($md->text($text), '<pre><code class="language\-(jet|php)">(.*?)</code></pre>');
        }
        $text = str_replace("\n\n", '<p>', unl($text));
        return $code($text, "```(jet|php|)(.*?)```");
    }

    static function php_method($fn, $method) {
        $php = unl($fn);
        $bc = '';
        if (preg_match("/^(.*?)function $method\([^\)]*\)\s*({.*)$/s", $php, $m)) {
            $n0 = substr_count($m[1], "\n");
            $br = Rare::bracket($m[2], '{');
            $sz = substr_count($br, "\n");
            $bc = str_repeat('=', $n0) . str_repeat('*', 1 + $sz);
        }
        return Display::php($php, $bc);
    }

    static function php($str, $bc = '', $no_lines = false) {
        self::$me or self::$me = new Display;
        $me = self::$me;
        if ($str === -1)
            return '';
        $me->lnum = '';
        $me->lenb = strlen($me->back = $bc);
        $me->disp = 0;
        if ($tag = preg_match("/^\s*[\$]\w+/sm", $str) || $no_lines)
            $str = "<?php $str";
        $str = str_replace(["\r", "\n", '<code>','</code>'], '', highlight_string($str, true));
        if ($tag)
            $str = preg_replace("|^(<span [^>]+>)<span [^>]+>&lt;\?php&nbsp;|", "$1", $str);
        $lines = explode('<br />', preg_replace("|^(<span [^>]+>)<br />|", "$1", $str));
        array_walk($lines, [$me, 'add_line_no']);
        if ($no_lines)
            return '<pre style="margin:0">' . implode('', $lines) . '</pre>';
        $table = self::lay_l . $me->lnum . self::lay_m . '<pre style="margin:0">' . implode('', $lines) . '</pre>' . self::lay_r;
        return sprintf('<div class="php">%s</div>', str_replace('%', '&#37;', $table));
    }

    private function add_line_no(&$val, $key) {
        $val = strtr($val, ['=TOP-TITLE=' => '&#61;TOP-TITLE&#61;']);
        $colors = ['' => '', '=' => '', '*' => 'ffd', '+' => 'dfd', '-' => 'fdd', '.' => 'eee'];
        $pad = $c = '';
        if (!$key) for(; $this->lenb > $key + $this->disp && $this->back[$key + $this->disp] == '.'; $this->disp++)
            $this->lnum .= "<br>" and $pad .= '<div class="code" style="background:#eee">&nbsp;</div>';
        if ($this->lenb > $key + $this->disp)
            $c = $colors[$this->back[$key + $this->disp]];
        $key++;
        $this->lnum .= str_pad($key, 3, 0, STR_PAD_LEFT) . "<br>";
        if ($val === '')
            $val = '&nbsp;';
        elseif ($val == '</span>')
            $val = '&nbsp;</span>';
        elseif ($val == '</span></span>')
            $val = '&nbsp;</span></span>';
        $val = $pad . ($c ? '<div class="code" style="background:'."#$c\">$val</div>" : "$val\n");
        for (; $this->lenb > $key + $this->disp && $this->back[$key + $this->disp] == '.'; $this->disp++) { # = - + * .
            $this->lnum .= "<br>";
            $val .= '<div class="code" style="background:#eee">&nbsp;</div>';
        }
    }

    static function log($html) { # 2do
        if ($p = strrpos($html, '<a'))
            $html = substr($html, 0, $p);
        if ($p = strrpos($html, '<span'))
            $html = substr($html, 0, $p);
        return '<pre>' . $html. '</pre>';
    }

    static function array($var, $add = '', $pad = 0) {
        static $skey, $len;
        if (!$pad)
            $skey = $len = 0;
        if (!$var)
            return '[]';
        $i = 0;
        $ary = [];
        foreach ($var as $k => $v) {
            $v = is_array($v) ? self::array($v, $add, 1 + $pad) : self::var($v, $add);
            if (is_string($k)) {
                $skey = true;//////////////
                $ary[] = "'" . html($k) . "' =&gt; $v";
            } else {
                $ary[] = $i !== $k ? "$k =&gt; $v" : $v;
                $i = $k < 0 ? 0 : 1 + $k;
            }
            if (($len += strlen($v)) > 200) {
                $ary[] = sprintf(span_g, 'cutted..');
                break;
            }
        }
        $pad = str_pad('', $pad * 2, ' ');
        $s = implode(', ', $ary);
        if (strlen($s) < 55)
            return "[$s]";
        return "[\n  $pad$add" . implode(",\n  $pad$add", $ary) . "\n$pad$add]";
    }

    static function var($var, $add = '    ', $quote = true) { # tune var_export for display in html
        switch ($type = gettype($var)) {
            case 'object':
                $cls = get_class($var);
                return $quote ? sprintf(span_m, "object $cls") : "<o_$cls>" . self::reflect($var, 'c', 2) . "</o>";
            case 'resource':
                return sprintf(span_m, $type); // php8 - get_debug_type($var) class@anonymous stdClass
            case 'unknown type':
                return sprintf(span_m, $type);
            case 'array':
                $var = self::array($var, $add);
                if ($quote)
                    return $var;
                return strlen($var) < 50 ? sprintf(span_y, 'Array ') . $var : "<r>$var</r>";
            case 'string':
                if ($gt = mb_strlen($var) > 50) // 100
                    $var = mb_substr($var, 0, 50);
                if ($quote) {
                    if (false === strpbrk($var, "\r\n\t")) {
                        $var = var_export($var, true);
                        if ($gt)
                            $var = substr($var, 0, -1);
                    } else {
                        $ary = ["\t" => "\\t", "\r" => "\\r", "\n" => "\\n", '"' => '\\"'];
                        $var = '"' . strtr($var, $ary) . ($gt ? '' : '"');
                    }
                }
                return $gt ? html($var) . sprintf(span_g, '&nbsp;cutted..') : html($var);
            default: # boolean integer double NULL
                return var_export($var, true);
        }
    }

    static function reflect($name, $type, $pp = null) {
        global $sky;

        null !== $pp or $pp = $sky->d_pp;
        $params = function ($func) {
            return array_map(function ($p) use ($func) {
                $one = $p->hasType() ? ($p->allowsNull() ? '?' : '') . $p->getType()->getName() . ' ' : '';
                if ($var = $p->isVariadic())
                    $one .= '...';
                $one .= ($p->isPassedByReference() ? '&' : '') . '$' . $p->getName();
                if ($var || !$p->isOptional())
                    return $one;
                return "$one = " . ($func->isInternal() && PHP_VERSION_ID < 80000
                    ? sprintf(span_r, 'err')
                    : ($p->isDefaultValueConstant() ? $p->getDefaultValueConstantName() : self::var($p->getDefaultValue())));
            }, $func->getParameters());
        };

        if ('f' == $type) { // function
            $fnc = new ReflectionFunction($name);
            return "<pre>function $fnc->name(" . implode(', ', $params($fnc)) . ')'
                . (($rt = $fnc->getReturnType()) ? ": " . $rt->getName() : '') . '</pre>';
        
        } elseif ('e' == $type) { // extensions
            $ext = new ReflectionExtension($name);
            return $ext->info();

        } else { // class, interface, trait
            $modifiers = function ($obj) {
                $m = Reflection::getModifierNames(~ReflectionMethod::IS_PUBLIC & $obj->getModifiers());
                return $m ? implode(' ', $m) . ' ' : '';
            };
            $obj = $name;
            $cls = is_string($name) ? new ReflectionClass($name) : new ReflectionObject($obj);
            $name = ('t' == $type ? 'trait' : ('i' == $type ? 'interface' : 'class')) . " $cls->name";
            if ($x = $cls->getParentClass())
                $name .= " extends " . $x->getName();
            if ($x = $cls->getInterfaceNames())
                $name .= ' implements ' . implode(', ', $x);
            $name = 2 == $pp ? '' : "$name\n";
                

            $data = 2 == $pp ? [] : array_map(function ($v, $k) {
                return "const $k = " . self::var($v);
            }, $c = $cls->getConstants(), array_keys($c));
            $defs = $cls->getDefaultProperties();
            $props = $cls->getProperties($pp ? null : ReflectionProperty::IS_PUBLIC);
            $data = array_merge($data, array_map(function ($p) use ($defs, $pp, $obj) {
                $one = $p->getName();
                $m = $p->getModifiers();
                if (2 == $pp) {
                    $p->setAccessible(true);
                    $pfx = ReflectionProperty::IS_STATIC & $m ? '::$' : '->';
                    if ($m &= ~ReflectionProperty::IS_STATIC & ~ReflectionProperty::IS_PUBLIC)
                        $pfx = implode(' ', Reflection::getModifierNames($m)) . " $pfx";
                    $one = "$pfx$one = " . self::var($p->getValue($obj));
                } else {
                    if (null !== $defs[$one] && $p->isDefault())
                        $one .= " = " . self::var($defs[$one]);
                    ReflectionProperty::IS_PUBLIC == $m or $m &= ~ReflectionProperty::IS_PUBLIC;
                    $one = implode(' ', Reflection::getModifierNames($m)) . " \$$one";
                }
                return $one;
            }, $props));
            sort($data);

            $funcs = 2 == $pp ? [] : array_map(function ($v) use ($params, $modifiers) {
                return $modifiers($v) . 'function ' . ($v->returnsReference() ? '&' : '')
                    . $v->name . '(' . implode(', ', $params($v)) . ')'
                    . (($rt = $v->getReturnType()) ? ': ' . $rt->getName() : '');
            }, $cls->getMethods(1 == $pp ? null : ReflectionMethod::IS_PUBLIC));
            sort($funcs);
            $traits = implode(', ', $cls->getTraitNames());
            $data = array_merge($traits ? ['use ' . $traits] : [], $data, $data && $funcs ? [''] : [], $funcs);
            return tag($modifiers($cls) . "$name{\n    " . implode("\n    ", $data) . "\n}", '', 'pre');
        }
    }
}
