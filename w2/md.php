<?php

class MD # the MarkDown
{
    const version = '0.121';

    static $md;

    private $hightlight;
    private $array = [];
    private $ref = [];
    private $j = 0;
    private $in;

    function __toString() {
        $out = $h16 = $paragraph = '';
        $stk = [];
        foreach ($this->tokens() as $t => $y) {
            if ($y->tok < 7) { # h1 h2 h3 h4 h5 h6
                if ($paragraph)
                    $out .= "</p>";
                $paragraph = false;
                $h16 = "</h$y->tok>";
                $out .= "<h$y->tok>";
            } elseif (7 == $y->tok) { # fenced code start
                if ($paragraph)
                    $out .= "</p>";
                $paragraph = false;
                'javascript' !== $type = trim($t, "\n `") or $type = 'js';
                $is_n = $y->n;
            } elseif (8 == $y->tok && (!$this->hightlight || !in_array($type, MD::$md->md_type))) {
                $is_n or $t = preg_replace("/(\A|\n) {4}/s", "$1", $t);
                $out .= pre(tag($t, $type ? 'class="language-' . $type . '"' : '', 'code'));
            } elseif (8 == $y->tok) {
                $out .= Display::$type($t);
            } elseif (10 == $y->tok) {
                $out .= tag(substr($t, 1, -1), '', 'code'); # inline code
            } elseif (12 == $y->tok) { # NL + space
                $out .= $h16 ?: ("\n" == $t ? ' ' : '');
                $h16 = false;
            } elseif (14 == $y->tok) {
                $y->m[1] = '[' != $y->m[1][0] ? substr($y->m[1], 1, -1) : $y->ref[$y->m[1]];
                $t = '!' == $t[0]
                    ? sprintf('<img src="%s" alt="%s">', $y->m[1], $y->m[0]) # image
                    : a($y->m[0], $y->m[1]); # link
                goto paragraph;
            } elseif (15 == $y->tok) { # auto-link
                $out .= a($t, $t);
            //} elseif (12 == $y->tok) {
            } elseif (!in_array($y->tok, [9, 16])) { # not reference
                if (13 == $y->tok)
                    $t = rtrim($t) . '<br>';
                paragraph:
                if ("\n\n" == $y->pv && '' !== trim($t)) {
                    if ($paragraph)
                        $out .= "</p>";
                    $out .= "<p>$t";
                    $paragraph = true;
                } else {
                    $out .= $t;
                }
            }
        }
        //return html($out);
        return $out;
    }

    function _tokens(&$x = null) {
        $y = new stdClass;
        //$y->tok = 0;
        $y->pv = '';
        foreach ($this->parse($x) as $ary) {
            [$y->tok, $t] = $ary;
            yield $t => $y;
            $y->pv = $t;
        }
    }

    function spn($set, $j = 0) {
        return substr($this->in, $j ?: $this->j, strspn($this->in, $set, $j ?: $this->j));
    }

    function cspn($set, $j = 0) {
        return substr($this->in, $j ?: $this->j, strcspn($this->in, $set, $j ?: $this->j));
    }

