<?php

class XML
{
    const version = 0.1;

    static $void;

    public $array;
    public $tail;

    private $at;
    private $pad = '  ';
    private $stack = [];
    private $_p;

    static function file($name) {
        $xml = new XML(file_get_contents($name), $name);
//var_export($xml->array);
        return $xml->dump($xml->array);
    }

    function __construct(string $in, $fn) {
        self::$void = yml('+ @inc(html_void)');
        $this->at = [$fn, 1];
        $this->array = [];
        $this->_p = [&$this->array];
        $in = unl($in);
        $this->parse($in);
    }

    private function tokens(&$in) {
    //$lr = fn($_) => $_ > 0x60 && $_ < 0x7B || $_ > 0x40 && $_ < 0x5B;
        $el = (object)[
            'mode' => 'txt',
            'j' => false,
            'ss' => false,
            'find' => false,
        ];
        for ($j = 0, $len = strlen($in); $j < $len; $j += $sz ?: strlen($t)) {
            $sz = $el->end = false;
            $el->j && ($j += $el->j);
            $t = $in[$j];
            if ($el->find) {
                $sx = strpos($in, $el->find, $j);
                $t = substr($in, $j, $sx - $j);
            } elseif (in_array($t, [' ', "\t", "\n"])) {
                $t = substr($in, $j, strspn($in, "\t \n", $j));
                if ($el->ss)
                    continue; # SkipSpace
            } elseif ('attr' == $el->mode && '>' != $t) {
                $el->attr = [substr($in, $j, $sz = strcspn($in, '> =', $j)), 0];
                $sz += strspn($in, "\t \n", $j + $sz); # skip space
                if ('=' == $in[$j + $sz]) {
                    $sz += 1 + strspn($in, "\t \n", 1 + $j + $sz); # skip space
                    $x = $in[$k = $j + $sz];
                    if ('"' == $x || "'" == $x) {
                        $sx = Rare::str($in, $k, $len) or $this->halt('Incorrect string');
                        $el->attr[1] = substr($in, $k + 1, ($sx -= $k) - 2);
                    } else {
                        $el->attr[1] = substr($in, $k, $sx = strcspn($in, ">\t \n", $k));
                    }
                    $sz += $sx;
                }
            } elseif ('<' == $t) {
                $el->mode = 'open';
                if ('<!--' == ($t = substr($in, $j, 4))) { # comment
                    $el->end = '-->';
                } elseif ('<![CDATA[' == ($t = substr($in, $j, 9))) {
                    $el->end = ']]>';
                } elseif ('<?' == ($t = substr($in, $j, 2))) {
                    '<?php' !== substr($in, $j, 5) or $t .= 'php';
                    $el->end = '?>';
                } else {
                    if ($close = '/' == $in[$j + 1])
                        $el->mode = 'close';
                    $sx = $close ? 2 : 1;
                    $t = substr($in, $j, $sx += strcspn($in, "\t \n>", $j + $sx));
                    if ($close && '>' == $in[$j + $sx])
                        $t .= '>';
                }
            } elseif ('>' != $t) {
                $t = substr($in, $j, strcspn($in, '<', $j));
            }
            $el->ss = $el->find = $el->j = false;
            yield $t => $el;
        }
    }

    private function parse(&$in) {
        $ends = [
            '-->' => '#comment',
            ']]>' => '#cdata',
            '?>' => '#php',
        ];
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
        $end = false;
        foreach ($this->tokens($in) as $t => $el) {
            if ($el->end) { # from <!-- or <![CDATA[ or <?php
                $push();
                $el->find = $el->end;
            } elseif ($end) {
                $this->push([$ends[$end], $t, false]);
                $el->j = strlen($end); # chars move
            } elseif ('open' == $el->mode) { # sample: <tag
                $push();
                $tag[0] = rtrim(strtolower(substr($t, 1)), '/');
                $el->ss = true;
                $el->mode = 'attr';
                continue;
            } elseif ('>' == $t && 'attr' == $el->mode) {
                if (in_array($tag[0], self::$void)) {
                    $this->push([$tag[0], 0, $tag[2]]);
                    $tag = [$str = '', [], []];
                } elseif (in_array($tag[0], ['script', 'style'])) {
                    $el->find = "</$tag[0]>";
                }
            } elseif ('attr' == $el->mode) { # attr continue
                $tag[2][$el->attr[0]] = $el->attr[1];
                $el->ss = true;
              $el->attr = 0;
                continue;
            } elseif ('close' == $el->mode) { # sample: </tag>
                $_tg = strtolower(substr($t, 2, -1));
                if ($_tg == $tag[0]) {
                    $this->push([$_tg, $str, $tag[2]]);
                } else {
                    '' === $tag[0] or $this->push([$tag[0], $str, $tag[2]]);
                    $this->push([$_tg, 1, 1]);
                }
                $tag = [$str = '', [], []];
            } else { # text
                '' !== $tag[0] or $tag[0] = '#text';
                $str .= $t;
            }
            $end = $el->end;
            $el->mode = 'txt';
        }
        $push();
    }

    private function dump($ary) {
        $tpl = [
            '#text' => '%s',
            '#comment' => '<!--%s-->',
            '#cdata' => '<![CDATA[%s]]>',
            '#php' => '<?php%s?>',
        ];
        $out = '';
        foreach ($ary as $one) {
            $node = key($one);
            $data = pos($one);
            if ('#' == $node[0]) {
                $out .= sprintf($tpl[$node], $data);
                continue;
            }
            $out .= "<$node";
            foreach (($one[0] ?? []) as $k => $v)
                $out .= ' ' . (0 === $v ? $k : "$k=\"$v\"");
            $out .= '>';
            if (is_array($data)) {
                $out .= $this->dump($data) . "</$node>";
            } elseif (0 !== $data) { # not void
                $out .= $data . "</$node>";
            }
        }
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
                case '#cdata':
                    $out .= '#cdata' == $node ? "<![CDATA[$data]]>\n" : "<!--$data-->\n";
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
/* <recursiv-ary> :
[
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
