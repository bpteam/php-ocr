<?php
/**
 * Created by JetBrains PhpStorm.
 * User: EC
 * Date: 15.05.13
 * Time: 17:06
 * Project: php_ocr
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */
use php_ocr\c_ocr\c_ocr as c_ocr;
require_once "../c_ocr.php";
c_ocr::open_image("../template/test_img/test.png");
