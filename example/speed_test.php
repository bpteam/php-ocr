<?php
/**
 * Created by PhpStorm.
 * User: EC
 * Date: 04.04.14
 * Time: 17:14
 * Email: bpteam22@gmail.com
 */

use phpOCR\cOCR as cOCR;
$startMem=memory_get_usage()/1024;
$startTime=microtime(true);
require_once "../cOCR.php";
require_once "function.php";
$file_name='../template/test_img/torg1.png';
$ex="png";
cOCR::setInfelicity(10);
$img=cOCR::openImg($file_name);
//Source image
echo "<br>Step 0 src img<br>";
showPic($img,$ex,100);

//load template
$name="torg";
$template=cOCR::loadTemplate($name);

// OCR
echo "<br>defineImg<br>";
$text=cOCR::defineImg(cOCR::$img,$template);
echo $text."<br>";

$endMem=memory_get_usage()/1024;
$endTime=microtime(true);
echo "<br> mem start:".(int)$startMem." kb and:".(int)$endMem.' kb<br>';
echo "<br> time ".($endTime-$startTime)."ms";