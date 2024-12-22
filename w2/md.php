<?php

class MD # the MarkDown
{
    const version = '0.101';
    const char = '*_#|\\`-=';

    function __toString() {
        $out = $close = '';
        foreach ($this->tokens() as $t => $y) {
            if ($y->tok < 7) {
                $close = "</h$y->tok>";
                $out .= "<h$y->tok>";
            } elseif (7 == $y->tok) {
                $out .= '</p>';
                $type = trim($t, "\n`");
            } elseif (8 == $y->tok) {
                $out .= Display::$type($t);
            } elseif (9 == $y->tok) {
                #$out .= '</pre>';
            } elseif (11 == $y->tok) {
                if ($close) {
                    $out .= $close;
                    $close = '';
                } else {
                    $out .= "\n\n" == $t ? '<p>' : ' ';
                }
            } else {
                $out .= $t;
            }
        }
        return $out;
    }

    function tokens() {
        $y = (object)['tok' => 0, 'pv' => "\n", 'n' => 0];
        $ws = fn($_) => in_array($_, [' ', "\t", "\n"], true);
        $len = strlen($in =& $this->in);
        for ($j = 0, $t = ''; $j < $len; $j += strlen($t)) {
            $y->pv = $t;
            $y->tok = 10;
            if ($y->n) {
                $t = substr($in, $j, $y->n);
                $y->tok = "\n```" == $t ? 9 : 8;
                $y->n = 9 == $y->tok ? 0 : 4;
            } elseif ("\n" == ($t = $in[$j])) {
                $t = substr($in, $j, strspn($in, "\n \t", $j));
                $y->tok = 11;
            } elseif ("\\" == $t) {
                $t .= $in[1 + $j] ?? '';
            } elseif (strpbrk($t, MD::char)) {
                $t = substr($in, $j, strspn($in, $t, $j));
                if ("\n" == $y->pv[-1]) {
                    if ('```' == $t && ($pos = strpos($in, "\n$t", $j))) {
                        $t = substr($in, $j, $p = strpos($in, "\n", $j) - $j + 1);
                        $y->n = $pos - $j - $p;
                        $y->tok = 7;
                    } elseif ('#' == $t[0]) {
                        $y->tok = strlen($t);
                    }
                }
                
            } else {
                $t = substr($in, $j, strcspn($in, "\n" . MD::char, $j));
            }
            
            yield $t => $y;
        }
    }

    function __construct(string $in) {
        $this->in = unl($in);
    }
}
