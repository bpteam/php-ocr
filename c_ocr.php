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
    /**
     * Массив шаблонов символов для распознования
     * @var array array(GD)
     */
    public static $img_letter_templates;

    /**
     * Хранит информацию о изображении
     * [0] ширина
     * [1] высота
     * [2] Тип рисунка png jpeg gif
     * @var array Массив полученый через функцию getimagesize
     */
    public static $img_info;

    /**
     * Фон ярче текста или нет
     * true ярче
     * false темнее
     * @var bool
     */
    public static $background_brightness;

    /**
     * @param string $img_file Имя файла с исображением
     * @return int|resource
     */
    static function open_img($img_file)
    {
        self::$img_info=getimagesize($img_file);
        self::$img = imagecreatetruecolor(self::$img_info[0], self::$img_info[1]);
        switch(self::$img_info[2])
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
                    default: return false;
                }
                break;
        }
        //Сохраняем прозрачность, если есть
        switch(self::$img_info[2])
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
        imagecopy(self::$img, $tmp_img, 0, 0, 0, 0, self::$img_info[0], self::$img_info[1]);
        return self::$img;
    }

    /**
     * Подсчитываем количество цветов в изображении и их долю в палитре
     * @return array
     * ['index'][index]=Количество цветов с таким индексом
     * ['count_pix']=Количество пикселей в изображении
     * ['percent'][index]= Процентное соотношение цветов на изображении
     */
    static function count_colors_indexs()
    {
        $count_index_array['index']=array();

        for($i=0;$i<self::$img_info[0];$i++)
        {
            for($j=0;$j<self::$img_info[1];$j++)
            {
                $pixel_index=imagecolorat(self::$img,$i,$j);
                if(array_key_exists($pixel_index,$count_index_array['index']))
                $count_index_array['index'][$pixel_index]++;
                else $count_index_array['index'][$pixel_index]=1;
            }
        }
        arsort($count_index_array['index'],SORT_NUMERIC);
        $count_index_array['count_pix']=self::$img_info[0]*self::$img_info[1];
        foreach ($count_index_array['index'] as $key => $value)
        {
            $count_index_array['percent'][$key]=($value/$count_index_array['count_pix'])*100;
        }
        return $count_index_array;
    }

    /**
     * Вычисления тона фона изображения с текстом, Тон светлее текста или наоборот
     */
    static function get_background_brightness()
    {
        $count_colors=self::count_colors_indexs();
        reset($count_colors['index']);
        $background_index=key($count_colors['index']);
        $background_color=imagecolorsforindex(self::$img, $background_index);
        $mid_color['red']=0;
        $mid_color['green']=0;
        $mid_color['blue']=0;
        // Собираем все цвета текста
        while(next($count_colors['index']))
        {
            $color_index=key($count_colors['index']);
            $color=imagecolorsforindex(self::$img, $color_index);
            $mid_color['red']+=$color['red'];
            $mid_color['green']+=$color['green'];
            $mid_color['blue']+=$color['blue'];
        }
        $count=count($count_colors['index'])-1;// исключаем из количества цветов цвет фона
        foreach ($mid_color as &$value) $value/=$count; //Вычисляем средний цвет текста
        unset($value);
        if(($background_color['red']+$background_color['green']+$background_color['blue'])>($mid_color['red']+$mid_color['green']+$mid_color['blue']))
            self::$background_brightness=true;
        else self::$background_brightness=false;
        return self::$background_brightness;
    }
}
