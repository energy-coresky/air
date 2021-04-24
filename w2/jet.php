<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class Jet
{
	private $parsed;
	private $replace = [];
	private $files = [];
	private $empty = [];
	private $for = [];
	private $div = '<div style="display:none" id="err-top"></div>';
	private $current = '';
	static $directive = false;
	static $dirs = [];

	static function directive($name, $func = null) {
		self::$dirs += is_array($name) ? $name : [$name => $func];
	}

	static function q($tpl, $arg) {
		$arg = in_array(@$arg[0], [false, "'", '"']) ? $arg : '"' . escape($arg, '\\"') . '"';
		return sprintf("<?php $tpl ?>", $arg);
	}
	
	function __construct($layout, $name, $fn = false, $is_fire = false) {
		global $sky;

		$this->body = $name;
		$part = '';
		if ($layout) {
			$name = "y_$layout";
		} else {
			if (strpos($name, '.'))
				list($name, $part) = explode('.', $name, 2);
			$dir = $sky->style ? DIR_V . "/$sky->style" : DIR_V;
			if ($sky->is_mobile && '_' == $name[0] && is_file("$dir/b$name.php"))
				$name = "b$name";
		}
		if (!self::$directive) {
			MVC::handle('jet_c');
			self::$directive = true;
		}
		$this->files[$name] = 1;
		$this->parsed = $this->parse($this->current = $name, $part);
		if ($fn) {
			$prefix = "<?php\n#" . implode(' ', array_keys($this->files)) . "\n?>";
			$postfix = DEV && $is_fire && !$layout ? '<?php if (2 == Ext::cfg("var")): Ext::ed_var(get_defined_vars()); endif; ?>' : '';
			file_put_contents($fn, $prefix . $this->parsed . $postfix);
		}
	}
	
	private function parse($name, $part = '') {
		$in = file_get_contents(MVC::fn_tpl($name));
		if ('' !== $part) {
			$ary = preg_split("/([\r\n]+|\A)\s*\#[\.\w+]*?\.{$part}[\.\w+]*?( .*?)?([\r\n]+|\z)/s", $in, 3);
			$in = '';
			if (count($ary) != 3)
				throw new Err("Jet: cannot find `$name.$part`");
			else
				$in = $ary[1];
		}
		for ($i = 0; $i < 20 && $this->preprocessor($in); $i++);
		$in = preg_replace('/([\r\n]+|\A)\s*#\.[\.\w+]+.*?([\r\n]+|\z)/s', '$2', $in); # delete nested part markers
		
		$in = preg_replace_callback('/@verb(.*?)~verb/s', function ($m) {
			$this->replace[$lab = '%__verb_' . count($this->replace) . '__%'] = $m[1];
			return $lab;
		}, $in);
		
		$out = '';
		foreach (token_get_all($in) as $token) {
			if (is_array($token)) {
				list($id, $str) = $token;
				$token = $str;
				if ($id == T_INLINE_HTML) {
					$this->parse_statements($token);
					$this->parse_echos($token);
				}
			}
			$out .= $token;
		}

		return $this->optimize(strtr($out, $this->replace));
	}

	private function preprocessor(&$in) {
		$a_i = $a_u = 0;
		$re = '/\B\#(end|if|elseif|else)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x';
		$in = preg_replace_callback($re, function ($a_match) use (&$a_i, &$a_u) {
			if ($a_u < 0)
				return $a_match[0];
			extract(MVC::$vars, EXTR_REFS);
			$a_label = '%__ife_' . ++$a_i . '__%';
			switch ($a_match[1]) {
				case 'end':
					$a_u = $a_u ? -$a_u : -99;
					return $a_label;
				case 'if':
					if ($a_i > 1)
						throw new Err("Jet preprocessor: cannot use nested #if..");
				case 'elseif':
					if (!isset($a_match[3]))
						return $a_match[0];
					$a_u or !eval("return $a_match[3];") or $a_u = $a_i;
					return $a_label;
				case 'else':
					$a_u or $a_u = $a_i;
					return $a_label;
			}
		}, $in);
		if ($a_i) {
			for ($ok = false, $prv = $i = 1; $i <= $a_i; $i++, $prv = $pos) {
				$pos = strpos($in, "%__ife_{$i}__%");
				if ($i > 1) { # from second step
					$size = $ok ? ($i > 10 ? 12 : 11) : $pos - $prv;
					$pos -= $size;
					$in = substr($in, 0, $prv) . substr($in, $prv + $size);
					$ok = false;
				}
				if ($i == -$a_u)
					$ok = true;
				if ($i == $a_i) # last cycle
					$in = str_replace("%__ife_{$i}__%", '', $in);
			}
		}
		return $a_i;
	}

	/* delete `<?php  ?>` and merge `?><?php` in parsed templates */
	private function optimize($in) {
		$out = $tmp = '';
		$flag = false;
		foreach (token_get_all($in) as $token) {
			if (is_array($token)) {
				list($id, $str) = $token;
				if (T_OPEN_TAG == $id) {
					if ($flag) {
						$out = substr($out, 0, -strlen($flag)) . ';';
						$tmp = '';
					} else {
						$out .= $tmp;
						$tmp = $str;
					}
				} elseif (T_CLOSE_TAG == $id) {
					'' === $tmp and $flag = $str;
					'' === $tmp ? ($out .= $str) : ($tmp = '');
					continue;
				} elseif (T_WHITESPACE == $id) {
					'' === $tmp ? ($out .= $str) : ($tmp .= $str);
				} else {
					$out .= $tmp . $str;
					$tmp = '';
				}
			} else {
				$out .= $tmp . $token;
				$tmp = '';
			}
			$flag = false;
		}
		return $out;
	}

	private function parse_echos(&$str) {
		$str = preg_replace_callback('/[~@]?{[{!\-](.*?)[\-!}]}/s', function ($m) {
			if ('@' == $m[0][0])
				return substr($m[0], 1); # verbatim
			$tilda = '~' == $m[0][0];
			$esc = '%s';
			switch ($m[0][1 + (int)$tilda]) {
			case '-':
				return $tilda ? '' : '<?php /*' . $m[1] . '*/ ?>'; # Jet comment
			case '{':
				$esc = 'html(%s)'; # echo escaped
			case '!':
				$or = $and = false;
				$left = $right = '';
				foreach (token_get_all("<?php $m[1]") as $t) { # a ?: b 
					if (is_array($t)) {
						if (T_OPEN_TAG == $t[0])
							continue;
						if (in_array($t[0], [T_LOGICAL_OR, T_LOGICAL_AND])) {
							T_LOGICAL_OR == $t[0] ? ($or = true) : ($and = true);
							continue;
						}
						$t = $t[1];
					}
					$or || $and ? ($right .= $t) : ($left .= $t);
				}
				if (!$or && !$and) {
					$val = trim($m[1]);
					$op = $tilda ? "isset($val) ? $val : ''" : $val;
					return sprintf("<?php echo $esc ?>", $op);
				}
				$left = trim($left);
				$right = trim($right);
				if ($and) {
					$op = $tilda ? "isset($left) && $left" : $left;
					return sprintf("<?php echo %s ? $esc : '' ?>", $op, $right);
				}
				# else `or`
				$op = $tilda
					? "isset($left) && '' !== trim($left) ? $left : $right"
					: "isset($left) ? $left : $right";
				return sprintf("<?php echo $esc ?>", $op);
			}
		}, $str);
	}

	private function parse_statements(&$str) {
		$str = preg_replace_callback('/[~@]([a-z]+)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', function ($m) {
			$end = '~' == $m[0][0];
			$sp = $m[2] ? ' ' : '';
			$arg = isset($m[3]) ? substr($m[3], 1, -1) : '';
			switch ($m[1]) {
				case 'php': return $end ? "?>$sp" : "<?php$sp";
				case 'unless': $arg = "!($arg)";
				case 'if': return $end ? "<?php endif ?>$sp" : "<?php if ($arg): ?>";
				case 'cache': return $end ? '<?php cache(); endif ?>' : "<?php if (cache($arg)): ?>";
				case 'do': return $this->_do($end, $arg); # do { .. } while(..)
				case 'for': return $this->_for($end, $arg); # for foreach while
			}

			if ($end)
				return $m[0];
			if (isset(self::$dirs[$m[1]])) # user defined
				return call_user_func(self::$dirs[$m[1]], $arg, $this);

			switch ($m[1]) {
				case 'pdaxt': return sprintf('<?php MVC::pdaxt(%s) ?>', $arg ? $arg : '') . $sp;
				case 'else': return '<?php else: ?>' . $sp;
				case 'elseif': return "<?php elseif ($arg): ?>";
				case 't': return Jet::q('echo t(%s)', $arg);
				case 'p': return Jet::q('echo \'"\' . PATH . %s . \'"\'', $arg);
				case 'continue': return $arg ? "<?php if ($arg): continue; endif ?>" : '<?php continue ?>' . $sp;
				case 'break': return $arg ? "<?php if ($arg): break; endif ?>" : '<?php break ?>' . $sp;
				case 'view': return Jet::q('view(%s)', $arg);
				case 'require':
				case 'inc': return $this->_file($arg, 'inc' == $m[1]);
				case 'body': return $this->_file('*', $arg == 'true');
				case 'dump': return "<?php echo '<pre>' . html(print_r($arg, true)) . '</pre>' ?>";
				case 'mime':
					$this->div = '';
					return Jet::q('MVC::doctype(%s)', $arg);
				case 'empty':
					if ('do' == end($this->for))
						throw new Err('Jet: no @empty statement for @do');
					$this->empty[] = $i = count($this->for) - 1;
					return $this->_for(true, '') . '<?php if (!' . $this->auto_a($i) . '): ?>';
				case 'head': return "<?php MVC::head($arg) ?>";
				case 'tail':
					$ed_var = DEV ? ' if (2 == Ext::cfg("var")): Ext::ed_var(get_defined_vars()); endif;' : '';
					return "<?php$ed_var MVC::tail($arg) ?>";
				case 'href': return 'href="javascript:;" onclick="' . $arg . '"';
				case 'csrf': return '<?php echo hidden() ?>' . $sp;
			}
			return $m[0];
		}, $str);
	}

	function auto_a($i) {
		return $i ? '$a_' . (1 + $i) : '$a';
	}

	function _do($end, $arg) {
		$cnt = count($this->for);
		$iv = $this->auto_a($end ? $cnt - 1 : $cnt);
		if (!$end) {
			$this->for[] = 'do';
			return "<?php $iv = 0; do { ?>";
		}
		array_pop($this->for);
	//	if (preg_match('/^\s*(\$e_\w+)\s*(\:\s*(\$\w+))?/', $arg, $m)) # $e_.. cycle
		//	$arg = (isset($m[3]) ? $m[3] : '$row') . " = SQL::row($m[1], $iv)";
		return "<?php $iv++; } while ($arg); ?>";
	}

	function _for($end, $arg) {
		$cnt = count($this->for);
		if ($end) {
			if (end($this->empty) == $cnt) {
				array_pop($this->empty);
				return '<?php endif ?>';
			}
			return sprintf('<?php %s++; end%s ?>', $this->auto_a($cnt - 1), array_pop($this->for));
		}
		$iv = $this->auto_a($cnt);
		if (preg_match('/^\s*(\$e_\w+)\s*(\:\s*(\$\w+))?/', $arg, $m)) { # $e_.. cycle
			$this->for[] = 'foreach';
			return sprintf("<?php $iv = 0; foreach ($m[1] as %s): ?>", isset($m[3]) ? $m[3] : '$row');
		}
		$is_for = $is_foreach = false;
		foreach (token_get_all("<?php $arg") as $t) {
			$is_for |= is_string($t) && ';' == $t;
			$is_foreach |= is_array($t) && T_AS == $t[0];
		}
		$this->for[] = ($for = $is_foreach ? 'foreach' : ($is_for ? 'for' : 'while'));
		return "<?php $iv = 0; $for ($arg): ?>";
	}

	function _file($name, $is_inc) {
		$red = '%s';
		$div = '';
		if ('*' == $name) {
			$div = $this->div;
			if ('_' === $this->body)
				return $div . '<?php echo $a_stdout ?>';
			$name = $this->body;
		} elseif ('r_' == substr($name, 0, 2) && DEV) { # red label
			$red = '<?php if ($sky->s_red_label): ?>' . tag(($is_inc ? '@inc' : '@require') . "($name)", 'class="red_label"')
				. "<?php else: ?>%s<?php endif ?>";
		}
		if ('.' == $name[0])
			$name = $this->current . $name;
		if ($is_inc) { # 2do: throw new Err('Jet: cycled @inc()');
			$lab = "%__inc_{$name}__%";
			if (isset($this->replace[$lab]))
				return $div . $lab;
			$me = new Jet('', $name);
			$this->replace[$lab] = sprintf($red, $me->parsed);
			$this->files += $me->files;
			return $div . $lab;
		} else { # require
			$me = new Jet('', $name, $fn = MVC::fn_parsed('', $name));
			$this->files += $me->files;
			return $div . sprintf($red, "<?php require MVC::jet('$fn'); ?>");
		}
	}
}
