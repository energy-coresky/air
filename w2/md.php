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
        $pre = fn($m) => $m[0] == "<$m[1]" || $m[2] && '/' != $m[2][0];
        if (!$x->grab) {
            if (!preg_match("/^<(\?|![a-z]|!|\/?[a-z][a-z\d\-]*)(\s|\/>|>)*.*$/i", $x->line, $m))
                return false;
            if ('?' == $m[1]) {
                $x->grab = '?>';
            } elseif ('!' == $m[1]) {
                if ('<!--' == substr($x->line, 0, 4)) {
                    $x->grab = '-->';
                } elseif ('<![CDATA[' == substr($x->line, 0, 9)) {
                    $x->grab = ']]>';
                } else {
                    return false;
                }
            } elseif ('!' == $m[1][0]) {
                $x->grab = '>';
            } elseif (in_array($tag = strtolower($m[1]), ['pre', 'script', 'style', 'textarea']) && $pre($m)) {
                $x->grab = "</$tag>";
            } else {
                $tag = strtolower($m[1]);
                if ('/' == $tag[0])
                    $tag = substr($tag, 1);
                $tags = in_array($tag, MD::$MD->tags) && ($m[2] || $m[0] == "<$m[1]");
                if ($tags || 1) {
                    $x->grab = "<"; # blank line
                } else {
                    return false;
                }
            }
            //$this->close('p');
            //$this->use_close($x);
        }
        $blank = '<' == $x->grab;
        if ($blank && $x->empty || !$blank && false !== strpos($x->line, $x->grab))
            $x->grab = false;
        $x->empty or $this->j += strlen($x->line);
        return $this->push('-html', $x->line);
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
            $exist = 'tbody' == $tbody->name or $tbody = $this->push('tbody');
            $exist ? ($node =& $this->last->right) : ($node =& $tbody->val);
            $x->nls = true;
            $x->cvl = 0;
            $this->line($x);
            return $this->last($node = $this->tr('td', $x->table, $node, $tbody, $tbody));
        } elseif (!is_int($x->cvl) || '' !== chop($x->line, "\t -:|")) {
            return $this->leaf_p($x);
        } else {
            $x->table = array_map(function ($v) {
                $align = ':' == $v[0] ? ' align="left"' : '';
                return ':' != $v[-1] ? $align : ($align ? ' align="center"' : ' align="right"');
            }, preg_split("/\s*\|\s*/", trim($x->line, "|\t ")));
            if (count($x->table) != $x->cvl)
                return $x->table = $x->cvl = false;
            $this->parent($this->up, 'table');
            $this->push('thead', $this->tr('th', $x->table, $this->up->val, $this->up));
            return $this->add($x->line, ['c' => 'r']);
        }
    }

    private function tr($tx, $ary, $node, $parent, $up = null) {
        $val = "<$tx" . array_shift($ary) . '>';
        if ('-t' == $node->name) {
            $node->val = $val;
            $node->attr['t'] = '|';
        } else {
            $node = $node->left = XML::node('-t', $val, ['t' => ''], $node);
        }
        $node->up = $tr = XML::node('tr', $last = $node, $parent->attr ?? null, null, $up);
        for ($close = false; $node = $node->right; $last = $node) {
            $node->up = $tr;
            $vl = '-t' == $node->name;
            if ($ary && $vl) {
                $node->val = "</$tx><$tx" . array_shift($ary) . '>';
                $node->attr['t'] = '|';
            } elseif ($close) {
                $node->name = '#skip';
            } elseif ($close = $vl) {
                $node->val = "</$tx>";
                $node->attr['t'] = '|';
            }
        }
        if (!$close) {
            $val = str_repeat("</$tx><$tx>", count($ary)) . "</$tx>";
            $last->right = XML::node('-t', $val, ['t' => ''], null, $tr, $last);
        }
        return $tr;
    }

    private function leaf_p($x) {
        if ($x->table)
            return $this->leaf_table($x);
        if ('p' == $this->up->name)
            return false; # lazy continue
        $this->use_close($x);
        if (!$x->empty) {
            $x->cvl = $x->nls = 0;
            $x->added = $this->push('p');
        }
        return false;
    }

    private function leaf_code($x) {
        if ('x-code' != $this->up->name)
            $x->added = $this->push('x-code', null, ['lang' => '', 't' => '']);
        $x->nls = false;
        $x->pad - 4 && $this->push('#text', str_pad('', $x->pad - 4));
        return $this->add($x->line);
    }

    private function blk_blockquote($x, &$y) {
        $old = $x->pad;
        if (!$this->set_x(2, $x, $y))
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
        if (!$attr = $this->set_x($n, $x, $y))
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
            $this->use_close($x);
            $x->grab = $x->nls = $m[1];
            $this->j += strlen($x->line);
            return $this->push('x-code', null, ['lang' => $m[2], 'pad' => (string)$x->pad, 't' => $x->line]);
        }
        return false;
    }

    protected function parse(): ?stdClass {
        $x = new stdClass;
        $z = $x->grab = $x->nls = $x->table = $x->cvl = false;
        $this->parent($this->root);
        do {
            $x->added = $x->close = false;
            if ("\n" == ($this->in[$this->j] ?? '')) {
                if (is_num($this->up->name[1] ?? ''))
                    $this->leaf_h6(false); # close h1..h6
                $this->add("\n", $x->nls);
                if ($x->nls && $x->grab)
                    $x->nls = false;
            }
            foreach ($this->set_x(3, $x, $y, $last) as $tag) {
                if ('li' == $tag->name) {
                    if (!$x->empty && $x->pad < ($tag->attr['pad'] + $x->base))
                        goto brk;
                    $x->base += $tag->attr['pad'];
                } elseif ('>' != $y || $x->pad > 3) {
                    brk: $x->close = $tag;
                    break;
                } else { # also blockquote
                    $x->pad && $this->add($x->pad);
                    $this->set_x(4, $x, $y, $last);
                    if ($x->pad) {
                        $this->last->val .= ' ';
                        $x->base++;
                    }
                }
            }
            $x->empty && ($x->table || $this->close('p')) ? $this->use_close($x) : $this->blocks($x, $y);
            $z && $x->added && $this->set_tight($z);
            $z = $last;
        } while ($this->line($x));
        $this->j = 0;
        $this->last->attr['last'] = 1;

        foreach ($this->for as $rf => $set) {
            if (!isset($this->ref[$rf]))
                continue;
            $url = $this->ref[$rf];
            foreach ($set as $node) {
                $head = substr($node->attr['head'], 1, -1);
                if ('!' == $node->val[0]) { # img
                    $node->name = 'img';
                    $node->attr = ['src' => $url, 'alt' => $head, 't' => $node->val, 'c' => 'c'];
                    $node->val = 0;
                } else {
                    $node->name = 'a';
                    $node->attr = ['href' => $url, 't' => $node->val, 'c' => 'g'];
                    $node->val = $head;
                }
            }
        }
        return $this->root->val;
    }

    private function blocks($x, $y) {
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
            $is_p = 'p' == $this->up->name or $this->use_close($x);
            $this->add($is_p ? $x->pad : 4);
            if ($is_p)
                return $this->leaf_p($x);
            return $this->leaf_code($x);
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

    private function line($x) {
        $in =& $this->in;
        $j =& $this->j;
        if (is_int($x->cvl))
            $x->cvl = $x->cvl ? false : ('|' == $in[$j] ? 0 : 1);
        while (!in_array($y = $in[$j] ?? '', ['', "\n"], true)) {
            $next = $in[1 + $j] ?? '';
            if ("\\" == $y) { # escape
                if ('' !== $next && strpbrk($next, MD::$MD->esc)) {
                    $this->add("\\", ['c' => 'r']);
                    $this->add($next);
                } else {
                    $this->add("\\");// 2do <br>
                }
            } elseif ('&' == $y && preg_match("/^&[a-z]+;/i", $this->cspn("\n"), $m)) {
                $this->push('-', $m[0], ['bg' => '+']);
                $j += strlen($m[0]);
            } elseif ('<' == $y && preg_match("/^<\/?([a-z_:][\w:\.\-]*)\b[^>]*>/i", $this->cspn("\n"), $m)) {
                $this->push('-', $m[0], ['bg' => '+']);
                $j += strlen($m[0]);
            } elseif ('|' == $y && is_int($x->cvl)) {
                "\n" == ($in[strspn($in, " \t", ++$j) + $j] ?? '') or $x->cvl++;
                $this->push('-t', '|');
            } elseif (strpbrk($y, MD::$MD->esc)) {
                $img = '!' == $y;
                if (!$img && '[' != $y || !$this->square($x, $y, true, $img)) {
                    if (strpbrk($y, "*_`~^=")) { # inlines
                        $b = isset($in[$j - 1]) && !strpbrk($in[$j - 1], " \t\r\n") ? 1 : 0; # end or not
                        $j += strlen($uu = substr($in, $j, strspn($in, $y, $j)));
                        $b += isset($in[$j]) && !strpbrk($in[$j], " \t\r\n") ? 2 : 0; # start or not
                        if (3 != $b || '*' == $y || '`' == $y) {
                            $this->push('-i', $uu, ['b' => $b]);
                            $this->up->attr['in'] = $uu;
                        } else {
                            $this->push('#text', $uu);
                        }
                    } else {
                        $this->add($y);
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
                goto schem_2;
            }
        } elseif (':' == $chr) {
            if ($inline || ':' == trim($tail = $this->cspn("\n", $j)))
                return false; # not reference
            $this->use_close($x);
            $this->ref[$rf] = trim(substr($tail, 1));
            return $this->add($head . $tail, ['c' => 'j']);
        } else {
            schem_1: # scheme: [q]
            if (2 == $len)
                return false;
            schem_2:
            $this->j += strlen($t = ($img ? '!' : '') . $head . $tail);
            return $this->for[$rf][] = $this->push('#a', $t, ['head' => $head]);
        }
        $this->j += strlen($t = ($img ? '!' : '') . $head . $tail);
        if ($img)
            return $this->push('img', 0, ['src' => $this->src($tail), 'alt' => substr($head, 1, -1), 'c' => 'c', 't' => $t]);
        return $this->push('a', substr($head, 1, -1), ['href' => substr($tail, 1, -1), 'c' => 'g', 't' => $t]);
    }

    function src($src) {
        return preg_replace("~^https://github.*?/([^\/\.]+)[^/]+$~", '_png?$1=' . Plan::$pngdir, substr($src, 1, -1));
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
            $depth < 0 or $this->inlines($node);
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
            } elseif ('-' == $node->name[0]) {
                $out .= $node->val;
            } elseif ('#' == $node->name[0]) {
                '' === $type ? ($out .= html($node->val)) : ($code .= $node->val);
            } elseif ('x-code' == $node->name) { // code 
                $type = $node->attr['lang'] ?: true;
                'javascript' !== $type or $type = 'js';
                $code = ''; # fenced code start
            } else {
                if ('li' == $node->name && $node->up->attr['tight'])
                    for ($p = $node->val; $p; $p = $p->right)
                        'p' != $p->name or $this->inlines($p, true);
                $out .= str_pad('', $depth * 2) . $this->tag($node, $close, MD::$MD->attr);
                $out .= null === $node->val || is_string($node->val) ? "$node->val$close\n" : "\n";
            }
        }
        return $out;
    }

    function code(&$n1) {
        for ($n2 = $n1->right, $s = ''; $n2; $n2 = $n2->right) {
            if ('-t' == $n2->name)
                return;
            if ($n1->val == $n2->val) {
                $n1->attr['t'] = $n2->attr['t'] = $n1->val;
                $n2->left = $n1->right = XML::node('#skip', $s, ['bg' => '*'], $n2, $n1->up, $n1);
                if (' ' == $s[0] && ' ' == $s[-1] && '' !== trim($s))
                    $s = substr($s, 1, -1);
                $n1->val = '<code>' . html($s);
                $n2->val = '</code>';
                return $n1 = $n2;
            }
            $s .= $n2->val;
        }
    }

    function inlines($p, $drop = false) {
        if (isset($p->attr['in'])) {
            unset($p->attr['in']);
            $stk = [];
            for ($n1 = $p->val; $n1; $n1 = $n1->right) {
                if ('-t' == $n1->name)
                    $stk = [];
                if ('-i' != $n1->name)
                    continue;
                if ('`' == $n1->val[0]) { # code
                    $this->code($n1);
                } else {
                    $tag = MD::$MD->inline[$n1->val] ?? '';
                    if (($from = $stk[$n1->val] ?? false) && (1 & $n1->attr['b'])) {
                        array_splice($stk, array_search($n1->val, array_keys($stk)));
                        $from->attr['t'] = $n1->attr['t'] = $n1->val;
                        $from->val = "<$tag>";
                        $n1->val = "</$tag>";
                    } elseif ($tag && (2 & $n1->attr['b'])) {
                        $stk[$n1->val] = $n1;
                    }
                }
            }
        }
        $drop && $this->remove($p, false);
    }

    private function use_close($x) {
        $this->close('p');
        $this->close('tbody');
        if ($this->close('table'))
            $x->table = $x->cvl = false;
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

    private function set_x($n, $x, &$y = null, &$last = null) {
        static $pad;

        $ary = [];
        if ($n < 3) {
            if (!preg_match(MD::$MD->blk_re[$n], $x->line, $match))
                return false;
            $pad += $ary['tight'] = 1 + strlen($x->m = $match[1]);
            $this->j += $ary['tight'];
            $n or 1 == $x->m or $ary['start'] = $x->m;
            $x->m .= $ary['d'] = $match[2];
        } elseif (3 == $n) {
            for ($tag = $this->last; '#root' != $tag->name; $tag = $tag->up)
                if (in_array($tag->name, ['li', 'blockquote']))
                    array_unshift($ary, $tag);
            $pad = $x->base = 0;
        } else {
            $pad++;
            $x->base = 0;
            $this->add('>', ['c' => 'r']);
        }
        $fn = fn() => $this->in[$this->j] ?? "\n";
        for ($base = $pad, $y = $fn(); strpbrk($y, " \t"); ) {
            $pad += $sz = strspn($this->in, ' ', $this->j);
            $this->j += $sz;
            for ($y = $fn(); "\t" == $y; $this->j++, $y = $fn())
                $pad += 4 - $pad % 4;
        }
        $last = ($x->empty = "\n" == $y) ? $this->last : false;
        $x->pad = $pad - $base;
        return $ary;
    }

    static function html($str) {
        return str_replace(['<', '>'], ['&lt;', '&gt;'], $str);
    }

    private function spn($set, $j = 0, &$sz = null) { # collect $set
        return substr($this->in, $j ?: $this->j, $sz = strspn($this->in, $set, $j ?: $this->j));
    }

    private function cspn($set, $j = 0, &$sz = null) { # collect other than $set
        return substr($this->in, $j ?: $this->j, $sz = strcspn($this->in, $set, $j ?: $this->j));
    }
}
/*
        sprintf('<img src="%s" alt="%s">', $p4, substr($p3, 1, -1)) # image
trace($x->close, '===');*/