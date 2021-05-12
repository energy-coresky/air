<?php

# For Licence and Disclaimer of this code, see http://coresky.net/license

class File_t extends MVC_BASE
{
    private $img_t = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
    ];

    private $img_f = [
        'jpg' => 'imagecreatefromjpeg',
        'png' => 'imagecreatefrompng',
        'gif' => 'imagecreatefromgif',
    ];

    private $ext_in;
    private $ext_out;
    static $me = false;

    static function read_f($id, $is_download = false) {
        self::$me or self::$me = new File_t;
        if (!$row = self::$me->t_file->one((int)$id))
            return;
        $ary = explode(' ', $row->type);
        if ($is_download && false === ($size = @filesize(DIR_U . "$id.$ary[1]")))
            return;
        while (ob_get_level())
            ob_end_clean();
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        $is_php = 'text/x-php' == $ary[0];
        if ('text/' == substr($ary[0], 0, 5))
            $ary[0] = $is_php ? "text/html; charset=$ary[2]" : "$ary[0]; charset=$ary[2]";
        header('Content-Type: ' . ($is_download ? 'application/octet-stream' : $ary[0]));
        if ($is_download) {
            header(sprintf('Content-Disposition: attachment; filename="%s"', $row->name));
            header("Content-Length: $size");
        }
        if ($is_php) {
            echo css(['~/sky.css']);
            echo Display::php(file_get_contents(DIR_U . "$id.$ary[1]"));
        } else {
            readfile(DIR_U . "$id.$ary[1]");
        }
        throw new Stop;
    }

    function remove($rule) {
        $cnt = 0;
        if ($tmp = $this->t_file->all($rule)) {
            $ids = [];
            foreach ($tmp as $id => $one) {
                $ary = explode(' ', $one->type);
                if (@unlink(DIR_U . "$id.$ary[1]")) {
                    $ids[] = $id;
                    $cnt++;
                }
            }
            if ($ids) {
                $d = $this->t_file->delete(qp(' id in ($@)', $ids));
                $d == $cnt or $cnt--;
            }
        }
        return $cnt == count($tmp) ? $cnt : false;
    }


    static function read_len($fn, $len = 1e4) {
        if (!$handle = fopen($fn, "rb"))
            throw new Err("Cannot open file `$fn` for reading");
        $bin = fread($handle, $len);
        fclose($handle);
        return mb_strcut($bin, 0, $len);
    }

    static function type($fn, $real_name) {
        self::$me or self::$me = new File_t;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($fn);
        $info = pathinfo($real_name);
        $ext = isset($info['extension']) ? $info['extension'] : '?';
        $ap = " $ext";
        $out = ['img' => 0];
        if ('image/' == substr($mime, 0, 6)) {
            $data = getimagesize($fn);
            $out += [
                'width' => $data[0],
                'height' => $data[1],
            ];
            if (in_array($data[2], array_keys(self::$me->img_t)) && $data[0] && $data[1]) {
                $out['img'] = 1;
                $ext = self::$me->img_t[$data[2]];
                $ap = " $ext";
            }
            $ap .= " $data[0] $data[1]";
        } elseif ('text/' == substr($mime, 0, 5)) {
            $ap .= " " . Rare::enc_detect(File_t::read_len($fn));
        }
        return [$mime . $ap, $ext, $out];
    }

    static function tmp() {
        global $user;
        self::$me or self::$me = new File_t;
        self::$me->remove(qp('obj is null and dt_c + interval 1 day < now()')); # delete unfinished files

        if (!isset($_FILES['file'])) {
            echo 'File don\'t transfered';
            return;
        }
        if ($_FILES['file']['error']) {
            echo $_FILES['file']['error'];
            return;
        }
        list ($mime, $ext, $out) = File_t::type($tmp = $_FILES['file']['tmp_name'], $_FILES['file']['name']);
        $id = self::$me->t_file->insert([
            '!dt_c' => 'now()',
            '.c_user_id' => $user->id,
            'name' => $_FILES['file']['name'],
            'size' => $_FILES['file']['size'],
            'type' => $_FILES['file']['type'] = $mime,
        ]);
        if (move_uploaded_file($tmp, DIR_U . "$id.$ext")) {
            json($out + ['id' => $id]);
            return;
        }
        self::$me->t_file->delete($id);
        echo 'File don\'t moved';
    }

    static function crop($id, $x0, $y0, $x1, $y1, $szx, $szy) {
        self::$me or self::$me = new File_t;
        if (!$row = self::$me->t_file->one(qp(' id=$.', $id)))
            return false;
        $ary = explode(' ', $row->type);
        $func = self::$me->img_f[$ary[1]];
        $src = $func($fn = DIR_U . "$id.$ary[1]");
        $dst = imagecreatetruecolor($szx, $szy);
        imagecopyresampled($dst, $src, 0, 0, $x0, $y0, $szx, $szy, $x1 - $x0, $y1 - $y0);
        imagejpeg($dst, $fn, 80);
        //self::$me->t_file->update();
    }

    static function delete_one() {
        self::$me or self::$me = new File_t;
        $cnt = self::$me->remove(qp(' id=$.', $_POST['id']));
        echo 1 == $cnt ? 'ok' : '-';
    }

    function img_load($fn) {
        list($x, $y, $type) = getimagesize($fn);
        if (!isset($this->img_t[$type]))
            return false;
        $this->ext_in = $this->img_t[$type];
        $func = $this->img_f[$this->ext_in];
        return [$x, $y, $func($fn)];
    }

    function resize($to_width, $to_height) {
        $width = $x;
        $height = $y;
        $ratio = $x / $y;
        if ($ratio > $to_width / $to_height) {
            $x = $to_width;
            $y = floor($x / $ratio);
        } else {
            $y = $to_height;
            $x = floor($y * $ratio);
        }
        $out = imagecreatetruecolor($x, $y);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $x, $y, $width, $height);
        imagejpeg($out, WWW . "img/task/$fn_out", 80);
    }

    function save() {
    }

    function listing() {
    }
}
