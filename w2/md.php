<?php

class MD extends XML # the MarkDown, follow Common Mark https://spec.commonmark.org/
{
    const version = '0.555';

    static $MD;

    public $hightlight = false; # if true then work Show::php(..) for ```php

    private $ref = [];
    private $for = [];

    function __construct(string $in = '', $tab = 2) {
        parent::__construct($in, $tab);
        MD::$MD or MD::$MD = Plan::set('main', fn() => yml('md', '+ @object @inc(md)', false, true));
    }

    private function leaf_html($x, &$t) {
        if (!preg_match("/^<(\?|!|\/?[a-z][a-z\-]*)/i", $x->line, $m))
            return false;
        if ('?' == $m[1]) {
            $x->html = '?>';
        } elseif ('!' == $m[1]) {
            foreach (MD::$MD->typ_1 as $k => $v)
                if ($k == substr($x->line, 0, strlen($k))) {
                    $x->html = $v;
                    $m[0] = $k;
                    goto end;
                }
        } elseif (in_array($v = strtolower($m[1]), MD::$MD->typ_2)) {
            $x->html = "</$v>";
        } else {
            $x->html = "\nqqq\n";
            goto end;
            //2do return false;
        }
        end:
        return $this->push('raw', html($t = $m[0]));
    }

    private function leaf_table($x, &$t) {
    }
    private function leaf_dl($x, &$t) {# :dl-dt-dd
    }

    private function leaf_h6($x, &$u) {
        if (!preg_match("/^#{1,6}(\s+|\z)/", $x->line, $m, PREG_OFFSET_CAPTURE))
            return false;
        $this->use_close($x);
        $this->close('p');
        $this->j += strlen($m[0][0]);
        return $this->push('h' . $m[1][1], null, ['t' => $m[0][0]]);
    }

    private function leaf_h2r($x, &$u) {
        if (!preg_match("/^\\{$u}[\\$u \t]*$/", $x->line))
            return false;
        $z = str_replace(["\t", ' '], '', $x->line);
        $p =& $this->ptr[1]->name;
        $h12 = '=' == $u ? 'h1' : ('-' == $u ? 'h2' : false);
        if ($h12 && 'p' == $p && $z == chop($x->line)) {
            $p = $h12;
            $this->close();
            return $this->add($x->line, ['c' => 'r']);
        }
        if ('=' == $u || strlen($z) < 3)
            return false;
        $this->use_close($x);
        $this->j += strlen($x->line);
        return $this->push('hr', 0, ['t' => $x->line]);
    }

    private function leaf_code($x, &$u) {
        if ('x-code' != $this->ptr[1]->name)
            $x->added = $this->push('x-code', null, ['lang' => '', 't' => '']);
        $x->nls = false;
        $this->add(4);
        $x->pad - 4 && $this->push('#text', str_pad('', $x->pad - 4));
        return $this->add($x->line);
    }

