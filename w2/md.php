<?php

class MD extends XML # the MarkDown, follow Common Mark https://spec.commonmark.org/
{
    const version = '0.333';

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
        if (!preg_match("/^(#{1,6})(\s+)\S/", $x->line, $m))
            return false;
        if ('p' == $this->ptr[1]->name)
            $this->close();
        return $this->push('h' . strlen($m[1]), null, ['t' => $u = $m[1] . $m[2]]);
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
            return $this->add($u = $x->line, ['c' => 'r']);
        }
        if ('=' == $u || strlen($z) < 3)
            return false;
        $this->use_close($x);
        return $this->push('hr', 0, ['t' => $u = $x->line]);
    }

    private function leaf_p($x, &$u) {
        if ('p' == $this->ptr[1]->name)
            return false; # lazy continue
        $this->use_close($x);
        if ($x->pad > 3) {
            'x-code' == $this->ptr[1]->name or $this->push('x-code', null, ['lang' => '']);
            return $this->add($u = $x->line);
        }
        $this->close('x-code');
        $x->empty or $x->added = $this->push('p');
        return false;
    }

    private function blk_bq($x, &$u) {
        if (!$m = $this->set_x($x, 2))
            return false;
        $this->use_close($x);
        return $this->push('blockquote', null, ['t' => $u = $m[0]]);
    }

    private function blk_ul($x, &$u) {
        return $this->blk_ol($x, $u, 1);
    }

    private function blk_ol($x, &$u, $n = 0) {
        if (!$m = $this->set_x($x, $n))
            return false;
        if ($x->close) {
            $this->last($li = $x->close);
            $x->close = false;
            if ($m[2] == $li->up->attr['d']) # next li
                return $this->push('li', null, ['pad' => $x->pad, 't' => $u = $m[0]]);
            $this->close(); # ul/ol
        }
        $attr = ['tight' => '1', 'd' => $m[2]];
        $n or 1 == $m[1] or $attr += ['start' => $m[1]];
        $this->push($n ? 'ul' : 'ol', null, $attr);
        return $this->push('li', null, ['pad' => $x->pad, 't' => $u = $m[0]]);
    }

    private function leaf_fenced($x, &$u) {
        if ($x->grab) { # already open
            if (preg_match("/^$x->grab+\s*$/", $x->line)) {
                $this->close();
                $x->grab = 0;
                return $x->nls = $this->add($u = $x->line, ['c' => 'r']);
            } else {
                $this->add($u = $x->line);
            }
        } elseif (preg_match("/^($u{3,})\s*(\w*).*$/", $x->line, $m)) {
            $this->use_close($x);
            $x->grab = $x->nls = $m[1];
            return $this->push('x-code', null, ['lang' => $m[2], 'pad' => (string)$x->pad, 't' => $u = $x->line]);
        }
        return false;
    }

    protected function parse(): ?stdClass { # lists: + - *
        $x = new stdClass;
        $x->grab = $x->html = $x->nls = $prev = 0;
        $this->last($this->root, true);
        $len = strlen($this->in);
        for ($j =& $this->j; $j < $len; $j += strlen($u)) {
            while ("\n" == ($u = $this->in[$j] ?? '') || !$j) { # start new line
                if ("\n" == $u) {
                    $this->add("\n", $x->nls);
                    $j++;
                    if ($x->nls && $x->grab)
                        $x->nls = false;
                }
                if (is_num($this->ptr[1]->name[1] ?? '')) # close h1..h6
                    $this->close();
                $x->added = false;
                if ($this->blocks_chk($x, $u, $empty)) {
                    $this->blocks_add($x, $u);
                    $prev && $x->added && $this->set_tight($prev);
                }
                $prev = $empty;
                if (!$j)
                    break;
            }
            $this->inlines($x, $u);
        }
        $this->j = 0;
        return $this->root->val;
    }

    private function blocks_chk($x, &$u, &$empty) {
        $ary = [];
        $x->close = false;
        for ($tag = $this->last; $tag && '#root' != $tag->name; $tag = $tag->up)
            in_array($tag->name, MD::$MD->blk_tag) && array_unshift($ary, $tag);
        $u = $this->set_x($x, 7);
        $empty = $x->empty ? $this->last : false;
        foreach ($ary as $tag) {
            if ('blockquote' == $tag->name) {
                if ('>' != $u || $x->pad > 3) {
                    if ($x->grab) {
                        $x->grab = false;
                        return $this->close();
                    }
                    return $x->close = $tag;
                }
                $u = $this->set_x($x, 5);
                if ($x->empty)
                    $empty = $this->last;
            } elseif ('li' == $tag->name) {
                if (!$x->empty && $x->pad < $tag->attr['pad'])
                    return $x->close = $tag;
            } elseif ('p' == $tag->name && $x->empty) {
                $this->last($tag);
                return false;
            }
        }
        return true;
    }

    private function blocks_add($x, &$u) {
        start: # add new blocks
        $x->line = $this->cspn("\n");
        if ($x->grab) {
            $this->leaf_fenced($x, $u);
            return $this->j += strlen($u);
        }
        foreach (MD::$MD->blk_chr as $chr => $func) {
            if ((!$chr || strpbrk($u, $chr)) && $this->$func($x, $u)) {
                $x->added = $this->j += strlen($u);
                $u = $this->set_x($x, 3);
                if ($x->empty || 'b' != $func[0])
                    break;
                goto start;
            }
        }
    }

    private function inlines($x, &$u) {
        $in =& $this->in;
        $j =& $this->j;
        if ("\\" == $u) { # escaped
            $next = $in[1 + $j] ?? '';
            $this->push('#text', $next, ['t' => $u .= $next]);
        } elseif ('[' == $u && $this->in_square($x, $u)) {
            ;
        } elseif (strpbrk($u, MD::$MD->esc)) {
            $this->add($u = $this->spn($u));
        } else {
            $u = substr($in, $j, $n = strcspn($in, "\n" . MD::$MD->esc, $j));
            if ('  ' == substr($u, -2) && "\n" == ($in[$j + strlen($u)] ?? '')) {
                $this->add($t2 = chop($u));
                $this->push('br', 0, ['t' => substr($u, strlen($t2))]);
            } elseif (':' == ($in[$j + $n] ?? '') && $this->auto_link($u, $j)) {
                
            } else {
                $this->add($u);
            }
        }
    }

    private function auto_link(&$t, $j) {
        if (!preg_match("/\bhttps?$/ui", $t, $m1))
            return false;
        if (!preg_match("/^:\/\/[^\s<]+\b\/*/ui", substr($this->in, $j + strlen($t)), $m2)) //2do substr
            return false;
        if ($m1[0] !== $t) {
            $t = substr($t, 0, -strlen($m1[0]));
            return false;
        }
        $this->push('a', $t .= $m2[0], ['href' => $t, 'c' => 'g']);
        return true;
    }

    private function in_square($x, &$t) { # 2do  footnote
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
            return $this->add($t = $head . $tail, ['c' => 'm']);
        } else {
            //$this->for[count()] = $head;
        }
        return $this->push('a', substr($head, 1, -1), ['href' => $tail, 'c' => 'g', 't' => $t = $head . $tail]);
    }

    function md_raw($in) {//return $this->xml_nice($in);
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
                '' === $type ? ($out .= $node->val . "\n") : ($code .= $node->val);
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
        #if ($x->close)
        #    $x->grab = false;
        $x->close = false;
    }

    private function add($str, $skip = false) {
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

    private function set_x($x, $n) {
        static $base, $pad, $u, $pmem;

        if ($n < 3) {
            if ($x->pad > 3 || !preg_match(MD::$MD->blk_re[$n], $x->line, $out)) //
                return false;
            $str = $out[$pmem = 0];
            $n < 2 or $base = $pad + ($out[3] ? 2 : 1);
            $x->pad = $pad + 2 + strlen($out[1]);
        } else {
            if (7 == $n) {
                $base = $pad = 0;
            } elseif (5 == $n) {
                $base = ++$pad;
                $this->j++;
                $this->add('>', ['c' => 'r']);
            }
            $str = substr($this->in, $this->j, $sz = strspn($this->in, " \t", $this->j));
            if (5 == $n && $sz)
                $base++;
            $x->empty = in_array($out = $u = $this->in[$this->j += $sz] ?? '', ['', "\n"]);
            if ($sz)
                $this->add($str, true);
        }
        for ($i = 0; true; $pad += 4 - $pad % 4) {
            $i += $sz = strcspn($str, "\t", $i);
            $pad += $sz;
            if ("\t" != ($str[$i++] ?? ''))
                break;
        }
        if ($n == 3) {
            $x->pad = $pmem;
            return $u;
        } elseif ($n > 3) {
            $x->pad = $pad - $base;
        } elseif ($x->empty || $pad - $x->pad > 3) {
            $pmem = $pad - $x->pad;
            $x->pad = $x->pad - $base;
        } else {
            $x->pad = $pad - $base;
        }
        return $out;
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