<?php

class XML
{
    const version = '0.777';

    static $XML;

    public $pad;
    public $render;
    public $selected = [];

    protected $in;
    protected $ptr;
    protected $i = 0;
    protected $root;

    static function file($in, $tab = 0) {
        return new XML(file_get_contents($in), $tab);
    }

    function __construct(string $in = '', $tab = 0) {
        $this->in = unl($in);
        $this->pad = str_pad('', $tab);
        $this->render = [$this, $tab ? 'draw_nice' : 'draw_simple'];
        $this->root = $this->node('#root');
        $this->ptr = [null, &$this->root->val, $this->root]; # setup the cursor: &prev, &place, &parent
        XML::$XML or XML::$XML = yml('+ @inc(html)');
    }

    function __get($name) {
        //2do XML::{get_class()}[$name];
        return XML::$XML[$name];
    }

    function __toString() {
        $this->root->val or $this->parse();
        return $this->in = call_user_func($this->render, $this->root->val);
    }

    function dump($in = false) {
        echo "depth.nn [left up right]\n\n";
        # arrow fn for iterator_apply must return something like true!
        iterator_apply($g = $this->gen($in), fn() => $g->current()->nn = ++$this->i);
        foreach ($this->gen($in) as $depth => $node) {
            echo str_pad('', 2 * $depth) . "$depth.$node->nn [";
            echo (isset($node->left) ? ($node->left->nn ?? '.') : '_') . ' ';
            echo (isset($node->up) ? ($node->up->nn ?? '.') : '_') . ' ';
            echo isset($node->right) ? ($node->right->nn ?? '.') . "] $node->name " : "_] $node->name ";
            if (is_string($node->val)) {
                echo strlen($node->val) . var_export(preg_replace("/\n/s", ' ', substr($node->val, 0, 33)), true) . "\n";
            } elseif (0 === $node->val) {
                echo ".........VOID\n";
            } else {
                echo 'object' == ($type = gettype($node->val)) ? '[' . ($node->val->nn ?? '.') . "]\n" : "$type\n";
            }
        }
    }

    function gen($node = false, $run_close = false, $depth = 0) {
        $node or $node = $this->root->val or $node = $this->parse();
        for (; $node; $node = $node->right) {
            yield $depth => $node;
            if ($obj = is_object($node->val))
                yield from $this->gen($node->val, $run_close, 1 + $depth);
            if ($run_close && $obj)
                yield -$depth - 1 => $node;
        }
    }

    function clone(?array $ary = null): ?stdClass {
        $ary or $ary = $this->selected;
        $new = false;
        foreach ($ary as $node) {
            $this->ptr = $new ? [$new, &$new->right, $this->root] : [null, &$this->root->val, $this->root];
            $this->relations($new = clone $node, true);
            if (is_object($node->val)) {
                foreach ($this->gen($node->val) as $node) {
                    $this->relations(clone $node, is_object($node->val));
                    if (null === $node->right)
                        $this->close();
                }
            }
        }
        $new->right = null;
        return $this->root->val;
    }

    function tag($node, &$close, $allow = false) {
        $close = '</' . ($open = $node->name) . '>';
        foreach ($node->attr ?? [] as $k => $v)
            if (!$allow || in_array($k, $allow))
                $open .= ' ' . (0 === $v ? $k : "$k=\"$v\"");
        return "<$open>";
    }

    function draw_simple($in, $allow = false) {
        $out = '';
        foreach ($this->gen($in, true) as $depth => $node) {
            if ('#' == $node->name[0]) {
                $out .= sprintf($this->spec[$node->name], $node->val);
            } elseif ($depth < 0) {
                $out .= "</$node->name>";
            } else {
                $out .= $this->tag($node, $close, $allow);
                if (null === $node->val || is_string($node->val))
                    $out .= $node->val . $close;
            }
        }
        return $out;
    }

    function draw_mini($node, $pad = '', $in_pre = false) { // 2do
    # $in_pre -- save whitespaces in text value
    }

    function draw_nice($node, $pad = '', $in_pre = false) { // 2do CSS: white-space: pre;
        $out = '';
        do {
            if ('#' == $node->name[0]) {
                $str = sprintf($this->spec[$node->name], $node->val);
                $out .= $in_pre ? $str : trim($str);
                continue;
            } else {
                $open = $this->tag($node, $close);
            }
            if (0 === $node->val) { # void element
                $out .= "\n$pad$open";
                continue;
            }
            if (is_object($node->val)) {
                $inner = $this->draw_nice($node->val, $pad . $this->pad, $in_pre || 'pre' == $node->name);
                $out .= $in_pre ? $open . $inner . $close : (strlen(trim($inner)) < 100
                    ? "\n$pad$open" . trim($inner) . $close
                    : "\n$pad$open" . $inner . "\n$pad$close");
            } else { # string
                $out .= $in_pre ? $open . $node->val . $close : "\n$pad$open" . trim($node->val) . $close;
            }
        } while ($node = $node->right);
        return $out;
    }

