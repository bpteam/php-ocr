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
    public static $img_char;
    /**
     * Массив шаблонов символов для распознования
     * @var array array(GD)
     */
    public static $img_char_templates;

    /**
     * Хранит информацию о изображении
     * [0] ширина
     * [1] высота
     * [2] Тип рисунка png jpeg gif
     * @var array Массив полученый через функцию getimagesize
     */
    public static $img_info;

    /**
     * [pix][X][Y]=Значение индекса цвета в пикселе XxY в изображении
     * ['index'][index]=Количество цветов с таким индексом
     * ['count_pix']=Количество пикселей в изображении
     * ['percent'][index]= Процентное соотношение цветов на изображении
     * @var array
     */
    public static $colors_index;

    /**
     * Средняя яркасть рисунка
     * @var float
     */
    public static $brightness_img;

    /**
     * Средняя яркость фона изображения
     * @var float
     */
    public static $brightness_background;
    /**
     * Средняя яркость текста
     * @var float
     */
    public static $brightness_text;
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
        //Убираем прозрачность
        switch(self::$img_info[2])
        {
            case IMAGETYPE_GIF:
                $transparent_index=imagecolortransparent($tmp_img);
                if($transparent_index!==-1)
                {
                    //$transparent_color=imagecolorsforindex($tmp_img, $transparent_index);
                    //$transparent_img_index=imagecolorallocate(self::$img, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                    //imagecolortransparent(self::$img, $transparent_img_index);
                    //imagefill(self::$img, 0, 0, $transparent_img_index);
                    $white=imagecolorallocate(self::$img, 255, 255, 255);
                    imagefill(self::$img, 0, 0, $white);
                }
                break;
            case IMAGETYPE_PNG:
                //imagealphablending(self::$img, false);
                //imagesavealpha(self::$img, true);
                $white=imagecolorallocate(self::$img, 255, 255, 255);
                imagefill(self::$img, 0, 0, $white);
                break;
            default:
                break;
        }
        imagecopy(self::$img, $tmp_img, 0, 0, 0, 0, self::$img_info[0], self::$img_info[1]);
        return self::$img;
    }

    /**
     * Подсчитываем количество цветов в изображении и их долю в палитре
     * Сбор индексов цвета каждого пикселя
     * @return array
     *
     */
    static function get_colors_index()
    {
        self::$colors_index['index']=array();
        for($x=0;$x<self::$img_info[0];$x++)
        {
            for($y=0;$y<self::$img_info[1];$y++)
            {
                $pixel_index=imagecolorat(self::$img,$x,$y);
                self::$colors_index['pix'][$x][$y]=$pixel_index;
                if(array_key_exists($pixel_index,self::$colors_index['index']))
                    self::$colors_index['index'][$pixel_index]++;
                else self::$colors_index['index'][$pixel_index]=1;
            }
        }
        arsort(self::$colors_index['index'],SORT_NUMERIC);
        self::$colors_index['count_pix']=self::$img_info[0]*self::$img_info[1];
        foreach (self::$colors_index['index'] as $key => $value)
        {
            self::$colors_index['percent'][$key]=($value/self::$colors_index['count_pix'])*100;
        }
        return self::$colors_index;
    }

    /**
     * Вычисления цвета фона изображения с текстом, Фон светлее текста или наоборот, если темнее то цвета инвертируются цвета
     */
    static function check_background_brightness()
    {
        $count_colors=self::get_colors_index();
        reset($count_colors['index']);
        $background_index=key($count_colors['index']);
        $background_color=imagecolorsforindex(self::$img, $background_index);
        $mid_color['red']=0;
        $mid_color['green']=0;
        $mid_color['blue']=0;
        // Собираем все цвета текста
        $count_text_color=0;
        while(next($count_colors['index']))
        {
            if($count_colors['percent'][key($count_colors['index'])]<1) continue;
            $count_text_color++;
            $color_index=key($count_colors['index']);
            $color=imagecolorsforindex(self::$img, $color_index);
            $mid_color['red']+=$color['red'];
            $mid_color['green']+=$color['green'];
            $mid_color['blue']+=$color['blue'];
        }
        foreach ($mid_color as &$value) $value/=$count_text_color; //Вычисляем средний цвет текста
        unset($value);
        self::$brightness_background=($background_color['red']+$background_color['green']+$background_color['blue'])/3;
        self::$brightness_text=($mid_color['red']+$mid_color['green']+$mid_color['blue'])/3;
        if(self::$brightness_background<self::$brightness_text)
        {
            imagefilter(self::$img,IMG_FILTER_NEGATE);
            self::get_colors_index();
        }
    }

    /**
     * Разбивает ресунок с текстом на маленькие рисунки с символом
     */
    static function divide_char()
    {

    }

    /**
     *  Разбивает рисунок на строки с текстом
     */
    static function divide_to_line()
    {
        // Находим среднее значение яркости каждой пиксельной строки и всего рисунка
        $brightness_lines=array();
        self::$brightness_img=0;
        for($y=0;$y<self::$img_info[1];$y++)
        {
            $brightness_lines[$y]=0;
            for($x=0;$x<self::$img_info[0];$x++) $brightness_lines[$y]+=self::get_brightness_to_index(self::$colors_index['pix'][$x][$y]);
            $brightness_lines[$y]/=self::$img_info[0];
            self::$brightness_img+=$brightness_lines[$y];
        }
        self::$brightness_img/=self::$img_info[1];
        $top_line=array();
        $bottom_line=array();
        //Находим все верхние и нижние границы строк текста
        for($y=2;$y<self::$img_info[1]-3;$y++)
        {
            //Top
            if($brightness_lines[$y-2]>self::$brightness_img &&
               $brightness_lines[$y-1]>self::$brightness_img &&
               $brightness_lines[$y]>self::$brightness_img &&
               $brightness_lines[$y+1]<self::$brightness_img &&
               $brightness_lines[$y+2]<self::$brightness_img &&
               $brightness_lines[$y+3]<self::$brightness_img
            )
                $top_line[]=$y;
            elseif($brightness_lines[$y-2]<self::$brightness_img &&
                $brightness_lines[$y-1]<self::$brightness_img &&
                $brightness_lines[$y]>self::$brightness_img &&
                $brightness_lines[$y+1]>self::$brightness_img &&
                $brightness_lines[$y+2]>self::$brightness_img
            )
                $bottom_line[]=$y;
        }
        $coord['top']=$top_line;
        $coord['bot']=$bottom_line;
        $red=imagecolorallocate(self::$img, 255, 0, 0);
        $green=imagecolorallocate(self::$img, 0, 255, 0);
        foreach ($top_line as $value) imageline(self::$img, 0, $value, self::$img_info[0], $value,$red);
        foreach ($bottom_line as $value) imageline(self::$img, 0, $value, self::$img_info[0], $value,$green);

        return $coord;
    }

    /**
     * Вычисляем яркость цвета по его индексу
     */
    static function get_brightness_to_index($color_index,$img=null)
    {
        if($img===null) $img=self::$img;
        $color=imagecolorsforindex($img, $color_index);
        return ($color['red']+$color['green']+$color['blue'])/3;
    }

}
