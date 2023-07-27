<?php

class Display
{
    const lay_l = '<table cellpadding="0" cellspacing="0" style="width:100%"><tr><td class="tdlnum code" style="width:10px">';
    const lay_m = '</td><td style="padding-left:1px;vertical-align:top">';
    const lay_r = '</td></tr></table>';

    private $back;
    private $disp;
    private $lnum;
    private $cut;

    static function jet($jet, $marker = '', $no_lines = false) {
        $s = function ($s, $c) {
            return '<span style="color:' . $c . '">' . html($s) . '</span>';
        };
        $lnum = $inm = '';
        $ary = [""];
        $yellow = $blue = [];

        foreach (explode("\n", unl($jet)) as $i => $line) {
            $lnum .= str_pad(1 + $i, 3, 0, STR_PAD_LEFT) . "<br>";
            $cnt = count($ary) - 1;
            if (preg_match("/^#\.([\.\w]+)(.*)$/", $line, $m)) {
                $line = $s('#.', '#090') . $s($m[1], 'red') . $s($m[2], '#b45309');
                $line = '<div class="code" style="background:#e7ebf2;">' . "$line</div>";
                $ary[] = [$line, explode('.', $m[1])];
                $ary[] = "";
                if (in_array($marker, explode('.', $m[1])))
                    $inm = !$inm;
                if ($inm)
                    $yellow[] = 2 + $cnt;
            } else {
                $ary[$cnt] .= "$line\n";
                
                if ($inm && preg_match("/(#use|@use|@inc|@block)\(\.([a-z\d_]+)/", $line, $m))
                    $blue[] = $m[2];
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
        $mname = [];
        foreach ($ary as $i => &$v) {
            $pp = ['if', 'elseif', 'else', 'end', 'use'];
            if (is_array($v)) {
                $new = array_diff($v[1], $mname);
                $mname = array_merge(array_diff($mname, $v[1]), $new);
                $v = $v[0];
            } else {
                $out = '';
                while (preg_match('/^(.*?)(~|@|#)([a-z]+)(.*)$/s', $v, $m)) {
                    $out .= $fu($m[1]);
                    $v = substr($m[4], strlen($br = Rare::bracket($m[4])));
                    $out .= $s($m[2] . $m[3], '#' == $m[2] ? (in_array($m[3], $pp) ? '#090' : '') : '#00f');
                    if ($br && '`' != $br[1] && in_array($m[3], ['inc', 'use', 'block']))
                        $out .= $s($br, 'red');
                    elseif ($br)
                        $out .= $s($br, '#00b;font-weight:bold');
                }
                $v = $out . $fu($v);
                if (in_array($i, $yellow)) {// || '' === $marker
                    $v = '<div class="code" style="background:#ffd;">' . "$v</div>";
                } elseif (array_intersect($mname, $blue)) {
                    $v = '<div class="code" style="background:#eff;">' . "$v</div>";
                }
            }
        }
        $out = implode("", $ary);
        #if ($out[-1] == "\n")
         #   $out = "\n" . $out;
        if ($no_lines)
            //return '<pre style="margin:0;background:#ffd">' . $out . '</pre>';
            return $out;
        $table = self::lay_l . $lnum . self::lay_m . '<pre style="margin:0;width:100%">' . $out . '</pre>' . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function bash($text) {
        return pre(preg_replace_callback("@(#.*)@m", function ($m) {
            return L::y($m[1]);
        }, $text), 'style="background:#e0e7ff; padding:5px"');
    }

    static function md($text) {
        $code = function ($text, $re) {
            return preg_replace_callback("@$re@s", function ($m) {
                if ('bash' == $m[1])
                    return self::bash(unhtml($m[2]));
                if ('php' == $m[1])
                    return self::php(unhtml($m[2]), '', true);
                return 'jet' == $m[1] ? self::jet(unhtml($m[2]), '-', true) : pre(html($m[2]), '');
            }, $text);
        };
        if (!$exist = Plan::has('Parsedown')) {
            if (is_dir('vendor/erusev/parsedown')) {
                Plan::vendor();
                $exist = true;
            }
        }
        if ($exist) {
            $md = new Parsedown;
            return $code($md->text($text), '<pre><code class="language\-(jet|php|bash)">(.*?)</code></pre>');
        }
        $text = str_replace("\n\n", '<p>', unl($text));
        return $code($text, "```(jet|php|bash|)(.*?)```");
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
        return self::php($php, $bc);
    }

    static function php($str, $bc = '', $no_lines = false) {
        static $d;
        null !== $d or $d = new self;
        if ($str === -1)
            return '';
        $d->cut = [-9, -9];
        if (is_array($bc)) {
            $bc[2] or $d->cut = [$bc[0] - 8, $bc[0] + 8];
            $bc = str_pad('', $bc[0] - 1, '=') . ($bc[1] ? '-' : '+');
        }
        $len = strlen($d->back = $bc);
        $d->lnum = '';
        $d->disp = 0;
        if ($tag = preg_match("/^\s*[\$]\w+/sm", $str) || $no_lines)
            $str = "<?php $str";
        $str = str_replace(["\r", "\n", '<code>','</code>'], '', highlight_string($str, true));
        if ($tag)
            $str = preg_replace("|^(<span [^>]+>)<span [^>]+>&lt;\?php&nbsp;|", "$1", $str);
        $lines = explode('<br />', preg_replace("|^(<span [^>]+>)<br />|", "$1", $str));
        array_walk($lines, [$d, 'add_line_no'], $len);
        if ($no_lines)
            return '<pre style="margin:0">' . implode('', $lines) . '</pre>';
        $table = self::lay_l . $d->lnum . self::lay_m . '<pre style="margin:0">' . implode('', $lines) . '</pre>' . self::lay_r;
        return sprintf('<div class="php">%s</div>', str_replace('%', '&#37;', $table));
    }

    private function add_line_no(&$val, $key, $len) {
        $val = strtr($val, ['=TOP-TITLE=' => '&#61;TOP-TITLE&#61;']);
        $colors = ['' => '', '=' => '', '*' => 'ffd', '+' => 'dfd', '-' => 'fdd', '.' => 'eee'];
        $pad = $c = '';
        if (!$key) for(; $len > $key + $this->disp && $this->back[$key + $this->disp] == '.'; $this->disp++) {
            $this->lnum .= "<br>";
            $pad .= '<div class="code" style="background:#eee">&nbsp;</div>';
        }
        if ($len > $key + $this->disp)
            $c = $colors[$this->back[$key + $this->disp]];
        $key++;
        if ($ok = -9 == $this->cut[0] || $key > $this->cut[0] && $key < $this->cut[1])
            $this->lnum .= str_pad($key, 3, 0, STR_PAD_LEFT) . "<br>";
        if ($val === '') {
            $val = '&nbsp;';
        } elseif ($val == '</span>') {
            $val = '&nbsp;</span>';
        } elseif ($val == '</span></span>') {
            $val = '&nbsp;</span></span>';
        }
        $val = $pad . ($c ? '<div class="code" style="background:'."#$c\">$val</div>" : "$val\n");
        for (; $len > $key + $this->disp && $this->back[$key + $this->disp] == '.'; $this->disp++) { # = - + * .
            $this->lnum .= "<br>";
            $val .= '<div class="code" style="background:#eee">&nbsp;</div>';
        }
        $ok or $val='';
    }

    static function log($html) { # 2do
        if ($p = strrpos($html, '<a'))
            $html = substr($html, 0, $p);
        if ($p = strrpos($html, '<span'))
            $html = substr($html, 0, $p);
        return '<pre>' . $html. '</pre>';
    }
}
