<?php
/**
 * Created by JetBrains PhpStorm.
 * User: EC
 * Date: 14.06.13
 * Time: 21:04
 * Project: php_ocr
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */

use php_ocr\c_ocr\c_ocr as c_ocr;
set_time_limit(600);
if(!isset($_POST['Submit1']))
{
chdir(dirname(__FILE__));
require_once '../c_ocr.php';
chdir(dirname(__FILE__));
/*
$pic_file[]='../template/test_img/slando.png';
$pic_file[]='../template/test_img/slando1.png';
*/
   // /*
$template_name='torg';
$pic_file[]='../template/test_img/torg.png';
$pic_file[]='../template/test_img/torg1.png';
$pic_file[]='../template/test_img/torg2.png';
$pic_file[]='../template/test_img/torg3.png';
$pic_file[]='../template/test_img/torg4.png';
$pic_file[]='../template/test_img/torg5.png';
$pic_file[]='../template/test_img/torg6.png';
  // */
$char_array=array();
foreach ($pic_file as $key => $value)
{
    $img=c_ocr::open_img($value);
    $tmp_img_array=c_ocr::divide_to_char($img);
    if(is_array($tmp_img_array)) $char_array=array_merge($char_array,$tmp_img_array);
}
$char=array();
    foreach($char_array as $value_img) foreach ($value_img as $value_line) foreach($value_line as $value_char) $char[]=$value_char;
    $char_array=c_ocr::find_unique_char($char);
?>
    Введите символы на изображениях в текстовые поля(одинаковые можно не заполнять):<br/>
    <form method="POST" action="">
<?php
foreach ($char_array as $key => $value)
{
    $name='./tmp/'.rand().microtime(true).'.png';
    imagepng($value,$name);
    $tmp=c_ocr::generate_template_char($value);
    ?>
    <img src="<?php echo $name;?>"/><input type='text' name="template_<?php echo $tmp;?>" value=''/><br/>
    <?php
}
?>
    <input name='Submit1' type='submit' value='Генерировать'>
    </form>
    <?php
}
else
{
    $chars=array();
    foreach ($_POST as $key => $value)
    {
        if(preg_match('#template_(?<template>[01]+)#ims',$key,$match) && $value!='')
        {
            $chars[$value]=$match['template'];
        }
    }
    echo $json=json_encode($chars,JSON_FORCE_OBJECT);
}