    function new_line(&$t, &$nl) {
        static $pad = 0, $depth = 0;
        static $fenced = false;
        $in =& $this->in;
        $j =& $this->j;

        /** new line */
        if ("\n" == $t[0]) {
            $pad = 0;
            return [12, $t = chop($t, " \t")];
        /** fenced code start-end */
        } elseif (preg_match("/^```(\w*)\n/s", $ln15 = substr($in, $j, 15), $m)) {
            $fenced = !$fenced;
            return [$fenced ? 7 : 9, $t = substr($m[0], 0, -1)];
        /** fenced code body */
        } elseif ($fenced) {
            return [8, $t = $this->cspn("\n")];
        /** indent */
        } elseif (strpbrk($t, " \t")) {
            $t = $this->spn(" \t");
            $pad += strlen(str_replace("\t", '1234', $t));
            if ($pad > 3)
                ;
            return [22, $t, $pad];
        /** blockquote */
        } elseif ('>' == $t) {
            return [31, $t, ++$pad];
        /** ul list */
        } elseif (strpbrk($t, '+-*') && ' ' == ($in[1 + $j] ?? '')) {
            return [32, $t .= ' ', $pad += 2];
        /** ol list */
        } elseif (preg_match("/^(\d{1,10})(\.|\)) /", $ln15, $m)) {
            return [33, $t = $m[0], $pad += strlen($t), $m];
        /** [reference]: or [url].. */
        } elseif ('[' == $t && ($a = $this->square($j, $tok))) {
            $nl = false;
            if (16 == $tok)
                $this->ref[$a[2]] = trim(substr($a[3], 1));
            return [$tok, $t = $a[2] . $a[3]] + $a;
        } else {
            return $nl = false;
        }
    }

    function square($j, &$tok) {
        if (!$len = strlen($head = Rare::bracket($this->in, '[', $j, "\n\\")))
            return false;
        $tok = 14;
        if (in_array($chr = $this->in[$j += $len], ['[', '('])) {
            $tail = Rare::bracket($this->in, $chr, $j, "\n\\");
        } elseif (':' == $chr) {
            if (':' == trim($tail = $this->cspn("\n", $j)))
                return false;
            $tok = 16;
        }
        return [2 => $head, $tail ?? ''];
    }

    # h1============= |table :dl-dt-dd
    function parse(&$x = null) { # lists: + - *
        $x = new stdClass;
        $x->mark = 0;
        $len = $nl = strlen($in =& $this->in);
        $j =& $this->j;
        $char = [
            "\\`*_{}[]()<>#+-.!|^=~:",
        ];
        $chr =& $char[0];
        $ary =& $this->array;
        for ($t = ''; $j < $len; $j += strlen($t)) {
            if ("\n" == ($t = $in[$j])) {
                $t = $this->spn($nl = "\n \t");
                $ary[] = $this->new_line($t, $nl);
            } elseif ($nl && ($tok = $this->new_line($t, $nl))) {
                $ary[] = $tok;
            } elseif ("\\" == $t) { # escaped
                $nl = false;
                $ary[] = [11, $t .= $in[1 + $j] ?? ''];
            } elseif (strpbrk($t, $chr)) {
                $ary[] = [11, $t = $this->spn($t)];
            } else {
                $t = substr($in, $j, $n = strcspn($in, "\n$chr", $j));
                if ('  ' == substr($t, -2) && "\n" == ($in[$j + strlen($t)] ?? '')) {
                    $ary[] = [13, $t];
                } elseif (':' == ($in[$j + $n] ?? '') && $this->auto_url($t, $j)) {
                    $ary[] = [15, $t];
                } else {
                    $ary[] = [11, $t];
                }
            }
        }
        return $ary;
    }

