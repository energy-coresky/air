<?php

class MD # the MarkDown
{
    const version = '0.232';
    //const char = '\\`*_{}[]()#+-.!|^=~:'; //    >

    static $md;

    private $hightlight; # if true then work Display::php(..) for ```php
    private $tok = [];
    private $ref = [];
    private $for = [];
    private $j = 0;
    private $in;

    function __construct(string $in, $hightlight = false) {
        MD::$md or MD::$md = Plan::set('main', fn() => yml('md', '+ @object @inc(md)'));
        $this->hightlight = $hightlight;
        $this->in = unl($in);
    }

    private function spn($set, $j = 0) {
        return substr($this->in, $j ?: $this->j, strspn($this->in, $set, $j ?: $this->j));
    }

    private function cspn($set, $j = 0) {
        return substr($this->in, $j ?: $this->j, strcspn($this->in, $set, $j ?: $this->j));
    }

    private function blk_html($x, &$t) {
        if (!preg_match("/^<(\?|!|\/?[a-z][a-z\-]*)/i", $x->line, $m))
            return false;
        if ('?' == $m[1]) {
            $x->html = '?>';
        } elseif ('!' == $m[1]) {
            foreach (MD::$md->typ_1 as $k => $v)
                if ($k == substr($x->line, 0, strlen($k))) {
                    $x->html = $v;
                    $m[0] = $k;
                    goto end;
                }
        } elseif (in_array($v = strtolower($m[1]), MD::$md->typ_2)) {
            $x->html = "</$v>";
        } else {
            $x->html = "\nqqq\n";
            goto end;
            //2do return false;
        }
        end:
        return [21, $t = $m[0]];
    }

    private function blk_h2r($x, &$t) {
        if (!preg_match("/^\\{$t}[\\$t \t]*$/", $x->line))
            return false;
        $z = str_replace(["\t", ' '], '', $x->line);
        if ($z == chop($x->line) && $x->stk) {
            $h = '=' == $t ? 1 : ('-' == $t ? 2 : 0);
            if ($h && 20 == $x->tok[0]) { # h1== h2--
                array_pop($x->stk);
                $x->tok = [$h, $x->tok[1], "<h$h>" . substr($x->tok[2], 3)];
                return [$h, $t = $x->line, "</h$h>\n"];
            }
        }
        if ('=' == $t || strlen($z) < 3)
            return false;
        return [19, $t = $x->line, "<hr>\n"];
    }

    private function blk_p($x, &$t) {
        $id = !$x->stk ? 0 : $this->tok[end($x->stk)[1]][0];
        if (!preg_match("/^\S/", $t) || in_array($id, [92, 93, 94, 20]))
            return false;
        $x->stk[] = ["</p>\n", count($this->tok), $x->pad, ''];
        return [20, $t, '\\' == $t ? '<p>' : "<p>$t"];
    }

    private function blk_table($x, &$t) {
    }
    private function blk_dl($x, &$t) {# :dl-dt-dd
    }

    private function blk_h6($x, &$t) {
        if (!preg_match("/^(#{1,6})(\s+)\S/", $x->line, $m))
            return false;
        $close = 20 == $x->id ? $this->close_blk($x, $x->cnt - 1) : '';
        $sz = strlen($m[1]);
        $x->stk[] = ["</h$sz>\n", count($this->tok), $x->pad, $t];
        return [$sz, $t = $m[1] . $m[2], "$close<h$sz>"];
    }

    private function blk_bq($x, &$t) {
        $_t = ' ' == ($x->line[1] ?? '') ? ' ' : '';
        if ($x->pv && $t == $x->pv[3])
            return [95, $t .= $_t, '']; # continue bq
        $close = $x->id < 90 ? $this->close_blk($x, $x->cnt - 1) : '';
        $x->stk[] = ["</blockquote>\n", count($this->tok), $x->pad, $t];
        return [91, $t .= $_t, "$close<blockquote>\n"];
    }

