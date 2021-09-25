<?php

class xCSS
{
    public $name = [];
    public $def = [];

    function __construct() {
        
    }

}

class CSS
{
    public $sequence = [];
    public $node = [];
    public $media = [];

    function __construct() {
        
    }

}

$handle = fopen("desktop.css", "r");
$tw_start = '* Tailwind custom reset styles';
$flag = $comm = $i = 0;
$cls = [];

while (($s = fgets($handle)) !== false) {
    $i++;
    $s = trim($s);
    if (!$flag && $tw_start == trim($s))
        $flag = $comm = 1;
    if (!$comm && '/*' == substr($s, 0, 2))
        $comm = 1;
    $_comm = $comm;
    if ($comm && '*/' == trim($s))
        $_comm = 0;
    if (!$flag || $comm || '' === $s) {
        $comm = $_comm;
        continue;
    }

    $_s = substr($s, 0,5);
    if (in_array($_s, ['.sm\:', '.md\:', '.lg\:', '.xl\:', '.\32x']) || '.group:hover' == substr($s, 0,12))
        continue;

    if (' {' == substr($s, -2)) {
        if ('.' !== $s[0])
            continue;
        $c = substr($s, 0, -2);
        if (!preg_match("/^\.(\-?[a-z]+)\-(.*)$/", $c, $m))
            continue;
        $cls[$m[1]][] = $m[2];
    }
    //break;
}

echo $i . ' '. count($cls) . "\n";
//$cls = array_slice($cls, 111, 11);
print_r(array_keys($cls));



@media (min-width: 640px) {
  .sm\:container {
    width: 100%;
  }

  @media (min-width: 640px) {
    .sm\:container {
      max-width: 640px;
    }
  }
}

@media (min-width: 640px) {
  .container {
    max-width: 640px;
  }
}