    private function leaf_p($x, &$u) {
        $x->pad && $this->add($x->pad);
        $x->pad = 0;
        if ('p' == $this->ptr[1]->name)
            return false; # lazy continue
        $this->use_close($x);
        $this->close('x-code');
        $x->empty or $x->added = $this->push('p');
        return false;
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
            if (preg_match("/^$x->grab+\s*$/", $x->line)) {
                $this->close();
                $x->grab = 0;
                return $x->nls = $this->add($x->line, ['c' => 'r']);
            } else {
                $x->pad && $this->push('#text', str_pad('', $x->pad));
                $this->add($x->line);
                $x->pad = 0;
            }
        } elseif (preg_match("/^($u{3,})\s*(\w*).*$/", $x->line, $m)) {
            $this->use_close($x);
            $x->grab = $x->nls = $m[1];
            $this->j += strlen($x->line);
            return $this->push('x-code', null, ['lang' => $m[2], 'pad' => (string)$x->pad, 't' => $x->line]);
        }
        return false;
    }

    protected function parse(): ?stdClass {
        $x = new stdClass;
        $x->grab = $x->html = $x->nls = $prev = false;
        $this->last($this->root, true);
        $len = strlen($this->in);
        for ($j =& $this->j; $j < $len; ) {
            while ("\n" == ($u = $this->in[$j] ?? '') || !$j) { # start new line
                if ("\n" == $u) {
                    $this->add("\n", $x->nls);
                    if ($x->nls && $x->grab)
                        $x->nls = false;
                }
                $x->added = $x->close = false;
                if (is_num($this->ptr[1]->name[1] ?? '')) # close h1..h6
                    $this->close();
                $this->chk_old($x, $u, $empty);
                $x->empty && $this->close('p') && $this->use_close($x) or $this->add_new($x, $u);
                $prev && $x->added && $this->set_tight($prev);
                $prev = $empty;
                if (!$j)
                    break;
            }
            $this->inlines($x, $u);
        }
        $this->j = 0;
        return $this->root->val;
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
            } elseif ('>' != $u || $x->pad > 3) {
                if ($x->grab) {
                    $x->grab = false;
                    return $this->close();
                }
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
        start: # add new blocks
        $x->line = $this->cspn("\n");
        if ($x->pad >= $x->base && $x->base) {
            $x->pad -= $x->base;
            $this->add($x->base);
        }
        if ($x->grab) {
            return $this->leaf_fenced($x, $u);
        } elseif ($x->pad > 3) {
            $this->use_close($x);
            if ('p' == $this->ptr[1]->name)
                return $this->leaf_p($x, $u);
            return $this->leaf_code($x, $u);
        }
        $x->pad && $this->add($x->pad);
        foreach (MD::$MD->blk_chr as $chr => $func) {
            if ((!$chr || strpbrk($u, $chr)) && $this->$func($x, $u)) {
                $x->added = true;
                if ($x->empty || 'b' != $func[0])
                    break;
                goto start;
            }
        }
    }

    private function inlines($x, &$u) {
        $in =& $this->in;
        $j =& $this->j;
        if ("\\" == $u) { # escape
            $next = $in[1 + $j] ?? '';
            $esc = '' !== $next && strpbrk($next, MD::$MD->esc);
            if ($esc) {
                $this->add("\\", true);
                $this->add($next);
            } else {
                $this->add("\\");
            }
        } elseif ('[' == $u && $this->in_square($x, $u)) {
            ;
        } elseif (strpbrk($u, MD::$MD->esc)) {
            $this->add($u = $this->spn($u));
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

    private function in_square($x, &$u) { # 2do  footnote
        if (!$len = strlen($head = Rare::bracket($this->in, '[', $j = $this->j, "\n\\")))
            return false;
        $tail = '';
        if (in_array($chr = $this->in[$j += $len], ['[', '('])) {
            $tail = Rare::bracket($this->in, $chr, $j, "\n\\");
            #if (!$tail || '[' == $chr)
                //$this->for[count()] = $tail && '[]' != $tail ? $tail : $head;
        } elseif (':' == $chr) {
            if (':' == trim($tail = $this->cspn("\n", $j)))//$x->lazy || 
                return false;
            $this->use_close($x);
            $this->ref[$head] = trim(substr($tail, 1));
            return $this->add($head . $tail, ['c' => 'm']);
        } else {
            //$this->for[count()] = $head;
        }
        $this->j += strlen($head . $tail);
        return $this->push('a', substr($head, 1, -1), ['href' => $tail, 'c' => 'g', 't' => $head . $tail]);
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
            } elseif ('#' == $node->name[0]) {
                '' === $type ? ($out .= $node->val) : ($code .= $node->val);
            } elseif ('x-code' == $node->name) { // code 
                $type = $node->attr['lang'] ?: true;
                'javascript' !== $type or $type = 'js';
                $code = ''; # fenced code start
            } else {
                if ('li' == $node->name && $node->up->attr['tight'])
                    for ($tag = $node->val; $tag; $tag = $tag->right)
                        'p' != $tag->name or $this->remove($tag, false);
                $out .= str_pad('', $depth * 2) . $this->tag($node, $close, ['href', 'start']);
                $out .= null === $node->val || is_string($node->val) ? "$node->val$close\n" : "\n";
            }
        }
        return $out;
    }

    private function use_close($x) {
        $x->close && $this->last('li' == $x->close->name ? $x->close->up : $x->close);
        $x->pad < 4 && $this->close('x-code');
        #if ($x->close)
        #    $x->grab = false;
        $x->close = false;
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

$out .= a($t, $t);
            sprintf('<img src="%s" alt="%s">', $p4, substr($p3, 1, -1)) # image
trace($x->close, '===');*/