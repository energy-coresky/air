<?php

class Saw
{
    const version = 0.888;

    static $file = '';
    static $line = 0;

    static function failed(string $error) {
        $fn = '' === self::$file ? '' : self::$file . ', ';
        throw new Error("Yaml error, {$fn}Line " . self::$line . ": $error");
    }

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

    static function yaml(string $in, $nofile = true) : array {
        $array = [];
        $p = ['' => &$array];
        $n = self::obj();
        $nofile or $in = file_get_contents(self::$file = $in);

        $add = function ($m) use (&$p) {
            $v = $m->json ? json_decode($m->json, true) : self::scalar($m->mod ? $m->val : trim($m->val));
            if ($m->json && json_last_error())
                self::failed('JSON failed');
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

        foreach (explode("\n", unl($in)) as $ln => $line) {
            $m = clone $n;
            self::$line = 1 + $ln;
            if (self::parse($line . ' ', $n))
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

    static function parse($in, &$n) {
        static $pad_0 = '', $pad_1;
        $pad = '';
        $szv = strlen($n->voc ? ($p =& $n->voc->val) : ($p =& $n->val));
        $k2 = $reqk = $ne = false;
        $w2 = $setk = true; # set key first

        for ($j = 0, $szl = strlen($in); $j < $szl; $j += $x) {
            if ($w = ' ' == $in[$j]) {
                $t = substr($in, $j, $x = strspn($in, ' ', $j));
            } elseif ($pad && !$reqk && '|' == $n->mod || '>' == $n->mod) {
                $t = substr($in, $j);
                $x = $szl;
            } elseif ('"' == $in[$j] || "'" == $in[$j]) {
                $x = Rare::str($in, $j) or self::failed('Incorrect string');
                $t = substr($in, $j, $x -= $j);
            } elseif ('#' == $in[$j] && $w2 && !$n->mod) {
                break; # cut comment
            } elseif (strpbrk($in[$j], '#:-|>{},[]')) {
                $t = $in[$j];
                $x = 1;
            } else {
                $t = substr($in, $j, $x = strcspn($in, '"\' #:-|>{},[]', $j));
            }
            $w2 = $w;

            if (!$j) { # first step
                $w ? ($pad = $t) : ($ne = $p .= $t);
                $reqk = $pad <= $pad_0; # require match key
                if (!$reqk && '|' == $n->mod)
                    '' === $p ? ($pad_1 = strlen($pad)) : ($p .= "\n" . substr($pad, $pad_1));
            } elseif ($w && $setk && $k2 && ($reqk || !$n->mod)) { # key found
                if (0)
                    self::failed('Mapping disabled');
                $setk = false;
                $sps = $t;
                $n = self::obj([
                    'pad' => $pad_0 = $pad,
                    'key' => $c2 ? self::scalar(substr($p, ($char ?? 0) + $szv, -1)) : true,
                ]);
                $p =& $n->val;
            } elseif ($w && true === $n->key && $c2 && !$n->voc) { # vocabulary
                $n->voc = self::obj([
                    'mod' => &$n->mod,
                    'pad' => "$n->pad $sps",
                    'key' => substr($p, 0, -1),
                ]);
                $p =& $n->voc->val;
            } elseif ($n->json && 1 == strlen($t) && !$reqk && strpbrk($t, '[]{},:')) {
                $n->json .= '' === ($p = trim($p)) ? $t : self::scalar($p, true, ':' != $t) . $t;
                $p = '';
            } elseif ('' === $p && ('{' == $t || '[' == $t) && !$n->mod) {
                $n->mod = $n->json = $t;
                $reqk = false;
            } else {
                if ($rule = !$reqk && '' !== $p && !$ne && '|' != $n->mod)
                    $char = 1;
                $p .= $rule ? " $t" : $t;
                $ne = true;
            }
            $k2 = ($c2 = ':' == $t) || '-' == $t;
        }

        if ($setk) {
            if ($reqk && $ne)
                self::failed('Cannot match key');
            if ($p && ' ' == $p[-1])
                $p = substr($p, 0, -1);
        } else {
            $p = rtrim($p);
            if ('|' == $p || '>' == $p) {
                $n->mod = $p;
                $p = '';
            }
        }
        return $setk;
    }

    static function scalar(string $in, $json = false, $notkey = true) {
        if ('' === $in || 'null' === $in || '~' === $in)
            return $json ? 'null' : null;
        $true = 'true' === $in;
        if ($true || 'false' === $in)
            return $json ? $in : $true;
        if ('"' == $in[0] && '"' == $in[-1])
            return $json ? $in : substr($in, 1, -1);
        if ("'" == $in[0] && "'" == $in[-1])
            return $json ? '"' . substr($in, 1, -1) . '"' : substr($in, 1, -1);
        if ($notkey && is_numeric($in))
            return $json ? $in : (is_num($in) ? (int)$in : (float)$in);
        return $json ? '"' . $in . '"' : $in;
    }
}
