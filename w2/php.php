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
        $this->pad = $pad;
    }

    static function file($name) {
        echo new PHP(file_get_contents($name));
    }

    function trace($i, $t) {
        $s = $t->tok . ' ' . token_name($t->tok) . ' ' . $t->str;
        if ($t->sz)
            $s .= " ------------------- SZ: $t->sz";
        return "===================== $i\n$s\n";// . var_export($t, 1);
    }

    function __toString() {
        $this->top or $this->parse();
        $out = $trace = '';
        $el = $this->top;
     $i=1;
        do {
$trace .= $this->trace($i++, $el);
            if (!$this->pad) {
                if (T_COMMENT == $el->tok
                    || T_WHITESPACE == $el->tok && in_array($el->prev->tok, self::$php->prev_space)
                    || T_WHITESPACE == $el->tok && in_array($el->next->tok, self::$php->after_space)
                )
                    continue;
            }
            $out .= $el->str;
        } while ($el = $el->next);
        return $out . "\nlines: $this->lines\n$trace";
    }

    function char($n) {
        $stk =& $this->stack;
        if (in_array($n->str, array_keys($this->oc))) {
            $n->oc = $n->sz = 1;
            $stk[] = $n;
        } elseif (in_array($n->str, array_values($this->oc))) {
            $n->oc = -1;
            if ($n->str == $this->oc[end($stk)->str]) { // checking!!
                $pos = array_pop($stk);
                if ($stk) {
                    $last = array_key_last($stk);
                    $stk[$last]->sz += $pos->sz - 1;
                }
            }
        }
    }

    function tokens() {
        $cur = null;
        $stk =& $this->stack;
        foreach (token_get_all($this->in) as $i => $tok) {
            $n = $this->tok($tok, $cur);
            if ($stk) {
                $last = array_key_last($stk);
                $stk[$last]->sz += strlen(' ' == $n->str ? ' ' : trim($n->str));
            }
            if (1 == strlen($n->str))
                $this->char($n);
            if (T_STRING == $n->tok) {
                
            } elseif (in_array($n->str, self::$php->keywords)) { // 2do: chk
                $n->rank = PHP::KEYWORD;
            } elseif (in_array($n->str, self::$php->types)) {
                $n->rank = PHP::TYPE;
            }
            if ($cur) {
                $cur->next = $n;
                if (T_COMMENT == $cur->tok && "\n" == $cur->str[-1] && T_WHITESPACE == $n->tok) {
                    $cur->str = substr($cur->str, 0, -1);
                    $n->str = "\n" . $n->str;
                    //$n->nl++;     $cur->nl--;
                }
                yield $i => $cur;
            } else {
                $this->top = $n;
            }
            $cur = $n;
        }
        yield ++$i => $cur;
    }

    function parse() {
        foreach ($this->tokens() as $i => $cur) {
            switch ($cur->tok) {
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
            $this->lines += $cur->nl;
        }
        //exit;
    }

    function tok($tok, $prev) {
        return (object)[
            'tok' => ($is = is_array($tok)) ? $tok[0] : 0,
            'str' => $str = $is ? $tok[1] : $tok,
            'nl' => substr_count($str, "\n"), # new lines count
            'prev' => $prev,
            'next' => null,
            'sz' => 0,
            'rank' => 0, # KEYWORD/CHARS/USAGE/DEFINITION
            'oc' => 0, # open/none/close 1/0/-1
        ];
    }
}
