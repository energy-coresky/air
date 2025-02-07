<?php

class MD extends XML # the MarkDown, follow Common Mark https://spec.commonmark.org/
{ # and rich MarkDown from https://github.com/xoofx/markdig
    const version = '0.599';

    static $MD;

    public $hightlight = false; # if true then work Show::php(..) for ```php

    private $ref = [];
    private $for = [];

    function __construct(string $in = '', $tab = 2) {
set_time_limit(3);
        parent::__construct($in, $tab);
        MD::$MD or MD::$MD = Plan::set('main', fn() => yml('md', '+ @object @inc(md)', false, true));
    }

    private function leaf_dl($x, &$y) {# :dl-dt-dd
    }

    private function leaf_html($x) {
        if ($x->grab)
            goto end;
        if (!preg_match("/^<(\?|!|\/?[a-z][a-z\-]*)/i", $x->line, $m))
            return false;
        if ('?' == $m[1]) {
            $x->grab = '?>';
        } elseif ('!' == $m[1]) {
            foreach (MD::$MD->typ_1 as $k => $v)
                if ($k == substr($x->line, 0, strlen($k))) {
                    $x->grab = $v;
                    goto end;
                }
            return false;
        } elseif (in_array($v = strtolower($m[1]), MD::$MD->typ_2)) {
            $x->grab = "</$v>";
        } else {
            $x->grab = "<"; # blank line
        }
        end:
        $blank = '<' == $x->grab;
        if ($blank && $x->empty || !$blank && false !== strpos($x->line, $x->grab))
            $x->grab = false;
        $x->empty or $this->j += strlen($x->line);
        return $this->push('y-html', $x->line);//, ['c' => 'r']
    }

    private function leaf_h6($x) {
        if (!$x) {
            if ($cut = $this->up->attr['len']) {
                $str = substr($this->last->val, -$cut);
                $this->last->val = substr($this->last->val, 0, -$cut);
                $this->add($str, ['c' => 'r']);
                $this->j -= $cut;
            }
            return $this->close();
        }
        if (!preg_match("/^(#{1,6})(\s+#+\s*\z|\s+|\z)(.*)$/", $x->line, $m))
            return false;
        $this->use_close($x);
        $this->close('p');
        $h = strlen($m[1]);
        $this->j += $h + strlen($m2 = $m[2]);
        preg_match("/(\s+#+\s*|\s*)$/", $m[3], $n);
        return $this->push("h$h", null, ['t' => $m[1] . $m2, 'len' => $n ? strlen($n[1]) : '0']);
    }

    private function leaf_h2r($x, $y) {
        if (!preg_match("/^\\{$y}[\\$y \t]*$/", $x->line))
            return false;
        $z = str_replace(["\t", ' '], '', $x->line);
        $h12 = '=' == $y ? 'h1' : ('-' == $y ? 'h2' : false);
        if ($h12 && 'p' == $this->up->name && $z == chop($x->line) && !$x->close) {
            $this->up->name = $h12;
            $br =& $this->last->left;
            if ($br && $br->name == 'br')
                $br->name = '#skip';
            $this->close();
            return $this->add($x->line, ['c' => 'r']);
        }
        if ('=' == $y || strlen($z) < 3)
            return false;
        $this->use_close($x);
        $this->j += strlen($x->line);
        return $this->push('hr', 0, ['t' => $x->line]);
    }

    private function leaf_table($x) {
        if ($x->table) {
            $tbody = $this->up;
            if ('tbody' == $tbody->name) {
                $node =& $this->last->right;
            } else {
                $tbody = $this->push('tbody');
                $node =& $tbody->val;
            }
            $x->nls = true;
            $this->line($x);
            return $this->last($node = $this->tr('td', $x->table, $node, $tbody));
        } elseif ('p' != $this->up->name || '' !== chop($x->line, "\t -:|")) {
            return $this->leaf_p($x);
        } else {
            $x->table = array_map(function ($v) {
                $align = ':' == $v[0] ? ' align="left"' : '';
                return ':' != $v[-1] ? $align : ($align ? ' align="center"' : ' align="right"');
            }, preg_split("/\s*\|\s*/", trim($x->line, "|\t ")));
            $node = $this->up->val; # first child
            $td = 'yd' == $node->name ? 1 : 0;
            if (end($x->td)->right->val == "\n")
                $td++;
            if (count($x->table) != 1 + count($x->td) - $td)
                return $x->table = false;
            $this->parent($this->up, 'table');
            $this->push('thead', $this->tr('th', $x->table, $node));
            return $this->add($x->line, ['c' => 'r']);
        }
    }

