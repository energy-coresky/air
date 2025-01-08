<?php

class XML
{
    const version = '0.777';

    static $html;

    public $pad = '';
    public $draw;
    public $selected = [];

    private $in;
    private $ptr;
    private $root;

    function __construct(string $in = '', $tab = 0) {
        $this->in = unl($in);
        $this->pad = str_pad('', $tab);
        $this->draw = [$this, $tab ? 'nice' : 'simple'];
        $this->root = $this->node('#root');
        self::$html or self::$html = yml('+ @inc(html)');
    }

    function __get($name) {
        return self::$html[$name];
    }

    function __toString() {
        $this->root->val or $this->parse();
        $this->in = '';
        call_user_func($this->draw, $this->root->val, '');
        return $this->in;
    }

    static function file($name, $pad = '  ') {
        $xml = new XML(file_get_contents($name));
        $xml->pad = $pad;
        return $xml;
    }

    function dump($root = false) {
        $root or $root = $this->root->val;
        echo "depth.n.nn [left up right]\n\n";
        $this->walk($root, fn($tag, $walk) => $tag->nn = $walk->nn);
        $this->walk($root, function ($tag, $walk) {
            echo str_pad('', 2 * $walk->depth, ' ') . "$walk->depth.$walk->n.$walk->nn [";
            echo (isset($tag->left) ? ($tag->left->nn ?? '.') : '_') . ' ';
            echo (isset($tag->up) ? ($tag->up->nn ?? '.') : '_') . ' ';
            echo isset($tag->right) ? ($tag->right->nn ?? '.') . "] $tag->name " : "_] $tag->name ";
            if (is_string($tag->val)) {
                echo strlen($tag->val) . var_export(preg_replace("/\n/s", ' ', substr($tag->val, 0, 33)), true) . "\n";
            } elseif (0 === $tag->val) {
                echo ".........VOID\n";
            } else {
                echo 'object' == ($type = gettype($tag->val)) ? '[' . ($tag->val->nn ?? '.') . "]\n" : "$type\n";
            }
        });
    }

    function walk($tag, $fn = false, $depth = 0, $walk = false) {
        $fn or ($fn = $tag) && ($tag = $this->root->val);
        $walk or $walk = (object)['nn' => 0, 'tail' => null];
        $walk->n = $n = 0;
        do {
            $walk->depth = $depth;
            $walk->nn++;
            $fn($tag, $walk);
            $walk->n = ++$n;
            is_object($tag->val) && $this->walk($tag->val, $fn, 1 + $depth, $walk);
        } while ($tag = $tag->right);
        $walk->tail && $fn(false, $walk);
        return $walk;
    }

    function draw($tag, &$close) {
        if (is_string($tag))
            return $tag;
        $close = '</' . ($open = $tag->name) . '>';
        foreach ($tag->attr ?? [] as $k => $v)
            $open .= ' ' . (0 === $v ? $k : "$k=\"$v\"");
        return "<$open>";
    }

    function simple($tag) {
        $this->walk($tag, function ($tag, $walk) {
            if (!$tag)
                return $this->in .= array_pop($walk->tail);
            if ('#' == $tag->name[0])
                return $this->in .= $this->draw(sprintf($this->spec[$tag->name], $tag->val), $tag->name);
            $this->in .= $this->draw($tag, $close);
            if (!is_string($tag->val)) # childs or void
                return $tag->val ? ($walk->tail[] = $close) : false;
            $this->in .= "$tag->val$close";
        });
    }

    function minify($tag, $pad, $in_pre = false) { // 2do
    # $in_pre -- save whitespaces in text value
    }

    function nice($tag, $pad, $in_pre = false) { // 2do CSS: white-space: pre;
        $out = '';
        do {
            if ('#' == $tag->name[0]) {
                $str = $this->draw(sprintf($this->spec[$tag->name], $tag->val), $tag->name);
                $out .= $in_pre ? $str : trim($str);
                continue;
            } else {
                $open = $this->draw($tag, $close);
            }
            if (0 === $tag->val) { # void element
                $out .= "\n$pad$open";
                continue;
            }
            if (is_object($tag->val)) {
                $inner = $this->nice($tag->val, $pad . $this->pad, $in_pre || 'pre' == $tag->name);
                $out .= $in_pre ? $open . $inner . $close : (strlen(trim($inner)) < 100
                    ? "\n$pad$open" . trim($inner) . $close
                    : "\n$pad$open" . $inner . "\n$pad$close");
            } else { # string
                $out .= $in_pre ? $open . $tag->val . $close : "\n$pad$open" . trim($tag->val) . $close;
            }
        } while ($tag = $tag->right);
        $pad or $this->in = $out;
        return $out;
    }

    function clone(?array $ary = null) : XML {
        $ary or $ary = $this->selected;
        $prev = false;
        foreach ($ary as $tag) $this->walk($tag, function ($tag, $walk) use (&$prev) {
            if ($walk->depth) {
                $this->relations(clone $tag, is_object($tag->val));
                return null !== $tag->right or $this->close();
            }
            if ($walk->n)
                return;
            $this->ptr = $prev
                ? [$prev, &$prev->right, $this->root]
                : [null, &$this->root->val, $this->root];
            $this->relations($prev = clone $tag, true);
            $prev->right = null;
        });
        return $this;
    }

    function byTag(string $name) {
        $this->root->val or $this->parse();
        $new = new XML;
        $this->walk(fn($tag) => $name != $tag->name or $new->selected[] = $tag);
        return $new;
    }

    function byClass(string $class) {
        return $this->byAttr($class, 'class', true);
    }

