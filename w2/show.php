<?php

class Show # php jet yaml html bash || php_method md || var diff log
{
    const lay_l = '<table cellpadding="0" cellspacing="0" style="width:100%"><tr><td class="tdlnum code" style="width:10px">';
    const lay_m = '</td><td style="padding-left:1px;vertical-align:top">';
    const lay_r = '</td></tr></table>';
    const style = 'style="tab-size:4; margin:0; color:%s; background-color:%s"';//width:100%%; 

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
        $_val['='] = $pal->background[$name[0]][0] ?: '#fff';
        $_key = array_map(fn($k) => "_$k", array_keys($_val));
        self::$bg = ['=' => false] + $pal->background[$name[0]] + array_combine($_key, $_val);
    }

    static function bg($str, $bg) {
        return "<span style=\"background-color:$bg\">$str</span>";
    }

    static function span($c, $str, $style = '') {
        $c = self::$clr[$c] ?? $c;
        $style = '' === $c ? $style : "color:$c;$style";
        return "<span style=\"$style\">" . html($str) . '</span>';
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
                    $out .= $fu($m[1], 1);
                    $v = substr($m[4], strlen($br = Rare::bracket($m[4])));
                    $out .= self::span('#' == $m[2] ? (in_array($m[3], $pp) ? '#090' : '') : 'd', $m[2] . $m[3]);
                    if ($br && '`' != $br[1] && in_array($m[3], ['inc', 'use', 'block']))
                        $out .= self::span('red', $br);
                    elseif ($br)
                        $out .= self::span('#00b', $br, 'font-weight:bold');
                }
                $v = $out . $fu($v, 1);
                if (in_array($i, $yellow)) { // || '' === $marker
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
                else
                    $out .= self::span('g', $m[2]);
            }
            return $out . html($v);
        });

        if ($no_lines)
            return $no_layout ? $out : pre($out, 'style="background:#f7fee7; margin:0"');
        $table = self::lay_l . $lnum . self::lay_m . '<pre style="margin:0;width:100%">' . $out . '</pre>' . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function diffx(string $new, string $old, $boundary = 3, $in = false) {
        $out = '';
        $add = $sub = $j = $z = $zz = $max = 0;
        $ary = self::diff($new, $old, $max, $boundary);
        $rest = $ary && is_int($last = $ary[0][0]) ? array_shift($ary) : false;
        $len = ($gt = $max > 999) ? 4 : 3;
        $in or $in = fn($l, $n, $s, $x = '+') => sprintf("$x%{$len}s|%{$len}s %s\n", $l, $n, $s);
        if ($ary || $rest) {
            foreach ($ary as $v) {
                [$chr, $str, $n, $l] = $v;
                if ('+' == $chr) {
                    $zz or ++$z && ++$zz;
                    $add++;
                    $out .= $in($j = '', $n, $str);
                } else {
                    if ($eq = '=' == $chr) {
                        if ($j && ++$j != $n)
                            $out .= ' =======' . ($gt ? '==' : '') . "\n";
                        $j = $n;
                        $zz = '';
                    } else {
                        $zz or ++$z && ++$zz;
                        $sub++;
                    }
                    $out .= $in($l, $eq ? $n : '', $str, $eq ? ' ' : '-');
                }
            }
            if ($rest) {
                $cnt = count($ary = $rest[1] ?: $rest[2]);
                ($plus = (bool)$rest[1]) ? ($add += $cnt) : ($sub += $cnt);
                foreach ($ary as $i => $v)
                    $out .= $plus ? $in('', $last + $i, $v) : $in($last + $i, '', $v, '-');
            }
        }
        return [$out, $add, $sub, "$z " . round(100 * $z / (($add + $sub) ?: 1)) . '%'];
    }

    static function diff(string $new, string $old, &$mode = null, int $boundary = 1) {
        $new = explode("\n", unl($new));
        $old = explode("\n", unl($old)); # 2do: LCS algo..
        $pm = fn($p, $m) => str_pad('', min($p, $m), '*') . str_pad('', abs($p - $m), $p > $m ? '+' : '.');
        $diff = $eq = $plus = [];
        $n = $l = $z = $p = $m = 0;
        for ($RR = ''; $new && $old; $a or array_shift($new), $b or array_shift($old)) {
            if (($sn = $new[0]) === $old[$a = $b = 0]) {
                $diff = array_merge($diff, array_splice($plus, 0));
                $RR .= $pm($p, $m) . '=';
                $p = $m = 0;
                $z++ ? ($diff[] = ['=', $sn, ++$n, ++$l]) : ($eq[] = ['=', $sn, ++$n, ++$l]);
                $z < 1 or $z = 0;
            } else {
                $diff = array_merge($diff, array_slice($eq, $z = -$boundary));
                $eq = [];
                $x = (bool)$fl = array_keys($new, $sl = $old[0]);
                if (($fn = array_keys($old, $sn)) || $x) {
                    if ($fn && $x) { # both found
                        for ($i = 1, $vn = $fn[0]; ($new[$i] ?? 0) === ($old[$vn + $i] ?? 1); $i++);
                        for ($j = 1, $vl = $fl[0]; ($old[$j] ?? 0) === ($new[$vl + $j] ?? 1); $j++);
                        $x = $i / $vn / count($fn) < $j / $vl / count($fl); // ++$vl div by zero
                    }
                    $x ? ($plus[] = ['+', $sn, ++$n, $b = ++$p]) : ($diff[] = ['.', $sl, ++$m, $a = ++$l]);
                } else {
                    $plus[] = ['+', $sn, ++$n, 0];
                    $diff[] = ['.', $sl, 0, ++$l];
                    $RR .= '*';
                }
            }
        }
        if (null === $mode)
            return $RR . $pm($p, $m) . str_pad('', count($new ?: $old), $new ? '+' : '.');
        $diff = array_merge($diff, $plus);
        $mode = max(++$n + count($new), ++$l + count($old));
        return $new || $old ? array_merge([[$new ? $n : $l, $new, $old]], $diff) : $diff;
    }

    static function var($in) {
        return Plan::var($in);
    }

    static function bash($text) {
        return pre(preg_replace_callback("@(#.*)@m", function ($m) {
            return L::y($m[1]);
        }, $text), 'style="background:#e0e7ff;"');
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
                    return self::php($php = unhtml($m[2]), '<?' == substr($php, 0, 2) ? '' : false, true);
                if ('css' == $m[1])
                    return self::css(unhtml($m[2]), '', true);
                if ('js' == $m[1])
                    return self::js(unhtml($m[2]), '', true);
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
            return $code($text, '<pre><code class="language\-(jet|php|html|css|js|bash|yaml)">(.*?)</code></pre>');
        }
        $text = str_replace("\n\n", '<p>', unl($text));
        return $code($text, "```(jet|php|html|css|js|bash|yaml|)(.*?)```");
    }

    static function doc($markdown, $render = 'md_nice', $hightlight = true) {
        $md = new MD($markdown);
        $md->render = [$md, $render];
        $md->hightlight = $hightlight;
        return (string)$md;
    }

    static function highlight_md($code, &$bg) { # r g d c m j - gray
        self::scheme();
        $md = new MD($code);
        $out = $node = $bg = '';
        $hl = '=';
        $attr = function ($n) use (&$node, $md) {
            return $md->attr($node, $n);
        };
        foreach ($md->gen() as $d => $node) { // self::bg('&nbsp;', self::$clr['r']);
            if (!is_null($t = $attr('t'))) {//reference $out .= self::span('m', $t);
                $out .= self::span($attr('c') ?? 'r', $t);
                if ('x-code' == $node->name)
                    $hl = '*';
            } elseif (is_string($node->val)) {
                if ($cnt = substr_count($node->val, "\n")) {
                    $bg .= str_pad('', $cnt, '#skip' == $node->name ? '=' : $hl);
                    if (is_null($node->right) && !$attr('last'))
                        $hl = '=';
                }
                if ($c = $attr('c')) {
                    $out .= self::span($c, $node->val);
                } else {
                    $out .= html($node->val);
                }
            }
        }
        $bg .= $hl;
        return $out;
    }

    static function css($code, $option = '', $no_lines = false) {
        $x = self::xdata($option);
        $y = false;
        $ary = explode("\n", self::highlight_css($code, $y));
        $x->len = strlen($x->diff);
        return self::table($ary, $x, $no_lines);
    }

    static function highlight_css($code, &$y, $u = '') {
        $css = new CSS($code);
        $out = '';
        foreach ($css->tokens($y) as $t => $y) {
            if ($y->found || $y->find) { /* css comment */
                $out .= self::span($u . 'c', $t);
            } elseif ($y->space || 1 == strlen($t) && strpbrk($t, '{:,;}')) {
                $out .= $t;
            } elseif ('d' == $y->mode) {
                $out .= self::span($u . ('@' == $t[0] ? 'g' : 'g'), $t);
            } else {
                $out .= self::span($u . ('k' == $y->mode ? 'r' : 'd'), $t);
            }
        }
        return $out;
    }

    static function js($code, $option = '', $no_lines = false) {
        $x = self::xdata($option);
        $y = false;
        $ary = explode("\n", self::highlight_js($code, $y));
        $x->len = strlen($x->diff);
        return self::table($ary, $x, $no_lines);
    }

    static function highlight_js($code, &$y, $u = '') { # r g d c m j - gray
        $js = new JS($code);
        $out = '';
        foreach ($js->tokens($y) as $t => $y) {
            if (T_COMMENT == $y->tok) {
                $out .= self::span($u . 'c', html($t));
            } elseif (T_CONSTANT_ENCAPSED_STRING == $y->tok) {
                $out .= self::span($u . 'r', $t);
            } elseif (T_STRING == $y->tok) {
                $out .= self::span($u . 'j', $t);
            } elseif ($y->tok) {
                $out .= self::span($u . ('@' == $t[0] ? 'g' : 'g'), $t);
            } else {
                $out .= html($t);
            }
        }
        return $out;
    }

    static function html($code, $option = '', $no_lines = false) {
        $x = self::xdata($option);
        $ary = explode("\n", self::highlight_html($code));
        $x->len = strlen($x->diff);
        return self::table($ary, $x, $no_lines);
    }

    static function highlight_html($code, &$y = null, $u = '') { # r g d c m j - gray
        $xml = new XML($code);
        $out = '';
        $attr = [];
        foreach ($xml->tokens($y) as $t => $y) {
            if ($y->end) { # from <!-- or <![CDATA[
                $out .= self::span($u . 'c', $t);
                $y->find = $y->end;
            } elseif ('</style>' == $y->found) {
                isset($y->css) or $y->css = false;
                $out .= self::highlight_css($t, $y->css, $u);
            } elseif ('</script>' == $y->found) {
                isset($y->js) or $y->js = false;
                $out .= self::highlight_js($t, $y->js, $u);
            } elseif (in_array($y->found, ['-->', ']]>'])) {
                $out .= self::span($u . 'c', $t . ($y->find ? '' : $y->found));
                $y->len += $y->find ? 0 : 3; # chars move
            } elseif ('close' == $y->mode) { # sample: </tag>
                '/style' != ($t = substr($t, 1, -1)) or $y->css = false;
                $out .= '&lt;' . self::span($u . 'r', $t) . '&gt;';
            } elseif ('open' == $y->mode) { # sample: <tag
                $out .= '&lt;' . self::span($u . 'r', $y->tag = substr($t, 1));
                $y->mode = 'attr';
                continue;
            } elseif ($u && $y->space || 'attr' != $y->mode) { # text
                $out .= html($t);
            } elseif ('>' == $t) {
                $out .= '&gt;';
                if (in_array($y->tag, ['script', 'style']))
                    $y->find = "</$y->tag>";
            } else { # attr continue
                if ($y->space) {
                    $out .= $t;
                } else {
                    $v = $xml->set_attr($attr, $t);
                    $out .= $v ? html($t) : self::span($u . 'j', $t);
                }
                continue;
            }
            $y->mode = 'txt';
        }
        return $out;
    }

    static function php($code, $option = '', $no_lines = false) {
        $x = self::xdata($option);
        if ($tag = false === $option)
            $code = "<?php $code";
        $out = $u = $ct = $halt = '';
        $ary = [];
        $sq = 0;
        $y = null;
        foreach (token_get_all($code) as $i => $t) {
            if ($tag && !$i)
                continue;
            $sx = 0;
            if (is_array($t)) switch ($t[0]) { # r g d c m j - gray
                case T_INLINE_HTML:
                    if ($halt) {
                        $out .= self::span('c', $t[1]);
                    } else {
                        $sx = count($ary);
                        $out = substr($out, 0, $sq) . self::bg(substr($out, $sq), self::$bg[0]);
                        $out .= $ct . self::highlight_html($t[1], $y, $u = '_');
                    }
                    break;
                case T_CLOSE_TAG:
                    $out .= self::span('r', '?>');
                    $ct = substr($t[1], 2);
                    break;
                case T_OPEN_TAG:
                case T_OPEN_TAG_WITH_ECHO:
                    '' === $out or $out = self::bg($out, self::$bg['_0']);
                    $sq = strlen($out);
                case T_ENCAPSED_AND_WHITESPACE:
                case T_CONSTANT_ENCAPSED_STRING:
                    $out .= self::span('r', $t[1]);
                    break;
                case T_COMMENT:
                case T_DOC_COMMENT:
                    $out .= self::span('c', $t[1]);
                    break;
                case T_VARIABLE:
                    $out .= self::span('d', $t[1]);
                    break;
                case T_STRING:
                    $out .= self::span('j', $t[1]);
                    break;
                case T_HALT_COMPILER:
                    $halt = true;
                default:
                    $out .= $t[0] == T_WHITESPACE ? $t[1] : self::span('g', $t[1]);
                    break;
            } else {
                $out .= '"' == $t ? self::span('r', '"') : html($t);
            }
            if (false !== strpos($out, "\n")) {
                $sq = 0;
                if ($sx)
                    $x->_ = str_pad($x->_, $sx, '1');
                $a = explode("\n", $out);
                $out = array_pop($a);
                array_splice($ary, $sz = count($ary), 0, $a);
                if ($sx) {
                    $x->_ = str_pad($x->_, $sz + count($a), '0');
                    '' === $out or $out = self::bg($out, self::$bg['_0']);
                }
                if (-9 != $x->cut[1] && $sz + count($a) > $x->cut[1])
                    break; # snippet mode, lines collected
            }
        }
        if (-9 == $x->cut[1])
            $ary[] = '' === $out ? '&nbsp;' : $out;
        if ($u)
            $x->_ = str_pad($x->_, count($ary), '1');
        $x->len = strlen($x->diff = str_pad($x->diff, strlen($x->_), '='));
        return self::table($ary, $x, $no_lines, $u);
    }

    static function table($ary, $x, $no_lines, $u = '') {
        array_walk($ary, 'Show::highlight_line', $x);
        $style = sprintf(self::style, self::$clr[$u . 'm'], self::$bg[$u . '0']);
        if ($no_lines)
            return pre(implode('', $ary), $style);
        $table = self::lay_l . $x->lnum . self::lay_m . pre(implode('', $ary), $style) . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function php_method($fn, $method) {
        $php = unl($fn);
        $diff = '';
        if (preg_match("/^(.*?)function $method\([^\)]*\)\s*({.*)$/s", $php, $m)) {
            $n0 = substr_count($m[1], "\n");
            $br = Rare::bracket($m[2], '{');
            $sz = substr_count($br, "\n");
            $diff = str_repeat('=', $n0) . str_repeat('*', 1 + $sz);
        }
        return self::php($php, $diff);
    }

    static function xdata($in) {
        self::$bg ?? self::scheme(SKY::d('php_hl') ?: 'w_php');///////////////////////////////
        $cut = [-9, -9];
        if (is_array($in)) {
            $in[2] or $cut = [$in[0] - 8, $in[0] + 6];
            $in = str_pad('', $in[0] - 1, '=') . ($in[0] ? ($in[1] ? '-' : '+') : '');
        }
        return (object)[
            'lnum' => '',
            'disp' => 0,
            'diff' => $in,
            'cut' => $cut,
            '_' => '',
            'prev' => '',
            'invert' => false,
            'colors' => 0, # 0: RGY, 1: Y, 2: RG
        ];
    }

    static function highlight_line(&$val, $key, $x) {
        if (-9 != $x->cut[0] && $key < $x->cut[0])
            return $val = '';
        $pad = $c = '';
        $pt = $x->invert ? '+' : '.';
        if (!$key) for (; $x->len > $x->disp && $x->diff[$x->disp] == $pt; $x->disp++) {
            $x->lnum .= "<br>";
            $pad .= '<div class="code" style="background:' . self::$bg['.'] . '">&nbsp;</div>';
        }
        if ($x->len > $key + $x->disp) {
            $chr = $x->diff[$key + $x->disp];
            if ($x->invert)
                $chr = '.' == $chr ? '-' : ('+' == $chr ? '.' : $chr);
            if ($x->colors && '=' != $chr)
                $chr = 1 == $x->colors ? '*' : ($x->invert ? '-' : '+');
            $inner = $x->_[$key] ?? false;
            $c = self::$bg[$inner ? "_$chr" : $chr]; # = - + * .
        }
#        $val = $x->prev . $val;
#        if ($x->prev = preg_match("/(<[^\/][^>]+>)([^>]*)$/", $val, $m) ? $m[1] : '')
#            $val .= '</span>';
        $x->lnum .= str_pad(++$key, 3, 0, STR_PAD_LEFT) . "<br>";
        if ('' === $val)
            $val = '&nbsp;';
        $val = $c ? $pad . '<div class="code" style="background:' . "$c\">$val</div>" : "$pad$val\n";
        for (; $x->len > $key + $x->disp && $x->diff[$key + $x->disp] == $pt; $x->disp++) {
            $x->lnum .= "<br>";
            $val .= '<div class="code" style="background:' . self::$bg['.'] . '">&nbsp;</div>';
        }
    }

    static function lines($in, $scheme = 'z_php', $option = '', $u = '') {
        self::scheme($scheme);
        $x = self::xdata($option);
        $x->len = strlen($option);
        return self::table(is_array($in) ? $in : explode("\n", $in), $x, false, $u);
    }

    static function log($html) { # 2do
        if ($p = strrpos($html, '<a'))
            $html = substr($html, 0, $p);
        if ($p = strrpos($html, '<span'))
            $html = substr($html, 0, $p);
        return '<pre>' . $html . '</pre>';
    }
}
