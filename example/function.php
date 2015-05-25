<?php
/**
 * Created by PhpStorm.
 * User: EC
 * Date: 04.04.14
 * Time: 17:19
 * Email: bpteam22@gmail.com
 */
/**
 * @param     $img
 * @param     $extension
 * @param int $prefix
 */
function showPic($img, $extension, $prefix = 0)
{
    $dirToSave = 'tmp/';
    if (is_array($img)) {
        foreach ($img as $key => $value) {
            if (is_array($value)) {
                showPic($value, $extension);
            } else {
                $t = rand();
                $fh = fopen($dirToSave . 'img' . $prefix . $t . $key . '.' . $extension, 'w+');
                fwrite($fh, '');
                fclose($fh);
                imagepng($value, $dirToSave . 'img' . $prefix . $t . $key . '.' . $extension, 9);
                echo "<img src='" . $dirToSave . "img" . $prefix . $t . $key . "." . $extension . "'>||";
            }
        }
    } else {
        $t = rand();
        $fh = fopen($dirToSave . 'img' . $prefix . $t . '.' . $extension, 'w+');
        fwrite($fh, '');
        fclose($fh);
        imagepng($img, $dirToSave . 'img' . $prefix . $t . '.' . $extension, 9);
        echo "<img src='" . $dirToSave . "img" . $prefix . $t . "." . $extension . "'>||";
    }
}