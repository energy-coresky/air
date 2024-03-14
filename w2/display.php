<?php

class Display # php jet yaml html bash || php_method md || var diff log
{
    const lay_l = '<table cellpadding="0" cellspacing="0" style="width:100%"><tr><td class="tdlnum code" style="width:10px">';
    const lay_m = '</td><td style="padding-left:1px;vertical-align:top">';
    const lay_r = '</td></tr></table>';

    private static $bg;
    private static $clr;

    static function jet($jet, $marker = '', $no_lines = false, $no_layout = false) {
        $out = self::pp($jet, $marker, $lnum);

        if ($out[-1] == "\n" && !$no_layout)
            $out = "\n" . $out;
        if ($no_lines)
            return $no_layout ? $out : '<pre style="margin:0;background:#ffd">' . $out . '</pre>';
        $table = self::lay_l . $lnum . self::lay_m . '<pre style="margin:0;width:100%">' . $out . '</pre>' . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function scheme($name = 'w_base') {
        $pal = Plan::php();
        # r red (in PHP - strings) d00
        # g green (in PHP - keywords) 070
        # d default (in PHP) blue 00b
        # c comment color #ff8000
        # m magenta
        # j - gray
        $_val = $pal->color[$pair = $pal->pairs[$name]];
        $_key = array_map(fn($k) => "_$k", array_keys($_val));
        self::$clr = $pal->color[$name] + array_combine($_key, $_val) + [
            'b' => '#000', # black (in PHP - HTML)
            'w' => '#fff', # white
        ];
        $_val = $pal->background[$pair[0]];
        $_val['='] = $_val[0] ?: '#fff';
        $_key = array_map(fn($k) => "_$k", array_keys($_val));
        self::$bg = ['=' => false] + $pal->background[$name[0]] + array_combine($_key, $_val);
    }

    static function css($in) {
    }

    static function js($in) {
    }

    static function span($c, $str, $style = '') {
        $c = self::$clr[$c] ?? $c;
        return '<span style="color:' . "$c;$style\">" . html($str) . '</span>';
    }

    static function pp($txt, $marker, &$lnum, $fu = false) {
        self::scheme();
        if ($is_jet = !$fu) $fu = function ($v) {
            $out = '';
            while (preg_match('/^(.*?)(~|@|){([{!\-])(.*?)([\-!}])}(.*)$/s', $v, $m)) {
                $out .= html($m[1]);
                $v = $m[6];
                if ('@' == $m[2])
                    $out .= html("@{"."$m[3]$m[4]$m[5]"."}");
                elseif ('{' == $m[3])
                    $out .= self::span('w', $m[2] . "{{".$m[4]."}}", 'background:#b45309');
                elseif ('!' == $m[3])
                    $out .= self::span('w', $m[2] . "{!".$m[4]."!}", 'background:#777');
                else
                    $out .= self::span('#b45309', $m[2] . "{-".$m[4]."-}"); # Jet comment
            }
            return $out . html($v);
        };
        $ary = [$lnum = $inm = ""];
        $yellow = $blue = [];

        foreach (explode("\n", unl($txt)) as $i => $line) {
            $lnum .= str_pad(1 + $i, 3, 0, STR_PAD_LEFT) . "<br>";
            $cnt = count($ary) - 1;
            if (preg_match("/^#\.([\.\w]+)(.*)$/", $line, $m)) {
                $line = self::span('#090', '#.') . self::span('red', $m[1]) . self::span('#b45309', $m[2]);
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
        $mname = [];
        foreach ($ary as $i => &$v) {
            $pp = ['if', 'elseif', 'else', 'end', 'use'];
            if (is_array($v)) {
                $new = array_diff($v[1], $mname);
                $mname = array_merge(array_diff($mname, $v[1]), $new);
                $v = $v[0];
            } else {
                $out = '';
                while (preg_match('/^(.*?)(~|@|#)(\w+)(.*)$/s', $v, $m)) {
                    $out .= $fu($m[1],1);
                    $v = substr($m[4], strlen($br = Rare::bracket($m[4])));
                    $out .= self::span('#' == $m[2] ? (in_array($m[3], $pp) ? '#090' : '') : 'd', $m[2] . $m[3]);
                    if ($br && '`' != $br[1] && in_array($m[3], ['inc', 'use', 'block']))
                        $out .= self::span('red', $br);
                    elseif ($br)
                        $out .= self::span('#00b', $br, 'font-weight:bold');
                }
                $v = $out . $fu($v,1);
                if (in_array($i, $yellow)) {// || '' === $marker
                    $v = '<div class="code" style="background:#ffd;">' . "$v</div>";
                } elseif (array_intersect($mname, $blue)) {
                    $v = '<div class="code" style="background:#eff;">' . "$v</div>";
                }
            }
        }
        return implode('', $ary);
    }

    static function yaml($yaml, $marker = '', $no_lines = false, $no_layout = false) {
        $out = self::pp($yaml, $marker, $lnum, function ($v, $is_left) {
            $out = '';
            while (preg_match('/^(.*?)(#[^\n]*|\$[A-Z_\d]+|[^ \n{\+]+:[ \n])(.*)$/s', $v, $m)) {
                $out .= html($m[1]);
                $v = $m[3];
                if ('$' == $m[2][0])
                    $out .= self::span('m', $m[2]);
                elseif ('#' == $m[2][0])
                    $out .= self::span('c', $m[2]); # YML comment
                else $out .= self::span('g', $m[2]);
            }
            return $out . html($v);
        });

        if ($no_lines)
            return $no_layout ? $out : pre($out, 'style="background:#f7fee7; margin:0"');
        $table = self::lay_l . $lnum . self::lay_m . '<pre style="margin:0;width:100%">' . $out . '</pre>' . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function diff($new, $old, $mode = false) {
        $V = [];
        $N = count($new = explode("\n", str_replace(["\r\n", "\r"], "\n", $new)));
        $L = count($old = explode("\n", str_replace(["\r\n", "\r"], "\n", $old)));

        for ($n = $l = 0; $n < $N && $l < $L; ) {
            $sn = current($new);
            $sl = current($old);
            if ($sn === $sl) {
                $V[$n++] = $l++;
                array_shift($new);
                array_shift($old);
            } else {
                $fn = array_keys($old, $sn);
                $fl = array_keys($new, $sl);
                if (!$fn && !$fl) {
                    $V[$n++] = '*';
                    $l++;
                    array_shift($new);
                    array_shift($old); # optimize?
                } elseif (!$fn) {
                    $V[$n++] = '?';
                    array_shift($new);
                } elseif (!$fl) {
                    $l++;
                    array_shift($old);
                } else { # cross
                    $tn = current($fn);
                    $tl = current($fl);
                    for ($in = 1; isset($new[$in]) && isset($old[$tn + $in]) && $new[$in] === $old[$tn + $in]; $in++);
                    for ($il = 1; isset($old[$il]) && isset($new[$tl + $il]) && $old[$il] === $new[$tl + $il]; $il++);
                    if ($in / $tn < $il / $tl) {
                        $V[$n++] = '?';
                        array_shift($new);
                    } else {
                        $l++;
                        array_shift($old);
                    }
                }
            }
        }
        if ($mode)
            return $V;

        for ($n = $l = 0, $rN = '', $c = count($V); $n < $N || $l < $L; ) {
            if ($n >= $N) {
                $rN .= '.';
                $l++;
            } elseif ($l >= $L) {
                $rN .= '+';
                $n++;
            } elseif ($n >= $c || $V[$n] === '*') {
                $rN .= '*';
                $n++;
                $l++;
            } elseif ($V[$n] === $l) {
                $rN .= '=';
                $n++;
                $l++;
            } elseif ($V[$n] !== '?') do {
                    $rN .= '.';
                } while ($V[$n] > ++$l);
            else {
                $cp = false;
                for ($p = 1; $n + $p < $c; $p++)
                    if (is_int($V[$n + $p])) {
                        $cp = true;
                        break;
                    }
                if ($cp) {
                    $q = $V[$n + $p] - $l;
                    while ($q && $p) {
                        $rN .= "*";
                        $n++;
                        $l++;
                        $q--;
                        $p--;
                    }
                    while ($p--) {
                        $rN .= "+";
                        $n++;
                    }
                    while ($q--) {
                        $rN .= ".";
                        $l++;
                    }
                } else do {
                    $rN .= '*';
                    $n++;
                    $l++;
                } while ($n < $N && $l < $L);
            }
        }
        return $rN;
    }

    static function var($in) {
        return Plan::var($in);
    }

    static function bash($text) {
        return pre(preg_replace_callback("@(#.*)@m", function ($m) {
            return L::y($m[1]);
        }, $text), 'style="background:#e0e7ff; padding:5px"');
    }

    static function md($text) {
        self::scheme();
        $code = function ($text, $re) {
            return preg_replace_callback("@$re@s", function ($m) {
                if ('yaml' == $m[1])
                    return self::yaml(unhtml($m[2]), '-', true);
                if ('bash' == $m[1])
                    return self::bash(unhtml($m[2]));
                if ('php' == $m[1])
                    return self::php(unhtml($m[2]), false, true);
                if ('html' == $m[1])
                    return self::html(unhtml($m[2]), '', true);
                return 'jet' == $m[1] ? self::jet(unhtml($m[2]), '-', true) : pre(html($m[2]), '');
            }, $text);
        };
        if (!$exist = Plan::has('Parsedown')) {
            if (is_dir('vendor/erusev/parsedown')) {
                Plan::vendor();
                $exist = true;
            }
        }
        if ($exist) {
            $md = new Parsedown;
            $text = preg_replace("~\"https://github.*?/([^/\.]+)[^/\"]+\"~", '"_png?$1=' . Plan::$pngdir . '"', $md->text($text));
            return $code($text, '<pre><code class="language\-(jet|php|html|bash|yaml)">(.*?)</code></pre>');
        }
        $text = str_replace("\n\n", '<p>', unl($text));
        return $code($text, "```(jet|php|html|bash|yaml|)(.*?)```");
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
        return self::php($php, $bc);
    }

    static function php($code, $option = '', $no_lines = false) {
        if (!$u_ = $option instanceof stdClass ? '_' : '')
            $x = self::xdata($option);
        ini_set('highlight.string', self::$clr[$u_ . 'r']);
        ini_set('highlight.keyword', self::$clr[$u_ . 'g']);
        ini_set('highlight.default', self::$clr[$u_ . 'd']);
        ini_set('highlight.comment', self::$clr[$u_ . 'c']);
        ini_set('highlight.html', '');
        if ($tag = false === $option)
            $code = "<?php $code";
        $code = substr(str_replace(["\r", "\n", '<code>','</code>'], '', highlight_string($code, true)), 22, -7);
        if ($tag)
            $code = preg_replace("|^(<span [^>]+>)&lt;\?php&nbsp;|", "$1", $code);
        $ary = explode('<br />', str_replace('<br /></span>', '</span><br />', $code));
        if ($u_)
            return self::invert($option, $ary);
        if (-9 != $x->cut[1])
            array_splice($ary, $x->cut[1]);
        $x->len = strlen($x->diff);
        array_walk($ary, 'Display::highlight', $x);
        $style = 'style="margin:0; color:' . self::$clr['m'] . '; background-color:' . self::$bg[0] . '"';
        if ($no_lines)
            return pre(implode('', $ary), $style);
        $table = self::lay_l . $x->lnum . self::lay_m . pre(implode('', $ary), $style) . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function invert($y, $ary) {
        array_walk($ary, function (&$val, $key, $y) {
            $emt = '?&gt;</span>' != substr($val, -12);
            if (!$key) {
                !$emt or $emt = '' === $y->s;
                $y->_ = str_pad($y->_, count($y->a), '0');
            }
            $val = $y->prev . $val;
            if ($y->prev = preg_match("/(<[^\/][^>]+>)([^>]*)$/", $val, $m) ? $m[1] : '')
                $val .= '</span>';
            if ($emt) {
                $y->_ .= '1';
            } else {
                $val = tag($val, 'style="background:' . self::$bg['_='] . '"', 'span');
            }
        }, $y);
        $y->diff = str_pad($y->diff, strlen($y->_), '=');
        return implode("\n", $ary);
    }

    static function xdata($in) {
        self::$bg ?? self::scheme(SKY::d('php_hl') ?: 'w_php');///////////////////////////////
        $cut = [-9, -9];
        if (is_array($in)) {
            $in[2] or $cut = [$in[0] - 8, $in[0] + 7];
            $in = str_pad('', $in[0] - 1, '=') . ($in[0] ? ($in[1] ? '-' : '+') : '');
        }
        return (object)[
            'lnum' => '',
            'disp' => 0,
            'diff' => $in,
            'cut' => $cut,
            '_' => '',
            'prev' => '',
        ];
    }

    static function highlight(&$val, $key, $x) {
        if (-9 != $x->cut[0] && $key < $x->cut[0])
            return $val = '';
        $pad = $c = '';
        if (!$key) for (; $x->len > $x->disp && $x->diff[$x->disp] == '.'; $x->disp++) {
            $x->lnum .= "<br>";
            $pad .= '<div class="code" style="background:' . self::$bg['.'] . '">&nbsp;</div>';
        }
        if ($x->len > $key + $x->disp) {
            $chr = $x->diff[$key + $x->disp];
            $inner = $x->_[$key] ?? false;
            $c = self::$bg[$inner ? "_$chr" : $chr]; # = - + * .
        }

        $val = $x->prev . $val;
        if ($x->prev = preg_match("/(<[^\/][^>]+>)([^>]*)$/", $val, $m) ? $m[1] : '')
            $val .= '</span>';
            
        $x->lnum .= str_pad(++$key, 3, 0, STR_PAD_LEFT) . "<br>";
        $val = $c ? $pad . '<div class="code" style="background:' . "$c\">$val</div>" : "$pad$val\n";
        for (; $x->len > $key + $x->disp && $x->diff[$key + $x->disp] == '.'; $x->disp++) {
            $x->lnum .= "<br>";
            $val .= '<div class="code" style="background:' . self::$bg['.'] . '">&nbsp;</div>';
        }
    }

    static function highlight_html($n, $m, $y) {
        if (is_array($n)) { # tag params
            $s = '&lt;' . self::span('r', substr($y->code, 1 + $y->i, $len = strlen($m)));
            $y->i += 1 + $len;
            foreach ($n as $k => $v) {
                $s .= substr($y->code, $y->i, $len = strspn($y->code, "\t \n", $y->i)); # spaces
                $y->i += $len;
                $s .= html(substr($y->code, $y->i, $len = strlen($k))); # key
                $y->i += $len;
                if (0 === $v) # key is void
                    continue;
                $s .= substr($y->code, $y->i, $len = strspn($y->code, "\t \n", $y->i)) . '='; # spaces and =
                $y->i += 1 + $len;
                $s .= substr($y->code, $y->i, $len = strspn($y->code, "\t \n", $y->i)); # spaces
                $y->i += $len;
                $len = in_array($y->code[$y->i], ["'", '"']) ? 2 : 0;
                $s .= html(substr($y->code, $y->i, $len += strlen($v))); # value
                $y->i += $len;
         //$s .= ' ' . html(0 === $v ? $k : "$k=\"$v\"");
                $s .= substr($y->code, $y->i, $len = strspn($y->code, "\t \n", $y->i)); # spaces
                $y->i += $len;
            }
            $y->i++;
            return $s . '&gt;';
        } elseif ('/' == $n) {
            $s = substr($y->code, $y->i, $len = strlen("</$m>")); # close tag
            if ($close = "</$m>" == strtolower($s))
                $y->i += $len;
            return $close ? '&lt;' . self::span('r', substr($s, 1, -1)) . '&gt;' : '';
        } elseif ('#php' == $n) {
            /* return self::php("<?php$m?>", $y);*/
            $s = '<?php' == substr($y->code, $y->i, 5) ? '<?php' : '<?';
            $y->i += strlen($s .= $m);
            if ($close = '?>' == substr($y->code, $y->i, 2))
                $y->i += 2;
            return self::php($s . ($close ? '?>' : ''), $y);
        } else { // #text or #data or #comment
            $y->i += strlen($str = sprintf(XML::$spec[$n], $m));
            return '#text' == $n ? html($str) : self::span('c', $str);
        }
    }

    static function html($code, $option = '', $no_lines = false) {
        if ('<?' == substr($code, 0, 2) && !strpos($code, '?>'))
            return self::php($code, $option, $no_lines);
        $y = self::xdata($option);
        $y->s = '';
        $y->a = [];
        $y->i = 0;
        $xml = new XML($code = unl($code));
        $y->code =& $code;
        $cut = $xml->walk($xml->array, function ($n, $m) use ($y) {
            $y->s .= self::highlight_html($n, $m, $y);
            if (false !== strpos($y->s, "\n")) {
                $ary = explode("\n", $y->s);
                $y->s = array_pop($ary);
                array_splice($y->a, $sz = count($y->a), 0, $ary);
                if (-9 != $y->cut[1] && $sz + count($ary) > $y->cut[1])
                    return true; # cut mode, lines collected
            }
        });
        if (true !== $cut)
            $y->a[] = $y->s;
        $y->len = strlen($y->diff);
        array_walk($y->a, 'Display::highlight', $y);
        $style = 'style="margin:0; color:' . self::$clr['m'] . '; background-color:' . self::$bg[0] . '"';
        if ($no_lines)
            return pre(implode('', $y->a), $style);
        $table = self::lay_l . $y->lnum . self::lay_m . pre(implode('', $y->a), $style) . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function log($html) { # 2do
        if ($p = strrpos($html, '<a'))
            $html = substr($html, 0, $p);
        if ($p = strrpos($html, '<span'))
            $html = substr($html, 0, $p);
        return '<pre>' . $html. '</pre>';
    }
}
