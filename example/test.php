<?php
/**
 * Created by JetBrains PhpStorm.
 * User: EC
 * Date: 15.05.13
 * Time: 17:48
 * Project: phpOCR
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */
use phpOCR\cOCR as cOCR;
$startMem=memory_get_usage()/1024;
$startTime=microtime(true);
require_once "../cOCR.php";
$file_name='../template/test_img/torg1.png';
$ex="png";
$img=cOCR::openImg($file_name);
//Исходное изображение
echo "<br>Step 0 src img<br>";
showPic($img,$ex,100);

//Перый шаг: Разрезаем на строчки
$img=cOCR::divideToLine(cOCR::$img);
echo "<br>Step 1 divideToLine<br>";
showPic($img,$ex,200);
//Второй шаг: Разрезаем на слова
$img=cOCR::divideToWord(cOCR::$img);
echo "<br>Step 2 divideToWord<br>";
showPic($img,$ex,300);

//Третий шаг: Разрезаем на символы
$img=cOCR::divideToChar(cOCR::$img);
echo "<br>Step 3 divide_char<br>";
showPic($img,$ex,400);
//Четвертый шаг: Генерация шаблона символа
$imgs=array();
echo "<br>Step 4 generateTemplateChar<br>";
foreach ($img as $line)
{
    foreach ($line as $word)
    {
        foreach ($word as $char)
        {
            $imgs[]=$char; //Собирается для следующего шага
            $tamplate_char=cOCR::generateTemplateChar($char);
        }
    }
}
/*
//Пятый шаг: Генерация шаблона в json сохранение и загрузка шаблона
echo "<br>Step 5 generateTemplateChar,saveTemplate,loadTemplate<br>";
$template=cOCR::generateTemplate(array('0','1','2','3','4','5','6','7','8','9','-'),$imgs);
$name="torg";
cOCR::saveTemplate($name,$template);*/
$name="torg";
$template=cOCR::loadTemplate($name);

//Проверка распознования
$file_name="../template/test_img/torg.".$ex;
//$file_name="../template/test_img/slando.".$ex;
$img=cOCR::openImg($file_name);
showPic($img,$ex,100);
//Шестой шаг: распознование изображения
echo "<br>Step 6 defineImg<br>";
$text=cOCR::defineImg(cOCR::$img,$template);
echo $text."<br>";

$file_name="../template/test_img/torg1.".$ex;
//$file_name="../template/test_img/slando1.".$ex;
$img=cOCR::openImg($file_name);
showPic($img,$ex,100);
//Перый шаг: Разрезаем на строчки
$img=cOCR::divideToLine(cOCR::$img);
echo "<br>Step 1 divideToLine<br>";
showPic($img,$ex,200);
//Второй шаг: Разрезаем на слова
$img=cOCR::divideToWord(cOCR::$img);
echo "<br>Step 2 divideToWord<br>";
showPic($img,$ex,300);
//Третий шаг: Разрезаем на символы
$img=cOCR::divideToChar(cOCR::$img);
echo "<br>Step 3 divide_char<br>";
showPic($img,$ex,400);
//Шестой шаг: распознование изображения
echo "<br>Step 6 defineImg<br>";
$text=cOCR::defineImg(cOCR::$img,$template);
echo $text."<br>";

$file_name="../template/test_img/torg2.".$ex;
//$file_name="../template/test_img/test7.".$ex;
$img=cOCR::openImg($file_name);
showPic($img,$ex,100);
//Шестой шаг: распознование изображения
echo "<br>Step 6 defineImg<br>";
$text=cOCR::defineImg(cOCR::$img,$template);
echo $text."<br>";

$file_name="../template/test_img/torg3.".$ex;
//$file_name="../template/test_img/test0.".$ex;
$img=cOCR::openImg($file_name);
showPic($img,$ex,100);
//Шестой шаг: распознование изображения
echo "<br>Step 6 defineImg<br>";
$text=cOCR::defineImg(cOCR::$img,$template);
echo $text."<br>";

$endMem=memory_get_usage()/1024;
$endTime=microtime(true);
echo "<br> mem start:".(int)$startMem." kb and:".(int)$endMem.'kb<br>';
echo "<br> time ".($endTime-$startTime)."ms";
//$data=cOCR::count_colors_indexs();
//arsort($data['index'],SORT_NUMERIC);
//var_dump($data['percent']);

function showPic($img,$ex,$prefix=0)
{
    chdir($_SERVER['DOCUMENT_ROOT']);
    $dirToSave='tmp/ocr/';
if(is_array($img))
{
    foreach ($img as $key => $value)
    {
        if(is_array($value)) showPic($value,$ex);
        else
        {
            $t=rand();
            $fh=fopen($dirToSave.'img'.$prefix.$t.$key.'.'.$ex,'w+');
            fwrite($fh,'');
            fclose($fh);
            imagepng($value,$dirToSave.'img'.$prefix.$t.$key.'.'.$ex,9);
            echo "<img src='/".$dirToSave."img".$prefix.$t.$key.".".$ex."'>||";
        }
    }
}
else
{
    $t=rand();
    $fh=fopen($dirToSave.'img'.$prefix.$t.'.'.$ex,'w+');
    fwrite($fh,'');
    fclose($fh);
    imagepng($img,$dirToSave.'img'.$prefix.$t.'.'.$ex,9);
    echo "<img src='/".$dirToSave."img".$prefix.$t.".".$ex."'>||";
}
    chdir(dirname(__FILE__));
}