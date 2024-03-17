<?php

class XML
{
    const version = 0.339;

    public $array;
    public $in;

    static $void;
    static $spec = [
        '#text' => '%s',
        '#comment' => '<!--%s-->',
        '#data' => '<![CDATA[%s]]>',
    ];

    private $pad;
    private $_p;

    static function text($in) {
        $xml = new XML($in);
        $xml->parse();
        return $xml->array;
    }

    static function file($name) {
        $xml = new XML(file_get_contents($name));
        $xml->parse();
//var_export($xml->array);
        echo $xml->dump($xml->array);
    }

    function __construct(string $in, $pad = '  ') {
        self::$void = yml('+ @inc(html_void)');
        $this->pad = $pad;
        $this->array = [];
        $this->_p = [&$this->array];
        $this->in = unl($in);
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

    function attr(&$attr, $t) {
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
        $tag = [$str = '', [], []]; # tag, value, attr
        $push = function () use (&$str, &$tag) {
            if ('' === $tag[0])
                return;
            if ('#text' == $tag[0]) {
                $tag[1] = $str;
                $str = '';
            }
            $this->push($tag);
            if ('' !== $str)
                $this->push(['#text', $str, false]);
            $tag = [$str = '', [], []];
        };
        foreach ($this->tokens() as $t => $y) {
            if ($y->end) { # from <!-- or <![CDATA[
                $push();
                $y->find = $y->end;
            } elseif (in_array($y->found, ['-->', ']]>'])) {
                $this->push(['-->' == $y->found ? '#comment' : '#data', $t, false]);
                $y->len += $y->find ? 0 : 3; # chars move
            } elseif ('open' == $y->mode) { # sample: <tag
                $push();
                $tag[0] = rtrim(strtolower(substr($t, 1)), '/');
                $y->mode = 'attr';
                $attr =& $tag[2];
                continue;
            } elseif ('attr' == $y->mode) {
                if ($y->space)
                    continue;
                if ('>' != $t) {
                    $this->attr($attr, $t);
                    continue;
                }
                if (in_array($tag[0], self::$void)) {
                    $this->push([$tag[0], 0, $tag[2]]);
                    $tag = [$str = '', [], []];
                } elseif (in_array($tag[0], ['script', 'style'])) {
                    $y->find = "</$tag[0]>";
                }
            } elseif ('close' == $y->mode) { # sample: </tag>
                $new = strtolower(substr($t, 2, -1));
                '' === $tag[0] or $this->push([$tag[0], $str, $tag[2]]);
                $new == $tag[0] or $this->push([$new, 1, 1]);
                $tag = [$str = '', [], []];
            } else { # text
                '' !== $tag[0] or $tag[0] = '#text';
                $str .= $t;
            }
            $y->mode = 'txt';
        }
        $push();
    }

    function walk($in, $fn, $pad = '') {
        foreach ($in as $one) {
            $node = key($one);
            $data = pos($one);
            if ('#' == $node[0]) {
                if ($fn($node, $data, $pad))
                    return true;
            } else {
                if ($fn($one[0] ?? [], $node, $pad))
                    return true;
                if (0 !== $data) { # NOT void element
                    if (is_array($data) ? $this->walk($data, $fn, $pad . $this->pad) : $fn('#text', $data, $pad))
                        return true;
                    $fn('/', $node, $pad);
                }
            }
        }
    }

    function dump($in, $beauty = false) {
        $out = '';
        $this->walk($in, function ($n, $m, $pad) use (&$out) {
            if (is_array($n)) {
                $out .= "<$m";
                foreach ($n as $k => $v)
                    $out .= ' ' . (0 === $v ? $k : "$k=\"$v\"");
                $out .= '>';
            } elseif ('/' == $n) { # close tag
                $out .= "</$m>";
            } else { // '#' === $n[0]
                $out .= sprintf(self::$spec[$n], $m);
            }
        });
        return $out;
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
    private function push($tag) {
        [$tag, $val, $attr] = $tag;
        $p =& $this->_p;
        $kl = array_key_last($p);
        if (1 === $val) { # close-tag 2do: chk consistency
            unset($p[$kl]); # move left in hierarchy
            return;
        }
        # add data
        $ary = [$tag => $val];
        if ($attr)
            $ary[] = $attr;
        $p[$kl][] = $ary; # add el
        if (is_array($val)) # store new pointer
            $p[] =& $p[$kl][array_key_last($p[$kl])][$tag];
    }
}
/* 
<recursiv-ary> [
  ['#text' => '.............'
  ],
  ['br' => 0 # void
  ],
  ['div' => [<recursiv-ary>]
    [attr],
  ],
  ...
]
*/
