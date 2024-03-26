<?php

class XML
{
    const version = 0.777;

    public $pad = '';
    public $selected = [];

    static $html;
    static $spec = [
        '#text' => '%s',
        '#comment' => '<!--%s-->',
        '#data' => '<![CDATA[%s]]>',
    ];

    private $in;
    private $ptr;
    private $root;

    function __construct(string $in = '') {
        self::$html or self::$html = yml('+ @object @inc(html)');
        $this->root = $this->node('#root');
        $this->in = unl($in);
    }

    static function file($name) {
        return new XML(file_get_contents($name));
    }

    function dump($root = false) {
        $root or $root = $this->root->val;
        $this->walk($root, fn($tag, $walk) => $tag->nn = $walk->nn);
        $this->walk($root, function ($tag, $walk) {
            echo str_pad('', 2 * $walk->depth, ' ') . "$walk->depth.$walk->n.$walk->nn [";
            echo (isset($tag->left) ? ($tag->left->nn ?? 'X') : '?') . ' ';
            echo (isset($tag->up) ? ($tag->up->nn ?? 'X') : '?') . ' ';
            echo isset($tag->right) ? ($tag->right->nn ?? 'X') . "] $tag->name " : "?] $tag->name ";
            if (is_string($tag->val)) {
                echo strlen($tag->val) . var_export(preg_replace("/\n/s", ' ', substr($tag->val, 0, 33)), true) . "\n";
            } elseif (0 === $tag->val) {
                echo ".........VOID\n";
            } else {
                echo 'object' == ($type = gettype($tag->val)) ? '[' . ($tag->val->nn ?? 'X') . "]\n" : "$type\n";
            }
        });
    }

    function walk($tag, $fn = false, $draw = false, $depth = 0, $walk = false) {
        $fn or ($fn = $tag) && ($tag = $this->root->val);
        $walk or $walk = (object)['nn' => 0]; // , 'dir' => 'right'
        $walk->n = 0;
        do {
            $walk->depth = $depth;
            $walk->nn++;
            if ('#' == $tag->name[0]) {
                $fn($tag, $walk, $tag->name);
                ++$walk->n;
            } else {
                $fn($tag, $walk, false);
                $n = ++$walk->n;
                if (0 === $tag->val) # void element
                    continue;
                is_object($tag->val)
                    ? $this->walk($tag->val, $fn, $draw, 1 + $depth, $walk)
                    : $draw && $fn($tag, $walk, '#text');
                $walk->n = $n;
                $draw && $fn($tag, $walk, '/');
            }
        } while ($tag = $tag->right);
        return $walk;
    }

    function beautifier() {
        return function ($tag, $walk, $x) {
            
        };
    }

    function __toString() {
        $this->root->val or $this->parse();
        $this->in = '';
        $draw = '' !== $this->pad ? $this->beautifier() : function ($tag, $_, $x) {
            if (!$x) {
                $this->in .= "<$tag->name";
                if ($tag->attr)
                    foreach ($tag->attr as $k => $v)
                        $this->in .= ' ' . (0 === $v ? $k : "$k=\"$v\"");
                $this->in .= '>';
            } elseif ('/' == $x) { # close tag
                $this->in .= "</$tag->name>";
            } else { // '#' === $x[0]
                $this->in .= sprintf(self::$spec[$x], $tag->val);
            }
        };
        $this->walk($this->root->val, $draw, true);
        return $this->in;
    }

    private function __dump($ary, $indent = '') {
        $out = '';
        foreach ($ary as $one) {
            $len = strlen($out);
            $node = key($one);
            $data = pos($one);
            if ('#text' == $node && '' === trim($data))
                continue;
            $attr = $one[0] ?? false;
            $out .= $indent;
            switch ($node) {
                case '#text': # #cdata-section #document #document-fragment
                    $out .= trim($data);
                    continue 2;
                case '#comment':
                case '#data':
                    $out .= '#data' == $node ? "<![CDATA[$data]]>\n" : "<!--$data-->\n";
                    continue 2;
                    $out .= "<span style=\"color:#885\"><!-- $data --></span>\n";
                    continue 2;
                default:
                    $tag = $node;//"<span class=\"vs-tag\">$node</span>";
                    if ($attr) {
                        $join = [];
                        foreach ($attr as $k => $v) {
                            $join[] = 0 === $v ? $k : $k . '="' . $v . '"';
                        }
                        $out .= "<$tag " . implode(' ', $join) . '>';
                    } else {
                        $out .= "<$tag>";
                    }
                    if (0 === $data) {
                        $out .= "\n"; # Void element
                        continue 2;
                    } elseif (is_array($data)) {
                        $out .= "\n" . $this->dump($data, $indent . $this->pad);// . $indent;
                        $out .= "$indent</$tag>\n";
                  #  } elseif ('' !== $data && strlen($data . $out) > $len + 280) {
                   #     $out .= "\n$indent$this->pad$data\n$indent";
                    } else {
                        $out .= trim($data) . "</$tag>\n";
                    }
            }
        }
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
        $this->walk(fn($tag, $_, $x) => $x or $name != $tag->name or $new->selected[] = $tag);
        return $new;
    }

    function byClass(string $class) {
        return $this->byAttr($class, 'class', true);
    }

    function byAttr(string $val, $attr = 'id', $single = false) {
        $this->root->val or $this->parse();
        $new = new XML;
        $this->walk(function ($tag, $_, $x) use ($new, $val, $attr, $single) {
            if ($x || !$tag->attr)
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
            for ($n = 0; $tag && $n < $m; !$text && '#' == $tag->name[0] or $n++)
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

    function attr($name, $val = null) {
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
            if ('#text' == $tag->name) {
                $tag->val = $str;
                $str = '';
            }
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
                if (in_array($tag->name, self::$html->void)) {
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

    private function push($tag, &$str = null) {
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
                $om = self::$html->omission[$parent->name] ?? false;
                if ($om && in_array($tag->name, $om))
                    $this->close();
                if ($parent->name == $tag->name && in_array($tag->name, self::$html->omission[0]))
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
