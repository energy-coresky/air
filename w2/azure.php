<?php

class Azure
{
    function __construct() {
        global $sky, $user;
        $user = new USER;
        $link = PROTO . '://' . DOMAIN;
        define('LINK', $link . PATH);
    }

    static function layout() {
        global $sky, $user;
        new Azure;

        $sky->k_title = 'Visual SKY';
        MVC::body('_vis.layout');
        MVC::$layout = '';

        ///////////////////////////////
        $tz = !$user->vid || '' === $user->v_tz ? "''" : (float)('' === $user->u_tz ? $user->v_tz : $user->u_tz);
        SKY::$vars['k_js'] = "sky.is_debug=$sky->debug; var addr='" . LINK . "'; sky.tz=$tz;";

        $fsize = [
            '320 x 480',
            '640 x 480',
            '768 x 768',
            '1024 x 555',
            '1366 x 768', # notebook
            '1536 x 555',
        ];
        $sections = [
            'tw' => 'Tailwind',
            'com' => 'Components',
            'icon' => 'Icons',
            'html' => 'HTML',
        ];
        $menu = [
            'tw' => [ // https://rogden.github.io/tailwind-config-viewer/
                'Dimension' => [
                    'Width' => '',
                    'Height' => '',
                    'Padding' => '', // all top-right-botton-left horizontal-vertical
                    'Margin' => '',
                    'Negative Margin' => '',
                    'Min Width' => '',
                    'Max Width' => '',
                    'Min Height' => '',
                    'Max Height' => '',
                ],
                'Text' => [
                    'Font Size' => "ajax('text',az.text)",
                    'Font Weight' => '',
                    'Letter Spacing' => '',
                    'Line Height' => '',
                ],
                'Border' => [
                    'Radius' => '',
                    'Width' => '',
                ],
                'Other' => [
                    'Screens' => '',
                    'Shadows' => '',
                    'Opacity' => '',
                    'Transitions' => '',
                ],
            ]
        ];

        $sky->k_static = [[], ["~/azure.js"], ["~/desktop.css", "~/azure.css"]];
        return [
            'fsize' => option(3, array_combine($fsize, $fsize)),
            'sections' => option(3, $sections),
            'menu' => $menu['tw'],
            'frame' => '<iframe src="" class="w-full h-full"></iframe>',
            'xy' => $user->v_xy,
        ];
    }

    function c_xy($xy) {
        global $user;
        $user->v_xy = $xy;
        return true;
    }
    
    function c_colors() {
        json([
            'right' => view('_vis.colors', [
                'list' => array_keys($this->colors)
            ])
        ]);
    }

    function c_dim() {
    }

    function c_text() {
        $size = [
            'xs',
            'sm',
            'base',
            'lg',
            'xl',
            '2xl',
            '3xl',
            '4xl',
            '5xl',
            '6xl',
            '7xl',
            '8xl',
            '9xl',
        ];
        return [
            'sizes' => $size,
        ];
    }

    function c_code() {
        require_once 'main/w3/simple_html_dom.php';

        $html = unl(file_get_contents($_POST['src']));
        $tidy = new tidy;
        $tidy->parseString($html, [
            'indent' => true,
            //'output-html' => true,
            'wrap'         => 0,
        ], 'utf8');
        $tidy->cleanRepair();
        $html = (string)$tidy;
        return [
            'code' => html($html),
            'lines' => implode("\n", range(1, 1 + substr_count($html, "\n"))),
        ];
    }

    function c_icons() {
        $src = 'node_modules/bootstrap-icons/icons/*.svg';
        $list = [];
        foreach (glob($src) as $fn) {
            $list[basename($fn, '.svg')] = file_get_contents($fn);
            if (count($list) > 49)
                break;
        }
        return [
            'list' => $list
        ];
    }

    function c_settings($x) {
        //echo 'Yes!<br>';var_dump($x);
    }

    private $pad = [
        '4 direction' => 'm',   // -m- -mx- p- px-  margin(2) padding(1)
        'left+right' => 'mx',
        'top+bottom' => 'my',
        'top' => 'mt',
        'right' => 'mr',
        'botom' => 'mb',
        'left' => 'ml',
    ];

    private $colors = [
        'gray' => '', // 50, 100, 200 .. 900
        'red' => '',
        'yellow' => '',
        'green' => '',
        'blue' => '',
        'indigo' => '',
        'purple' => '',
        'pink' => '',
        #'' => '',
  #      'black' => '', //
     #   'white' => '',
    ];

    private $left = [];

    function dimensions() {
        $left = range(0, 12);
        array_push($left, 14, 16);
        $left = array_merge($left, range(20, 64, 4));
        array_push($left, 72, 80, 96);
        $left[] = 'auto';
        $left[] = 'px';
        $left[] = '0.5';
        $left[] = '1.5';
        $left[] = '2.5';
        $left[] = '3.5';
        $left[] = '1/2';
        $left[] = '1/3';
        $left[] = '2/3';
        $left[] = '1/4';
        $left[] = '2/4';
        $left[] = '3/4';
        $left[] = 'full';
    }

    private $groups = [
        'w' => 'Width',
        'h' => 'Height',
        'bg' => 'Background',
        'font' => '',
        'text' => '',
        'border' => '',
        'z' => 'Z-index',

        '-inset' => '',
        '-top' => '',
        '-right' => '',
        '-bottom' => '',
        '-left' => '',

        '-translate' => '',
        '-rotate' => '',
        '-skew' => '',
        '-space' => '',
        '-hue' => '',
        '-backdrop' => '',
        'max' => '',
        'min' => '',
        'gap' => '',
        'line' => '',
        'opacity' => '',
        'align' => '',
        'shadow' => '',
        'rounded' => '',
        'stroke' => '',

        'flex' => '',
        'order' => '',
        'col' => '',
        'row' => '',

        'focus-within:' => '',
 /*
        'btn' => '',
        'sr' => '',
        'not' => '',*/

        'focus' => '',
        'pointer' => '',
        'isolation' => '',

        'float' => '',
        'clear' => '',

        'box' => '',
        'inline' => '',
        'table' => '',
        'flow' => '',
        'list' => '',

        'origin' => '',
        'transform' => '',
        'scale' => '',
        'animate' => '',
        'cursor' => '',
        'select' => '',
        'resize' => '',
        'appearance' => '',
        'auto' => '',
        'grid' => '',
        'place' => '',
        'content' => '',
        'items' => '',
        'justify' => '',

        'divide' => '',
        'self' => '',
        'overflow' => '',
        'overscroll' => '',
        'whitespace' => '',
        'break' => '',
        'from' => '',
        'via' => '',
        'to' => '',
        'decoration' => '',
        'fill' => '',
        'object' => '',
        'normal' => '',
        'slashed' => '',
        'lining' => '',
        'oldstyle' => '',
        'proportional' => '',
        'tabular' => '',
        'diagonal' => '',
        'stacked' => '',
        'leading' => '',
        'tracking' => '',
        'no' => '',
        'subpixel' => '',
        'placeholder' => '',
        'mix' => '',
        'outline' => '',
        'ring' => '',
        'filter' => '',
        'blur' => '',
        'brightness' => '',
        'contrast' => '',
        'drop' => '',
        'grayscale' => '',
        'invert' => '',
        'saturate' => '',
        'sepia' => '',
        'transition' => '',
        'delay' => '',
        'duration' => '',
        'ease' => '',
    ];
}

