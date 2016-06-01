<?php
/**
 * Created by PhpStorm.
 * User: ec
 * Date: 03.03.2016
 * Time: 20:11
 */
namespace bpteam\phpOCR;

use \PHPUnit_Framework_TestCase;
use \ReflectionClass;

class GeneratorTest extends PHPUnit_Framework_TestCase
{

    /**
     * @param        $name
     * @param string $className
     * @return \ReflectionMethod
     */
    protected static function getMethod($name, $className = 'bpteam\phpOCR\Recognizer')
    {
        $class = new ReflectionClass($className);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * @param        $name
     * @param string $className
     * @return \ReflectionProperty
     */
    protected static function getProperty($name, $className = 'bpteam\phpOCR\phpOCR')
    {
        $class = new ReflectionClass($className);
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        return $property;
    }

    public function testGenerateTemplateChar()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $width = imagesx($img);
        $height = imagesy($img);
        $template = Recognizer::generateTemplateChar($img, $width, $height);
        $this->assertEquals(preg_match_all('%[01]%', $template), $width * $height);
    }

    /*public function testBlueNumber()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/blue_img1.png');
        Recognizer::setInfelicity(5);
        $imgs = Divider::byChar($img);
        $allChar = [];
        foreach ($imgs as $lines) {
            foreach ($lines as $words) {
                foreach ($words as $charValue) {
                    $allChar[] = $charValue;
                }
            }
        }
        $chars = Recognizer::findUniqueChar($allChar);
        $templates = [];
        foreach ($chars as $char) {
            $templates[] = Recognizer::generateTemplateChar($char);
        }
        $templates;
    }*/
}