<?php

class PHP
{
    const version = 0.202;

    const KEYWORD = 1;
    const USAGE = 2;
    const DEFINITION = 3;
    const CHARS = 4;
    const TYPE = 5;

    static $php;

    public $in;
    public $lines = 1;
    public $pad; # 0 for minified PHP
    public $top;

    private $ns = '';
    private $use = [];
    private $stack = [];
    private $oc = [
        '{' => '}',
        '(' => ')',
        '[' => ']',
    ];

    function __construct(string $in = '', $pad = 4) {
        self::$php or self::$php = yml('+ @object@inc(php)');
        $this->in = unl($in);
        $this->lines = substr_count($this->in, "\n");
        $this->pad = $pad;
    }

    static function file($name) {
        echo new PHP(file_get_contents($name));
    }

    function trace($t) {
        static $i = 0; $i++;
        $s = $t->tok . ' ' . token_name($t->tok) . ' ' . $t->str;
        if ($t->sz)
            $s .= " ------------------- SZ: $t->sz";
        return "===================== $i\n$s\n";// . var_export($t, 1);
    }

    function __toString() {
        $this->top or $this->parse_nice();
        $out = $trace = '';
        for ($el = $this->top; $el; $el = $el->next) {
$trace .= $this->trace($el);
            if (!$this->pad) {
                if (T_COMMENT == $el->tok
                    || T_WHITESPACE == $el->tok && in_array($el->prev->tok, self::$php->prev_space)
                    || T_WHITESPACE == $el->tok && in_array($el->next->tok, self::$php->after_space)
                )
                    continue;
            }
            $out .= $el->str;
        }
        return $out . "\nlines: $this->lines\n$trace";
    }

    function tok($tok, $prev) {
        return (object)[
            'tok' => ($is = is_array($tok)) ? $tok[0] : 0,
            'str' => $str = $is ? $tok[1] : $tok,
            'prev' => $prev,
            'next' => null,
            'rank' => 0, # KEYWORD/CHARS/USAGE/DEFINITION
            'sz' => 0,
            'oc' => 0, # open/none/close 1/0/-1
        ];
    }

    function tokens() {
        $y = null;
        foreach (token_get_all($this->in) as $i => $tok) {
            $n = $this->tok($tok, $y);
            if ($y) {
                $y->next = $n;
                if (T_COMMENT == $y->tok && "\n" == $y->str[-1] && T_WHITESPACE == $n->tok) {
                    $y->str = substr($y->str, 0, -1);
                    $n->str = "\n" . $n->str;
                }
                yield $i => $y;
            } else {
                $this->top = $n;
            }
            $y = $n;
        }
        yield ++$i => $y;
    }

    function parse_easy($func = null) {
        iterator_apply($y = $this->tokens(), $func ?? fn() => true, [$y]);
    }

    function parse_nice() {
        $stk =& $this->stack;
        $open = array_keys($this->oc);
        $close = array_values($this->oc);
        foreach ($this->tokens() as $i => $y) {
            if ($stk) {
                $last = array_key_last($stk);
                $stk[$last]->sz += strlen(' ' == $y->str ? ' ' : trim($y->str));
            }
            if (in_array($y->str, $open)) {
                $y->oc = $y->sz = 1;
                $stk[] = $y;
            } elseif (in_array($y->str, $close)) {
                $y->oc = -1;
                if ($y->str == $this->oc[end($stk)->str]) { // checking!!
                    $pos = array_pop($stk);
                    if ($stk) {
                        $last = array_key_last($stk);
                        $stk[$last]->sz += $pos->sz - 1;
                    }
                }
            }
        }
    }

    function parse_rank() {
        foreach ($this->tokens() as $i => $y) {
            if (T_STRING == $n->tok) {
                
            } elseif (in_array($n->str, self::$php->keywords)) { // 2do: chk
                $n->rank = PHP::KEYWORD;
            } elseif (in_array($n->str, self::$php->types)) {
                $n->rank = PHP::TYPE;
            }
            switch ($y->tok) {
                case T_NAMESPACE:
                    $this->ns = '';
                    break;
                case T_EVAL: ;
                case T_GLOBAL: ;
                case T_CONST: ;
                case T_VARIABLE: ;
                case T_INTERFACE: ;
                case T_TRAIT: ;
            }
        }
    }
}