    private function tr($tx, $ary, $node, $up = null) {
        $val = "<$tx" . array_shift($ary) . '>';
        if ('yd' == $node->name) {
            $node->val = $val;
            $node->attr['t'] = '|';
        } else {
            $node = $node->left = XML::node('yd', $val, ['t' => ''], $node); # that is err: $node->left = $node ..
        }
        $node->up = $tr = XML::node('tr', $last = $node, null, null, $up);
        for ($close = false; $node = $node->right; $last = $node) {
            $node->up = $tr;
            $yd = 'yd' == $node->name;
            if ($ary && $yd) {
                $node->val = "</$tx><$tx" . array_shift($ary) . '>';
                $node->attr['t'] = '|';
            } elseif ($close) {
                $node->name = '#skip';
            } elseif ($close = $yd) {
                $node->val = "</$tx>";
                $node->attr['t'] = '|';
            }
        }
        if (!$close) {
            $val = str_repeat("</$tx><$tx>", count($ary)) . "</$tx>";
            $last->right = XML::node('yd', $val, ['t' => ''], null, $tr, $last);
        }
        return $tr;
    }

    private function leaf_p($x) {
        if ($x->table)
            return $this->leaf_table($x);
        if ('p' == $this->up->name)
            return false; # lazy continue
        $this->use_close($x);
        $x->empty or $x->added = $this->push('p');//, null, ['j' => $this->j]
        return false;
    }

    private function leaf_code($x) {
        if ('x-code' != $this->up->name)
            $x->added = $this->push('x-code', null, ['lang' => '', 't' => '']);
        $x->nls = false;
        $x->pad - 4 && $this->push('#text', str_pad('', $x->pad - 4));
        return $this->add($x->line);
    }

    private function blk_bq($x, &$y) {
        $old = $x->pad;
        if (!$this->set_x($x, 2, $y))
            return false;
        $this->use_close($x);
        return $this->push_pad($x, $old);
    }

    private function push_pad($x, $old) {
        $bq = '>' == $x->m;
        $x->pad -= $pad = $bq || $x->empty || $x->pad > 4 ? 1 : $x->pad;
        $pad = $old + strlen($t = $x->m . str_pad('', $pad));
        return $this->push($bq ? 'blockquote' : 'li', null, ['t' => $t, 'pad' => $pad]);
    }

    private function blk_ol($x, &$y, $n = 0) {
        $old = $x->pad;
        if (!$attr = $this->set_x($x, $n, $y))
            return false;
        if ($tag = $x->close) {
            if ('li' == $tag->name) {
                $this->last($tag);
                $x->close = false;
                if ($attr['d'] == $tag->up->attr['d'])
                    return $this->push_pad($x, $old); # next li
                $this->close(); # ul/ol
            } else {
                $this->use_close($x);
            }
        }
        $this->push($n ? 'ul' : 'ol', null, $attr);
        return $this->push_pad($x, $old);
    }

    private function blk_ul($x, &$y) {
        return $this->blk_ol($x, $y, 1);
    }

    private function leaf_fenced($x, $y) {
        if ($x->grab) { # already open
            if (!in_array($x->grab[0], ['~', '`']))
                return $this->leaf_html($x);
            if ($x->pad < 4 && preg_match("/^$x->grab+\s*$/", $x->line)) {
                $this->close();
                $x->grab = 0;
                return $x->nls = $this->add(str_pad('', $x->pad) . $x->line, ['c' => 'r']);
            } else {
                $pad = (int)$this->up->attr['pad'];
                if ($x->pad && $pad) {
                    $this->add($pad = min($x->pad, $pad));
                    $x->pad -= $pad;
                }
                $x->pad && $this->push('#text', str_pad('', $x->pad));
                $this->add($x->line);
                $x->pad = 0;
            }
        } elseif (preg_match("/^($y{3,})\s*(\w*).*$/", $x->line, $m)) {
            $this->close('p');
            $this->use_close($x);
            $x->grab = $x->nls = $m[1];
            $this->j += strlen($x->line);
            return $this->push('x-code', null, ['lang' => $m[2], 'pad' => (string)$x->pad, 't' => $x->line]);
        }
        return false;
    }