    function byTag(string $name) {
        $new = new XML;
        iterator_apply($g = $this->gen(), fn() => $name != $g->current()->name or $new->selected[] = $g->current());
        return $new;
    }

    function byClass(string $class) {
        return $this->byAttr($class, 'class', true);
    }

    function byAttr(string $val, $attr = 'id', $single = false) {
        $new = new XML;
        foreach ($this->gen() as $node) {
            if ($node->attr) {
                foreach ($node->attr as $k => $v) {
                    if ($attr == $k && ($single ? in_array($val, preg_split("/\s+/s", $v)) : $val == $v))
                        $new->selected[] = $node;
                }
            }
        }
        return $new;
    }

    function childs($query) {
    }

    function remove($ary = []) {
        $ary or $ary = $this->selected;
        foreach ($ary as $node) {
            $node->right and $node->right->left = $node->left;
            $node->left ? ($node->left->right = $node->right) : ($node->up->val = $node->right ?? '');
        }
    }

    private function left_up_right($src, $left, $up, $right, &$last = null) {
        for ($src->left = $left; $src; $src = $src->right) {
            $src->up = $up;
            $last = $src;
        }
        $last->right = $right;
    }

    function inner($src) {
        if (!$src instanceof XML)
            ($src = new XML($src))->parse();
        $src->selected or $src->selected = [$src->root->val];
        foreach ($this->selected as $dst) {
            $dst->val = $src->clone();
            $this->left_up_right($dst->val, null, $dst, null);
        }
    }

    function outer($src) {
        if (!$src instanceof XML)
            ($src = new XML($src))->parse();
        $src->selected or $src->selected = [$src->root->val];
        foreach ($this->selected as $dst) {
            $this->left_up_right($copy = $src->clone(), $dst->left, $dst->up, $dst->right, $last);
            $dst->left ? ($dst->left->right = $copy) : ($dst->up->val = $copy);
            if ($dst->right)
                $dst->right->left = $last;
        }
    }

    function _move(&$ary, $to, $m = 1, $text = false) {
        $new = [];
        foreach ($ary as $node) {
            for ($n = 0; $node && $n < $m; !$text && $node && '#' == $node->name[0] or $n++)
                $node = $node->$to;
            if ($node && $node->up)
                $new[] = $node;
        }
        $ary = $new;
        return $this;
    }

    function prev($m = 1, $text = false) {
        return $this->_move($this->selected, 'left', $m, $text);
    }

    function next($m = 1, $text = false) {
        return $this->_move($this->selected, 'right', $m, $text);
    }

    function parent($m = 1) {
        return $this->_move($this->selected, 'up', $m);
    }

    function parents($query) {
    }

    function attr($node, string $name, $val = null) {
        $old = $node->attr[$name] ?? null;
        if (false === $val) {
            unset($node->attr[$name]);
        } elseif (is_string($val)) {
            $node->attr = [$name => $val] + $node->attr;
        }
        return $old;
    }

    function query($q, $is_all = false) {
    }

    function each($fn) {
        foreach ($this->selected as $n => $to)
            $fn($to, $n);
    }

    function tokens(?stdClass $y = null) {
        $y or $y = (object)['mode' => 'txt', 'find' => false];
        $len = strlen($in =& $this->in);
        for ($j = 0; $j < $len; $j += $y->len) {
            $y->end = false;
            if ($y->found = $y->find) {
                if (false === ($sz = strpos($in, $y->find, $j))) {
                    $t = substr($in, $j); # /* </style> */ is NOT comment inside <style>!
                } else {
                    $t = substr($in, $j, $sz - $j);
                    $y->find = false;
                }
            } elseif ($y->space = 'attr' == $y->mode && ($sz = strspn($in, "\t \n", $j))) {
                $t = substr($in, $j, $sz);
            } elseif ('>' != ($t = $in[$j]) && 'attr' == $y->mode) {
                if ('"' == $t || "'" == $t) {
                    $sz = Rare::str($in, $j, $len);
                    $t = !$sz ? substr($in, $j) : substr($in, $j, $sz - $j);
                } elseif ('=' != $t) {
                    $t = substr($in, $j, strcspn($in, "=>\t \n", $j));
                }
            } elseif ('<' == $t && preg_match("@^(<!\-\-|<!\[CDATA\[|</?[a-z\d\-]+)(\s|>)@is", substr($in, $j, 51), $match)) {
                [$m0, $t] = $match;
                $y->mode = '/' == $t[1] ? 'close' : 'open';
                if ('<!--' == $t) { # comment
                    $y->end = '-->';
                } elseif ('<![CDATA[' == $t) {
                    $y->end = ']]>';
                } elseif ('/' == $t[1] && '>' == $m0[-1]) {
                    $t = $m0;
                }
            } elseif ('>' != $t && '<' != $t) {
                $t = substr($in, $j, strcspn($in, '<', $j));
            }
            $y->len = strlen($t);
            yield $t => $y;
        }
    }

