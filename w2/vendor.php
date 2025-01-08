<?php

class Vendor
{
    public $types = ['all', 'library', 'project', 'metapackage', 'composer-plugin', 'symfony-bundle'];
    public $e = [];
    public $lock = [];
    public $json = [];

    function __construct() {
        $this->e = [is_file('composer.json'), is_file('composer.lock'), is_dir('vendor')];
        $this->lock = $this->e[1] ? json_decode(file_get_contents('composer.lock'))->packages : [];
        if ($this->json = $this->e[0] ? json_decode(file_get_contents('composer.json'), true) : [])
            $this->json = ($this->json['require'] ?? []) + ($this->json['require-dev'] ?? []);
    }

    function c_md() {
        if ('https://' == substr($fn = $_POST['fn'], 0, 8)) {
            $ary = get($fn);
            $fn = $ary['download_url'];
        }
        echo tag(Show::md(file_get_contents($fn)), 'style="padding-left:10px"');
        return true;
    }

    function c_exec() {
        exec("composer $_POST[s] 2>&1", $output, $return);
        echo implode("\n", $output);
        return true;
    }

    function c_detail($name = false, $repo = false) {
        $return = $name or $name = $_POST['n'];
        $repo or $repo = $_POST['r'];
           //$one = file_get_contents("https://packagist.org/packages/$name.json");
        $response = file_get_contents(Plan::php()->packagist . "$name.json");

        $docs = [];
        if ($repo && 'https://github.com/' == substr($repo, 0, 19)) {
            $ghname = substr($repo, 19);
            //https://raw.githubusercontent.com/$name/master/README.md
            $ary = get("https://api.github.com/repos/$ghname/contents");
            foreach ($ary as $one)
                if ('LICENSE' == $one['name'] || '.md' == substr($one['name'], -3))
                    $docs[$one['name']] = $one['url'];
        }

        $tags = function ($a) {
            return implode('', array_map(function ($v) {
                return '<div class="tags" onclick="sky.d.vend(1,\'' . $v . '\')">' . "$v</div>";
            }, $a));
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
        $mds = function ($name) {
            [$vend, $pack] = explode('/', $name);
            $mds = [];
            foreach (Rare::walk_dirs("vendor/$vend") as $dir) {
                $mds = array_merge($mds, glob("$dir/*.md"));
            }
            return $mds;
        };
        $com = is_dir("vendor/$name") ? 'remove' : 'require';
        $skip = ['bin', 'composer', 'autoload.php'];
        $ary = ['html' => view('_vend.detail', [
            'act_name' => $name,
            'cnt' => $cnt = count($list = unjson($response)->packages->$name),
            'row' => $last = $list[0] ?? [],
            'detail' => print_r($last, 1),
            'composer' => ($last && 'project' != $last->type ? "$com " : "create-project ") . $name,
            'ver' => $last ? ($cnt > 1 ? ', ' . $list[1]->version . ($cnt > 2 ? ' ..' : '') : '') : '',
            'authors' => $last ? $authors($last->authors) : '-',
            'vendors' => array_diff(array_map('basename', glob('vendor/*')), $skip),
            'mds' => $mds($name),
            'docs' => $docs,
        ]), 'tags' => $last ? $tags($last->keywords) : ''];
        return $return ? (object)$ary : json($ary);
    }

    function c_search() {
        SKY::d('vend_s', $s = $_POST['s'] ?? 'coresky');
        $q = urlencode($s) . '&page=' . ($p = $_POST['p']);
        $name = $tag = '';
        if ('all' != $_POST['t'])
            $q .= "&type=$_POST[t]";
        if ('' != $_POST['g']) {
            $q .= "&tags=$_POST[g]";
            $tag = " tag is: $_POST[g]" . ' <input type="button" value="hide" onclick="sky.d.vend(1,1)"/>';
        }
        $response = file_get_contents('https://packagist.org/search.json?per_page=100&q=' . $q);
        $std = unjson($response);
        $total = 'Found: 0';
        if ($std->total) {
            $name = $std->results[0]->name;
            $nl = $std->total > 100 * $p;
            $total = (1 + 100 * ($p - 1)) . '-' . ($nl ? 100 * $p : $std->total) . " of $std->total";
            if ($nl)
                $total .= ' <input type="button" value="next" onclick="sky.d.vend(' . (1 + $p) . ')"/>';
        }
        json([
            'total' => $total . $tag,
            'next' => $std->next ?? 0,
            'packages' => view('_vend.packages', [
                'act_name' => $name,
                'list' => $std->results,
                'url' => $std->results[0]->url ?? '',
                'repo' => $repo = $std->results[0]->repository ?? '',
                'row' => $name ? $this->c_detail($name, $repo) : '',
            ]),
            'raw' => pre(print_r($std,1), ''),
        ]);
    }

    function c_local() {
        $mode = $this->lock ? 0 : ($this->json ? 1 : 2);
        return [
            'vnd' => $this,
            'e_list' => function () use ($mode) {
                if (2 == $mode || !$this->lock && !$mode || !$this->json && $mode)
                    return false;
                if ($mode) {
                    $n = key($this->json);
                    $ver = array_shift($this->json);
                    return !strpos($n, '/') ?: [
                        'name' => tag($n, '', 'b'),
                        'ver' => $ver,
                        'desc' => '??',
                        'time' => '??',
                    ];
                }
                $row = array_shift($this->lock);
                return [
                    'name' => isset($this->json[$n = $row->name]) ? "<b>$n</b>" : $n,
                    'ver' => $row->version,
                    'desc' => $row->description ?? '??',
                    'time' => substr($row->time ?? '???', 0,10),
                ];
            },
        ];
    }

    function c_remote() {
        return ['vnd' => $this];
    }

    static function history($var = 'php', $in = []) {
        $ary = unserialize(SKY::w($var = "history_$var") ?: 'a:0:{}');
        if ($in) { # add to history
            $ary = (is_array($in) ? $in : [$in => $in]) + $ary;
            SKY::w($var, serialize(array_slice($ary, 0, 27, true)));
        }
        return $ary; # return history list
    }
}