    private function blk_ul($x, &$t) { // loose<p> tight
        if (!in_array($_t = $x->line[1] ?? '', [' ', ''], true))
            return false;
        $close = $this->close_blk($x, $x->v);
        if ($x->pv && $t == $x->pv[3])
            return [94, $t .= $_t, "$close</li><li>"];
        $x->stk[] = ["</li></ul>\n", count($this->tok), $x->pad, $t];
        return [92, $t .= $_t, "$close<ul><li>"];
    }

    private function blk_ol($x, &$t) {
        if (!preg_match("/^(\d{1,9})(\.|\)) /", $x->line, $m))
            return false;
        $close = $this->close_blk($x, $x->v);
        if ($x->pv && $m[2] == $x->pv[3])
            return [94, $t = $m[0], "$close</li><li>"];
        $x->stk[] = ["</li></ol>\n", count($this->tok), $x->pad, $m[2]];
        $start = 1 == $m[1] ? '' : ' start="' . $m[1] . '"';
        return [93, $t = $m[0], "$close<ol$start><li>"];
    }

    private function blk_fenced($x, &$t) {
        if ($x->grab) { # already open
            if (preg_match("/^$x->grab+\s*$/", $x->line)) {
                $x->grab = '';
                return [9, $t = $x->line, array_pop($x->stk)[0]];
            }
            return [$x->id = 8, $t = $x->line];
        } elseif (preg_match("/^($t{3,})\s*(\w*).*$/", $x->line, $m)) {
            $x->stk[] = ["</code></pre>\n", count($this->tok), $x->pad, $x->grab = $m[1]];
            $str = $m[2] ? "<pre><code class=\"language-$m[2]\">" : "<pre><code>";
            return [7, $t = $x->line, $str, $m[2]];
        }
        return false;
    }

    private function close_blk($x, $n = 0) {
        $close = '';
        for ($i = count($x->stk) - 1; $x->stk && $i >= $n; $i--)
            $close .= array_pop($x->stk)[0];
        $x->grab = '';
        $x->id = $x->stk ? $this->tok[end($x->stk)[1]][0] : 0;
        return $close;
    }

    private function new_line($x, &$t) {
        $x->cnt = count($x->stk);
        if ("\n" == $t[0]) {
            $x->pad = $x->v = 0;
            $n2 = strlen($t = chop($t, " \t")) > 1;
            if ($x->grab) # fenced indent-code html table?
                return [8, $t, 7 == $x->id ? '' : $t];
            if ($n2) {
                $htm = $this->close_blk($x);
            } elseif ($x->id && $x->id < 7) {
                $htm = $this->close_blk($x, count($x->stk) - 1);
            } else {
                $htm = "\n";
            }
            return [12, $t, $htm];
        } elseif (strpbrk($t, " \t")) {
            $t = $this->spn(" \t");
            $x->pad += strlen(str_replace("\t", '1234', $t));
            if ($x->grab)
                return [8, $t];
            if ($x->pad > 3)
                ;
            return [22, $t, ''];/** indent */
        } else {
            $x->line = $this->cspn("\n");
            if ($x->pv = $x->stk[$x->v++] ?? false) {
                $x->tok =& $this->tok[$x->pv[1]];
            }
            if ($x->grab) {
                if ('>' == $t && '>' == $x->pv[3]) {
                    return $this->blk_bq($x, $t);
                }
                return $this->blk_fenced($x, $t);
            }
            foreach (MD::$md->blk as $set => $func) {
                if ($set && !strpbrk($t, $set))
                    continue;
                if ($a = $this->$func($x, $t)) {
                    $a[0] > 90 or $x->nl = false;
                    $x->pad += strlen($t);
                    $x->id = $a[0];
                    if (0) {
                        $close = array_pop($x->stk)[0];
                        $a[2] = $close . $a[2];
                    }
                    return $a;
                }
            }
            return $x->nl = false;
        }
    }

