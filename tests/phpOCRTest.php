<?php
/**
 * Created by PhpStorm.
 * User: ec
 * Date: 27.05.15
 * Time: 23:23
 * Project: gd2-php-ocr
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */

namespace bpteam\phpOCR;

use \PHPUnit_Framework_TestCase;
use \ReflectionClass;

class phpOCRTest extends PHPUnit_Framework_TestCase
{
    protected static function getMethod($name, $className = 'phpOCR')
    {
        $class = new ReflectionClass($className);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    protected static function getProperty($name, $className = 'phpOCR')
    {
        $class = new ReflectionClass($className);
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        return $property;
    }

    public function testOpenImage()
    {
        $imgFile = phpOCR::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $this->assertEquals('gd', get_resource_type($imgFile));
        $imgString = phpOCR::openImg(file_get_contents(__DIR__ . '/../template/test_img/olx1.png'));
        $this->assertEquals('gd', get_resource_type($imgString));
    }

}
