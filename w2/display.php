<?php

class Display
{
    static $me = false;
    const lay_l = '<table cellpadding="0" cellspacing="0" style="width:100%"><tr><td class="tdlnum code" style="width:10px">';
    const lay_m = '</td><td style="padding-left:1px;vertical-align:top">';
    const lay_r = '</td></tr></table>';

    static function jet($fn, $marker = '', $no_lines = false) {
        $s = function ($s, $c) {
            return '<span style="color:' . $c . '">' . html($s) . '</span>';
        };
        $in = $fn;
        $lnum = $inm = '';
        $ary = [""];
        $list = [];

        foreach (explode("\n", unl($in)) as $i => $line) {
            $lnum .= str_pad(1 + $i, 3, 0, STR_PAD_LEFT) . "<br>";
            $cnt = count($ary) - 1;
            if (preg_match("/^#\.([\.\w]+)(.*)$/", $line, $m)) {
                $line = $s('#.', '#090') . $s($m[1], 'red') . $s($m[2], '#b45309');
                $ary[] = ['<div class="code" style="background:#e7ebf2;">' . "$line</div>"];
                $ary[] = "";
                if (in_array($marker, explode('.', $m[1])))
                    $inm = !$inm;
                if ($inm)
                    $list[] = 2 + $cnt;
            } else {
                $ary[$cnt] .= "$line\n";
            }
        }
        $fu = function ($v) use ($s) {
            $out = '';
            while (preg_match('/^(.*?)(~|@|){([{!\-])(.*?)([\-!}])}(.*)$/s', $v, $m)) {
                $out .= html($m[1]);
                $v = $m[6];
                if ('@' == $m[2])
                    $out .= html("@{"."$m[3]$m[4]$m[5]"."}");
                elseif ('{' == $m[3])
                    $out .= $s($m[2] . "{{".$m[4]."}}", '#fff; background:#b45309');
                elseif ('!' == $m[3])
                    $out .= $s($m[2] . "{!".$m[4]."!}", '#fff; background:#777');
                else
                    $out .= $s($m[2] . "{-".$m[4]."-}", '#b45309'); # Jet comment
            }
            return $out . html($v);
        };
        foreach ($ary as $i => &$v) {
            if (is_array($v)) {
                $v = $v[0];
            } else {
                $out = '';
                while (preg_match('/^(.*?)(~|@|#)([a-z]+)(.*)$/s', $v, $m)) {
                    $out .= $fu($m[1]);
                    $v = substr($m[4], strlen($br = Rare::bracket($m[4])));
                    $out .= $s($m[2] . $m[3], '#' == $m[2] ? '#090' : '#00f');
                    if ($br && '`' != $br[1] && in_array($m[3], ['inc', 'use', 'block']))
                        $out .= $s($br, 'red');
                    elseif ($br)
                        $out .= $s($br, '#00b;font-weight:bold');
                }
                $v = $out . $fu($v);
                if (in_array($i, $list) || '' === $marker)
                    $v = '<div class="code" style="background:#ffd;">' . "$v</div>";
            }
        }
        $out = implode("", $ary);
        if ($out[-1] == "\n")
            $out = "\n" . $out;
        if ($no_lines)
            return '<pre style="margin:0;background:#ffd">' . $out . '</pre>';
        $table = self::lay_l . $lnum . self::lay_m . '<pre style="margin:0">' . $out . '</pre>' . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function php_method($fn, $method) {
        $php = unl($fn);
        $bc = '';
        if (preg_match("/^(.*?)function $method\([^\)]*\)\s*({.*)$/s", $php, $m)) {
            $n0 = substr_count($m[1], "\n");
            $br = Rare::bracket($m[2], '{');
            $sz = substr_count($br, "\n");
            $bc = str_repeat('=', $n0) . str_repeat('*', 1 + $sz);
        }
        return Display::php($php, $bc);
    }

    static function php($str, $bc = '', $no_lines = false) {
        self::$me or self::$me = new Display;
        $me = self::$me;
        if ($str === -1)
            return '';
        $me->lnum = '';
        $me->lenb = strlen($me->back = $bc);
        $me->disp = 0;
        if ($tag = preg_match("/^\s*[\$]\w+/sm", $str) || $no_lines)
            $str = "<?php $str";
        $str = str_replace(["\r", "\n", '<code>','</code>'], '', highlight_string($str, true));
        if ($tag)
            $str = preg_replace("|^(<span [^>]+>)<span [^>]+>&lt;\?php&nbsp;|", "$1", $str);
        $lines = explode('<br />', preg_replace("|^(<span [^>]+>)<br />|", "$1", $str));
        array_walk($lines, [$me, 'add_line_no']);
        if ($no_lines)
            return '<pre style="margin:0">' . implode('', $lines) . '</pre>';
        $table = self::lay_l . $me->lnum . self::lay_m . '<pre style="margin:0">' . implode('', $lines) . '</pre>' . self::lay_r;
        return sprintf('<div class="php">%s</div>', str_replace('%', '&#37;', $table));
    }

    private function add_line_no(&$val, $key) {
        $val = strtr($val, ['=TOP-TITLE=' => '&#61;TOP-TITLE&#61;']);
        $colors = ['' => '', '=' => '', '*' => 'ffd', '+' => 'dfd', '-' => 'fdd', '.' => 'eee'];
        $pad = $c = '';
        if (!$key) for(; $this->lenb > $key + $this->disp && $this->back[$key + $this->disp] == '.'; $this->disp++)
            $this->lnum .= "<br>" and $pad .= '<div class="code" style="background:#eee">&nbsp;</div>';
        if ($this->lenb > $key + $this->disp)
            $c = $colors[$this->back[$key + $this->disp]];
        $key++;
        $this->lnum .= str_pad($key, 3, 0, STR_PAD_LEFT) . "<br>";
        if ($val === '')
            $val = '&nbsp;';
        elseif ($val == '</span>')
            $val = '&nbsp;</span>';
        elseif ($val == '</span></span>')
            $val = '&nbsp;</span></span>';
        $val = $pad . ($c ? '<div class="code" style="background:'."#$c\">$val</div>" : "$val\n");
        for (; $this->lenb > $key + $this->disp && $this->back[$key + $this->disp] == '.'; $this->disp++) { # = - + * .
            $this->lnum .= "<br>";
            $val .= '<div class="code" style="background:#eee">&nbsp;</div>';
        }
    }
}