    function byAttr(string $val, $attr = 'id', $single = false) {
        $this->root->val or $this->parse();
        $new = new XML;
        $this->walk(function ($tag) use ($new, $val, $attr, $single) {
            if (!$tag->attr)
                return;
            foreach ($tag->attr as $k => $v) {
                if ($attr == $k && ($single ? in_array($val, preg_split("/\s+/s", $v)) : $val == $v))
                    $new->selected[] = $tag;
            }
        });
        return $new;
    }

    function childs($query) {
    }

    function remove($ary = []) {
        $ary or $ary = $this->selected;
        foreach ($ary as $tag) {
            $tag->right and $tag->right->left = $tag->left;
            $tag->left ? ($tag->left->right = $tag->right) : ($tag->up->val = $tag->right ?? '');
        }
    }

    private function left_up_right($tag, $left, $up, $right = null) {
        $tag->left = $left;
        do {
            $tag->up = $up;
            $last = $tag;
        } while ($tag = $tag->right);
        $last->right = $right;
    }

    function inner($xml) {
        if (!$xml instanceof XML)
            ($xml = new XML($xml))->parse();
        $xml->selected or $xml->selected = [$xml->root->val];
        foreach ($this->selected as $one) {
            $xml->clone();
            $one->val = $xml->root->val;
            $this->left_up_right($xml->root->val, null, $one->val);
        }
    }

    function outer($xml) {
        if (!$xml instanceof XML)
            ($xml = new XML($xml))->parse();
        $xml->selected or $xml->selected = [$xml->root->val];
        foreach ($this->selected as $one) {
            $xml->clone();
            $one->left ? ($one->left->right = $xml->root->val) : ($one->up->val = $xml->root->val);
            $this->left_up_right($xml->root->val, $one->left, $one->up, $one->right);
        }
    }

    function _move(&$ary, $to, $m = 1, $text = false) {
        $new = [];
        foreach ($ary as $tag) {
            for ($n = 0; $tag && $n < $m; !$text && $tag && '#' == $tag->name[0] or $n++)
                $tag = $tag->$to;
            if ($tag && $tag->up)
                $new[] = $tag;
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

    function attr($tag, string $name, $val = null) {
        $old = $tag->attr[$name] ?? null;
        if (false === $val) {
            unset($tag->attr[$name]);
        } elseif (is_string($val)) {
            $tag->attr = [$name => $val] + $tag->attr;
        }
        return $old;
    }

    function query($q, $is_all = false) {
    }

    function each($fn) {
        foreach ($this->selected as $n => $one)
            $fn($one, $n);
    }

    function tokens($y = false) {
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

    function parse() {
        $tag = $this->node($str = '');
        $push = function () use (&$str, &$tag) {
            if ('' === $tag->name)
                return;
            if ('#text' == $tag->name)
                [$tag->val, $str] = [$str, ''];
            $tag = $this->push($tag);
            '' === $str or $tag = $this->push($tag, $str);
        };
        $this->ptr = [null, &$this->root->val, $this->root]; # &prev, &place, &parent
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
                $tag->name = rtrim(strtolower(substr($t, 1)), '/');
                $y->mode = 'attr';
                continue;
            } elseif ('attr' == $y->mode) {
                if ($y->space)
                    continue;
                if ('>' != $t) {
                    $this->_attr($tag->attr, $t);
                    continue;
                }
                $bs = is_array($tag->attr) && '/' == array_key_last($tag->attr) && 0 === $tag->attr['/'];
                if ($bs || in_array($tag->name, $this->void)) {
                    $tag = $this->push([$tag->name, 0, $tag->attr]);
                    $str = '';
                } elseif (in_array($tag->name, ['script', 'style'])) {
                    $y->find = "</$tag->name>";
                }
            } elseif ('close' == $y->mode) { # sample: </tag>
                $new = strtolower(substr($t, 2, -1));
                '' === $tag->name or $this->push([$tag->name, $str, $tag->attr]);
                $new == $tag->name or $this->push([$new, 1]);
                $tag = $this->node($str = '');
            } else { # text
                '' !== $tag->name or $tag->name = '#text';
                $str .= $t;
            }
            $y->mode = 'txt';
        }
        $push();
        return $this->root->val;
    }

    private function relations($tag, $is_parent = false) {
        $tag->left = $this->ptr[0];
        $tag->up = $this->ptr[2];
        $this->ptr[1] = $tag; # add node
        $this->ptr = $is_parent
            ? [null, &$tag->val, $tag] # childs: &prev, &place, &parent
            : [$tag, &$tag->right, $tag->up];
    }

    private function close() {
        $parent = $this->ptr[2];
        $this->ptr = [$parent, &$parent->right, $parent->up];
    }

    function push($tag, &$str = null) {
        if (is_array($tag))
            $tag = $this->node($tag[0], $tag[1], $tag[2] ?? null);
        if (null !== $str) {
            $tag->name = '#text';
            $tag->val = $str;
            $str = '';
        }
        //$prev = $this->ptr[0] ?? $this->ptr[2];
        $parent = $this->ptr[2];
        if (1 === $tag->val) { # close-tag
            if ($tag->name == $parent->name)
                $this->close();
        } else {
            if ('#' != $tag->name[0]) {
                $om = $this->omis[$parent->name] ?? false;
                if ($om && in_array($tag->name, $om))
                    $this->close();
                if ($parent->name == $tag->name && in_array($tag->name, $this->omis[0]))
                    $this->close();
            }
            $this->relations($tag, null === $tag->val);
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
