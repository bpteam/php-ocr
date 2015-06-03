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
set_time_limit(600);

require_once __DIR__ . '/../bootstrap.php';

use bpteam\phpOCR\Img;
use bpteam\phpOCR\Recognizer;

$startMem = memory_get_usage()/1024;
$startTime = microtime(true);
$file_name = __DIR__ . '/../template/test_img/olx1.png';
$ex = 'png';
Recognizer::setInfelicity(10);
$img = Img::open($file_name);
//Source image
echo "<br>Step 0 src img<br>";
Img::show($img,$ex,100);

//load template
$name = 'olx';
Recognizer::setTemplateDir(__DIR__ . '/../template/');
$template = Recognizer::loadTemplate($name);

// OCR
echo "<br>defineImg<br>";
$text = Recognizer::read($file_name, $template);
echo $text."<br>";

$endMem = memory_get_usage()/1024;
$endTime = microtime(true);
echo "<br> mem start:".(int)$startMem." kb and:".(int)$endMem.' kb<br>';
echo "<br> time ".($endTime-$startTime)."ms";