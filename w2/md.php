<?php

class MD # the MarkDown
{
    const version = '0.232';
    //const char = '\\`*_{}[]()#+-.!|^=~:'; //    >

    static $md;

    private $hightlight;
    private $tok = [];
    private $ref = [];
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
            $close = '/' == $v[0] ? '/' : '';
            $y = $close ? $v[1] : $v[0];
            if (isset(MD::$md->tags[$y])) {
                foreach (MD::$md->tags[$y] as $tag)
                    if ($v == $close . $tag) {
                        $x->html = "\ntags\n";
                        goto end;
                    }
            }
            $x->html = "\nqqq\n";
            goto end;
            //2do return false;
        }
        end:
        $x->nl = false;
        return [99, $t = $m[0]];
    }

    private function blk_fenced($x, &$t) {
        if (preg_match("/^$t$t$t(\w*)$/", $x->line, $m)) {
            if ($m[1] || $x->fenced !== $t) {
                $str = $x->fenced ? '</code></pre>' : '';
                $str .= $m[1] ? "<pre><code class=\"language-$m[1]\">" : "<pre><code>";
                $x->fenced = $t;
                return [7, $t = $x->line, $str, $m[1]];
            } else {
                $x->fenced = 0;
                return [9, $t = $x->line, '</code></pre>'];
            }
        } elseif ($x->fenced) { # continue
            return [8, $t = $x->line, false];
        } else {
            return false;
        }
    }

    private function blk_bq($x, &$t) {
        return [31, $t, '<blockquote>'];
    }

    private function blk_h6($x, &$t) {
        if (!preg_match("/^(#{1,6})\s+(\S.*?)\s*$/", $x->line, $m))
            return false;
        return [$sz = strlen($m[1]), $t = $x->line, "<h$sz>$m[2]</h$sz>"];
    }

    private function blk_h2r($x, &$t) {
        if (!preg_match("/^\\{$t}[\\$t \t]*$/", $x->line))
            return false;
        $z = str_replace(["\t", ' '], '', $x->line);
        if ($z == chop($x->line)) {
            if ('=' == $t)
                return [1, $t = $x->line, '</h1>'];
            if ('-' == $t)
                return [2, $t = $x->line, '</h2>'];
        }
        if ('=' == $t || strlen($z) < 3)
            return false;
        return [19, $t = $x->line, '<hr>'];
    }

    private function blk_ul($x, &$t) {
        if (' ' != ($x->line[1] ?? ''))
            return false;
        return [32, $t .= ' ', '<ul>'];
    }

    private function blk_table($x, &$t) {
    }
    private function blk_dl($x, &$t) {# :dl-dt-dd
    }

    private function blk_ol($x, &$t) {
        if (!preg_match("/^(\d{1,10})(\.|\)) /", $x->line, $m))
            return false;
        return [33, $t = $m[0], "<ol start=\"$m[1]\">"];
    }

    private function new_line($x, &$t) {
        static $pad = 0, $stk = [];
        static $fenced = false, $n2 = false;

        $in =& $this->in;
        $j =& $this->j;

        if ("\n" == $t[0]) {
            $pad = 0;
            $n2 = strlen($t = chop($t, " \t")) > 1;
            return [12, $t];
        } elseif ('[' == $t && ($a = $this->square($j, $id))) {
            $x->nl = false;
            if (16 == $id)
                $this->ref[$a[3]] = trim(substr($a[4], 1));
            return [$id, $t = $a[3] . $a[4]] + $a;
        } elseif (strpbrk($t, " \t")) {/** indent */
            $t = $this->spn(" \t");
            $pad += strlen(str_replace("\t", '1234', $t));
            if ($pad > 3)
                ;
            return [22, $t, ''];
        } else {
            $x->line = $this->cspn("\n");
            foreach (MD::$md->blk as $set => $func) {
                if (strpbrk($t, $set) && ($a = $this->$func($x, $t))) {
                    //$x->nl = false;
                    //$pad += strlen($t);
                    return $a;
                }
            }
            return $x->nl = false;
        }
    }

    function &parse(&$x = null) { # lists: + - *
        $x = new stdClass;
        $x->fenced = 0;
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
                $tok[] = [11, $t .= $in[1 + $j] ?? ''];
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

    private function square($j, &$id) {
        if (!$len = strlen($head = Rare::bracket($this->in, '[', $j, "\n\\"))) # 2do  footnote
            return false;
        $id = 14;
        if (in_array($chr = $this->in[$j += $len], ['[', '('])) {
            $tail = Rare::bracket($this->in, $chr, $j, "\n\\");
        } elseif (':' == $chr) {
            if (':' == trim($tail = $this->cspn("\n", $j)))
                return false;
            $id = 16;
        }
        return [2 => '', $head, $tail ?? ''];
    }

    function __toString() {
        $out = $tmp = '';
        foreach ($this->parse() as $ary) {
            [$tok, $t, $htm, $p3, $p4] = $ary + [2 => $ary[1], 0, 0];
            if (15 == $tok) { # auto-link
                $out .= a($t, $t);
            } elseif (14 == $tok) {
                if ('' === $p4 || '[]' === $p4)
                    $p4 = $p3;
                $p4 = '[' != $p4[0] ? substr($p4, 1, -1) : $this->ref[$p4];
                $out .= '!' == $t[0]
                    ? sprintf('<img src="%s" alt="%s">', $p4, substr($p3, 1, -1)) # image
                    : a(substr($p3, 1, -1), $p4); # link
            } elseif (true === $htm && $tok > 90) { # close leaf block
                $out .= $type ? Display::$type($tmp) : $tmp;
            } elseif (true === $htm) { # open leaf block
                //(!$this->hightlight || !in_array($type, MD::$md->code_type))
                //$out .= pre(tag($t, $type ? 'class="language-' . $type . '"' : '', 'code'));
                # 'javascript' !== $type = trim($t, "\n `") or $type = 'js';
                $tmp = '';
            } elseif (false === $htm) {
                $tmp .= $t;
            } else {
                $out .= $htm;
            }
            //$is_n or $t = preg_replace("/(\A|\n) {4}/s", "$1", $t);
            //$out .= tag(substr($t, 1, -1), '', 'code'); # inline code
            # not reference
            # fenced code start
        }
        return $out;
    }
}
