<?php

class Azure
{
    private $layout = false;

    function __construct() {
        global $sky, $user;
        $user = new USER;
        $link = PROTO . '://' . DOMAIN;
        define('LINK', $link . PATH);
    }

    function files() {
        $list = array_map(function($v) {
            return basename($v);
        }, glob(WWW . 'tw/*.html'));
        $list[] = 'https://ukrposhta.ua/ru';
        return array_combine($list, array_map(function($v) {
            return "az.test('$v')";
        }, $list));
    }

    static function layout() {
        global $sky, $user;
        $az = new Azure;
        $az->layout = true;

        MVC::$layout = '';
        if ($_GET) {
            echo $az->file(end($_GET));
            return;
        }
        $sky->k_title = 'Visual SKY';
        $sky->k_static = [[], ["~/azure.js"], ["~/desktop.css", "~/azure.css"]];
        MVC::body('_vis.layout');

        $tz = !$user->vid || '' === $user->v_tz ? "''" : (float)('' === $user->u_tz ? $user->v_tz : $user->u_tz);
        SKY::$vars['k_js'] = "sky.is_debug=$sky->debug; var addr='" . LINK . "'; sky.tz=$tz;";

        $fsize = ['320 x 480', '640 x 480', '768 x 768', '1024 x 555', '1366 x 768', /* notebook */ '1536 x 555'];
        return [
            'fsize' => option(3, array_combine($fsize, $fsize)),
            'frame' => '<iframe src="" class="w-full h-full"></iframe>',
            'reg' => '/^<?\w[^>]*[^\/]$/',
        ];
    }

    function c_code($fn) {
        $html = $this->file($fn);
        json([
            'html' => preg_replace("/&(\w+);/", '&amp;$1;', $html),
            'list' => view('_vis.popup', [
                'files' => $this->files(),
                'components' => (new Tailwind)->components(),
            ]),
        ]);
    }

    function c_save() {
        $html = preg_replace("~<(br\s*/?|div)>~is", "\n", $_POST['html']);
        $html = html_entity_decode(strip_tags($html));
        return $this->file($_POST['fn'], $html);
    }

    function file($fn, $save = null) {
        if (':' == $fn[0]) {
            if ($save) {
                sqlf('update $_azure set tmemo=%s where id=%d', $save, substr($fn, 1));
                return true;
            }
            $css = $this->layout ? '<link rel="stylesheet" href="pub/desktop.css">' : '';
            return $css . sqlf('+select tmemo from $_azure where id=%d', substr($fn, 1));
        } elseif (strpos($fn, '/')) {
            preg_match('/^https?:/', $fn) or $fn = "https://$fn";
        } else {
            $fn = WWW . 'tw/' . basename($fn);
        }
        /*require_once 'main/w3/simple_html_dom.php';
        $node = str_get_html(unl($html));
        $node->find('body', 0)->e = 1;
        $i = 2;
        foreach($node->find('body *') as $el)
            $el->e = $i++;*/
        if ($save) {
            file_put_contents($fn, $save);
            return true;
        }

        return file_get_contents($fn);
    }

    function c_menu($v) {
        $tw = new Tailwind;
        $menu = [ // https://rogden.github.io/tailwind-config-viewer/
            't' => $tw->tools,
            'h' => [
                'HTML Colors' => "ajax('htmlcolors',az.htmlcolors)",
                'CSS Styles' => "az.css(document.body)",
                'UTF-8 Table' => "az.utf8()",
            ],
            'i' => [
                'Bootstrap' => "ajax('icons',az.icons)",
            ],
        ];
        isset($menu[$v]) or $v = 't';
        return ['menu' => $menu[$v]];
    }
    
    function c_colors() {
        json([
            'right' => view('_vis.colors', [
                'list' => array_keys($this->colors)
            ])
        ]);
    }

    function c_sortcolors() {
        return $this->c_htmlcolors(1);
    }

    function c_htmlcolors($sort) {
        $list = HTML::colors();
        if ($sort)
            sort($list);
        return [
            'list' => $list,
        ];
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
        if ($_POST)
            sql('update $_memory set tmemo=$+ where id=99', $_POST['s']);
        return [
            'ta' => sql('+select tmemo from $_memory where id=99'),
        ];
    }

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

}

