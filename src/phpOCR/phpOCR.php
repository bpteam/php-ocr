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
class phpOCR
{
    /**
     * Изображение которое будем обрабатывать
     * @var resource
     */
    public static $img;

    /**
     * Добавляет к краям изображения количество пикселей для удобного разрезания
     * @var int
     */
    protected static $sizeBorder = 2;

    protected static $colorDiff = 50;

    /**
     * Погрешность в сравнении с шаблоном в процентах
     * @var float
     */
    protected static $infelicity = 10;

    public static $boldSize = 1;

    protected static $templateDir = null;

    const WIDTH = 1;

    const HEIGHT = 2;

    /**
     * @param string $imgFile Имя файла с исображением
     * @return bool|resource
     */
    public static function openImg($imgFile)
    {
        $info = @getimagesize($imgFile);
        switch ($info[2]) {
            case IMAGETYPE_PNG :
                $tmpImg2 = imagecreatefrompng($imgFile);
                $tmpImg = imagecreatetruecolor($info[0], $info[1]);
                $white = imagecolorallocate($tmpImg, 255, 255, 255);
                imagefill($tmpImg, 0, 0, $white);
                imagecopy($tmpImg, $tmpImg2, 0, 0, 0, 0, $info[0], $info[1]);
                imagedestroy($tmpImg2);
                break;
            case IMAGETYPE_JPEG :
                $tmpImg = imagecreatefromjpeg($imgFile);
                break;
            case IMAGETYPE_GIF :
                $tmpImg = imagecreatefromgif($imgFile);
                break;
            default:
                if ($tmpImg2 = @imagecreatefromstring($imgFile)) {
                    $info[0] = imagesx($tmpImg2);
                    $info[1] = imagesy($tmpImg2);
                    $tmpImg = imagecreatetruecolor($info[0], $info[1]);
                    $white = imagecolorallocate($tmpImg, 255, 255, 255);
                    imagefill($tmpImg, 0, 0, $white);
                    imagecopy($tmpImg, $tmpImg2, 0, 0, 0, 0, $info[0], $info[1]);
                    imagedestroy($tmpImg2);
                } elseif ($tmpImg = @imagecreatefromgd($imgFile)) {
                } else {
                    return false;
                }
                break;
        }
        $imgInfo[0] = imagesx($tmpImg);
        $imgInfo[1] = imagesy($tmpImg);
        self::$img = imagecreatetruecolor($imgInfo[0] + (self::$sizeBorder * 2), $imgInfo[1] + (self::$sizeBorder * 2));
        $white = imagecolorallocate(self::$img, 255, 255, 255);
        imagefill(self::$img, 0, 0, $white);
        $tmpImg = self::changeBackgroundBrightness($tmpImg);
        imagecopy(self::$img, $tmpImg, self::$sizeBorder, self::$sizeBorder, 0, 0, $imgInfo[0], $imgInfo[1]);

        return self::$img;
    }

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
    protected static function getColorsIndexTextAndBackground($img)
    {
        $countColors = self::getColorsIndex($img);
        $countColors['index'] = array_keys($countColors['index']);
        $backgroundIndex = array_shift($countColors['index']);
        $indexes['background'] = $backgroundIndex;
        $indexes['pix'] = $countColors['pix'];
        // Собираем все цвета отличные от фона
        $backgroundBrightness = self::getBrightnessFromIndex($img, $backgroundIndex);
        $backgroundBrightness = $backgroundBrightness - ($backgroundBrightness * 0.2);
        foreach ($countColors['index'] as $colorKey => $colorValue) {
            $colorBrightness = self::getBrightnessFromIndex($img, $colorValue);
            if ($backgroundBrightness < ($colorBrightness + self::$colorDiff)) {
                unset($countColors['index'][$colorKey]);
            }
        }
        $indexes['text'] = $countColors['index'];
        $indexes['background'] = $backgroundIndex;
        $indexes['pix'] = $countColors['pix'];

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
        $brightnessBackground = self::getBrightnessFromIndex($img, $colorIndexes['background']);
        if ($colorIndexes['text']) {
            $midColor = self::getMidColorFromIndexes($img, $colorIndexes['text']);
            $brightnessText = ($midColor['red'] + $midColor['green'] + $midColor['blue']) / 3;
        } else {
            $brightnessText = 255;
        }
        if ($brightnessBackground < $brightnessText) {
            imagefilter($img, IMG_FILTER_NEGATE);
            $colorIndexes = self::getColorsIndexTextAndBackground($img);
        }
        imagecolorset($img, $colorIndexes['background'], 255, 255, 255);

        return $img;
    }

