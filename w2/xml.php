<?php

class XML extends CSS
{
    const version = '0.777';

    static $XML;
    static $void_slash = true; # allow slash in voids: <br />

    public $selected = [];
    public $root;

    protected $up;
    protected $left;
    protected $last;
    protected $next;
    protected $j = 0;

    function __construct(string $in = '', $tab = 2) {
        parent::__construct($in, $tab);
        $this->root = XML::node('#root');
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
        echo "depth:ID [left up right] name val\n\n";
        # arrow fn for iterator_apply must return something like true!
        iterator_apply($g = $this->gen($in), fn() => $g->current()->id = ++$this->j);
        foreach ($this->gen($in) as $depth => $node) {
            echo str_pad('', 2 * $depth) . "$depth:$node->id [";
            echo (isset($node->left) ? ($node->left->id ?? '_') : '.') . ' ';
            echo (isset($node->up) ? ($node->up->id ?? '_') : '.') . ' ';
            echo isset($node->right) ? ($node->right->id ?? '_') . "] $node->name " : ".] $node->name ";
            if (is_string($node->val)) {
                echo Plan::str($node->val, 80, true) . "\n";
            } elseif (0 === $node->val) {
                echo ".........VOID\n";
            } else {
                echo 'object' == ($type = gettype($node->val)) ? '[' . ($node->val->id ?? '_') . "]\n" : "$type\n";
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
        $last = false;
        foreach ($ary ?? $this->selected as $node) {
            $last ? $this->last($last) : $this->parent($this->root);
            $this->insert($last = clone $node, true);
            if (is_object($node->val)) {
                foreach ($this->gen($node->val) as $node) {
                    $this->insert(clone $node, is_object($node->val));
                    is_null($node->right) && $this->close();
                }
            }
        }
        $last->right = null;
        return $this->root->val;
    }

    static function tag($node, &$close, $allow = false) {
        $close = '</' . ($open = $node->name) . '>';
        foreach ($node->attr ?? [] as $k => $v)
            if ((!$allow || in_array($k, $allow)) && (XML::$void_slash || '/' !== $k))
                $open .= ' ' . (0 === $v ? $k : "$k=\"$v\"");
        return "<$open>";
    }

    function xml_simple($in, $allow = false) {
        $out = '';
        foreach ($this->gen($in, true) as $depth => $node) {
            if ('#' == $node->name[0]) {
                $out .= sprintf($this->spec[$node->name] ?? '%s', $node->val);
            } elseif ($depth < 0) {
                $out .= "</$node->name>";
            } else {
                $out .= XML::tag($node, $close, $allow);
                if (null === $node->val || is_string($node->val))
                    $out .= $node->val . $close;
            }
        }
        return $out;
    }

    function xml_mini($in, $in_pre = false) { # $in_pre -- save whitespaces in text value
        $out = '';
        foreach ($this->gen($in, true) as $depth => $node) {
            if ('#' == $node->name[0]) {
                $out .= sprintf($this->spec[$node->name] ?? '%s', trim($node->val));
            } elseif ($depth < 0) {
                $out .= "</$node->name>";
            } else {
                $out .= $this->tag($node, $close);
                if (null === $node->val || is_string($node->val))
                    $out .= trim($node->val) . $close;
            }
        }
        return $out;
    }

    function xml_nice($node, $pad = '', $in_pre = false) { // 2do CSS: white-space: pre;
        for ($out = ''; $node; $node = $node->right) {
            $open = $pad . XML::tag($node, $close);
            if ('#' == $node->name[0]) {
                $str = sprintf($this->spec[$node->name] ?? '%s', $node->val);
                $out .= trim($str);
            } elseif (0 === $node->val) { # void element
                $out .= "$open\n";
            } elseif (is_object($node->val)) {
                $inner = $this->xml_nice(
                    $node->val,
                    $pad . $this->pad,
                    $in_pre || 'pre' == $node->name
                );
                if (strlen($trim = trim($inner)) < 100) {
                    $out .= $open . $trim . $close . "\n";
                } else {
                    $out .= "$open\n" . $inner . "$pad$close\n";
                }
            } else {
                $out .= $open . trim($node->val) . $close;
            }
        }
        return $out;
    }

    function byTag(string $tag) {
        $new = new XML;
        iterator_apply($g = $this->gen(), fn() => $tag != $g->current()->name or $new->selected[] = $g->current());
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

    function has_childs($node, &$obj = null) {
        $obj = is_object($node->val);
        return $obj || is_string($node->val) && '' !== $node->val && '#' != $node->name[0];
    }

    function remove($ary = [], $with_childs = true) {
        $ary or $ary = $this->selected;
        is_array($ary) or $ary = [$ary];
        foreach ($ary as $dst) {
            if ($with_childs || !$this->has_childs($dst, $obj)) {
                $this->del_topology($dst, $dst->right, $dst->left);
            } elseif ($obj) {
                $this->ins_topology($dst->val, $dst->left, $dst->up, $dst->right, $last);
                $this->del_topology($dst, $dst->val, $last);
            } else {
                $dst->name = '#text';
            }
        }
    }

    function inner($src) {
        $src instanceof XML or ($src = new XML($src))->parse();
        $src->selected or $src->selected = [$src->root->val];
        foreach ($this->selected as $dst) {
            $this->ins_topology($dst->val = $src->clone(), null, $dst, null);
        }
    }

    function outer($src) {
        $src instanceof XML or ($src = new XML($src))->parse();
        $src->selected or $src->selected = [$src->root->val];
        foreach ($this->selected as $dst) {
            $this->ins_topology($first = $src->clone(), $dst->left, $dst->up, $dst->right, $last);
            $this->del_topology($dst, $first, $last);
        }
    }

    function before($src) {
        $src instanceof XML or ($src = new XML($src))->parse();
        $src->selected or $src->selected = [$src->root->val];
        foreach ($this->selected as $dst) {
            $this->ins_topology($first = $src->clone(), $dst->left, $dst->up, $dst, $last);
            $dst->left ? ($dst->left->right = $first) : ($dst->up->val = $first);
            $dst->left = $last;
        }
    }

    function after($src) {
        $src instanceof XML or ($src = new XML($src))->parse();
        $src->selected or $src->selected = [$src->root->val];
        foreach ($this->selected as $dst) {
            $this->ins_topology($first = $src->clone(), $dst, $dst->up, $dst->right, $last);
            if ($dst->right)
                $dst->right->left = $last;
            $dst->right = $first;
        }
    }

    function prepend($src) {
        $src instanceof XML or ($src = new XML($src))->parse();
        $src->selected or $src->selected = [$src->root->val];
        foreach ($this->selected as $dst) {
            $this->ins_topology($first = $src->clone(), null, $dst, $dst->val, $last);
            [$dst->val->left, $dst->val] = [$last, $first];
        }
    }

    function append($src) {
        $src instanceof XML or ($src = new XML($src))->parse();
        $src->selected or $src->selected = [$src->root->val];
        foreach ($this->selected as $dst) {
            for ($left = $dst->val; $left->right; $left = $left->right);
            $this->ins_topology($left->right = $src->clone(), $left, $dst, null);
        }
    }

    function go(&$ary, $to, $m = 1, $text = false) { # go relative
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
        return $this->go($this->selected, 'left', $m, $text);
    }

    function next($m = 1, $text = false) {
        return $this->go($this->selected, 'right', $m, $text);
    }

    function query($q, $is_all = false) {
    }

    function childs($query) {
    }

    #function parents($m = 1) {
    #    return $this->go($this->selected, 'up', $m);
    #}
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
            } elseif ('<' == $t && preg_match("@^(<!\-\-|<!\[CDATA\[|</?[a-z][a-z\d\-]*)(\s|>)@is", substr($in, $j, 51), $match)) {
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

    function set_attr(&$attr, $t) { # attr-name= /^[a-z_:][a-z_:\d\.\-]*$/
        static $key = '', $val = false;
        if ($quot = in_array($t[0], ["'", '"']))
            $t = substr($t, 1, -1);
        if ('=' == $t && !$quot) {
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

    protected function parse(): ?stdClass { # 2do strict mode
        $name = $str = $attr = null;
        $flush = function () use (&$name, &$str, &$attr) {
            if ('#text' == $name) {
                $this->push($name, $str);
            } elseif (!is_null($name)) {
                $this->push($name, null, $attr);
                is_null($str) or $this->push('#text', $str);
            }
            $name = $str = $attr = null;
        };
        $this->parent($this->root);
        $this->selected = [];
        foreach ($this->tokens() as $t => $y) {
            if ($y->end) { # from <!-- or <![CDATA[
                $flush();
                $y->find = $y->end;
            } elseif (in_array($y->found, ['-->', ']]>'])) {
                $this->push('-->' == $y->found ? '#comment' : '#data', $t);
                $y->len += $y->find ? 0 : 3; # correct $y->len!
            } elseif ('open' == $y->mode) { # sample: <tag
                $flush();
                $name = rtrim(strtolower(substr($t, 1)), '/'); ///
                $y->mode = 'attr';
                continue;
            } elseif ('attr' == $y->mode) {
                if ($y->space || '>' != $t) {
                    $y->space || $this->set_attr($attr, $t);
                    continue;
                }
                if (in_array($name, ['script', 'style'])) {
                    $y->find = "</$name>";
                } elseif (in_array($name, $this->void) || $attr && '/' == array_key_last($attr) && 0 === $attr['/']) {
                    $this->push($name, 0, $attr);
                    $name = $str = $attr = null;
                }
            } elseif ('close' == $y->mode) { # sample: </tag>
                is_null($name) or $this->push($name, $str ?? '', $attr);
                $tag = strtolower(substr($t, 2, -1));
                $tag === $name or $this->close($tag);
                $name = $str = $attr = null;
            } else { # text
                if (is_null($name))
                    $name = '#text';
                $str .= $t;
            }
            $y->mode = 'txt';
        }
        $flush();
        return $this->root->val;
    }

    protected function close($tag = null) {
        return is_null($tag) || $tag === $this->up->name ? $this->last($this->up) : false;
    }

    protected function last($node) { # setup cursor right
        $this->up = $node->up;
        $this->next =& $node->right;
        return $this->left = $this->last = $node;
    }

    protected function parent($node, $name = null) { # setup cursor down
        is_null($name) or $node->name = $name;
        $this->left = null;
        $this->next =& $node->val;
        return $this->up = $this->last = $node;
    }

    protected function insert($node, $parent) {
        $node->left = $this->left;
        $node->up = $this->up;
        return $parent ? $this->parent($this->next = $node) : $this->last($this->next = $node);
    }

    protected function push($name, $val = null, $attr = null) {
        if ('#' != $name[0]) { //2do omission tags & !doctype https://html.spec.whatwg.org/dev/syntax.html#syntax-tag-omission
            $om = $this->omis[$this->up->name] ?? false;
            if ($om && in_array($name, $om))
                $this->close();
            if ($this->up->name == $name && in_array($name, $this->omis[0]))
                $this->close();
        }
        $node = XML::node($name, $val, $attr);
        is_object($val) && $this->ins_topology($val, null, $node, null);
        return $this->insert($node, is_null($val));
    }

    protected function ins_topology($ins, $left, $up, $right, &$last = null) {
        for ($ins->left = $left; $ins; $ins = $ins->right)
            [$ins->up, $last] = [$up, $ins];
        $last->right = $right;
    }

    protected function del_topology($del, $first, $last) {
        $del->left ? ($del->left->right = $first) : ($del->up->val = $first);
        is_null($del->right) or $del->right->left = $last;
    }

    static function node($name, $val = null, $attr = null, $right = null, $up = null, $left = null) {
        return (object)[
            'name' => $name,
            'attr' => $attr,
            'up' => $up,
            'val' => $val, # text | object=first-child | 0=void | null=not-void
            'left' => $left,
            'right' => $right,
        ];
    }
}
