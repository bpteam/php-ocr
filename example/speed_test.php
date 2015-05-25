<?php
/**
 * Created by PhpStorm.
 * User: EC
 * Date: 04.04.14
 * Time: 17:14
 * Email: bpteam22@gmail.com
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../loader.php';
require_once __DIR__ . '/function.php';

use bpteam\phpOCR\phpOCR;

$startMem = memory_get_usage()/1024;
$startTime = microtime(true);
$file_name = __DIR__ . '/../template/test_img/speed_test1.png';
$ex = 'png';
phpOCR::setInfelicity(10);
$img = phpOCR::openImg($file_name);
//Source image
echo "<br>Step 0 src img<br>";
showPic($img,$ex,100);

//load template
$name = 'speed_test';
phpOCR::setTemplateDir(__DIR__ . '/../template/');
$template = phpOCR::loadTemplate($name);

// OCR
echo "<br>defineImg<br>";
$text = phpOCR::defineImg($file_name,$template);
echo $text."<br>";

$endMem = memory_get_usage()/1024;
$endTime = microtime(true);
echo "<br> mem start:".(int)$startMem." kb and:".(int)$endMem.' kb<br>';
echo "<br> time ".($endTime-$startTime)."ms";