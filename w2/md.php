<?php

class MD extends XML # the MarkDown, follow Common Mark https://spec.commonmark.org/
{
    const version = '0.555';

    static $MD;

    public $hightlight = false; # if true then work Show::php(..) for ```php

    private $ref = [];
    private $for = [];

    function __construct(string $in = '', $tab = 2) {
set_time_limit(5);
        parent::__construct($in, $tab);
        MD::$MD or MD::$MD = Plan::set('main', fn() => yml('md', '+ @object @inc(md)', false, true));
    }

    private function leaf_html($x, &$t) {
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
        return $this->push('#raw', $x->line, ['c' => 'r']);
    }
    private function leaf_dl($x, &$t) {# :dl-dt-dd
    }

    private function leaf_h6($x, &$u) {
        if (!preg_match("/^(#{1,6})(\s+#+\s*\z|\s+|\z)(.*)$/", $x->line, $m))
            return false;
        $this->use_close($x);
        $this->close('p');
        $h = strlen($m[1]);
        $this->j += $h + strlen($m2 = $m[2]);
        preg_match("/(\s+#+\s*|\s*)$/", $m[3], $n);
        return $this->push("h$h", null, ['t' => $m[1] . $m2, 'len' => $n ? strlen($n[1]) : '0']);
    }

    private function leaf_h2r($x, &$u) {
        if (!preg_match("/^\\{$u}[\\$u \t]*$/", $x->line))
            return false;
        $z = str_replace(["\t", ' '], '', $x->line);
        $p =& $this->ptr[1]->name;
        $h12 = '=' == $u ? 'h1' : ('-' == $u ? 'h2' : false);
        if ($h12 && 'p' == $p && $z == chop($x->line) && !$x->close) {
            $p = $h12;
            $br =& $this->last->left;
            if ($br && $br->name == 'br')
                $br->name = '#skip';
            $this->close();
            return $this->add($x->line, ['c' => 'r']);
        }
        if ('=' == $u || strlen($z) < 3)
            return false;
        $this->use_close($x);
        $this->j += strlen($x->line);
        return $this->push('hr', 0, ['t' => $x->line]);
    }

    private function leaf_table($x, &$t) {
        if ($x->table) {
            'tbody' == $this->ptr[1]->name or $this->push('tbody');
            $this->push('tr');
            $line = trim($x->line);
            if ($line && '|' == $line[0])
                $line = substr($line, 1);
            $ary = preg_split("/\s*\|\s*/", $line && '|' == $line[-1] ? substr($line, 0, -1) : $line);
            foreach ($x->table as $n => $align)
                $this->push('td', $ary[$n] ?? '', $align);
            $this->close('tr');
        } elseif ('p' != $this->ptr[1]->name || '' !== chop($x->line, "\t -:|")) {
            return $this->leaf_p($x, $u); 
        } else {
            $x->table = array_map(function ($v) {
                $align = ':' == $v[0] ? ['align' => 'left'] : [];
                return ':' != $v[-1] ? $align : ['align' => $align ? 'center' : 'right'];
            }, preg_split("/\s*\|\s*/", trim($x->line, "|\t ")));
            $line = '';
            foreach ($this->gen($this->ptr[1]->val) as $node)
                '#text' != $node->name or $line .= $node->val;
            $ary = preg_split("/\s*\|\s*/", trim($line, "|\n\t "));
            if (count($ary) != count($x->table))
                return $x->table = false;
            $this->ptr[1]->name = 'table';
            $this->last($this->ptr[1], true);
            $thead = $this->push('thead', null, ['t' => chop($line)]);
            $this->push('#text', "\n");
            $this->push('tr', null, ['t' => $x->line]);
            foreach ($x->table as $n => $align)
                $this->push('th', $ary[$n], $align + ['t' => '']);
            $this->last($thead);
        }
        return $this->j += strlen($x->line);
    }

    private function leaf_p($x, &$u) {
        if ($x->table)
            return $this->leaf_table($x, $u);
        if ('p' == $this->ptr[1]->name)
            return false; # lazy continue
        $this->use_close($x);
        $x->empty or $x->added = $this->push('p');
        return false;
    }

    private function leaf_code($x, &$u) {
        if ('x-code' != $this->ptr[1]->name)
            $x->added = $this->push('x-code', null, ['lang' => '', 't' => '']);
        $x->nls = false;
        $x->pad - 4 && $this->push('#text', str_pad('', $x->pad - 4));
        return $this->add($x->line);
    }

    private function blk_bq($x, &$u) {
        $prev = $x->pad;
        if (!$this->set_x($x, 2, $u))
            return false;
        $this->use_close($x);
        return $this->push(...$this->b_tag($x, $prev));
    }

