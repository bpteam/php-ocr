<?php
require_once __DIR__ . '/vendor/autoload.php';
use bpteam\phpOCR\Recognizer;
use bpteam\phpOCR\Img;

$file_name = __DIR__ . '/template/test_img/olx1.png';
$ex = 'png';
Recognizer::setInfelicity(10);
$img = Recognizer::openImg($file_name);
//Source image
echo "<br>Step 0 src img<br>";
Img::show($img,$ex,100);

//load template
$name = 'olx';
Recognizer::setTemplateDir(__DIR__ . '/template/');
$template = Recognizer::loadTemplate($name);

// OCR
echo "<br>defineImg<br>";
$text = Recognizer::read($file_name, $template);
echo $text."<br>";