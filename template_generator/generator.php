<?php
/**
 * Created by JetBrains PhpStorm.
 * User: EC
 * Date: 14.06.13
 * Time: 21:04
 * Project: phpOCR
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */

use phpOCR\cOCR as cOCR;
set_time_limit(600);
if(!isset($_POST['Submit1']))
{
chdir(dirname(__FILE__));
require_once '../cOCR.php';
chdir(dirname(__FILE__));
$template_name='torg';
for($i=1;file_exists('../template/test_img/'.$template_name.$i.'.png');$i++){
	$pic_file[] = '../template/test_img/'.$template_name.$i.'.png';
}
$char_array=array();
foreach ($pic_file as $key => $value)
{
    $img=cOCR::openImg($value);
    cOCR::setInfelicity(10);
    $tmp_img_array=cOCR::divideToChar($img);
    if(is_array($tmp_img_array)) $char_array=array_merge($char_array,$tmp_img_array);
}
$char=array();
    foreach($char_array as $value_img) foreach ($value_img as $value_line) foreach($value_line as $value_char) $char[]=$value_char;
    $char_array=cOCR::findUniqueChar($char);
?>
    <form method="POST" action="">
<?php
foreach ($char_array as $key => $value)
{
    $name='./tmp/'.rand().microtime(true).'.png';
    imagepng($value,$name);
    $tmp=cOCR::generateTemplateChar($value);
    ?>
    <img src="<?php echo $name;?>"/><input type='text' name="template_<?php echo $tmp;?>" value=''/><br/>
    <?php
}
?>
    <input name='Submit1' type='submit' value='Gen'>
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
