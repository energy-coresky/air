<?php

class Vendor
{
    function __construct() {
    }

    function c_detail($name = false) {
        $return = $name or $name = $_POST['n'];
            //$one = file_get_contents("https://packagist.org/packages/$name.json");
        $response = file_get_contents("https://repo.packagist.org/p2/$name.json");
        $tags = function ($a) {
            return '<div class="tags">' . implode('</div><div class="tags">', $a) . '</div>';
        };
        $authors = function ($authors) {
            return implode('<br>', array_map(function ($v) {
                $s = $v->name;
                if (isset($v->role))
                    $s .= " ($v->role)";
                if (isset($v->email))
                    $s .= ", $v->email";
                if (isset($v->homepage))
                    $s .= ", $v->homepage";
                return $s;
            }, $authors));
        };
        $json = ['html' => view('_vend.detail', [
            'act_name' => $name,
            'cnt' => $cnt = count($list = unjson($response)->packages->$name),
            'row' => $last = $list[0] ?? [],
            'detail' => print_r($last, 1),
            'ver' => $last ? ($cnt > 1 ? ', ' . $list[1]->version . ($cnt > 2 ? ' ..' : '') : '') : '',
            'authors' => $last ? $authors($last->authors) : '-',
        ]), 'tags' => $last ? $tags($last->keywords) : ''];
        return $return ? (object)$json : json($json);
    }

    function c_search() {
        SKY::d('vend_s', $s = $_POST['s'] ?? 'coresky');
        $q = urlencode($s) . '&page=' . ($p = $_POST['p']);
        if ('all' != $_POST['t'])
            $q .= "&type=$_POST[t]";
        $response = file_get_contents('https://packagist.org/search.json?per_page=100&q=' . $q);
        $std = unjson($response);
        $name = '';
        $total = 'Found: 0';
        if ($std->total) {
            $name = $std->results[0]->name;
            $nl = $std->total > 100 * $p;
            $total = (1 + 100 * ($p - 1)) . '-' . ($nl ? 100 * $p : $std->total) . " of $std->total";
            if ($nl)
                $total .= ' <input type="button" value="next" onclick="sky.d.vend(' . (1 + $p) . ')"/>';
        }
        json([
            'total' => $total,
            'next' => $std->next ?? 0,
            'packages' => view('_vend.packages', [
                'act_name' => $name,
                'list' => $std->results,
                'row' => $name ? $this->c_detail($name) : '',
                'url' => $std->results[0]->url ?? '',
                'repo' => $std->results[0]->repository ?? '',
            ]),
            'raw' => tag(print_r($std,1), '', 'pre'),
        ]);
    }

    function c_list() {
        global $sky;
        return [
            'obj' => $this,
            'types' => ['all', 'library', 'project', 'metapackage', 'composer-plugin'],
        ];
    }
}

/*
                    [url] => https://packagist.org/packages/laravel/laravel
                    [repository] => https://github.com/laravel/laravel

 [next] => https://packagist.org/search.json?q=lara&page=2

                    */