    protected function parse(): ?stdClass {
        $x = new stdClass;
        $x->table = $x->grab = $x->nls = $prev = false;
        $this->parent($this->root);
        do {
            $y = $this->in[$this->j] ?? '';
            if (is_num($this->up->name[1] ?? ''))
                $this->leaf_h6(false, false); # close h1..h6
            if ("\n" == $y) {
                $this->add("\n", $x->nls);
                if ($x->nls && $x->grab)
                    $x->nls = false;
            }
            $x->added = $x->close = false;
            $this->old_blk($x, $y, $empty);
            if ($x->empty && $this->close('p')) {
                $this->use_close($x);
            } elseif ($x->empty && $x->table) {
                $this->close('tbody');
                $this->close('table');
                $x->table = false;
            } else {
                $this->new_blk($x, $y);
            }
            $prev && $x->added && $this->set_tight($prev);
            $prev = $empty;
        } while ($this->line($x));
        $this->j = 0;
        $this->last->attr['last'] = 1;
        return $this->root->val;
    }

    private function line($x, $line = '') {
        if ('' === $line) {
            $in =& $this->in;
            $j =& $this->j;
            $x->td = [];
        } else {
            $in =& $line;
            $j = 0;
        }
        while (!in_array($y = $in[$j] ?? '', ['', "\n"], true)) {
            $next = $in[1 + $j] ?? '';
            if ("\\" == $y) { # escape
                if ('' !== $next && strpbrk($next, MD::$MD->esc)) {
                    $this->add("\\", ['c' => 'r']);
                    $this->add($next);
                } else {
                    $this->add("\\");// 2do <br>
                }
            } elseif ('<' == $y && preg_match("/^<\/?([a-z_:][\w:\.\-]*)\b[^>]*>/i", $this->cspn("\n"), $m)) {
                $this->push('y', $m[0], ['bg' => '+']);
                $j += strlen($m[0]);
            } elseif ('|' == $y) {
                $j++;
                $x->td[] = $this->push('yd', '|');//, ['i' => count($x->td)]
            } elseif (strpbrk($y, MD::$MD->esc)) {
                $img = '!' == $y;
                if (!$img && '[' != $y || !$this->square($x, $y, true, $img)) {
                    $uu = substr($in, $j, strspn($in, $y, $j));
                    if (strpbrk($y, "*_")) {
                        $j += $sz = strlen($uu);
                        $this->stk_call($uu, $sz, $in[$j - $sz - 1] ?? '', $in[$j] ?? '');
                    } else {
                        $this->add($uu);
                    }
                }
            } else {
                $y = substr($in, $j, $n = strcspn($in, "\n" . MD::$MD->esc, $j));
                $is_p = 'p' == $this->up->name;
                if ($is_p && '  ' == substr($y, -2) && "\n" == ($in[$j + strlen($y)] ?? '')) {
                    $this->add($t2 = chop($y));
                    $this->push('br', 0, ['t' => $rest = substr($y, strlen($t2))]);
                    $j += strlen($rest);
                } elseif (':' == ($in[$j + $n] ?? '') && $this->auto_link($j, $y, $in)) {
                    $j += strlen($y);
                    $this->push('a', $y, ['href' => $y, 'c' => 'g']);
                } else {
                    $this->add($y);
                }
            }
        }
        return "\n" == $y;
    }

    private function old_blk($x, &$y, &$empty) {
        $ary = [];
        for ($tag = $this->last; '#root' != $tag->name; $tag = $tag->up)
            if (in_array($tag->name, ['li', 'blockquote']))
                array_unshift($ary, $tag);
        $y = $this->set_x($x, 7);
        $empty = $x->empty ? $this->last : false;
        foreach ($ary as $tag) {
            if ('li' == $tag->name) {
                if (!$x->empty && $x->pad < ($tag->attr['pad'] + $x->base))
                    return $x->close = $tag;
                $x->base += $tag->attr['pad'];
            } elseif ('>' != $y || $x->pad > 3) {// && 'p' != $this->up->name
                return $x->close = $tag;
            } else { # also blockquote
                $x->pad && $this->add($x->pad);
                $y = $this->set_x($x, 5);
                if ($x->empty)
                    $empty = $this->last;
                if ($x->pad) {
                    $this->last->val .= ' ';
                    $x->base++;
                }
            }
        }
    }

