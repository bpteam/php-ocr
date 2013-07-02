<?php
/**
 * Created by JetBrains PhpStorm.
 * User: EC
 * Date: 15.05.13
 * Time: 17:48
 * Project: php_ocr
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */
use php_ocr\c_ocr\c_ocr as c_ocr;
$startMem=memory_get_usage()/1024;
$startTime=microtime(true);
require_once "../c_ocr.php";
$file_name='../template/test_img/torg.png';
$ex="png";
$img=c_ocr::open_img($file_name);
//Исходное изображение
echo "<br>Step 0 src img<br>";
show_pic($img,$ex,100);

//Перый шаг: Разрезаем на строчки
$img=c_ocr::divide_to_line(c_ocr::$img);
echo "<br>Step 1 divide_to_line<br>";
show_pic($img,$ex,200);
//Второй шаг: Разрезаем на слова
$img=c_ocr::divide_to_word(c_ocr::$img);
echo "<br>Step 2 divide_to_word<br>";
show_pic($img,$ex,300);

//Третий шаг: Разрезаем на символы
$img=c_ocr::divide_to_char(c_ocr::$img);
echo "<br>Step 3 divide_char<br>";
show_pic($img,$ex,400);
//Четвертый шаг: Генерация шаблона символа
$imgs=array();
echo "<br>Step 4 generate_template_char<br>";
foreach ($img as $line)
{
    foreach ($line as $word)
    {
        foreach ($word as $char)
        {
            $imgs[]=$char; //Собирается для следующего шага
            $tamplate_char=c_ocr::generate_template_char($char);
        }
    }
}
/*
//Пятый шаг: Генерация шаблона в json сохранение и загрузка шаблона
echo "<br>Step 5 generate_template_char,save_template,load_template<br>";
$template=c_ocr::generate_template(array('0','1','2','3','4','5','6','7','8','9','-'),$imgs);
$name="torg";
c_ocr::save_template($name,$template);*/
$name="torg";
$template=c_ocr::load_template($name);

//Проверка распознования
$file_name="../template/test_img/torg.".$ex;
//$file_name="../template/test_img/slando.".$ex;
$img=c_ocr::open_img($file_name);
show_pic($img,$ex,100);
//Шестой шаг: распознование изображения
echo "<br>Step 6 define_img<br>";
$text=c_ocr::define_img(c_ocr::$img,$template);
echo $text."<br>";

$file_name="../template/test_img/torg1.".$ex;
//$file_name="../template/test_img/slando1.".$ex;
$img=c_ocr::open_img($file_name);
show_pic($img,$ex,100);
//Перый шаг: Разрезаем на строчки
$img=c_ocr::divide_to_line(c_ocr::$img);
echo "<br>Step 1 divide_to_line<br>";
show_pic($img,$ex,200);
//Второй шаг: Разрезаем на слова
$img=c_ocr::divide_to_word(c_ocr::$img);
echo "<br>Step 2 divide_to_word<br>";
show_pic($img,$ex,300);
//Третий шаг: Разрезаем на символы
$img=c_ocr::divide_to_char(c_ocr::$img);
echo "<br>Step 3 divide_char<br>";
show_pic($img,$ex,400);
//Шестой шаг: распознование изображения
echo "<br>Step 6 define_img<br>";
$text=c_ocr::define_img(c_ocr::$img,$template);
echo $text."<br>";

$file_name="../template/test_img/torg2.".$ex;
//$file_name="../template/test_img/test7.".$ex;
$img=c_ocr::open_img($file_name);
show_pic($img,$ex,100);
//Шестой шаг: распознование изображения
echo "<br>Step 6 define_img<br>";
$text=c_ocr::define_img(c_ocr::$img,$template);
echo $text."<br>";

$file_name="../template/test_img/torg3.".$ex;
//$file_name="../template/test_img/test0.".$ex;
$img=c_ocr::open_img($file_name);
show_pic($img,$ex,100);
//Шестой шаг: распознование изображения
echo "<br>Step 6 define_img<br>";
$text=c_ocr::define_img(c_ocr::$img,$template);
echo $text."<br>";

$endMem=memory_get_usage()/1024;
$endTime=microtime(true);
echo "<br> Памяить старт:".(int)$startMem."кб конец:".(int)$endMem.'кб<br>';
echo "<br> Время выполнения ".($endTime-$startTime)."мс";
//$data=c_ocr::count_colors_indexs();
//arsort($data['index'],SORT_NUMERIC);
//var_dump($data['percent']);

function show_pic($img,$ex,$prefix=0)
{
    chdir($_SERVER['DOCUMENT_ROOT']);
    $dir_to_save='tmp/ocr/';
if(is_array($img))
{
    foreach ($img as $key => $value)
    {
        if(is_array($value)) show_pic($value,$ex);
        else
        {
            $t=rand();
            $fh=fopen($dir_to_save.'img'.$prefix.$t.$key.'.'.$ex,'w+');
            fwrite($fh,'');
            fclose($fh);
            imagepng($value,$dir_to_save.'img'.$prefix.$t.$key.'.'.$ex,9);
            echo "<img src='/".$dir_to_save."img".$prefix.$t.$key.".".$ex."'>||";
        }
    }
}
else
{
    $t=rand();
    $fh=fopen($dir_to_save.'img'.$prefix.$t.'.'.$ex,'w+');
    fwrite($fh,'');
    fclose($fh);
    imagepng($img,$dir_to_save.'img'.$prefix.$t.'.'.$ex,9);
    echo "<img src='/".$dir_to_save."img".$prefix.$t.".".$ex."'>||";
}
    chdir(dirname(__FILE__));
}