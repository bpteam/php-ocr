<?php
/**
 * Created by PhpStorm.
 * User: ec
 * Date: 03.06.15
 * Time: 11:42
 * Project: gd2-php-ocr
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */

namespace bpteam\phpOCR;;


class Img {

    public static function open($img)
    {
        $format = false;
        if (strlen($img) < 255 && file_exists($img)) {
            $info = getimagesize($img);
            $format = $info[2];
        }

        switch ($format) {
            case IMAGETYPE_PNG :
                $img = self::openPNG($img);
                break;
            case IMAGETYPE_JPEG :
                $img = imagecreatefromjpeg($img);
                break;
            case IMAGETYPE_GIF :
                $img = imagecreatefromgif($img);
                break;
            default:
                $img = self::openUnknown($img);
                break;
        }
        return $img;
    }

    protected static function openPNG($file)
    {
        $tmpImg2 = imagecreatefrompng($file);
        $wight = imagesx($tmpImg2);
        $height = imagesy($tmpImg2);
        $tmpImg = imagecreatetruecolor($wight, $height);
        $white = imagecolorallocate($tmpImg, 255, 255, 255);
        imagefill($tmpImg, 0, 0, $white);
        imagecopy($tmpImg, $tmpImg2, 0, 0, 0, 0, $wight, $height);
        imagedestroy($tmpImg2);
        return $tmpImg;
    }

    protected static function openUnknown($img)
    {
        if ($tmpImg2 = imagecreatefromstring($img)) {
            $wight = imagesx($tmpImg2);
            $height = imagesy($tmpImg2);
            $tmpImg = imagecreatetruecolor($wight, $height);
            $white = imagecolorallocate($tmpImg, 255, 255, 255);
            imagefill($tmpImg, 0, 0, $white);
            imagecopy($tmpImg, $tmpImg2, 0, 0, 0, 0, $wight, $height);
            imagedestroy($tmpImg2);
        } else {
            $tmpImg = imagecreatefromgd($img);
        }
        return $tmpImg;
    }

    /**
     * Прапорциональное изменение размера изображения
     * @param resource $img   изображение
     * @param int      $width ширина
     * @param int      $height высота
     * @return resource
     */
    public static function resize($img, $width, $height)
    {
        $sizeX = imagesx($img);
        $sizeY = imagesy($img);
        $newImg = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($newImg, Recognizer::$background['red'], Recognizer::$background['green'], Recognizer::$background['blue']);
        imagefill($newImg, 0, 0, $white);
        if ($sizeX < $sizeY) {
            $width = $sizeX * ($height / $sizeY);
        } else {
            $height = $sizeY * ($width / $sizeX);
        }
        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $width, $height, $sizeX, $sizeY);

        return $newImg;
    }

    public static function show($img, $extension = 'png', $prefix = 0, $dirToSave = false)
    {
        //return false;
        $home = $_SERVER["DOCUMENT_ROOT"] . '/';
        $subDir = str_replace($home, '', __DIR__);
        if (!$dirToSave)
            $dirToSave = '/' . $subDir .'/example/tmp/';
        if (is_array($img)) {
            foreach ($img as $key => $value) {
                self::show($value, $extension);
            }
        } else {
            $random = rand();
            $picName = $dirToSave . 'img' . $prefix . $random . '.' . $extension;
            $fileHead = fopen( $home . $picName, 'w+');
            fwrite($fileHead, '');
            fclose($fileHead);
            imagepng($img, $home . $picName, 9);
            echo "<img src='" . $picName . "'></br>\n";
        }
    }

    /**
     * Подсчитывает средний цвет из массива индексов
     * @param resource $img
     * @param array    $arrayIndexes
     * @return array
     */
    public static function getMidColorFromIndexes($img, $arrayIndexes)
    {
        $midColor['red'] = 0;
        $midColor['green'] = 0;
        $midColor['blue'] = 0;
        foreach ($arrayIndexes as $key => $value) {
            $color = imagecolorsforindex($img, $key);
            $midColor['red'] += $color['red'];
            $midColor['green'] += $color['green'];
            $midColor['blue'] += $color['blue'];
        }
        $count = count($arrayIndexes);
        $midColor['red'] /= $count;
        $midColor['green'] /= $count;
        $midColor['blue'] /= $count;
        return $midColor;
    }

    /**
     * Вычисляем яркость цвета по его индексу
     * @param resource $img
     * @param int      $colorIndex
     * @return int
     */
    public static function getBrightnessFromIndex($img, $colorIndex)
    {
        $color = imagecolorsforindex($img, $colorIndex);

        return ($color['red'] + $color['green'] + $color['blue']) / 3;
    }
}