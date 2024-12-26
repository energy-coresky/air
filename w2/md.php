<?php

class MD # the MarkDown
{
    const version = '0.121';
    const type = ['jet', 'php', 'css', 'js', 'html', 'yaml', 'bash']; // 2do zml

    private $hightlight;

    function __toString() {
        $out = $h16 = $paragraph = '';
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
            } elseif (8 == $y->tok && (!$this->hightlight || !in_array($type, self::type))) {
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
            } elseif (15 == $y->tok) {
                $out .= a($t, $t); # auto-link
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

    function references($y) {
        preg_match_all("/(\[\w+\]):([^\n]+)(\n|\z)/s", $this->in, $matches, PREG_SET_ORDER);
        $y->ref = [];
        foreach ($matches as $m)
            $y->ref[$m[1]] = trim($m[2]);
        $y->tok = $y->n = 0;
        $y->pv = "\n";
    }

    function tokens() {
        $y = new stdClass;
        $this->references($y);
        $len = strlen($in =& $this->in);
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
                } elseif (("!" == $t && "[" == $t2 || "[" == $t) && $this->square($t, $j, $y)) {
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

    function inline_url(&$t, $j, $y) {
        if (!preg_match("/\bhttps?$/ui", $t, $m1))
            return false;
        if (!preg_match("/^:\/\/[^\s<]+\b\/*/ui", substr($this->in, $j + strlen($t)), $m2)) //2do substr
            return false;
        $t = substr($t, 0, $m1 = -strlen($m1[0]));
        $y->n = strlen($m2[0]) - $m1;
        return true;
    }

    function square(&$t, $j, $y) {
        if ($return = preg_match("/!?\[([^\]\n]+)\](\([^\)\n]+\)|\[[^\]\n]+\])/s", $this->in, $match, 0, $j)) {
            [$t, $y->m[0], $y->m[1]] = $match;
            $y->tok = 14; # an img or link
        } elseif ('!' == $t) {
            return false;
        } elseif ($return = preg_match("/\[\^\w+\]:/", $this->in, $match, 0, $j)) {
            [$t, $y->tok] = [$match[0], 22]; # footnote def
        } elseif ($return = preg_match("/\[\w+\]:[^\n]+/s", $this->in, $match, 0, $j)) {
            [$t, $y->tok] = [$match[0], 16]; # reference
        }
        return $return;
    }

    function __construct(string $in, $hightlight = false) {
        $this->hightlight = $hightlight;
        $this->in = unl($in);
    }

    const char = '\\`*_{}[]()#+-.!|^=~:'; //    >
}

__halt_compiler();

#.md '[':a !:img `:code ```:pre-code
'#': h1  =============
~~: s
~: sub
^: sup
**: strong
*:  em
==: mark
---: hr
>:  blockquote
|:  table
'[^1]': footnote
':': dl dt dd
#.md
