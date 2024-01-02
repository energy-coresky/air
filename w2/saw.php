<?php

class Saw
{
    static function obj($in = []) {
        $in = (array)$in + ['m' => '', 'p' => '', 'k' => '', 'v' => '', 'voc' => false];
        return (object)$in;
    }

    static function yaml(string $in, int $tab = 4) {
        $array = [];
        $p = ['' => &$array];
        $add = function ($m) use (&$p) {
            if (array_key_exists($m->p, $p)) {
                array_splice($p, 1 + array_flip(array_keys($p))[$m->p]);
                $z =& $p[$m->p];
            } else {
                $lt = array_key_last($p);
                $z =& $p[$lt][array_key_last($p[$lt])];
            }
            true === $m->k ? ($z[] = $m->v) : ($z[$m->k] = $m->v);
            $p[$m->p] =& $z;
        };

        $tab = str_pad('', $tab, ' ');
        $n = self::obj();
        foreach (explode("\n", unl($in)) as $line) {
            $m = self::obj($n);
            if (self::parse($line, $tab, $m, $n))
                continue;
            '' === $m->k or $add($m);
            if ($n->voc) {
                $n->v = [];
                $add($n); # vocabulary: - key: val
                $n = $n->voc;
            }
        }
        '' === $n->k or $add($n);

        return $array;
    }

    static function parse(string $in, $tab, &$m, &$n) {
        static $json = false, $pad_0 = '', $pad_1;
        $pad = '';
        $tabs = fn($s) => str_replace("\t", $tab, $s);
        $w2 = $is_k = true;
        $k2 = $reqk = false;
        $len = strlen($n->voc ? ($p =& $n->voc->v) : ($p =& $n->v));
        if ('' === trim($in))
            return true;

        foreach (token_get_all("<?php $in ") as $i => $s) {
            if (!$i)
                continue;
            [$t, $s] = is_array($s) ? $s : [0, $s];
            if ($w2 && T_COMMENT == $t && '#' == $s[0])
                continue;

            $w = T_WHITESPACE == $t;
            if (1 == $i) { # first step
                $w ? ($pad = $tabs($s)) : ($p .= $s);
                if ($reqk = $pad <= $pad_0) # require match key
                    $pad_0 = $pad;
                if (!$reqk && '|' == $n->m)
                    '' === $p ? ($pad_1 = strlen($pad)) : ($p .= "\n" . substr($pad, $pad_1));
            } elseif ($w && $is_k && $k2 && ($reqk || !$n->m)) { # key found
                if (0)
                    throw new Error('Yaml error (Mapping disabled)');
                $m->v = $json ? json_decode($json, true) : self::scalar($m->m ? $m->v : trim($m->v));
                if ($json && json_last_error())
                    throw new Error('Yaml error (json)');
                $json = $is_k = false;
                $sps = $s;
                $n = self::obj([
                    'p' => $pad_0 = $pad,
                    'k' => $c2 ? substr($p, $len, -1) : true,
                ]);
                $p =& $n->v;
            } elseif ($w && true === $n->k && $c2 && !$is_k && !$n->voc) { # vocabulary
                $n->voc = self::obj([
                    'm' => &$n->m,
                    'p' => $n->p . ' ' . $tabs($sps),
                    'k' => substr($p, 0, -1),
                ]);
                $p =& $n->voc->v;
            } elseif ($json && 1 == strlen($s) && !$reqk && strpbrk($s, '[]{},:')) {
                $json .= '' === ($p = trim($p)) ? $s : self::scalar($p, true, ':' != $s) . $s;
                $p = '';
            } elseif ('' === $p && ('{' == $s || '[' == $s) && !$n->m) {
                $n->m = $json = $s;
                $reqk = false;
            } else {
                $p .= $s;
            }
            $k2 = ($c2 = ':' == $s) || '-' == $s;
            $w2 = $w;
        }

        if ($is_k) {
            if ($reqk)
                throw new Error('Yaml error (Cannot match key)');
        } else {
            $p = rtrim($p);
            if ('|' == $p || '>' == $p) {
                $n->m = $p;
                $p = '';
            }
        }
        //'' === $p or $p = substr($p, 0, -1);
        return $is_k;
    }

    static function scalar(string $in, $json = false, $notkey = true) {
        if ('' === $in || 'null' === $in)
            return $json ? 'null' : null;
        $true = 'true' === $in;
        if ($true || 'false' === $in)
            return $json ? $in : $true;
        if ('"' == $in[0])
            return $json ? $in : substr($in, 1, -1);
        if ("'" == $in[0])
            return $json ? '"' . substr($in, 1, -1) . '"' : substr($in, 1, -1);
        if ($notkey && is_numeric($in))
            return is_num($in) ? (int)$in : (float)$in;
        return $json ? '"' . $in . '"' : $in;
    }
     // T_CONSTANT_ENCAPSED_STRING  '      T_START_HEREDOC  T_END_HEREDOC
     // " T_ENCAPSED_AND_WHITESPACE [ - T_WHITESPACE       T_CONSTANT_ENCAPSED_STRING
}
