<?php

class XML
{
    const version = 0.339;

//    public $array;
    public $in;

    static $void;
    static $spec = [
        '#text' => '%s',
        '#comment' => '<!--%s-->',
        '#data' => '<![CDATA[%s]]>',
    ];

    private $pad;
    private $ptr;
    private $root;
    private $selected = [];

    static function file($name) {
        $xml = new XML(file_get_contents($name));
        echo $xml;
    }

    function __construct(string $in = '', $pad = '') {
        self::$void or self::$void = yml('+ @inc(html_void)');
        $this->pad = $pad;
        $this->root = $this->node('#root');
        $this->ptr = [null, &$this->root->val, $this->root]; # &prev, &place, &parent
        $this->in = unl($in);
    }

    function attr($name, $val = null) {
    }

    function remove() {
        foreach ($this->selected as &$tag) {
            $tag;
        }
    }

    function prev($text = false) {
        foreach ($this->selected as &$tag) {
            do {
                $tag = $tag->left;
            } while ($tag && '#' == $tag->name[0]);
        }
        return $this;
    }

    function next($text = false) {
        foreach ($this->selected as &$tag) {
            do {
                $tag = $tag->right;
            } while ($tag && '#' == $tag->name[0]);
        }
        return $this;
    }

    function outer($xml) {
        if (!$xml instanceof XML)
            ($xml = new XML($xml))->parse();
        foreach ($this->selected as $one)
            ;
    }

    function inner($xml) {
        if (!$xml instanceof XML)
            ($xml = new XML($xml))->parse();
        foreach ($this->selected as $one)
            $one->val = $xml->root->val;
    }

    function query($q, $is_all = false) {
    }

    function byTag(string $name) {
        $this->root->val or $this->parse();
        $new = new XML;
        $new->selected = $this->walk($this->root->val, function ($opt, &$m, $pad) use ($name) {
            if (!$opt && $name == $m->name)
                return true;
        });
        return $new;
    }

    function byClass(string $class) {
        return $this->byAttr($class, 'class', true);
    }

    function byAttr(string $val, $attr = 'id', $single = false) {
        $this->root->val or $this->parse();
        $new = new XML;
        $new->selected = $this->walk($this->root->val, function ($opt, &$m) use ($val, $attr, $single) {
            if (!$opt && $m->attr) {
                foreach ($m->attr as $k => $v) {
                    if ($attr == $k && ($single ? in_array($val, preg_split("/\s+/s", $v)) : $val == $v))
                        return true;
                }
            }
        });
        return $new;
    }

    function __toString() {
        $this->in = '';
        $beauty = '' !== $this->pad;
        if ($this->selected) {
            $prev = null;
            foreach ($this->selected as $i => $tag) {
                $tag->up = $this->root;
                $i or $this->root->val = $tag;
                $tag->left = $prev;
                if ($prev)
                    $prev->right = $tag;
                $prev = $tag;
            }
        } else {
            $this->root->val or $this->parse();
        }
        $this->walk($this->root->val, function ($opt, $m, $pad) {
            if (!$opt) {
                $this->in .= "<$m->name";
                if ($m->attr)
                    foreach ($m->attr as $k => $v)
                        $this->in .= ' ' . (0 === $v ? $k : "$k=\"$v\"");
                $this->in .= '>';
            } elseif ('/' == $opt) { # close tag
                $this->in .= "</$m>";
            } else { // '#' === $opt[0]
                $this->in .= sprintf(self::$spec[$opt], $m);
            }
        });
        return $this->in;
    }

    function go($tag, $to = 'right') { # right(next) left(prev) up(parent)
        do {
            yield $tag;
        } while (null !== ($tag = $tag->$to));
    }

    function walk($first_child, $fn, $pad = '') {
        $selected = [];
        foreach ($this->go($first_child) as $tag) {
            if ('#' == $tag->name[0]) {
                $fn($tag->name, $tag->val, $pad);
            } else {
                if ($fn(false, $tag, $pad))
                    $selected[] = $tag;
                if (0 === $tag->val) # void element
                    continue;
                if (is_object($tag->val)) {
                    $selected = array_merge($selected, $this->walk($tag->val, $fn, $pad . $this->pad));
                } else {
                    $fn('#text', $tag->val, $pad);
                }
                $fn('/', $tag->name, $pad);
            }
        }
        return $selected;
    }

    function tokens($y = false) {
        $y or $y = (object)['mode' => 'txt', 'find' => false];
        $len = strlen($in =& $this->in);
        for ($j = 0; $j < $len; $j += $y->len) {
            $y->end = false;
            if ($y->found = $y->find) {
                if (false === ($sz = strpos($in, $y->find, $j))) {
                    $t = substr($in, $j); //2do $y->find MUST NOT inside strings or parse JS!
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

    private function parse() {
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
                if (in_array($tag->name, self::$void)) {
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

    # $val: 0-void, 1=</close-tag> [..]array OR "if string -immeditly close"
    #
    private function push($tag, &$str = null) {
        if (is_array($tag))
            $tag = $this->node($tag[0], $tag[1], $tag[2] ?? null);
        if (null !== $str) {
            $tag->name = '#text';
            $tag->val = $str;
            $str = '';
        }
        if (1 === $tag->val) { # close-tag 2do: chk consistency
            $parent = $this->ptr[2];
            $this->ptr = [$parent, &$parent->right, $parent->up];
            return $this->node();
        }
        $tag->left = $this->ptr[0];
        $tag->up = $this->ptr[2];
        $this->ptr[1] = $tag; # add node
        $this->ptr = null === $tag->val # is parent node?
            ? [null, &$tag->val, $tag] # can has childs: &prev, &place, &parent
            : [$tag, &$tag->right, $tag->up];
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