    private function new_blk($x, &$y) {
        if ($x->pad >= $x->base && $x->base) {
            $x->pad -= $x->base;
            $this->add($x->base);
        }
        start: # add new blocks
        $x->line = $this->cspn("\n");
        if ($x->grab) {
            if (!$x->close)
                return $this->leaf_fenced($x, $y);
            $this->use_close($x);
        }
        if ($x->pad > 3) {
            $is_p = 'p' == $this->up->name;
            $is_p or $this->use_close($x);
            $this->add($is_p ? $x->pad : 4);
            if ($is_p)
                return $this->leaf_p($x);
            return $this->leaf_code($x, $y);
        }
        $this->close('x-code');
        $x->pad && $this->add($x->pad);
        foreach (MD::$MD->blk_chr as $chr => $func) {
            if (('' === $chr || false !== strpbrk($y, $chr)) && $this->$func($x, $y)) {
                $x->added = true;
                if ($x->empty || 'b' != $func[0])
                    break;
                goto start;
            }
        }
    }

    private function stk_call($uu, $sz, $left, $right) {
        $u2 = $uu[0] . $uu[0];
        $y = ($em = 1 == $sz) ? $uu : $u2;
        $close = $left !== '' && !strpbrk($left, " \t\r\n");
        $open = $right !== '' && !strpbrk($right, " \t\r\n");
        $node =& $this->stk[$y];
        if ($close && $node) {
            $node->val = $em ? '<em>' : '<strong>';
            $node = false;
            $this->push('y', $em ? '</em>' : '</strong>', ['t' => $uu]);
        } elseif ($open && !$node) {
            $node = $this->push('y', $uu, ['t' => $uu]);
        } else {
            $this->push('#text', $uu);
        }
    }

    private function auto_link($j, &$y, &$in) {
        if (!preg_match("/\bhttps?$/ui", $y, $m1))
            return false;
        if (!preg_match("/^:\/\/[^\s<]+\b\/*/ui", substr($in, $j + strlen($y)), $m2)) //2do substr
            return false;
        if ($m1[0] === $y)
            return $y .= $m2[0];
        $y = substr($y, 0, -strlen($m1[0]));
        return false;
    }

    private function square($x, $y, $inline = false, $img = false) { # 2do  footnote
        $j = $img ? 1 + $this->j : $this->j;
        if (!$len = strlen($head = Rare::bracket($this->in, '[', $j, "\n\\")))
            return false;
        $tail = '';
        $rf = strtolower($head);
        if (in_array($chr = $this->in[$j += $len], ['[', '('])) {
            if (!$tail = Rare::bracket($this->in, $chr, $j, "\n\\") or '[]' == $tail)
                goto schem_1;
            if ('[' == $chr) {
                $rf = strtolower($tail);
                goto schem_2; # scheme: [][q] or [a][q]
            }
        } elseif (':' == $chr) {
            if ($inline || ':' == trim($tail = $this->cspn("\n", $j)))
                return false; # not reference
            $this->use_close($x);
            $this->ref[$rf] = trim(substr($tail, 1));
            return $this->add($head . $tail, ['c' => 'm']);
        } else {
            schem_1: # scheme: [q]
            if (2 == $len)
                return false;
            schem_2:
            $this->for[$rf][] = true;
            $p =& $this->for[$rf][array_key_last($this->for[$rf])];
        }
        $this->j += strlen($t = ($img ? '!' : '') . $head . $tail);
        if ($img)
            return $this->push('img', 0, ['src' => substr($tail, 1, -1), 'alt' => substr($head, 1, -1), 'c' => 'c', 't' => $t]);
        return $this->push('a', substr($head, 1, -1), ['href' => substr($tail, 1, -1), 'c' => 'g', 't' => $t]);
    }

    function md_raw($in) {//return $this->xml_mini($in);
        $out = '';
        foreach ($this->gen($in, true) as $depth => $node) {
            if ('#' == $node->name[0]) {
                $out .= $node->val;
            } elseif ($depth < 0) {
                $out .= str_pad('', -$depth * 2 - 2) . "</$node->name>\n";
            } else {
                $out .= str_pad('', $depth * 2) . $this->tag($node, $close);
                $out .= null === $node->val || is_string($node->val) ? "$node->val$close\n" : "\n";
            }
        }
        return $out;
    }

