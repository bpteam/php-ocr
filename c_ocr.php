<?php
namespace php_ocr\c_ocr;
/**
 * Class c_ocr
 * Класс для распознования текста на изображении с помощью шаблонов.
 * Режим Обучение - скармливаете рисунок и
 * @author Evgeny Pynykh <bpteam22@gmail.com>
 * @package php_ocr
 * @version 2.0
 */
class c_ocr
{
    /**
     * Изображение которое будем обрабатывать
     * @var GD
     */
    public static $img;
    /**
     * Массив рисунков символов для распознования
     * @var array array(GD)
     */
    public static $img_letters;

    static function open_image($img_file)
    {
        $img_info=getimagesize($img_file);
        self::$img = imagecreatetruecolor($img_info[0], $img_info[1]);
        switch($img_info[2])
        {
            case IMAGETYPE_PNG :
                $tmp_img = imagecreatefrompng($img_file);
                break;
            case IMAGETYPE_JPEG :
                $tmp_img = imagecreatefromjpeg($img_file);
                break;
            case IMAGETYPE_GIF :
                $tmp_img = imagecreatefromgif($img_file);
                break;
            default:
                switch(true)
                {
                    case $tmp_img = @imagecreatefromstring($img_file): break;
                    case $tmp_img = @imagecreatefromgd($img_file): break;
                    default: return 0;
                }
                break;
        }
        //Сохраняем прозрачность, если есть
        switch($img_info[2])
        {
            case IMAGETYPE_GIF:
                $transparent_index=imagecolortransparent($tmp_img);
                if($transparent_index!==-1)
                {
                    $transparent_color=imagecolorsforindex($tmp_img, $transparent_index);
                    $transparent_img_index=imagecolorallocate(self::$img, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                    imagecolortransparent(self::$img, $transparent_img_index);
                    imagefill(self::$img, 0, 0, $transparent_img_index);
                }
                break;
            case IMAGETYPE_PNG:
                imagealphablending(self::$img, false);
                imagesavealpha(self::$img, true);
                break;
            default:
                break;
        }
        imagecopy(self::$img, $tmp_img, 0, 0, 0, 0, $img_info[0], $img_info[1]);
        return self::$img;
    }
}
