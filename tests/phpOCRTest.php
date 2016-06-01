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

    public function testOpenImage()
    {
        $imgFile = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $this->assertEquals('gd', get_resource_type($imgFile));
        $imgString = Recognizer::openImg(file_get_contents(__DIR__ . '/../template/test_img/olx1.png'));
        $this->assertEquals('gd', get_resource_type($imgString));
    }

    public function testGetColorsIndex()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $data = self::getMethod('getColorsIndex', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $this->assertArrayHasKey('index', $data);
        $this->isTrue(is_array($data['index']));
        $this->assertGreaterThanOrEqual(2, count($data['index']));
        $this->assertArrayHasKey('pix', $data);
        $this->isTrue(is_array($data['pix']));
        $this->assertGreaterThanOrEqual(2, count($data['pix']));
        $this->isTrue(isset($data['pix'][0][0]));
        $this->isTrue(isset($data['pix'][1][1]));
    }

    public function testGetBrightnessFromIndex()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $colors = self::getMethod('getColorsIndex', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $colors['index'] = array_keys($colors['index']);
        $data = self::getMethod('getBrightnessFromIndex', 'bpteam\phpOCR\Img')->invoke(null, $img, array_shift($colors['index']));
        $this->assertGreaterThan(100, $data);
    }

    public function testGetColorsIndexTextAndBackground()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $data = self::getMethod('getColorsIndexTextAndBackground', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $this->assertArrayHasKey('text', $data);
        $this->assertArrayHasKey('background', $data);
        $this->assertArrayHasKey('pix', $data);
        $this->isTrue(is_array($data['text']));
        $this->isTrue(is_array($data['pix']));
        $this->assertGreaterThanOrEqual(2, count($data['text']));
        $this->assertGreaterThanOrEqual(2, count($data['pix']));
        $this->isTrue(isset($data['pix'][0][0]));
        $this->isTrue(isset($data['pix'][1][1]));
    }

    public function testGetMidColorFromIndexes()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $data = self::getMethod('getColorsIndexTextAndBackground', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $data = self::getMethod('getMidColorFromIndexes', 'bpteam\phpOCR\Img')->invoke(null, $img, $data['text']);
        $this->assertArrayHasKey('red', $data);
        $this->assertArrayHasKey('green', $data);
        $this->assertArrayHasKey('blue', $data);
        $this->assertGreaterThanOrEqual(0, $data['red']);
        $this->assertGreaterThanOrEqual(0, $data['green']);
        $this->assertGreaterThanOrEqual(0, $data['blue']);
    }

    public function testChangeBackgroundBrightness()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $data = self::getMethod('changeBackgroundBrightness', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $this->assertEquals('gd', get_resource_type($data));
        $colors = self::getMethod('getColorsIndexTextAndBackground', 'bpteam\phpOCR\Divider')->invoke(null, $data);
        $color = imagecolorsforindex($img, $colors['background']);
        $this->assertArrayHasKey('red', $color);
        $this->assertArrayHasKey('green', $color);
        $this->assertArrayHasKey('blue', $color);
        $this->assertEquals(255, $color['red']);
        $this->assertEquals(255, $color['green']);
        $this->assertEquals(255, $color['blue']);

        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1_negative.png');
        $data = self::getMethod('changeBackgroundBrightness', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $this->assertEquals('gd', get_resource_type($data));
        $colors = self::getMethod('getColorsIndexTextAndBackground', 'bpteam\phpOCR\Divider')->invoke(null, $data);
        $color = imagecolorsforindex($img, $colors['background']);
        $this->assertArrayHasKey('red', $color);
        $this->assertArrayHasKey('green', $color);
        $this->assertArrayHasKey('blue', $color);
        $this->assertGreaterThanOrEqual(230, $color['red']);
        $this->assertGreaterThanOrEqual(230, $color['green']);
        $this->assertGreaterThanOrEqual(230, $color['blue']);
    }

    public function testBoldText()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $boldImg = self::getMethod('boldText', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $width = imagesx($img);
        $height = imagesy($img);
        $colorBoldImg = $colorImg = 0;
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $colorImg += array_sum(imagecolorsforindex($img, imagecolorat($img, $x, $y)));
                $colorBoldImg += array_sum(imagecolorsforindex($boldImg, imagecolorat($boldImg, $x, $y)));
            }
        }
        $this->assertGreaterThan($colorBoldImg, $colorImg);
    }

    public function testCoordinatesImg()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $coordinates = self::getMethod('coordinatesImg', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $this->assertArrayHasKey('start', $coordinates);
        $this->assertArrayHasKey('end', $coordinates);
        $this->assertCount(2, $coordinates['start']);
        $this->assertCount(2, $coordinates['end']);
        foreach ($coordinates['start'] as $key => $start) {
            $end = $coordinates['end'][$key];
            $this->assertNotEmpty($end);
            $this->assertGreaterThan($start, $end);
        }
    }

    public function testDivideByLine()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $lines = self::getMethod('byLine', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $this->assertCount(2, $lines);
        foreach ($lines as $line) {
            $this->assertEquals('gd', get_resource_type($line));
        }
    }

    public function testDivideByWord()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $lines = self::getMethod('byWord', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $this->assertCount(2, $lines);
        foreach ($lines as $line) {
            foreach ($line as $word) {
                $this->assertEquals('gd', get_resource_type($word));
            }
        }
    }

    public function testDivideByChar()
    {
        $img = Recognizer::openImg(__DIR__ . '/../template/test_img/olx1.png');
        $lines = self::getMethod('byChar', 'bpteam\phpOCR\Divider')->invoke(null, $img);
        $this->assertCount(2, $lines);
        foreach ($lines as $line) {
            foreach ($line as $words) {
                foreach ($words as $char) {
                    $this->assertEquals('gd', get_resource_type($char));
                }
            }
        }
    }

    public function testResizeImg()
    {
        $img = imagecreatetruecolor(21, 33);
        $resizeImg = self::getMethod('resize', 'bpteam\phpOCR\Img')->invoke(null, $img, 7, 11);
        $this->assertEquals('gd', get_resource_type($resizeImg));
        $this->assertEquals(7, imagesx($resizeImg));
        $this->assertEquals(11, imagesy($resizeImg));
    }

    public function testBlueBackground()
    {
        Recognizer::setInfelicity(1);
        $text = Recognizer::read(__DIR__ . '/../template/test_img/blue_img1.png', Recognizer::loadTemplate('blue_background'));
        $this->assertEquals('+7(91 8) 432-57-00',$text);
    }

}