    /**
     * Подсчитывает средний цвет из массива индексов
     * @param resource $img
     * @param array    $arrayIndexes
     * @return array
     */
    protected static function getMidColorFromIndexes($img, $arrayIndexes)
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
        return $midColor;
    }

    public static function getSizeBorder()
    {
        return self::$sizeBorder;
    }

    public static function setSizeBotder($val)
    {
        self::$sizeBorder = $val;
    }

    public static function getInfelicity()
    {
        return self::$infelicity;
    }

    public static function setInfelicity($val)
    {
        self::$infelicity = $val;
    }

    /**
     * @return boolean|string
     */
    public static function getTemplateDir()
    {
        return self::$templateDir;
    }

    /**
     * @param boolean|string $templateDir
     */
    public static function setTemplateDir($templateDir)
    {
        self::$templateDir = $templateDir;
    }

    /**
     * Разбивает рисунок на строки с текстом
     * @param resource $img
     * @return array
     */
    protected static function divideByLine($img)
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
            $width = $imgWidth + (self::$sizeBorder * 2);
            $height = $bottomLine[$key] - $topLine[$key] + (self::$sizeBorder * 2);
            $imgLine[$key] = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($imgLine[$key], 255, 255, 255);
            imagefill($imgLine[$key], 0, 0, $white);
            $src_h = $bottomLine[$key] - $topLine[$key];
            imagecopy($imgLine[$key], $img, self::$sizeBorder, self::$sizeBorder, 0, $topLine[$key], $imgWidth, $src_h);
        }

        return $imgLine;
    }

    /**
     * Разбиваем текстовые строки на слова
     * @param resource $img
     * @return array
     */
    protected static function divideByWord($img)
    {
        $imgLine = self::divideByLine($img);
        $imgWord = [];
        foreach ($imgLine as $lineKey => $lineValue) {
            $lineHeight = imagesy($lineValue);
            $coordinates = self::coordinatesImg($lineValue, 270);
            $beginWord = $coordinates['start'];
            $endWord = $coordinates['end'];
            // Нарезаем на слова
            foreach ($beginWord as $beginKey => $beginValue) {
                $width = $endWord[$beginKey] - $beginValue + (self::$sizeBorder * 2);
                $height = $lineHeight + (self::$sizeBorder * 2);
                $word = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($word, 255, 255, 255);
                imagefill($word, 0, 0, $white);
                $dst_x = self::$sizeBorder;
                $dst_y = self::$sizeBorder;
                $src_w = $endWord[$beginKey] - $beginValue;
                imagecopy($word, $lineValue, $dst_x, $dst_y, $beginValue, 0, $src_w, $lineHeight);
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
    public static function divideByChar($img)
    {
        $imgWord = self::divideByWord($img);
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
                    $white = imagecolorallocate($tmpImg, 255, 255, 255);
                    imagefill($tmpImg, 0, 0, $white);
                    imagecopy($tmpImg, $wordValue, 0, 0, $beginValue, 0, $endHeightChar[$beginKey] - $beginValue, $wordHeight);
                    $wight = imagesx($tmpImg);
                    $coordinates = self::coordinatesImg($tmpImg, 0, 1);
                    $imgChar = imagecreatetruecolor($wight, $coordinates['end'][0] - $coordinates['start'][0]);
                    $white = imagecolorallocate($imgChar, 255, 255, 255);
                    imagefill($imgChar, 0, 0, $white);
                    imagecopy($imgChar, $tmpImg, 0, 0, 0, $coordinates['start'][0], $wight, $coordinates['end'][0]);
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
            $white = imagecolorallocate($img, 255, 255, 255);
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
        for ($positionY = 0; $positionY < $height; $positionY++) {
            $brightnessLines[$positionY] = 0;
            $brightnessLinesNormal[$positionY] = 0;
            for ($x = 0; $x < $width; $x++) {
                $brightnessLines[$positionY] += self::getBrightnessFromIndex($img, $colorsIndexBold['pix'][$x][$positionY]);
                $brightnessLinesNormal[$positionY] += self::getBrightnessFromIndex($img, $colorsIndex['pix'][$x][$positionY]);
            }
            $brightnessLines[$positionY] /= $width;
            $brightnessImg += $brightnessLinesNormal[$positionY] / $width;
        }
        $brightnessImg /= $height;
        $coordinates['start'] = [];
        $coordinates['end'] = [];
        //search border of text
        for ($positionY = $border; $positionY < ($height - $border); $positionY++) {
            if (self::isTopBorder($brightnessLines, $brightnessImg, $positionY, $border)) {
                $coordinates['start'][] = $positionY;
            } elseif (self::isBottomBorder($brightnessLines, $brightnessImg, $positionY, $border)) {
                $coordinates['end'][] = $positionY;
            } elseif (self::isSpaceBetweenLines($brightnessLines, $brightnessImg, $positionY, $border)) {
                $coordinates['start'][] = $positionY;
                $coordinates['end'][] = $positionY;
            }
        }

        return $coordinates;
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
     * Вычисляем яркость цвета по его индексу
     * @param resource $img
     * @param int      $colorIndex
     * @return int
     */
    protected static function getBrightnessFromIndex($img, $colorIndex)
    {
        $color = imagecolorsforindex($img, $colorIndex);

        return ($color['red'] + $color['green'] + $color['blue']) / 3;
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
        $imgInfo['x'] = imagesx($img);
        $imgInfo['y'] = imagesy($img);
        $blurImg = imagecreatetruecolor($imgInfo['x'], $imgInfo['y']);
        imagecopy($blurImg, $img, 0, 0, 0, 0, $imgInfo['x'], $imgInfo['y']);
        $black = imagecolorallocate($blurImg, 0, 0, 0);
        for ($x = 0; $x < $imgInfo['x']; $x++) {
            for ($y = 0; $y < $imgInfo['y']; $y++) {
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

    /**
     * Прапорциональное изменение размера изображения
     * @param resource $img   изображение
     * @param int      $width ширина
     * @param int      $height высота
     * @return resource
     */
    protected static function resizeImg($img, $width, $height)
    {
        $imgInfo['x'] = imagesx($img);
        $imgInfo['y'] = imagesy($img);
        $newImg = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($newImg, 255, 255, 255);
        imagefill($newImg, 0, 0, $white);
        if ($imgInfo['x'] < $imgInfo['y']) {
            $width = $imgInfo['x'] * ($height / $imgInfo['y']);
        } else {
            $height = $imgInfo['y'] * ($width / $imgInfo['x']);
        }
        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $width, $height, $imgInfo['x'], $imgInfo['y']);

        return $newImg;
    }


    /**
     * Генерация шаблона из одного символа
     * @param resource $img
     * @param int      $width
     * @param int      $height
     * @return string
     */
    public static function generateTemplateChar($img, $width = 15, $height = 16)
    {
        $imgInfo['x'] = imagesx($img);
        $imgInfo['y'] = imagesy($img);
        if ($imgInfo['x'] != $width || $imgInfo['y'] != $height) {
            $img = self::resizeImg($img, $width, $height);
        }
        $colorIndexes = self::getColorsIndexTextAndBackground($img);
        $colorTextIndexes = array_flip($colorIndexes['text']);
        $line = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (isset($colorTextIndexes[$colorIndexes['pix'][$x][$y]])) {
                    $line .= '1';
                } else {
                    $line .= '0';
                }
            }
        }
        return $line;
    }

    /**
     * Генерация шаблона для распознования
     * @param array $chars Массив string из символов в последовательности как на картинках
     * @param array $imgs  Массив resource из изображений для создания шаблона
     * @return array|bool
     */
    public static function generateTemplate($chars, $imgs)
    {
        if (count($chars) != count($imgs)) {
            return false;
        }
        $template = [];
        foreach ($chars as $charKey => $charValue) {
            $template[$charValue] = self::generateTemplateChar($imgs[$charKey]);
        }

        return $template;
    }

    /**
     * Сохранение шаблона в файл
     * @param string $name     Имя шаблона
     * @param array  $template шаблон
     */
    public static function saveTemplate($name, $template)
    {
        $json = json_encode($template);
        $name = self::getTemplateDir() . $name . '.json';
        file_put_contents($name, $json);
    }

    /**
     * Загрузка шаблона из файла
     * @param string $name имя шаблона
     * @return array|bool
     */
    public static function loadTemplate($name)
    {
        if (!self::getTemplateDir()) {
            self::setTemplateDir(__DIR__ . '/template/');
        }
        $name = self::getTemplateDir() . $name . '.json';
        $json = file_get_contents($name);

        return json_decode($json, true);
    }

    /**
     * Распознование символа по шаблону
     * @param resource $img
     * @param array    $template
     * @return int|string
     */
    protected static function defineChar($img, $template)
    {
        $templateChar = self::generateTemplateChar($img);
        foreach ($template as $key => $value) {
            if (self::compareChar($templateChar, $key)) {
                return $value;
            }
        }
        return '?';
    }

    /**
     * Сравнивает шаблоны символов на похожесть
     * @param string $char1 символ 1 в виде шаблона
     * @param string $char2 символ 1 в виде шаблона
     * @return bool
     */
    protected static function compareChar($char1, $char2)
    {
        $difference = levenshtein($char1, $char2);
        if ($difference < strlen($char1) * (self::$infelicity / 100)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Распознование текста на изображении
     * @param       $imgFile
     * @param array $template
     * @return string
     */
    public static function defineImg($imgFile, $template)
    {
        $img = self::openImg($imgFile);
        $imgs = self::divideByChar($img);
        $text = '';
        foreach ($imgs as $line) {
            foreach ($line as $word) {
                foreach ($word as $char) {
                    $text .= self::defineChar($char, $template);
                }
                if (count($word) > 1) {
                    $text .= " ";
                }
            }
            if (count($line) > 1) {
                $text .= "\n";
            }
        }

        return trim($text);
    }

    /**
     * Находит уникальные символы в массиве символов
     * @param array $imgs Масси изображений символов
     * @return array Массив изображений уникальных символов
     */
    public static function findUniqueChar($imgs)
    {
        $templateChars = [];
        foreach ($imgs as $value) {
            $templateChars[] = self::generateTemplateChar($value);
        }
        $templateChars = array_unique($templateChars);
        $cloneKey = [];
        foreach ($templateChars as $key => $value) {
            foreach (array_slice($templateChars, $key, null, true) as $tmpKey => $tmpValue) {
                if (self::compareChar($value, $tmpValue) && $key != $tmpKey) {
                    $cloneKey[] = $tmpKey;
                }
            }
        }
        foreach ($cloneKey as $value) {
            unset($templateChars[$value]);
        }

        $newImgs = [];
        foreach ($templateChars as $key => $value) {
            $newImgs[] = $imgs[$key];
        }

        return $newImgs;
    }

    public static function showImg($img, $extension = 'png', $prefix = 0, $dirToSave = false)
    {
        if (!$dirToSave)
            $dirToSave ='tmp/';
        if (is_array($img)) {
            foreach ($img as $key => $value) {
                if (is_array($value)) {
                    self::showImg($value, $extension);
                } else {
                    $t = rand();
                    $picName = $dirToSave . 'img' . $prefix . $t . $key . '.' . $extension;
                    $fh = fopen($picName, 'w+');
                    fwrite($fh, '');
                    fclose($fh);
                    imagepng($value, $picName, 9);
                    echo "<img src='" . $picName . "'></br>\n";
                }
            }
        } else {
            $t = rand();
            $picName = $dirToSave . 'img' . $prefix . $t . '.' . $extension;
            $fh = fopen($picName, 'w+');
            fwrite($fh, '');
            fclose($fh);
            imagepng($img, $picName, 9);
            echo "<img src='" . $picName . "'></br>\n";
        }
    }
}
