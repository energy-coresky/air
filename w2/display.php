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
        $list = [];

        foreach (explode("\n", unl($in)) as $i => $line) {
            $lnum .= str_pad(1 + $i, 3, 0, STR_PAD_LEFT) . "<br>";
            $cnt = count($ary) - 1;
            if (preg_match("/^#\.([\.\w]+)(.*)$/", $line, $m)) {
                $line = $s('#.', '#090') . $s($m[1], 'red') . $s($m[2], '#b45309');
                $ary[] = ['<div class="code" style="background:#e7ebf2;">' . "$line</div>"];
                $ary[] = "";
                if (in_array($marker, explode('.', $m[1])))
                    $inm = !$inm;
                if ($inm)
                    $list[] = 2 + $cnt;
            } else {
                $ary[$cnt] .= "$line\n";
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
        foreach ($ary as $i => &$v) {
            if (is_array($v)) {
                $v = $v[0];
            } else {
                $out = '';
                while (preg_match('/^(.*?)(~|@|#)([a-z]+)(.*)$/s', $v, $m)) {
                    $out .= $fu($m[1]);
                    $v = substr($m[4], strlen($br = Rare::bracket($m[4])));
                    $out .= $s($m[2] . $m[3], '#' == $m[2] ? '#090' : '#00f');
                    if ($br && '`' != $br[1] && in_array($m[3], ['inc', 'use', 'block']))
                        $out .= $s($br, 'red');
                    elseif ($br)
                        $out .= $s($br, '#00b;font-weight:bold');
                }
                $v = $out . $fu($v);
                if (in_array($i, $list) || '' === $marker)
                    $v = '<div class="code" style="background:#ffd;">' . "$v</div>";
            }
        }
        $out = implode("", $ary);
        if ($out[-1] == "\n")
            $out = "\n" . $out;
        if ($no_lines)
            return '<pre style="margin:0;background:#ffd">' . $out . '</pre>';
        $table = self::lay_l . $lnum . self::lay_m . '<pre style="margin:0">' . $out . '</pre>' . self::lay_r;
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
        if (isset(SKY::$plans['main']['class']['Parsedown'])) {
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

    static function var($v, $html = true) { # tune var_export
        $var = @var_export($v, true);
        if ($len = strlen($var) > 50)
            $var = sprintf(span_r, '>50 chars, ' . gettype($v));
        if ("array (\n)" == $var)
            $var = '[]';
        return $html && !$len ? html($var) : $var; // need more
    }

    static function reflect($name, $type, $pp = null) {
        global $sky;

        $params = function ($func) {
            return array_map(function ($p) use ($func) {
                $pre = $p->hasType() ? ($p->allowsNull() ? '?' : '') . $p->getType()->getName() . ' ' : '';
                if ($var = $p->isVariadic())
                    $pre .= '...';
                if (!$var && $p->isOptional()) {
                    $p->name .= $func->isInternal() && PHP_VERSION_ID < 80000
                        ? sprintf(span_r, " =err")
                        : ' = ' . ($p->isDefaultValueConstant() ? $p->getDefaultValueConstantName() : Display::var($p->getDefaultValue()));
                }
                return $pre . ($p->isPassedByReference() ? '&' : '') . '$' . $p->name;
            }, $func->getParameters());
        };
        null !== $pp or $pp = $sky->d_pp;

        if ('f' == $type) { // function
            $fnc = new ReflectionFunction($name);
            $rt = ($rt = $fnc->getReturnType()) ? " : " . $rt->getName() : '';
            return "<pre>function " . $fnc->name . '(' . implode(', ', $params($fnc)) . ")$rt</pre>";
        
        } elseif ('e' == $type) { // extensions
            $ext = new ReflectionExtension($name);
            return $ext->info();

        } else { // class
            $mds = function ($obj) {
                $s = ReflectionMethod::IS_PUBLIC;
                $s = implode(' ', Reflection::getModifierNames(~$s & $obj->getModifiers()));
                return $s ? "$s " : '';
            };
            
            $cls = new ReflectionClass($name);
            $type .= 't' == $type ? 'rait' : ('i' == $type ? 'nterface' : 'lass');
            $q = "class." . strtolower($cls->getName()) . '.php';
            $name = $mds($cls) . $type . ' ' . $cls->getName();
            if ($x = $cls->getParentClass())
                $name .= " extends " . $x->getName();
            if ($x = $cls->getInterfaceNames())
                $name .= ' implements ' . implode(', ', $x);

            $data = array_map(function ($v, $k) {
                return "const $k = " . Display::var($v);
            }, $c = $cls->getConstants(), array_keys($c));
            $dp = $cls->getDefaultProperties();
            $props = array_map(function ($v) use ($dp) {
                $m = $v->getModifiers();
                if ($m != ReflectionProperty::IS_PUBLIC)
                    $m &= ~ReflectionProperty::IS_PUBLIC;
                $s = implode(' ', Reflection::getModifierNames($m)) . ' $';
                if (null !== $dp[$v->name] && $v->isDefault())
                    $v->name .= " = " . Display::var($dp[$v->name]);
                return $s . $v->name;
            }, $cls->getProperties($pp ? null : ReflectionProperty::IS_PUBLIC));
            $data = array_merge($data, $props);
            sort($data);

            $methods = array_map(function ($v) use ($params, $mds) {
                $m = $mds($v) . 'function ';
                if ($v->returnsReference())
                    $m .= '&';
                $rt = ($rt = $v->getReturnType()) ? " : " . $rt->getName() : '';
                return $m . $v->name . '(' . implode(', ', $params($v)) . ")$rt";
            }, $cls->getMethods($pp ? null : ReflectionMethod::IS_PUBLIC));
            sort($methods);
            $traits = implode(', ', $cls->getTraitNames());
            $data = array_merge($traits ? ['use ' . $traits] : [], $data, $data && $methods ? [''] : [], $methods);
            return "<pre>$name\n{\n    " . implode("\n    ", $data) . "\n}</pre>";
        }
    }
}
