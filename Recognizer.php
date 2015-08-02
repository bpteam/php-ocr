<?php
/**
 * Created by PhpStorm.
 * User: ec
 * Date: 03.06.15
 * Time: 11:48
 * Project: gd2-php-ocr
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */

namespace bpteam\phpOCR;


class Recognizer {

    protected static $templateDir = null;
    public static $background = ['red' => 255, 'green' => 255, 'blue' => 255, 'alpha' => 0];
    /**
     * Погрешность в сравнении с шаблоном в процентах
     * @var float
     */
    protected static $infelicity = 10;

    /**
     * @param string $imgFile Имя файла с исображением
     * @return bool|resource
     */
    public static function openImg($imgFile)
    {
        $img = Img::open($imgFile);
        if ($img) {
            $img = Divider::changeBackgroundBrightness($img);
            $colorIndexes = Divider::getColorsIndexTextAndBackground($img);
            self::$background = imagecolorsforindex($img, $colorIndexes['background']);
            $img = Divider::addBorder($img, self::$background['red'], self::$background['green'], self::$background['blue']);
        }
        return $img;
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
     * Генерация шаблона из одного символа
     * @param resource $img
     * @param int      $width
     * @param int      $height
     * @return string
     */
    public static function generateTemplateChar($img, $width = 15, $height = 16)
    {
        if (imagesx($img) != $width || imagesy($img) != $height) {
            $img = Img::resize($img, $width, $height);
        }
        $colorIndexes = Divider::getColorsIndexTextAndBackground($img);
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
    public static function read($imgFile, $template)
    {
        $img = self::openImg($imgFile);
        $imgs = Divider::byChar($img);
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
            //Img::show($value);
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

}