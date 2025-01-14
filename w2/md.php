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
            return $this->push('skip', $t = $x->line, ['c' => 'r']);
        }
        if ('=' == $t || strlen($z) < 3)
            return false;
        return $this->push('hr', 0, ['t' => $t = $x->line]);
    }

    private function leaf_p($x, &$t) {
        if ('p' != $this->ptr[1]->name) {
            $this->push('p');
        }
        $this->add($t);
        return false;
    }

    private function leaf_table($x, &$t) {
    }
    private function leaf_dl($x, &$t) {# :dl-dt-dd
    }

    private function blk_bq($x, &$t) {
        if (!$m = $this->x_pad($x, 0))
            return false;

        $_t = ' ' == ($x->line[1] ?? '') ? ' ' : '';
        if ('blockquote' == $x->tag->name) # continue bq
            return $this->push('skip', $t .= $_t);
        if ('p' == $this->ptr[1]->name)
            $this->close();
        return $this->push('blockquote', null, ['t' => $t .= $_t, 'pad' => 2 + $x->pad]);
    }

    private function blk_ul($x, &$t) {
        if (!$m = $this->x_pad($x, 1))
            return false;
        if ('ul' == $x->tag->name && $x->pad < $x->tag->attr['pad']) {
            if ($t == $x->tag->attr['d']) { # next li
                $this->last($x->tag);
                $x->tag->attr['pad'] = 2 + $x->pad;
                return $this->push('li', null, ['t' => $t = $m[0]]);
            } else { # other marker, close current ul
                $this->last($x->tag->up);
            }
        }
        $this->push('ul', null, ['pad' => 2 + $x->pad, 'tight' => '1', 'd' => $t]);
        return $this->push('li', null, ['t' => $t = $m[0]]);
    }

    private function blk_ol($x, &$t) {
        if (!$m = $this->x_pad($x, 2))
            return false;
        $pad = $x->pad + strlen($m[0]) + (' ' == $m[3] ? 0 : 1);

        if ('ol' == $x->tag->name) {
            if ($m[2] == $x->tag->attr['d']) { # next li
                $this->last($x->tag);
                return $this->push('li', null, ['pad' => $pad, 't' => $t = $m[0]]);
            } else { # other marker, close current ol
                $this->last($x->tag->up);
            }
        }
        $attr = 1 == $m[1] ? [] : ['start' => $m[1]];
        $this->push('ol', null, $attr + ['tight' => '1', 'd' => $m[2]]);
        return $this->push('li', null, ['pad' => $pad, 't' => $t = $m[0]]);
    }

    private function leaf_fenced($x, &$t) {
        if ($x->grab) { # already open
            if (preg_match("/^$x->grab+\s*$/", $x->line)) {
                $x->grab = '';
                $this->last($x->tag->up);
                return $this->push('skip', $t = $x->line);
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

    private function bleaf_indent($x, &$t) {
        $this->x_pad($x, $t = $this->spn(" \t"));
        $diff = $x->pad - $x->_pad;
        if ($diff > 3) {
            
            
        }
        //$this->push('skip', $t);
        return true;
    }

    private function new_line($x, &$t) {
        if ("\n" == $t) {
            $x->line = $this->cspn("\n");
            $x->pad = $x->v = 0;
            $x->nl = true;
            if (is_num($this->ptr[1]->name[1] ?? '')) # close h1..h6
                $this->close();
            $x->sz = $x->ini = $this->set_up($this->last);
            if ($x->sz && 'x' == $this->up(-1)->name[0]) { // x_code, x_html
                $this->add($t);
            } else {
                $this->push('skip', $t);
            }
        } elseif ($x->ini) {
            $x->ini = false;
            foreach ($this->stk as $tag) {
                //trace($tag);
            }
        } else {
            $x->tag = $this->up(++$x->v);
            foreach (MD::$MD->blk_chr as $chr => $func) {
                if ($chr && !strpbrk($t, $chr))
                    continue;
                if ($this->$func($x, $t))
                    return $x->nl = 'b' == $func[0];
            }
            return $x->nl = false;
        }
    }

    protected function parse(): ?stdClass { # lists: + - *
        $x = new stdClass;
        $x->line = $this->cspn("\n", 0);
        $x->grab = $x->html = $x->pad = $x->v = $x->lazy = 0;
        $len = $x->nl = $x->ini = strlen($in =& $this->in);
        $this->ptr = [null, $this->last = $this->root, &$this->root->val]; # setup the cursor: &prev, &parent, &place
        $j =& $this->j;
        $char = [
            "\\`*_{}[]()<>#+-.!|^=~:",
        ];
        $this->node = $this->node();
        $chr =& $char[0];
        for ($t = ''; $j < $len; $j += strlen($t)) {
            if ("\n" == ($t = $in[$j]) || $x->nl) {
                $this->new_line($x, $t);
            } elseif ("\\" == $t) { # escaped
                $x->nl = false;
                $next = $in[1 + $j] ?? '';
                $this->push('#text', $next, ['t' => $t .= $next]);
            } elseif ('[' == $t && $this->inline_square($x, $t)) {
                ;
            } elseif (strpbrk($t, $chr)) {
                $this->add($t = $this->spn($t));
            } else {
                $t = substr($in, $j, $n = strcspn($in, "\n$chr", $j));
                if ('  ' == substr($t, -2) && "\n" == ($in[$j + strlen($t)] ?? '')) {
                    $this->add($_t = chop($t));
                    $this->push('br', 0, ['t' => substr($t, strlen($_t))]);
                } elseif (':' == ($in[$j + $n] ?? '') && $this->auto_link($t, $j)) {
                    
                } else {
                    $this->add($t);
                }
            }
        }
        foreach ($this->for as $i => $for) {
            if (isset($this->ref[$for])) {
                #$this->tok[$i][4] = $this->ref[$for];
            } else {
                #$this->tok[$i][2] = $this->tok[$i][1];
                #$this->tok[$i][0] = 11;
            }
        }
        return $this->root->val;
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
            $this->ref[$head] = trim(substr($tail, 1));
            return $this->push('skip', $t = $head . $tail, ['c' => 'm']);
        } else {
            //$this->for[count()] = $head;
        }
        $x->nl = false;
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
            if ('skip' == $node->name)
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

    private function add($str, $name = '#text') {
        if ($name == $this->last->name)
            return $this->last->val .= $str;
        $this->push($name, $str);
    }

    function set_up($node) {
        for ($this->stk = []; '#root' != $node->name; $node = $node->up)
            isset(MD::$MD->blk_tag[$node->name]) && array_unshift($this->stk, $node);
        return count($this->stk);
    }

    private function x_pad($x, $str) {
        if (is_int($str)) {
            if (!preg_match(MD::$MD->blk_re[$str], $x->line, $match))
                return false;
            $str = $match[0];
        }
        for ($x->_pad = $x->pad, $i = 0; true; $x->pad += 4 - $x->pad % 4) {
            $i += $len = strcspn($str, "\t", $i);
            $x->pad += $len;
            if ("\t" != ($str[$i++] ?? ''))
                return $match ?? null;
        }
    }

    private function spn($set, $j = 0) {
        return substr($this->in, $j ?: $this->j, strspn($this->in, $set, $j ?: $this->j));
    }

    private function cspn($set, $j = 0) {
        return substr($this->in, $j ?: $this->j, strcspn($this->in, $set, $j ?: $this->j));
    }
}
/* $out .= a($t, $t);
                in_array($type = $p3, MD::$MD->code_type) && $this->hightlight or $type = '';
                'javascript' !== $type or $type = 'js';
                $sub = $type ? '' : $htm;
            # not reference
            # fenced code start           html($htm);
            $out .= $type ? Show::$type($sub) : $sub . $htm;
            sprintf('<img src="%s" alt="%s">', $p4, substr($p3, 1, -1)) # image
*/