    function auto_url(&$t, $j) {
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

    function inline_url(&$t, $j, $y) {
        if (!preg_match("/\bhttps?$/ui", $t, $m1))
            return false;
        if (!preg_match("/^:\/\/[^\s<]+\b\/*/ui", substr($this->in, $j + strlen($t)), $m2)) //2do substr
            return false;
        $t = substr($t, 0, $m1 = -strlen($m1[0]));
        $y->n = strlen($m2[0]) - $m1;
        return true;
    }

    function tokens() {
        $y = new stdClass;
        $len = strlen($in =& $this->in);
        preg_match_all("/(\[\w+\]):([^\n]+)(\n|\z)/s", $in, $matches, PREG_SET_ORDER);
        $y->ref = [];
        foreach ($matches as $m)
            $y->ref[$m[1]] = trim($m[2]);
        $y->tok = $y->n = 0;
        $y->pv = "\n";
        for ($j = 0, $t = ''; $j < $len; $j += strlen($t)) {
            if ($y->n) {
                start:
                $t = substr($in, $j, $y->n);
                if ($y->ntok ?? 0) {
                    $y->tok = $y->ntok;
                    $y->n = $y->ntok = 0;
                } else {
                    $y->tok = "\n```" == $t ? 9 : 8;
                    $y->n = 9 == $y->tok ? 0 : 4;
                }
            } elseif (7 == $y->tok) { # multiline code by 4-indent
                $y->tok = 8;
                for ($t = $nl = '', $i = $j; $i < $len; $i += $sz) {
                    $tt = substr($in, $i, $sz = strcspn($in, "\n", $i));
                    if ('    ' != substr($tt, 0, 4))
                        break;
                    $t .= $nl . $tt;
                    $nl = substr($in, $i += $sz, $sz = strspn($in, "\n", $i));
                }
            } elseif ("\n" == ($t = $in[$j])) {
                $t = substr($in, $j, $sz = strspn($in, "\n \t", $j));
                $y->tok = 12;
                if ('    ' == substr($t, -4) && "\n" != ($in[$j + $sz] ?? '')) {
                    $y->tok = 7;
                    $t = substr($t, 0, -4);
                }
            } elseif (strpbrk($t2 = $in[1 + $j] ?? '', MD::char) && "\\" == $t) {
                $y->tok = 11;
                $t .= $t2; # escaped
            } elseif (strpbrk($t, MD::char)) {
                $y->tok = 11;
                $t = substr($in, $j, strspn($in, $t, $j));
                if ("`" == $t && ($p = Rare::str($in, $j, $len))) {
                    $t = substr($in, $j, $p - $j);
                    $y->tok = 10;
                } elseif (("!" == $t && "[" == $t2 || "[" == $t) && $this->_square($t, $j, $y)) {
                    ;
                } elseif ("\n" == $y->pv[-1]) {
                    if ('```' == $t && ($pos = strpos($in, "\n$t", $j))) { # fenced code
                        $t = substr($in, $j, $p = strpos($in, "\n", $j) - $j + 1);
                        $y->n = $pos - $j - $p;
                        $y->tok = 7;
                    } elseif ('#' == $t[0] && ($sz = strlen($t)) < 7) {
                        $y->tok = $sz;
                    }
                }
            } else {
                $y->tok = 11;
                $t = substr($in, $j, $n = strcspn($in, "\n" . MD::char, $j));
                if ('  ' == substr($t, -2) && "\n" == ($in[$j + strlen($t)] ?? '')) {
                    $y->tok = 13;
                } elseif (':' == ($in[$j + $n] ?? '') && $this->inline_url($t, $j, $y)) {
                    $y->ntok = 15;
                    if ('' === $t && $y->n)
                        goto start;
                }
            }
            yield $t => $y;
            $y->pv = $t;
        }
    }

    function _square(&$t, $j, $y) {
        if ($return = preg_match("/!?\[([^\]\n]+)\](\([^\)\n]+\)|\[[^\]\n]+\])/s", $this->in, $match, 0, $j)) {
            [$t, $y->m[0], $y->m[1]] = $match;
            $y->tok = 14; # an img or link
        } elseif ('!' == $t) {
            return false;
        } elseif ($return = preg_match("/\[\^\w+\]:/", $this->in, $match, 0, $j)) {
            [$t, $y->tok] = [$match[0], 17]; # footnote def
        } elseif ($return = preg_match("/\[\w+\]:[^\n]+/s", $this->in, $match, 0, $j)) {
            [$t, $y->tok] = [$match[0], 16]; # reference
        }
        return $return;
    }

    function __construct(string $in, $hightlight = false) {
        MD::$md or MD::$md = Plan::set('main', fn() => yml('md', '+ @object @inc(md)'));
        $this->hightlight = $hightlight;
        $this->in = unl($in);
    }

    const char = '\\`*_{}[]()#+-.!|^=~:'; //    >
}