    private function b_tag($x, $prev) {
        $bq = '>' == $x->m;
        $x->pad -= $pad = $bq || $x->empty || $x->pad > 4 ? 1 : $x->pad;
        $pad = $prev + strlen($t = $x->m . str_pad('', $pad));
        return [$bq ? 'blockquote' : 'li', null, ['t' => $t, 'pad' => $pad]];
    }

    private function blk_ol($x, &$u, $n = 0) {
        $prev = $x->pad;
        if (!$attr = $this->set_x($x, $n, $u))
            return false;
        if ($tag = $x->close) {
            if ('li' == $tag->name) {
                $this->last($tag);
                $x->close = false;
                if ($attr['d'] == $tag->up->attr['d'])
                    return $this->push(...$this->b_tag($x, $prev)); # next li
                $this->close(); # ul/ol
            } else {
                $this->use_close($x);
            }
        }
        $this->push($n ? 'ul' : 'ol', null, $attr);
        return $this->push(...$this->b_tag($x, $prev));
    }

    private function blk_ul($x, &$u) {
        return $this->blk_ol($x, $u, 1);
    }

    private function leaf_fenced($x, &$u) {
        if ($x->grab) { # already open
            if (!in_array($x->grab[0], ['~', '`']))
                return $this->leaf_html($x, $u);
            if ($x->pad < 4 && preg_match("/^$x->grab+\s*$/", $x->line)) {
                $this->close();
                $x->grab = 0;
                return $x->nls = $this->add(str_pad('', $x->pad) . $x->line, ['c' => 'r']);
            } else {
                $pad = (int)$this->ptr[1]->attr['pad'];
                if ($x->pad && $pad) {
                    $this->add($pad = min($x->pad, $pad));
                    $x->pad -= $pad;
                }
                $x->pad && $this->push('#text', str_pad('', $x->pad));
                $this->add($x->line);
                $x->pad = 0;
            }
        } elseif (preg_match("/^($u{3,})\s*(\w*).*$/", $x->line, $m)) {
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
        $this->last($this->root, true);
        do {
            $u = $this->in[$this->j] ?? '';
            if (is_num($this->ptr[1]->name[1] ?? '')) { # close h1..h6
                if ($cut = $this->ptr[1]->attr['len']) {
                    $str = substr($this->last->val, -$cut);
                    $this->last->val = substr($this->last->val, 0, -$cut);
                    $this->add($str, ['c' => 'r']);
                    $this->j -= $cut;
                }
                $this->close();
            }
            if ("\n" == $u) {
                $this->add("\n", $x->nls);
                if ($x->nls && $x->grab)
                    $x->nls = false;
            }
            $x->added = $x->close = false;
            $this->chk_old($x, $u, $empty);
            if ($x->empty && $this->close('p')) {
                $this->use_close($x);
            } elseif ($x->empty && $this->close('tbody')) {
                $this->close('table');
                $x->table = false;
            } else {
                $this->add_new($x, $u);
            }
            $prev && $x->added && $this->set_tight($prev);
            $prev = $empty;
        } while ($this->str_rest($x));
        $this->j = 0;
        $this->last->attr['last'] = 1;
        return $this->root->val;
    }

    private function str_rest($x) {
        $in =& $this->in;
        $j =& $this->j;
        for (; !in_array($u = $in[$j] ?? '', ['', "\n"], true); ) {
            $next = $in[1 + $j] ?? '';
            if ("\\" == $u) { # escape
                $esc = '' !== $next && strpbrk($next, MD::$MD->esc);
                if ($esc) {
                    #$this->push('#esc', $next, ['t' => "\\$next"]);
                    #$j += 2;
                    $this->add("\\", ['c' => 'r']);
                    $this->add($next);
                } else {
                    $this->add("\\");// 2do <br>
                }
            } elseif (strpbrk($u, MD::$MD->esc)) {
                $ok = '[' == $u && $this->in_square($x, $u, true)
                    or '!' == $u && '[' == $next && $this->in_square($x, $u, true, true);
                if (!$ok) {
                    $uu = $this->spn($u);
                    if (strpbrk($u, "*_")) {
                        $j += $sz = strlen($uu);
                        $this->inline($uu, $sz, $in[$j - $sz - 1] ?? '', $in[$j] ?? '');
                    } else {
                        $this->add($uu);
                    }
                }
            } else {
                $u = substr($in, $j, $n = strcspn($in, "\n" . MD::$MD->esc, $j));
                $is_p = 'p' == $this->ptr[1]->name;
                if ($is_p && '  ' == substr($u, -2) && "\n" == ($in[$j + strlen($u)] ?? '')) {
                    $this->add($t2 = chop($u));
                    $this->push('br', 0, ['t' => $rest = substr($u, strlen($t2))]);
                    $j += strlen($rest);
                } elseif (':' == ($in[$j + $n] ?? '') && $this->auto_link($j, $u)) {
                    $j += strlen($u);
                    $this->push('a', $u, ['href' => $u, 'c' => 'g']);
                } else {
                    $this->add($u);
                }
            }
        }
        return "\n" == $u;
    }

    private function chk_old($x, &$u, &$empty) {
        $ary = [];
        for ($tag = $this->last; '#root' != $tag->name; $tag = $tag->up)
            if (in_array($tag->name, ['li', 'blockquote']))
                array_unshift($ary, $tag);
        $u = $this->set_x($x, 7);
        $empty = $x->empty ? $this->last : false;
        foreach ($ary as $tag) {
            if ('li' == $tag->name) {
                if (!$x->empty && $x->pad < ($tag->attr['pad'] + $x->base))
                    return $x->close = $tag;
                $x->base += $tag->attr['pad'];
            } elseif ('>' != $u || $x->pad > 3) {// && 'p' != $this->ptr[1]->name
                return $x->close = $tag;
            } else { # also blockquote
                $x->pad && $this->add($x->pad);
                $u = $this->set_x($x, 5);
                if ($x->empty)
                    $empty = $this->last;
                if ($x->pad) {
                    $this->last->val .= ' ';
                    $x->base++;
                }
            }
        }
    }

    private function add_new($x, &$u) {
        if ($x->pad >= $x->base && $x->base) {
            $x->pad -= $x->base;
            $this->add($x->base);
        }
        start: # add new blocks
        $x->line = $this->cspn("\n");
        if ($x->grab) {
            if (!$x->close)
                return $this->leaf_fenced($x, $u);
            $this->use_close($x);
        }
        if ($x->pad > 3) {
            $is_p = 'p' == $this->ptr[1]->name;
            $is_p or $this->use_close($x);
            $this->add($is_p ? $x->pad : 4);
            if ($is_p)
                return $this->leaf_p($x, $u);
            return $this->leaf_code($x, $u);
        }
        $this->close('x-code');
        $x->pad && $this->add($x->pad);
        foreach (MD::$MD->blk_chr as $chr => $func) {
            if (('' === $chr || false !== strpbrk($u, $chr)) && $this->$func($x, $u)) {
                $x->added = true;
                if ($x->empty || 'b' != $func[0])
                    break;
                goto start;
            }
        }
    }

    private function inline($uu, $sz, $left, $right) {
        $u2 = $uu[0] . $uu[0];
        $u = ($em = 1 == $sz) ? $uu : $u2;
        $close = $left !== '' && !strpbrk($left, " \t\r\n");
        $open = $right !== '' && !strpbrk($right, " \t\r\n");
        $node =& $this->stk[$u];
        if ($close && $node) {
            $node->val = $em ? '<em>' : '<strong>';
            $node = false;
            $this->push('#raw', $em ? '</em>' : '</strong>', ['t' => $uu]);
        } elseif ($open && !$node) {
            $node = $this->push('#raw', $uu, ['t' => $uu]);
        } else {
            $this->push('#text', $uu);
        }
    }

    private function auto_link($j, &$u) {
        if (!preg_match("/\bhttps?$/ui", $u, $m1))
            return false;
        if (!preg_match("/^:\/\/[^\s<]+\b\/*/ui", substr($this->in, $j + strlen($u)), $m2)) //2do substr
            return false;
        if ($m1[0] === $u)
            return $u .= $m2[0];
        $u = substr($u, 0, -strlen($m1[0]));
        return false;
    }

    private function in_square($x, $u, $inline = false, $img = false) { # 2do  footnote
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
            } elseif ('#raw' == $node->name) {
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
        #if (!$x->close && 'tbody' == $this->ptr[1]->name)
        #    $x->close = $this->ptr[1]->up; # table
        #if (!$x->close && 'table' == $this->ptr[1]->name)
        #    $x->close = $this->ptr[1];
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

    private function set_x($x, $n, &$u = null) {
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
        for ($base = $pad, $u = $fn(); strpbrk($u, " \t"); ) {
            $this->j += $sz = strspn($this->in, ' ', $this->j);
            $pad += $sz;
            for ($u = $fn(); "\t" == $u; $this->j++, $u = $fn())
                $pad += 4 - $pad % 4;
        }
        $x->empty = "\n" == $u;
        $x->pad = $pad - $base;
        return $attr ?? $u;
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