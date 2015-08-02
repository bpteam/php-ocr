<?php
/**
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */
namespace bpteam\phpOCR;

/**
 * Class cOCR
 * Класс для распознования символов по шаблону
 * @package phpOCR\cOCR
 */
class Divider
{

    protected static $colorDiff = 50;

    public static $boldSize = 1;

    const WIDTH = 1;

    const HEIGHT = 2;

    /**
     * Добавляет к краям изображения количество пикселей для удобного разрезания
     * @var int
     */
    protected static $sizeBorder = 2;

    /**
     * Подсчитываем количество цветов в изображении и их долю в палитре
     * Сбор индексов цвета каждого пикселя
     * @param resource $img
     * @return array
     */
    protected static function getColorsIndex($img)
    {
        $colorsIndex = [];
        $width = imagesx($img);
        $height = imagesy($img);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $pixelIndex = imagecolorat($img, $x, $y);
                $colorsIndex['pix'][$x][$y] = $pixelIndex;
                if (isset($colorsIndex['index'][$pixelIndex])) {
                    $colorsIndex['index'][$pixelIndex]++;
                } else {
                    $colorsIndex['index'][$pixelIndex] = 1;
                }
            }
        }
        arsort($colorsIndex['index'], SORT_NUMERIC);

        return $colorsIndex;
    }

    /**
     * Получаем индексы цветов текста и индекс цвета фона
     * @param resource $img
     * @return array
     */
    public static function getColorsIndexTextAndBackground($img)
    {
        $countColors = self::getColorsIndex($img);
        $countColors['index'] = array_keys($countColors['index']);
        $backgroundIndex = array_shift($countColors['index']);
        $indexes['background'] = $backgroundIndex;
        $indexes['pix'] = $countColors['pix'];
        // Собираем все цвета отличные от фона
        $backgroundBrightness = Img::getBrightnessFromIndex($img, $backgroundIndex);
        $backgroundBrightness -= $backgroundBrightness * 0.2;
        foreach ($countColors['index'] as $colorKey => $colorValue) {
            $colorBrightness = Img::getBrightnessFromIndex($img, $colorValue);
            if ($backgroundBrightness < ($colorBrightness + self::$colorDiff))
                unset($countColors['index'][$colorKey]);
        }
        $indexes['text'] = $countColors['index'];

        return $indexes;
    }

    /**
     * Вычисления цвета фона изображения с текстом, Фон светлее текста или наоборот, если темнее то цвета инвертируются
     * @param resource $img
     * @return resource
     */
    public static function changeBackgroundBrightness($img)
    {
        $colorIndexes = self::getColorsIndexTextAndBackground($img);
        $brightnessBackground = Img::getBrightnessFromIndex($img, $colorIndexes['background']);
        if ($colorIndexes['text']) {
            $midColor = Img::getMidColorFromIndexes($img, $colorIndexes['text']);
            $brightnessText = ($midColor['red'] + $midColor['green'] + $midColor['blue']) / 3;
        } else {
            $brightnessText = 255;
        }

        if ($brightnessBackground < $brightnessText) {
            imagefilter($img, IMG_FILTER_NEGATE);
        }

        return $img;
    }

    public static function addBorder($img, $red = null, $green = null, $blue = null)
    {
        $red = is_int($red) ? $red : Recognizer::$background['red'];
        $green = is_int($green) ? $green : Recognizer::$background['green'];
        $blue = is_int($blue) ? $blue : Recognizer::$background['blue'];
        $imgWidth = imagesx($img);
        $imgHeight = imagesy($img);
        $imgSrc = imagecreatetruecolor($imgWidth + (self::$sizeBorder * 2), $imgHeight + (self::$sizeBorder * 2));
        $borderColor = imagecolorallocate($imgSrc, $red, $green, $blue);
        imagefill($imgSrc, 0, 0, $borderColor);
        imagecopy($imgSrc, $img, self::$sizeBorder, self::$sizeBorder, 0, 0, $imgWidth, $imgHeight);
        return $imgSrc;
    }

    public static function getSizeBorder()
    {
        return self::$sizeBorder;
    }

    public static function setSizeBotder($val)
    {
        self::$sizeBorder = $val;
    }

    /**
     * Разбивает рисунок на строки с текстом
     * @param resource $img
     * @return array
     */
    protected static function byLine($img)
    {
        $imgWidth = imagesx($img);
        $imgHeight = imagesy($img);
        $coordinates = self::coordinatesImg($img);
        $topLine = $coordinates['start'];
        $bottomLine = $coordinates['end'];
        // Ищем самую низкую строку для захвата заглавных букв
        $hMin = 99999;
        foreach ($topLine as $key => $value) {
            $hLine = $bottomLine[$key] - $topLine[$key];
            if ($hMin > $hLine) {
                $hMin = $hLine;
            }
        }
        // Увеличим все строки на пятую часть самой маленькой для захвата заглавных букв хвостов букв у и т.д.
        $changeSize = 0.2 * $hMin;
        foreach ($topLine as $key => $value) {
            if (($topLine[$key] - $changeSize) >= 0) {
                $topLine[$key] -= $changeSize;
            }
            if (($bottomLine[$key] + $changeSize) <= ($imgHeight - 1)) {
                $bottomLine[$key] += $changeSize;
            }
        }
        // Нарезаем на полоски с текстом
        $imgLine = [];
        foreach ($topLine as $key => $value) {
            $width = $imgWidth;
            $height = $bottomLine[$key] - $topLine[$key];
            $line = imagecreatetruecolor($width, $height);
            imagecopy($line, $img, 0, 0, 0, $topLine[$key], $width, $height);
            $imgLine[] = self::addBorder($line);
        }

        return $imgLine;
    }

    /**
     * Разбиваем текстовые строки на слова
     * @param resource $img
     * @return array
     */
    protected static function byWord($img)
    {
        $imgLine = self::byLine($img);
        $imgWord = [];
        foreach ($imgLine as $lineKey => $lineValue) {
            $lineHeight = imagesy($lineValue);
            $coordinates = self::coordinatesImg($lineValue, 270);
            $beginWord = $coordinates['start'];
            $endWord = $coordinates['end'];
            // Нарезаем на слова
            foreach ($beginWord as $beginKey => $beginValue) {
                $width = $endWord[$beginKey] - $beginValue;
                $height = $lineHeight;
                $word = imagecreatetruecolor($width, $height);
                imagecopy($word, $lineValue, 0, 0, $beginValue, 0, $width, $height);
                $word = self::addBorder($word);
                $imgWord[$lineKey][] = $word;
            }
        }

        return $imgWord;
    }

    /**
     * Разбивает рисунок с текстом на маленькие рисунки с символом
     * @param resource $img
     * @return array
     */
    public static function byChar($img)
    {
        $imgWord = self::byWord($img);
        $imgChars = [];
        foreach ($imgWord as $lineKey => $lineValue) {
            foreach ($lineValue as $wordKey => $wordValue) {
                $wordHeight = imagesy($wordValue);
                $coordinates = self::coordinatesImg($wordValue, 270, 1);
                $beginHeightChar = $coordinates['start'];
                $endHeightChar = $coordinates['end'];
                // Нарезаем на символы
                foreach ($beginHeightChar as $beginKey => $beginValue) {
                    $tmpImg = imagecreatetruecolor($endHeightChar[$beginKey] - $beginValue, $wordHeight);
                    $white = imagecolorallocate($tmpImg, Recognizer::$background['red'], Recognizer::$background['green'], Recognizer::$background['blue']);
                    imagefill($tmpImg, 0, 0, $white);
                    imagecopy($tmpImg, $wordValue, 0, 0, $beginValue, 0, $endHeightChar[$beginKey] - $beginValue, $wordHeight);
                    $wight = imagesx($tmpImg);
                    $coordinates = self::coordinatesImg($tmpImg, 0, 1);
                    $start = $coordinates['start'][0];
                    $end = end($coordinates['end']);
                    $imgChar = imagecreatetruecolor($wight, $end - $start);
                    $white = imagecolorallocate($imgChar, Recognizer::$background['red'], Recognizer::$background['green'], Recognizer::$background['blue']);
                    imagefill($imgChar, 0, 0, $white);
                    imagecopy($imgChar, $tmpImg, 0, 0, 0, $start, $wight, $end);
                    $imgChars[$lineKey][$wordKey][] = $imgChar;
                }
            }
        }

        return $imgChars;
    }

    /**
     * Поиск точек разделения изображения
     * @param resource $img    Изображения для вычесления строк
     * @param int     $rotate Поворачивать изображени или нет в градусах
     * @param int      $border Размер границы одной части текста до другой
     * @return array координаты для обрезания
     */
    protected static function coordinatesImg($img, $rotate = 0, $border = 2)
    {
        if ($rotate) {
            $white = imagecolorallocate($img, Recognizer::$background['red'], Recognizer::$background['green'], Recognizer::$background['blue']);
            $img = imagerotate($img, $rotate, $white);
        }
        // Находим среднее значение яркости каждой пиксельной строки и всего рисунка
        $brightnessLines = [];
        $brightnessImg = 0;
        $boldImg = self::boldText($img, self::WIDTH);
        $colorsIndexBold = self::getColorsIndex($boldImg);
        $colorsIndex = self::getColorsIndex($img);
        $width = imagesx($boldImg);
        $height = imagesy($boldImg);
        for ($currentY = 0; $currentY < $height; $currentY++) {
            $brightnessLines[$currentY] = 0;
            $brightnessLinesNormal[$currentY] = 0;
            for ($currentX = 0; $currentX < $width; $currentX++) {
                $brightnessLines[$currentY] += Img::getBrightnessFromIndex($img, $colorsIndexBold['pix'][$currentX][$currentY]);
                $brightnessLinesNormal[$currentY] += Img::getBrightnessFromIndex($img, $colorsIndex['pix'][$currentX][$currentY]);
            }
            $brightnessLines[$currentY] /= $width;
            $brightnessImg += $brightnessLinesNormal[$currentY] / $width;
        }
        $brightnessImg /= $height;

        return self::getBlocks($brightnessLines, $brightnessImg, $height, $border);
    }

    protected static function getBlocks($brightnessLines, $brightnessImg, $height, $border)
    {
        $start = [];
        $end = [];
        //search border of text
        for ($currentY = $border; $currentY < ($height - $border); $currentY++) {
            if (self::isTopBorder($brightnessLines, $brightnessImg, $currentY, $border)) {
                $start[] = $currentY;
            } elseif (self::isBottomBorder($brightnessLines, $brightnessImg, $currentY, $border)) {
                $end[] = $currentY;
            } elseif (self::isSpaceBetweenLines($brightnessLines, $brightnessImg, $currentY, $border)) {
                $start[] = $currentY;
                $end[] = $currentY;
            }
        }
        return ['start' => $start, 'end' => $end];
    }

    protected static function isTopBorder($brightnessLines, $brightnessImg, $positionY, $border)
    {
        return ($brightnessLines[$positionY - $border] > $brightnessImg
            && ($brightnessLines[$positionY - ($border - 1)] > $brightnessImg || $border == 1)
            && $brightnessLines[$positionY] > $brightnessImg
            && ($brightnessLines[$positionY + ($border - 1)] < $brightnessImg || $border == 1)
            && $brightnessLines[$positionY + $border] < $brightnessImg);
    }

    protected static function isBottomBorder($brightnessLines, $brightnessImg, $positionY, $border)
    {
        return ($brightnessLines[$positionY - $border] < $brightnessImg
            && ($brightnessLines[$positionY - ($border - 1)] < $brightnessImg || $border == 1)
            && $brightnessLines[$positionY] > $brightnessImg
            && ($brightnessLines[$positionY + ($border - 1)] > $brightnessImg || $border == 1)
            && $brightnessLines[$positionY + $border] > $brightnessImg);
    }

    protected static function isSpaceBetweenLines($brightnessLines, $brightnessImg, $positionY, $border)
    {
        return ($brightnessLines[$positionY - $border] < $brightnessImg
            && $brightnessLines[$positionY] > $brightnessImg
            && $brightnessLines[$positionY + $border] < $brightnessImg
            && $border == 1);
    }

    /**
     * Заливаем текст для более точного определения по яркости
     * @param resource $img
     * @param int   $bType тип утолщения WIDTH or HEIGHT
     * @return resource
     */
    protected static function boldText($img, $bType = self::WIDTH)
    {
        $colorIndexes = self::getColorsIndexTextAndBackground($img);
        $colorTextIndexes = array_flip($colorIndexes['text']);
        $width = imagesx($img);
        $height = imagesy($img);
        $blurImg = imagecreatetruecolor($width, $height);
        imagecopy($blurImg, $img, 0, 0, 0, 0, $width, $height);
        $black = imagecolorallocate($blurImg, 0, 0, 0);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                if (isset($colorTextIndexes[$colorIndexes['pix'][$x][$y]])) {
                    if ($bType == self::WIDTH) {
                        imagefilledrectangle($blurImg, $x - self::$boldSize, $y, $x + self::$boldSize, $y, $black);
                    } elseif ($bType == self::HEIGHT) {
                        imagefilledrectangle($blurImg, $x, $y - self::$boldSize, $x, $y + self::$boldSize, $black);
                    }
                }
            }
        }
        return $blurImg;
    }
}
