<?php

class MD # the MarkDown
{
    const version = '0.121';

    function __toString() {
        $out = $h16 = $code = '';
        foreach ($this->tokens() as $t => $y) {
            if ($y->tok < 7) {
                $h16 = "</h$y->tok>";
                $out .= "<h$y->tok>";
            } elseif (7 == $y->tok) {
                $out .= '</p>';
                $type = trim($t, "\n`");
            } elseif (8 == $y->tok) {
                $out .= Display::$type($t);
            } elseif (9 == $y->tok) {
                #$out .= '</pre>';
            } elseif (10 == $y->tok) {
                $out .= $code ? '</code>' : '<code>';
                $code = !$code;
            } elseif (12 == $y->tok) { # NL space
                if ($h16)
                    $out .= $h16;
                $h16 = '';
            } elseif (14 == $y->tok) {
                $chr = $y->m[1][0];
                $y->m[1] = substr($y->m[1], 1, -1);
                $out .= '!' == $t[0]
                    ? sprintf('<img src="%s" alt="%s">', $y->m[1], $y->m[0]) # image
                    : a($y->m[0], $y->m[1]); # link
            } elseif (15 == $y->tok) {
                $out .= a($t, $t); # auto-link
            //} elseif (12 == $y->tok) {
            } else {
                if (13 == $y->tok)
                    $t = rtrim($t) . '<br>';
                $out .= "\n\n" == $y->pv ? "<p>$t" : $t;
            }
        }
        return $out;
    }

    function tokens() {
        $y = (object)['tok' => 0, 'pv' => "\n", 'n' => 0];
        $len = strlen($in =& $this->in);
        for ($j = 0, $t = ''; $j < $len; $j += strlen($t)) {
            $y->pv = $t;
            $y->tok = 11;
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
            } elseif ("\n" == ($t = $in[$j])) {
                $t = substr($in, $j, strspn($in, "\n \t", $j));
                $y->tok = 12;
            } elseif (strpbrk($t2 = $in[1 + $j] ?? '', MD::char) && "\\" == $t) {
                $t .= $t2; # escaped
            } elseif (strpbrk($t, MD::char)) {
                $t = substr($in, $j, strspn($in, $t, $j));
                if ("`" == $t) {
                    $y->tok = 10;
                } elseif (("!" == $t && "[" == $t2 || "[" == $t) && $this->square($t, $j, $y->m)) {
                    $y->tok = 14; # an img or link
                } elseif ("\n" == $y->pv[-1]) {
                    if ('```' == $t && ($pos = strpos($in, "\n$t", $j))) {
                        $t = substr($in, $j, $p = strpos($in, "\n", $j) - $j + 1);
                        $y->n = $pos - $j - $p;
                        $y->tok = 7;
                    } elseif ('#' == $t[0] && ($sz = strlen($t)) < 7) {
                        $y->tok = $sz;
                    }
                }
            } else {
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

    function square(&$t, $j, &$m) {
        $re = "!?\[([^\]\n]+)\](\([^\)\n]+\)|\[[^\]\n]+\])";
        if (!preg_match("/$re/s", $this->in, $match, 0, $j))
            return false;
        [$t, $m[0], $m[1]] = $match;
        return true;
    }

    function __construct(string $in) {
        $this->in = unl($in);
    }

    const char = '\\`*_{}[]()#+-.!|^=~:'; //    >
}

__halt_compiler();

#.md
'#': h1
~~: s
~: sub
^: sup
!:  img
**: strong
*:  em
>:  blockquote
`:  code
---: hr
!:  img
'[': a
|:  table
```: pre code
'[^1]': footnote
':': dl dt dd
==: mark

#.md
