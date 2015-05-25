<?php
/**
 * Created by JetBrains PhpStorm.
 * User: EC
 * Date: 14.06.13
 * Time: 21:04
 * Project: phpOCR
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */
require_once __DIR__ . '/../loader.php';

use bpteam\phpOCR\phpOCR;

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);

if (!isset($_POST['Submit1'])) {
    $template_name = 'speed_test';
    $pic_file = [];
    for ($i = 1; file_exists(__DIR__ . '/../template/test_img/' . $template_name . $i . '.png'); $i++) {
        $pic_file[] = __DIR__ . '/../template/test_img/' . $template_name . $i . '.png';
    }
    $char_array = array();
    foreach ($pic_file as $key => $value) {
        $img = phpOCR::openImg($value);
        phpOCR::setInfelicity(10);
        $tmp_img_array = phpOCR::divideToChar($img);
        if (is_array($tmp_img_array)) {
            $char_array = array_merge($char_array, $tmp_img_array);
        }
    }
    $char = array();
    foreach ($char_array as $value_img) {
        foreach ($value_img as $value_line) {
            foreach ($value_line as $value_char) {
                $char[] = $value_char;
            }
        }
    }
    $char_array = phpOCR::findUniqueChar($char);
    ?>
    <form method="POST" action="">
        <?php
        foreach ($char_array as $key => $value) {
            $name = './tmp/' . rand() . microtime(true) . '.png';
            imagepng($value, $name);
            $tmp = phpOCR::generateTemplateChar($value);
            ?>
            <img src="<?= $name; ?>"/><label>
                <input type='text' name="template_<?= $key; ?>" value=''/>
            </label><br/>
            <input type="hidden" name="pattern_<?= $key; ?>" value="<?= $tmp ?>">
        <?php
        }
        ?>
        <input name='Submit1' type='submit' value='Gen'>
    </form>
<?php
} else {
    $chars = array();
    foreach ($_POST as $key => $value) {
        if (preg_match('%template_(?<template>[^"]+)%ims', $key, $match) && $value != '') {
            $chars[$_POST['pattern_' . $match['template']]] = $value;
        }
    }
    echo $json = json_encode($chars, JSON_FORCE_OBJECT);
}