    function md_nice($in) {
        $out = $type = $code = '';
        foreach ($this->gen($in, true) as $depth => $node) {
            if ('#skip' == $node->name)
                continue;
            if ($depth < 0) {
                if ($code && "\n" == $code[-1])
                    $code = substr($code, 0, -1);
                if ('' === $type) {
                    $out .= str_pad('', -$depth * 2 - 2) . "</$node->name>\n";
                } elseif ($this->hightlight && in_array($type, MD::$MD->code_type, true)) {
                    Show::scheme();
                    $out .= Show::$type($code, '<?' == substr($code, 0, 2) ? '' : false, true);
                } else {
                    $out .= pre(tag(html($code), true === $type ? '' : "class=\"language-$type\"", 'code'), '');
                }
                $type = '';
            } elseif ('y' == $node->name[0]) {
                $out .= $node->val;
            } elseif ('#' == $node->name[0]) {
                '' === $type ? ($out .= html($node->val)) : ($code .= $node->val);
            } elseif ('x-code' == $node->name) { // code 
                $type = $node->attr['lang'] ?: true;
                'javascript' !== $type or $type = 'js';
                $code = ''; # fenced code start
            } else {
                if ('li' == $node->name && $node->up->attr['tight'])
                    for ($tag = $node->val; $tag; $tag = $tag->right)
                        'p' != $tag->name or $this->remove($tag, false);
                $out .= str_pad('', $depth * 2) . $this->tag($node, $close, MD::$MD->attr);
                $out .= null === $node->val || is_string($node->val) ? "$node->val$close\n" : "\n";
            }
        }
        return $out;
    }

    private function use_close($x) {
        #if (!$x->close && 'tbody' == $this->up->name)
        #    $x->close = $this->up->up; # table
        #if (!$x->close && 'table' == $this->up->name)
        #    $x->close = $this->up;
        $x->close && $this->last('li' == $x->close->name ? $x->close->up : $x->close);
        $x->pad < 4 && $this->close('x-code');
        $x->close = $x->grab = false;
    }

    private function add($str, $skip = false) {
        if (is_int($str)) {
            $str = str_pad('', $str);
            $skip or $skip = true;
        } else {
            $this->j += strlen($str);
        }
        if (is_array($skip))
            return $this->push('#skip', $str, $skip);
        $name = $skip ? '#skip' : '#text';
        if ($name == $this->last->name)
            return $this->last->val .= $str;
        return $this->push($name, $str);
    }

    private function set_tight($li) {
        for (; $li && 'li' != $li->name; $li = $li->up);
        if (!$left = $li)
            return;
        for ($li = $this->last; $li && 'li' != $li->name; $li = $li->up);
        if (!$right = $li)
            return;
        for ($j = 1; $li; $j++, $li = $li->up);
        for ($i = 1, $li = $left; $li; $i++, $li = $li->up);
        $i < $j or $left = $right;
        $left->up->attr['tight'] = '0';
    }

    private function set_x($x, $n, &$y = null) {
        static $pad;

        if ($n < 3) {
            if (!preg_match(MD::$MD->blk_re[$n], $x->line, $match))
                return false;
            $pad += $sz = 1 + strlen($x->m = $match[1]);
            $this->j += $sz;
            $attr = ['tight' => '1', 'd' => $match[2]];
            $n or 1 == $x->m or $attr += ['start' => $x->m];
            $x->m .= $match[2];
        } elseif (7 == $n) {
            $pad = $x->base = 0;
        } elseif (5 == $n) {
            $pad++;
            $x->base = 0;
            $this->add('>', ['c' => 'r']);
        }
        $fn = fn() => $this->in[$this->j] ?? "\n";
        for ($base = $pad, $y = $fn(); strpbrk($y, " \t"); ) {
            $this->j += $sz = strspn($this->in, ' ', $this->j);
            $pad += $sz;
            for ($y = $fn(); "\t" == $y; $this->j++, $y = $fn())
                $pad += 4 - $pad % 4;
        }
        $x->empty = "\n" == $y;
        $x->pad = $pad - $base;
        return $attr ?? $y;
    }

    protected function last($node, $down = false) {
        if (!$down) {
            //$this->last
        }
        //$this->stk = [];
        return parent::last($node, $down);
    }

    private function spn($set, $j = 0, &$sz = null) { # collect $set
        return substr($this->in, $j ?: $this->j, $sz = strspn($this->in, $set, $j ?: $this->j));
    }

    private function cspn($set, $j = 0, &$sz = null) { # collect other than $set
        return substr($this->in, $j ?: $this->j, $sz = strcspn($this->in, $set, $j ?: $this->j));
    }
}
/*      foreach ($this->for as $i => $for) {
            if (isset($this->ref[$for])) {
                #$this->tok[$i][4] = $this->ref[$for];
            } else {
                #$this->tok[$i][2] = $this->tok[$i][1];
                #$this->tok[$i][0] = 11;
            }
        }

        sprintf('<img src="%s" alt="%s">', $p4, substr($p3, 1, -1)) # image
trace($x->close, '===');*/