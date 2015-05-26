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
    $templateName = 'olx';
    $picFiles = [];
    $maxTemplate = 1000;
    for ($i = 1; file_exists(__DIR__ . '/../template/test_img/' . $templateName . $i . '.png') && $i <= $maxTemplate; $i++) {
        $picFiles[] = __DIR__ . '/../template/test_img/' . $templateName . $i . '.png';
    }
    $chars = [];
    foreach ($picFiles as $key => $fileName) {
        $img = phpOCR::openImg($fileName);
        phpOCR::showImg($img);
        phpOCR::setInfelicity(10);
        $imgs = phpOCR::divideToChar($img);
        if (is_array($imgs)) {
            $chars = array_merge($chars, $imgs);
        }
    }
    $allChar = [];
    foreach ($chars as $lines) {
        foreach ($lines as $words) {
            foreach ($words as $charValue) {
                $allChar[] = $charValue;
            }
        }
    }
    $chars = phpOCR::findUniqueChar($allChar);
    ?>
    <form method="POST" action="">
        <?
        foreach ($chars as $key => $fileName) {
            $name = './tmp/' . rand() . microtime(true) . '.png';
            imagepng($fileName, $name);
            $tmp = phpOCR::generateTemplateChar($fileName);
            ?>
            <img src="<?= $name; ?>"/><label>
                <input type='text' name="template_<?= $key; ?>" value=''/>
            </label><br/>
            <input type="hidden" name="pattern_<?= $key; ?>" value="<?= $tmp ?>">
        <?}?>
        <input name='Submit1' type='submit' value='Gen'>
    </form>
<?
} else {
    $chars = [];
    foreach ($_POST as $key => $fileName) {
        if (preg_match('%template_(?<template>[^"]+)%ims', $key, $match) && $fileName != '') {
            $chars[$_POST['pattern_' . $match['template']]] = $fileName;
        }
    }
    echo $json = json_encode($chars, JSON_FORCE_OBJECT);
}