<?php

class Saw
{
    const version = 0.888;

    static function obj(array $in = []) : stdClass {
        $in += [
            'mod' => '',
            'pad' => '',
            'key' => '',
            'val' => '',
            'voc' => false,
            'json' => false,
        ];
        return (object)$in;
    }

    static function yaml(string $in, int $tab = 4) : array {
        $array = [];
        $p = ['' => &$array];
        $tab = str_pad('', $tab, ' ');
        $n = self::obj();

        $add = function ($m) use (&$p) {
            $v = $m->json ? json_decode($m->json, true) : self::scalar($m->mod ? $m->val : trim($m->val));
            if ($m->json && json_last_error())
                throw new Error('Yaml error (json)');
            if (array_key_exists($m->pad, $p)) {
                array_splice($p, 1 + array_flip(array_keys($p))[$m->pad]);
                $z =& $p[$m->pad];
            } else {
                $lt = array_key_last($p);
                $z =& $p[$lt][array_key_last($p[$lt])];
            }
            true === $m->key ? ($z[] = $v) : ($z[$m->key] = $v);
            $p[$m->pad] =& $z;
        };

        foreach (explode("\n", unl($in)) as $line) {
            $m = clone $n;
            if (self::parse($line, $tab, $n))
                continue;
            '' === $m->key or $add($m);
            if ($n->voc) {
                $n->val = null;
                $add($n); # vocabulary: - key: val
                $n = $n->voc;
            }
        }
        '' === $n->key or $add($n);

        return $array;
    }

    static function parse(string $in, $tab, &$n) {
        static $pad_0 = '', $pad_1;
        $pad = '';
        $tabs = fn($s) => str_replace("\t", $tab, $s);
        $w2 = $is_k = true;
        $k2 = $reqk = false;
        $len = strlen($n->voc ? ($p =& $n->voc->val) : ($p =& $n->val));
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
                $reqk = $pad <= $pad_0; # require match key
                if (!$reqk && '|' == $n->mod)
                    '' === $p ? ($pad_1 = strlen($pad)) : ($p .= "\n" . substr($pad, $pad_1));
            } elseif ($w && $is_k && $k2 && ($reqk || !$n->mod)) { # key found
                if (0)
                    throw new Error('Yaml error (Mapping disabled)');
                $is_k = false;
                $sps = $s;
                $n = self::obj([
                    'pad' => $pad_0 = $pad,
                    'key' => $c2 ? substr($p, $len, -1) : true,
                ]);
                $p =& $n->val;
            } elseif ($w && true === $n->key && $c2 && !$is_k && !$n->voc) { # vocabulary
                $n->voc = self::obj([
                    'mod' => &$n->mod,
                    'pad' => $n->pad . ' ' . $tabs($sps),
                    'key' => substr($p, 0, -1),
                ]);
                $p =& $n->voc->val;
            } elseif ($n->json && 1 == strlen($s) && !$reqk && strpbrk($s, '[]{},:')) {
                $n->json .= '' === ($p = trim($p)) ? $s : self::scalar($p, true, ':' != $s) . $s;
                $p = '';
            } elseif ('' === $p && ('{' == $s || '[' == $s) && !$n->mod) {
                $n->mod = $n->json = $s;
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
                $n->mod = $p;
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
