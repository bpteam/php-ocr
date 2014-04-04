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
cOCR::setInfelicity(15);
$img=cOCR::openImg($file_name);
//Исходное изображение
echo "<br>Step 0 src img<br>";
showPic($img,$ex,100);

//1 шаг: Разрезаем на строчки
$img=cOCR::divideToLine(cOCR::$img);
echo "<br>Step 1 divideToLine<br>";
showPic($img,$ex,200);

//2 шаг: Разрезаем на слова
$img=cOCR::divideToWord(cOCR::$img);
echo "<br>Step 2 divideToWord<br>";
showPic($img,$ex,300);

//3 шаг: Разрезаем на символы
$img=cOCR::divideToChar(cOCR::$img);
echo "<br>Step 3 divide_char<br>";
showPic($img,$ex,400);

//Подключаем шаблон
$name="torg";
$template=cOCR::loadTemplate($name);

//4 шаг: распознование изображения
echo "<br>Step 4 defineImg<br>";
$text=cOCR::defineImg(cOCR::$img,$template);
echo $text."<br>";

$endMem=memory_get_usage()/1024;
$endTime=microtime(true);
echo "<br> mem start:".(int)$startMem." kb and:".(int)$endMem.'kb<br>';
echo "<br> time ".($endTime-$startTime)."ms";