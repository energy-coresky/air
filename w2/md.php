<?php

class MD extends XML # the MarkDown, follow Common Mark https://spec.commonmark.org/
{
    const version = '0.333';

    static $MD;

    public $hightlight = false; # if true then work Show::php(..) for ```php

    private $ref = [];
    private $for = [];
    private $j = 0;

    function __construct(string $in = '', $tab = 2) {
        parent::__construct($in, $tab);
        MD::$MD or MD::$MD = Plan::set('main', fn() => yml('md', '+ @object @inc(md)'));
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

    private function leaf_h6($x, &$t) {
        if (!preg_match("/^(#{1,6})(\s+)\S/", $x->line, $m))
            return false;
        if ('p' == $this->ptr[1]->name)
            $this->close();
        return $this->push('h' . strlen($m[1]), null, ['t' => $t = $m[1] . $m[2]]);
    }

    private function leaf_h2r($x, &$t) {
        if (!preg_match("/^\\{$t}[\\$t \t]*$/", $x->line))
            return false;
        $z = str_replace(["\t", ' '], '', $x->line);
        $p =& $this->ptr[1]->name;
        $h12 = '=' == $t ? 'h1' : ('-' == $t ? 'h2' : false);
        if ($h12 && 'p' == $p && $z == chop($x->line)) {
            $p = $h12;
            $this->close();
            return $this->add($t = $x->line, ['c' => 'r']);
        }
        if ('=' == $t || strlen($z) < 3)
            return false;
        $this->use_close($x);
        return $this->push('hr', 0, ['t' => $t = $x->line]);
    }

    private function leaf_p($x, &$t) {
        if ('p' == $this->ptr[1]->name)
            return false; # lazy continue
        $this->use_close($x);
        if ($x->pad > 3) {
            'x-code' == $this->ptr[1]->name or $this->push('x-code');
            return $this->add($t = $x->line);
        }
        $this->close('x-code');
        $x->empty or $x->added = $this->push('p');
        return false;
    }

    private function blk_bq($x, &$t, $test = false) {
        if (!$m = $this->set_x($x, 2))
            return false;
        $this->use_close($x);
        return $this->push('blockquote', null, ['t' => $t = $m[0]]);
    }

    private function blk_ul($x, &$t) {
        return $this->blk_ol($x, $t, 1);
    }

    private function blk_ol($x, &$t, $n = 0) {
        if (!$m = $this->set_x($x, $n))
            return false;
        if ($x->close) {
            $this->last($li = $x->close);
            $x->close = false;
            if ($m[2] == $li->up->attr['d']) # next li
                return $this->push('li', null, ['pad' => $x->pad, 't' => $t = $m[0]]);
            $this->close(); # ul/ol
        }
        $attr = ['tight' => '1', 'd' => $m[2]];
        $n or 1 == $m[1] or $attr += ['start' => $m[1]];
        $this->push($n ? 'ul' : 'ol', null, $attr);
        return $this->push('li', null, ['pad' => $x->pad, 't' => $t = $m[0]]);
    }

    private function leaf_fenced($x, &$t) {
        if ($x->grab) { # already open
            if (preg_match("/^$x->grab+\s*$/", $x->line)) {
                $x->grab = '';
                $this->last($x->tag->up);
                return $this->add($t = $x->line, ['c' => 'r']);
            }
        } elseif (preg_match("/^($t{3,})\s*(\w*).*$/", $x->line, $m)) {
            $x->grab = $m[1];
            if ('p' == $this->ptr[1]->name)//$x->id < 90
                $this->close();
            $lang = $m[2] ? ['lang' => $m[2]] : []; // code class="language-$m[2]"
            return $this->push('pre', null, $lang + ['pad' => $x->pad, 't' => $t = $x->line]);
        }
        return false;
    }

    protected function parse(): ?stdClass { # lists: + - *
        $x = new stdClass;
        $x->grab = $x->html = $p_empty = 0;
        $this->last($this->root, true);
        $len = strlen($in =& $this->in);
        for ($j =& $this->j; $j < $len; $j += strlen($u)) {
            while ("\n" == ($u = $in[$j] ?? '') || !$j) {
                if ("\n" == $u) {
                    $this->add("\n");
                    $j++;
                }
                if (is_num($this->ptr[1]->name[1] ?? '')) # close h1..h6
                    $this->close();
                $close = $x->added = 0;
                # test continue blocks
                $tag = $this->last;
                for ($this->stk = []; $tag && '#root' != $tag->name; $tag = $tag->up)
                    in_array($tag->name, MD::$MD->blk_tag) && array_unshift($this->stk, $tag);
                $u = $this->set_x($x, 7);
                $empty = $x->empty ? $this->last : false;
                foreach ($this->stk as $tag) {
                    if ('blockquote' == $tag->name) {
                        if ($close = '>' != $u || $x->pad > 3)
                            break;
                        $u = $this->set_x($x, 5);
                        if ($x->empty)
                            $empty = $this->last;
                    } elseif ('li' == $tag->name) {
                        if ($close = !$x->empty && $x->pad < $tag->attr['pad'])
                            break;
                    } elseif ('p' == $tag->name && $x->empty) {
                        $this->last($tag);
                        goto end;
                    }
                }
                $x->close = $close ? $tag : false;
                add: # add new blocks
                $x->line = $this->cspn("\n");
                foreach (MD::$MD->blk_chr as $chr => $func) {
                    if ($chr && !strpbrk($u, $chr) || !$this->$func($x, $u))
                        continue;
                    $x->added = $j += strlen($u);
                    $u = $this->set_x($x, 3);
                    if ('b' == $func[0] && !$x->empty) {
                        goto add;
                    } else {
                        break;
                    }
                }
                if ($p_empty && $x->added)
                    $this->set_tight($p_empty);
                end:
                $p_empty = $empty;
                if (!$j)
                    break;
            }
            $this->inline($x, $u);
        }
        return $this->root->val;
    }

    private function inline($x, &$u) {
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
            if ($x->lazy || ':' == trim($tail = $this->cspn("\n", $j)))
                return false;
            $this->use_close($x);
            $this->ref[$head] = trim(substr($tail, 1));
            return $this->add($t = $head . $tail, ['c' => 'm']);
        } else {
            //$this->for[count()] = $head;
        }
        return $this->push('a', substr($head, 1, -1), ['href' => $tail, 'c' => 'g', 't' => $t = $head . $tail]);
    }