    function &parse(&$x = null) { # lists: + - *
        $x = new stdClass;
        $x->grab = $x->html = $x->pad = $x->v = $x->id = 0;
        $x->stk = $x->tok = [];
        $len = $x->nl = strlen($in =& $this->in);
        $j =& $this->j;
        $char = [
            "\\`*_{}[]()<>#+-.!|^=~:",
        ];
        $chr =& $char[0];
        $tok =& $this->tok;
        for ($t = ''; $j < $len; $j += strlen($t)) {
            if ("\n" == ($t = $in[$j])) {
                $t = $this->spn($x->nl = "\n \t");
                $tok[] = $this->new_line($x, $t);
            } elseif ($x->nl && ($a = $this->new_line($x, $t))) {
                $tok[] = $a;
            } elseif ("\\" == $t) { # escaped
                $x->nl = false;
                $next = $in[1 + $j] ?? '';
                $tok[] = [11, $t .= $next, $next];
            } elseif ('[' == $t && ($a = $this->square($x, $t))) {
                $tok[] = $a;
            } elseif (strpbrk($t, $chr)) {
                $tok[] = [11, $t = $this->spn($t)];
            } else {
                $t = substr($in, $j, $n = strcspn($in, "\n$chr", $j));
                if ('  ' == substr($t, -2) && "\n" == ($in[$j + strlen($t)] ?? '')) {
                    $tok[] = [13, $t];
                } elseif (':' == ($in[$j + $n] ?? '') && $this->auto_link($t, $j)) {
                    $tok[] = [15, $t];
                } else {
                    $tok[] = [11, $t];
                }
            }
        }
        foreach ($this->for as $i => $for) {
            if (isset($this->ref[$for])) {
                $this->tok[$i][4] = $this->ref[$for];
            } else {
                $this->tok[$i][2] = $this->tok[$i][1];
                $this->tok[$i][0] = 11;
            }
        }
        $tok[] = [11, '', $this->close_blk($x)];
        return $tok;
    }

    private function auto_link(&$t, $j) {
        if (!preg_match("/\bhttps?$/ui", $t, $m1))
            return false;
        if (!preg_match("/^:\/\/[^\s<]+\b\/*/ui", substr($this->in, $j + strlen($t)), $m2)) //2do substr
            return false;
        if ($m1[0] !== $t) {
            $t = substr($t, 0, -strlen($m1[0]));
            return false;
        }
        $t .= $m2[0];
        return true;
    }

    private function square($x, &$t) { # 2do  footnote
        if (!$len = strlen($head = Rare::bracket($this->in, '[', $j = $this->j, "\n\\")))
            return false;
        $id = 14;
        $tail = '';
        if (in_array($chr = $this->in[$j += $len], ['[', '('])) {
            $tail = Rare::bracket($this->in, $chr, $j, "\n\\");
            if (!$tail || '[' == $chr)
                $this->for[count($this->tok)] = $tail && '[]' != $tail ? $tail : $head;
        } elseif (':' == $chr) {
            if (':' == trim($tail = $this->cspn("\n", $j)))
                return false;
            $id = 16;
            $this->ref[$head] = trim(substr($tail, 1));
        } else {
            $this->for[count($this->tok)] = $head;
        }
        $x->nl = false;
        return [$id, $t = $head . $tail, '', $head, $tail];
    }

    function __toString() {
        $out = $sub = '';
        foreach ($this->parse() as $ary) {
            [$id, $t, $htm, $p3, $p4] = $ary + [2 => $ary[1], '', ''];
            if (15 == $id) { # auto-link
                $out .= a($t, $t);
            } elseif (14 == $id) {
                if ('(' == $p4[0])
                    $p4 = substr($p4, 1, -1);
                $out .= '!' == $t[0]
                    ? sprintf('<img src="%s" alt="%s">', $p4, substr($p3, 1, -1)) # image
                    : a(substr($p3, 1, -1), $p4); # link
              # close leaf block [fenced|indent code|html]
              # open leaf block
            } elseif (7 == $id) {
                in_array($type = $p3, MD::$md->code_type) && $this->hightlight or $type = '';
                'javascript' !== $type or $type = 'js';
                $sub = $type ? '' : $htm;
            } elseif (8 == $id) {
                $sub .= $type ? $htm : html($htm);
            } elseif (9 == $id) {
                $out .= $type ? Display::$type($sub) : $sub . $htm;
            } else {
                $out .= $htm;
            }
            # not reference
            # fenced code start
        }
        return $out;
    }
}