    function _attr(&$attr, $t) {
        static $key = '', $val = false;
        if (in_array($t[0], ["'", '"']))
            $t = substr($t, 1, -1);
        if ('=' == $t) {
            $_ = $val = true;
            $attr[$key] = '';
        } elseif ($_ = $val) {
            $val = false;
            $attr[$key] = $t;
        } else {
            $attr[$key = $t] = 0;
        }
        return $_;
    }

    protected function parse(): ?stdClass {
        $node = $this->node($str = '');
        $push = function () use (&$str, &$node) {
            if ('' === $node->name)
                return;
            if ('#text' == $node->name)
                [$node->val, $str] = [$str, ''];
            $node = $this->push($node);
            '' === $str or [$node, $str] = [$this->push($node, $str), ''];
        };
        $this->ptr = [null, &$this->root->val, $this->root];
        $this->selected = [];
        foreach ($this->tokens() as $t => $y) {
            if ($y->end) { # from <!-- or <![CDATA[
                $push();
                $y->find = $y->end;
            } elseif (in_array($y->found, ['-->', ']]>'])) {
                $this->push(['-->' == $y->found ? '#comment' : '#data', $t]);
                $y->len += $y->find ? 0 : 3; # chars move
            } elseif ('open' == $y->mode) { # sample: <tag
                $push();
                $node->name = rtrim(strtolower(substr($t, 1)), '/');
                $y->mode = 'attr';
                continue;
            } elseif ('attr' == $y->mode) {
                if ($y->space)
                    continue;
                if ('>' != $t) {
                    $this->_attr($node->attr, $t);
                    continue;
                }
                $bs = is_array($node->attr) && '/' == array_key_last($node->attr) && 0 === $node->attr['/'];
                if ($bs || in_array($node->name, $this->void)) {
                    $node = $this->push([$node->name, 0, $node->attr]);
                    $str = '';
                } elseif (in_array($node->name, ['script', 'style'])) {
                    $y->find = "</$node->name>";
                }
            } elseif ('close' == $y->mode) { # sample: </tag>
                $new = strtolower(substr($t, 2, -1));
                '' === $node->name or $this->push([$node->name, $str, $node->attr]);
                $new == $node->name or $this->push([$new, 1]);
                $node = $this->node($str = '');
            } else { # text
                '' !== $node->name or $node->name = '#text';
                $str .= $t;
            }
            $y->mode = 'txt';
        }
        $push();
        return $this->root->val;
    }

    protected function relations($node, $is_parent) {
        $node->left = $this->ptr[0];
        $node->up = $this->ptr[2];
        $this->ptr[1] = $node; # add the node
        $this->ptr = $is_parent ? [null, &$node->val, $node] : [$node, &$node->right, $node->up];
    }

    protected function close($cnt = 1) {
        $parent = $this->ptr[2];
        if ($cnt--) {
            for (; $cnt--; $parent = $parent->up);
            $this->ptr = [$parent, &$parent->right, $parent->up];
        }
        return $parent->name;
    }

    protected function push($node, $str = 0) {
        if (is_array($node))
            $node = $this->node(...$node);
        if (is_string($str)) {
            $node->name = '#text';
            $node->val = $str;
        }
        $parent = $this->ptr[2];
        if (1 === $node->val) { # close-tag
            if ($node->name == $parent->name)
                $this->close();
        } else {
            if ('#' != $node->name[0]) {
                $om = $this->omis[$parent->name] ?? false;
                if ($om && in_array($node->name, $om))
                    $this->close();
                if ($parent->name == $node->name && in_array($node->name, $this->omis[0]))
                    $this->close();
            }
            $this->relations($node, null === $node->val);
        }
        return $this->node();
    }

    function node($name = '', $val = null, $attr = null) {
        return (object)[
            'name' => $name,
            'attr' => $attr,
            'up' => null,
            'val' => $val, # text | []first-child | 0=void
            'left' => null,
            'right' => null,
        ];
    }
}
