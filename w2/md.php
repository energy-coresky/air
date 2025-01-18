<?php

class MD extends XML # the MarkDown
{
    const version = '0.232';
    //const char = '\\`*_{}[]()#+-.!|^=~:'; //    >

    static $MD;

    public $hightlight = false; # if true then work Show::php(..) for ```php

    private $ref = [];
    private $for = [];
    private $j = 0;
    private $node;

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
        $x->close && $this->last('li' == $x->close->name ? $x->close->up : $x->close);
        return $this->push('hr', 0, ['t' => $t = $x->line]);
    }

    private function leaf_p($x, &$t) {
        if ($x->close)
            $this->last('li' == $x->close->name ? $x->close->up : $x->close);
        if ('p' != $this->ptr[1]->name)
            $this->push('p');
        return false;
    }

    private function leaf_table($x, &$t) {
    }
    private function leaf_dl($x, &$t) {# :dl-dt-dd
    }

    private function blk_bq($x, &$t, $tag = false) {
        if (!$m = $this->set_x($x, 2))
            return false;
        $x->close && $this->last('li' == $x->close->name ? $x->close->up : $x->close);
        $x->close = false;
        return $this->push('blockquote', null, ['t' => $t = $m[0], 'pad' => $x->pad]);
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

    private function leaf_indent($x, &$t) {
        if ($x->pp > 3) {
        }
        return true;
    }

    protected function parse(): ?stdClass { # lists: + - *
        $x = new stdClass;
        $x->grab = $x->html = $x->pad = $x->lazy = $jj =0;
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
                $x->pad = $close = 0;
                $tag = $this->last;
                for ($this->stk = []; $tag && '#root' != $tag->name; $tag = $tag->up)
                    in_array($tag->name, MD::$MD->blk_tag) && array_unshift($this->stk, $tag);
                $u = $this->set_x($x);
                foreach ($this->stk as $tag) { # test continue blocks
                    if ('blockquote' == $tag->name) {
                        if ($close = '>' != $u || $x->pad > 3)
                            break;
                        $j++;
                        $x->pad++;
                        $u = $this->set_x($x);
                    } elseif ('li' == $tag->name) {
                        if ($close = !$x->empty && $x->pad < $tag->attr['pad'])
                            break;
                    } elseif ('p' == $tag->name && $x->empty) {
                        $this->last($tag);
                        goto end;
                    }
                }
                $x->close = $close ? $tag : false;
                add:
                $x->line = $this->cspn("\n");
                foreach (MD::$MD->blk_chr as $chr => $func) { # search for new blocks
                    if ($chr && !strpbrk($u, $chr) || !$this->$func($x, $u))
                        continue;
                    $j += strlen($u);
                    $u = $this->set_x($x);
                    if ('b' == $func[0] && !$x->empty) {
                        goto add;
                    } else {
                        break;
                    }
                }
                end:
                if (!$j || $jj++ > 1000)
                    break;
            }
            $this->inline($x, $u);
            
if($jj++ > 1000)break;
        }
        return $this->root->val;
    }

    private function inline($x, &$u) {
        $in =& $this->in;
        $j =& $this->j;
        if ("\\" == $u) { # escaped
            $next = $in[1 + $j] ?? '';
            $this->push('#text', $next, ['t' => $u .= $next]);
        } elseif ('[' == $u && $this->inline_square($x, $u)) {
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

    private function inline_square($x, &$t) { # 2do  footnote
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
            $x->close && $this->last($x->close);
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
            if ('li' == $node->name && 1 == $node->up->attr['tight'] && is_object($node->val))
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

    private function add($str, $skip = false) {
        //$this->j += strlen($str);
        if (is_array($skip))
            return $this->push('#skip', $str, $skip);
        $name = $skip ? '#skip' : '#text';
        if ($name == $this->last->name)
            return $this->last->val .= $str;
        return $this->push($name, $str);
    }

    private function set_x($x, $str = null) {
        if (is_int($str)) {
            if (!preg_match(MD::$MD->blk_re[$str], $x->line, $out))
                return false;
            if ('' === $out[3])
                $x->pad++;
            $str = $out[0];
        } elseif (is_null($str)) {
            $str = substr($this->in, $this->j, $sz = strspn($this->in, " \t", $this->j));
            $x->empty = in_array($out = $this->in[$this->j += $sz] ?? '', ['', "\n"]);
            if ($sz)
                $this->add($str, true);
        }
        for ($x->pp = $x->pad, $i = 0; true; $x->pad += 4 - $x->pad % 4) {
            $i += $len = strcspn($str, "\t", $i);
            $x->pad += $len;
            if ("\t" != ($str[$i++] ?? ''))
                break;
        }
        $x->pp = $x->pad - $x->pp;
        return $out ?? null;
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