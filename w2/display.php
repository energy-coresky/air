<?php

class Display
{
    static $me = false;
    const lay_l = '<table cellpadding="0" cellspacing="0" style="font-size:14px;width:100%"><tr><td class="tdlnum mono" style="width:10px">';
    const lay_m = '</td><td style="padding-left:1px;vertical-align:top">';
    const lay_r = '</td></tr></table>';

    static function jet($fn, $bc = '') {
        $s = function ($s, $c) {
            return '<span style="color:' . $c . '">' . html($s) . '</span>';
        };
        $in = file_get_contents($fn);
        $lnum = $out = '';
        while (preg_match('/^(.*?)(~|@|#)([a-z]+)(.*)$/s', $in, $m)) {
            $out .= html($m[1]);
            $in = substr($m[4], strlen($br = Rare::bracket($m[4])));
            $out .= $s($m[2] . $m[3], '#' == $m[2] ? '#090' : '#00f');
            if ($br)
                $out .= $s($br, '#00b;font-weight:bold');
        }
        $lines = explode("\n", unl($out));
        foreach ($lines as $i => &$line) {
            $lnum .= str_pad(1 + $i, 3, 0, STR_PAD_LEFT) . "<br>";
            if (preg_match("/^#\.([\.\w]+)(.*)$/", $line, $m)) {
                $line = $s('#.', '#222') . $s($m[1], 'red') . $s($m[2], '#b45309');
                $line = '<div class="code" style="background:#e7ebf2;font-family: monospace;font-size: 14px;">' . "$line</div>";
            } else {
                $line .= "\n";
            }
        }
        $out = implode("", $lines);

        $table = self::lay_l . $lnum . self::lay_m . '<pre style="margin:0">' . $out . '</pre>' . self::lay_r;
        return '<div class="php">' . $table . '</div>';
    }

    static function php($str, $bc = '') {
        self::$me or self::$me = new Display;
        $me = self::$me;
        if ($str === -1)
            return '';
        $me->lnum = '';
        $me->lenb = strlen($me->back = $bc);
        $me->disp = 0;
        if ($tag = preg_match("/^\s*[\$]\w+/sm", $str))
            $str = "<?php $str";
        $str = str_replace(["\r", "\n", '<code>','</code>'], '', highlight_string($str, true));
        if ($tag)
            $str = preg_replace("|^(<span [^>]+>)<span [^>]+>&lt;\?php&nbsp;|", "$1", $str);
        $lines = explode('<br />', preg_replace("|^(<span [^>]+>)<br />|", "$1", $str));
        array_walk($lines, [$me, 'add_line_no']);
        $table = self::lay_l . $me->lnum . self::lay_m . '<pre style="margin:0">' . implode('', $lines) . '</pre>' . self::lay_r;
        return sprintf('<div class="php">%s</div>', str_replace('%', '&#37;', $table));
    }

    private function add_line_no(&$val, $key) {
        $val = strtr($val, ['=TOP-TITLE=' => '&#61;TOP-TITLE&#61;']);
        $colors = ['' => '', '=' => '', '*' => 'ff7', '+' => 'dfd', '-' => 'fdd', '.' => 'eee'];
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
