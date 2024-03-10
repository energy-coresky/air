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
    private static $color = [ # php's highlight_string(..) default colors
        'r' => '#f77', # red (in PHP - strings) d00
        'g' => '#2b3', # green (in PHP - keywords) 070
        'd' => '#00e', # default (in PHP) blue 00b
        'c' => '#b88', # comment color #ff8000
        'b' => '#000', # black (in PHP - HTML)
        'w' => '#fff', # white
        'm' => '#93c', # magenta
    ];

    static function jet($jet, $marker = '', $no_lines = false, $no_layout = false) {
        $out = self::pp($jet, $marker, $lnum);

        if ($out[-1] == "\n" && !$no_layout)
            $out = "\n" . $out;
        if ($no_lines)
            return $no_layout ? $out : '<pre style="margin:0;background:#ffd">' . $out . '</pre>';
        $table = self::lay_l . $lnum . self::lay_m . '<pre style="margin:0;width:100%">' . $out . '</pre>' . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function html($in) {
    }

    static function set($ary = []) {
        self::$color = $ary + self::$color;
        ini_set('highlight.default', self::$color['d']);
        ini_set('highlight.comment', self::$color['c']);
        ini_set('highlight.html', self::$color['m']);
        ini_set('highlight.keyword', self::$color['b']);
        ini_set('highlight.string', self::$color['r']);
    }

    static function span($c, $str, $style = '') {
        $c = self::$color[$c] ?? $c;
        return '<span style="color:' . "$c;$style\">" . html($str) . '</span>';
    }

    static function pp($txt, $marker, &$lnum, $fu = false) {
        if ($is_jet = !$fu) $fu = function ($v) {
            $out = '';
            while (preg_match('/^(.*?)(~|@|){([{!\-])(.*?)([\-!}])}(.*)$/s', $v, $m)) {
                $out .= html($m[1]);
                $v = $m[6];
                if ('@' == $m[2])
                    $out .= html("@{"."$m[3]$m[4]$m[5]"."}");
                elseif ('{' == $m[3])
                    $out .= self::span('w', $m[2] . "{{".$m[4]."}}", 'background:#b45309');
                elseif ('!' == $m[3])
                    $out .= self::span('w', $m[2] . "{!".$m[4]."!}", 'background:#777');
                else
                    $out .= self::span('#b45309', $m[2] . "{-".$m[4]."-}"); # Jet comment
            }
            return $out . html($v);
        };
        $ary = [$lnum = $inm = ""];
        $yellow = $blue = [];

        foreach (explode("\n", unl($txt)) as $i => $line) {
            $lnum .= str_pad(1 + $i, 3, 0, STR_PAD_LEFT) . "<br>";
            $cnt = count($ary) - 1;
            if (preg_match("/^#\.([\.\w]+)(.*)$/", $line, $m)) {
                $line = self::span('#090', '#.') . self::span('red', $m[1]) . self::span('#b45309', $m[2]);
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
        $mname = [];
        foreach ($ary as $i => &$v) {
            $pp = ['if', 'elseif', 'else', 'end', 'use'];
            if (is_array($v)) {
                $new = array_diff($v[1], $mname);
                $mname = array_merge(array_diff($mname, $v[1]), $new);
                $v = $v[0];
            } else {
                $out = '';
                while (preg_match('/^(.*?)(~|@|#)(\w+)(.*)$/s', $v, $m)) {
                    $out .= $fu($m[1],1);
                    $v = substr($m[4], strlen($br = Rare::bracket($m[4])));
                    $out .= self::span('#' == $m[2] ? (in_array($m[3], $pp) ? '#090' : '') : 'd', $m[2] . $m[3]);
                    if ($br && '`' != $br[1] && in_array($m[3], ['inc', 'use', 'block']))
                        $out .= self::span('red', $br);
                    elseif ($br)
                        $out .= self::span('#00b', $br, 'font-weight:bold');
                }
                $v = $out . $fu($v,1);
                if (in_array($i, $yellow)) {// || '' === $marker
                    $v = '<div class="code" style="background:#ffd;">' . "$v</div>";
                } elseif (array_intersect($mname, $blue)) {
                    $v = '<div class="code" style="background:#eff;">' . "$v</div>";
                }
            }
        }
        return implode('', $ary);
    }

    static function yaml($yaml, $marker = '', $no_lines = false, $no_layout = false) {
        $out = self::pp($yaml, $marker, $lnum, function ($v, $is_left) {
            $out = '';
            while (preg_match('/^(.*?)(#[^\n]*|\$[A-Z_\d]+|[^ \n{\+]+:[ \n])(.*)$/s', $v, $m)) {
                $out .= html($m[1]);
                $v = $m[3];
                if ('$' == $m[2][0])
                    $out .= self::span('m', $m[2]);
                elseif ('#' == $m[2][0])
                    $out .= self::span('c', $m[2]); # YML comment
                else $out .= self::span('g', $m[2]);
            }
            return $out . html($v);
        });

        if ($no_lines)
            return $no_layout ? $out : pre($out, 'style="background:#f7fee7; margin:0"');
        $table = self::lay_l . $lnum . self::lay_m . '<pre style="margin:0;width:100%">' . $out . '</pre>' . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function var($in) {
        return Plan::var($in);
    }

    static function bash($text) {
        return pre(preg_replace_callback("@(#.*)@m", function ($m) {
            return L::y($m[1]);
        }, $text), 'style="background:#e0e7ff; padding:5px"');
    }

    static function md($text) {
        $code = function ($text, $re) {
            return preg_replace_callback("@$re@s", function ($m) {
                if ('yaml' == $m[1])
                    return self::yaml(unhtml($m[2]), '-', true);
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
            $text = preg_replace("~\"https://github.*?/([^/\.]+)[^/\"]+\"~", '"_png?$1=' . Plan::$pngdir . '"', $md->text($text));
            return $code($text, '<pre><code class="language\-(jet|php|bash|yaml)">(.*?)</code></pre>');
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
        self::set();
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
