<?php

class PHP
{
    const version = 0.121;
    const KEYWORD = 1;
    const USAGE = 2;
    const DEFINITION = 3;
    const CHARS = 4;
    const TYPE = 5;

    static $php;

    public $in;
    public $array = [];
    public $pad; # 0 for minified PHP

    private $stack;
    private $y = [
        'tok' => 0,
        'str' => '',
        'line' => 1,
        'sz' => 0,
        'rank' => 0, # KEYWORD/CHARS/USAGE/DEFINITION
        'oc' => 0, # open/none/close 1/0/-1
    ];
    private $oc = [
        '{' => '}',
        '(' => ')',
        '[' => ']',
    ];

    function __construct(string $in = '', $pad = 4) {
        self::$php or self::$php = yml('+ @object@inc(php)');
        $this->in = unl($in);
        $this->stack = [];
        $this->pad = $pad;
    }

    static function file($name) {
        echo new PHP(file_get_contents($name));
    }

    function trace($i, $t) {
        $t->tok = $t->tok . ' ' . token_name($t->tok);
        return "===================== $i\n" . var_export($t, 1);
    }

    function __toString() {
        $this->array or $this->parse();
        [$lines] = array_shift($this->array);
        $out = $prev = $trace = '';
        foreach ($this->array as $i => $t) {
$trace .= $this->trace($i, $t);
            if (!$this->pad) {
                $next = isset($this->array[$i + 1]) ? $this->array[$i + 1]->tok : '';
                if (T_COMMENT == $t->tok
                    || T_WHITESPACE == $t->tok && in_array($prev, self::$php->prev_space)
                    || T_WHITESPACE == $t->tok && in_array($next, self::$php->after_space)
                )
                    continue;
            }
            $out .= $t->str;
            $prev = $t->str;
        }
        return $out . "\nlines: $lines\n$trace";
    }

    function char($y, $i) {
        $ary =& $this->stack;
        if (in_array($y->str, array_keys($this->oc))) {
            $y->oc = 1;
            $ary[] = [$y->str, $i, 1];
        } elseif (in_array($y->str, array_values($this->oc))) {
            $y->oc = -1;
            if ($y->str == $this->oc[end($ary)[0]]) { // checking!!
                $pop = array_pop($ary);
                if ($ary) {
                    $last = array_key_last($ary);
                    $ary[$last][2] += $pop[2] - 1;
                }
                return $pop;
            }
        }
    }

    function tokens($y = false) {
        $y or $y = (object)$this->y;
        $prev = false;
        $ary =& $this->stack;
        foreach (token_get_all($this->in) as $i => $t) {
            [$y->tok, $y->str] = is_array($t) ? $t : [0, $t];
            $y->oc = $y->sz = $y->rank = 0;
            if ($ary) {
                $last = array_key_last($ary);
                $ary[$last][2] += strlen(' ' == $y->str ? ' ' : trim($y->str));
            }
            if (1 == strlen($y->str))
                $y->sz = $this->char($y, 1 + $i);
            if (in_array($y->str, self::$php->keywords)) { // 2do: chk
                $y->rank = PHP::KEYWORD;
            } elseif (in_array($y->str, self::$php->types)) {
                $y->rank = PHP::TYPE;
            }
            if ($prev) {
                if (T_COMMENT == $prev->tok && "\n" == $prev->str[-1] && T_WHITESPACE == $y->tok) {
                    $prev->str = substr($prev->str, 0, -1);
                    $y->str = "\n" . $y->str;
                    $y->line--;
                }
                yield $i => $prev;
            }
            $prev = clone $y;
            $y->line += substr_count($y->str, "\n");
        }
        yield ++$i => $prev;
        return $y->line;
    }

    function parse() {
        $this->array = [[]];
        $tokens = $this->tokens();
        foreach ($tokens as $i => $y) {
            switch ($y->tok) {
                case T_NAMESPACE: ;
                case T_EVAL: ;
                case T_GLOBAL: ;
                case T_CONST: ;
                case T_VARIABLE: ;
                case T_INTERFACE: ;
                case T_TRAIT: ;
            }
            if ($y->sz) {
                [, $pos, $sz] = $y->sz;
                $y->sz = 0;
                $this->array[$pos]->sz = $sz;
            }
            $this->array[$i] = clone $y;
        }
        $this->array[0] = [$tokens->getReturn()];
    }
}