    function md_raw($in) {return $this->xml_nice($in);
        $out = '';
        foreach ($this->gen($in, true) as $depth => $node) {
            if ('#' == $node->name[0]) {
                $out .= sprintf($this->spec[$node->name], $node->val) . "\n";
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
        $out = '';
        foreach ($this->gen($in, true) as $depth => $node) {
            if ('#skip' == $node->name)
                continue;
            if ('li' == $node->name && $node->up->attr['tight'])
                for ($tag = $node->val; $tag; $tag = $tag->right)
                    'p' != $tag->name or $this->remove($tag, false);
            if ('#' == $node->name[0]) {
                $out .= sprintf($this->spec[$node->name], $node->val) . "\n";
            } elseif ($depth < 0) {
                $out .= str_pad('', -$depth * 2 - 2) . "</$node->name>\n";
            } else {
                $out .= str_pad('', $depth * 2) . $this->tag($node, $close, ['href', 'start']);
                $out .= null === $node->val || is_string($node->val) ? "$node->val$close\n" : "\n";
            }
        }
        return $out;
    }

    private function use_close($x) {
        $x->close && $this->last('li' == $x->close->name ? $x->close->up : $x->close);
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

    private function set_tight($li) { // 2do use $this->stk ?
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
            if (!preg_match(MD::$MD->blk_re[$n], $x->line, $out))//$x->p1 > 3 || 
                return false;
            $str = $out[0];
            $pmem = 0;
            $n < 2 or $base = $pad + ($out[3] ? 2 : 1);
            $x->pad = $pad + 2 + strlen($out[1]);
        } else {
            if (7 == $n) {
                $base = $pad = 0;
            } elseif (5 == $n) {
                $base = $pad + 1;
                $pad++;
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
            $i += $len = strcspn($str, "\t", $i);
            $pad += $len;
            if ("\t" != ($str[$i++] ?? ''))
                break;
        }
        if ($n > 3) {
            $x->pad = $pad - $base;
        } elseif ($n == 3) {
            $x->pad = $pmem;
        } elseif ($x->empty || $pad - $x->pad > 3) {
            $pmem = $pad - $x->pad;
            $x->pad = $x->pad - $base;
        } else {
            $x->pad = $pad - $base;
        }
        return 3 == $n ? $u : $out;
    }

    private function spn($set, $j = 0, &$sz = null) { # collect $set
        return substr($this->in, $j ?: $this->j, $sz = strspn($this->in, $set, $j ?: $this->j));
    }

    private function cspn($set, $j = 0, &$sz = null) { # collect other than $set
        return substr($this->in, $j ?: $this->j, $sz = strcspn($this->in, $set, $j ?: $this->j));
    }
}
/* 

        foreach ($this->for as $i => $for) {
            if (isset($this->ref[$for])) {
                #$this->tok[$i][4] = $this->ref[$for];
            } else {
                #$this->tok[$i][2] = $this->tok[$i][1];
                #$this->tok[$i][0] = 11;
            }
        }

$out .= a($t, $t);
                in_array($type = $p3, MD::$MD->code_type) && $this->hightlight or $type = '';
                'javascript' !== $type or $type = 'js';
                $sub = $type ? '' : $htm;
            # not reference
            # fenced code start           html($htm);
            $out .= $type ? Show::$type($sub) : $sub . $htm;
            sprintf('<img src="%s" alt="%s">', $p4, substr($p3, 1, -1)) # image
trace($x->close, '===');*/