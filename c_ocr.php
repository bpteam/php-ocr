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
                    $white=imagecolorallocate(self::$img, 255, 255, 255);
                    imagefill(self::$img, 0, 0, $white);
                }
                break;
            case IMAGETYPE_PNG:
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
        self::$colors_index=array();
        for($x=0;$x<self::$img_info[0];$x++)
        {
            for($y=0;$y<self::$img_info[1];$y++)
            {
                $pixel_index=imagecolorat(self::$img,$x,$y);
                self::$colors_index['pix'][$x][$y]=$pixel_index;
                if(isset(self::$colors_index['index']) && array_key_exists($pixel_index,self::$colors_index['index']))
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
     * Вычисления цвета фона изображения с текстом, Фон светлее текста или наоборот, если темнее то цвета инвертируются
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
        // Собираем все цвета отличные от фона
        $count_text_color=0;
        $color_index_text=array();
        while(next($count_colors['index']))
        {
            $count_text_color++;
            $color_index_text[]=key($count_colors['index']);
            $color=imagecolorsforindex(self::$img, key($count_colors['index']));
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
            imagefilter(self::$img,IMG_FILTER_NEGATE); //Инвертируем если фон черный
            self::get_colors_index();
        }
    }

    /**
     * Разбивает рисунок с текстом на маленькие рисунки с символом
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
        for($y=2;$y<self::$img_info[1]-2;$y++)
        {
            //Top
            if( $brightness_lines[$y-2]>self::$brightness_img &&
                $brightness_lines[$y-1]>self::$brightness_img &&
                $brightness_lines[$y]>self::$brightness_img &&
                $brightness_lines[$y+1]<self::$brightness_img &&
                $brightness_lines[$y+2]<self::$brightness_img
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
        // Находим кратное 2 количество строк
        if(!count($top_line) || !count($bottom_line))
        {
            $top_line[]=0;
            $bottom_line[]=self::$img_info[1]-1;
        }
        elseif(count($top_line)==count($bottom_line)) /* :| ниче не делаю, сокращаю время выполнения */ ;
        elseif(count($top_line)>count($bottom_line)) $top_line=array_slice($top_line,0,count($bottom_line));
        elseif(count($top_line)<count($bottom_line)) $bottom_line=array_slice($bottom_line,0,count($top_line));
        /* TODO: Сделать подсчет последовательности строк, чтоб избежать двух подряд верхних краев строки */
        // Ищем самую низкую строку для захвата заглавных букв
        // Ищем самую низкую строку для захвата заглавных букв
        $h_min=99999;
        foreach ($top_line as $key => $value)
        {
            $h_line=$bottom_line[$key]-$top_line[$key];
            if($h_min>$h_line) $h_min=$h_line;
        }
        // Увеличим все строки на треть самой маленькой
        $change_size=0.35*$h_min;
        foreach ($top_line as $key => $value)
        {
            if(($top_line[$key]-$change_size)>=0) $top_line[$key]-=$change_size;
            if(($bottom_line[$key]+$change_size)<=self::$img_info[1]) $bottom_line[$key]+=$change_size;
        }
       /* $red=imagecolorallocate(self::$img, 255, 0, 0);
        $green=imagecolorallocate(self::$img, 0, 255, 0);
        foreach ($top_line as $value) imageline(self::$img, 0, $value, self::$img_info[0], $value,$red);
        foreach ($bottom_line as $value) imageline(self::$img, 0, $value, self::$img_info[0], $value,$green);
        $coord['top']=$top_line;
        $coord['bot']=$bottom_line;*/
        // Нарезаем на полоски с текстом
        $img_line=array();
        foreach ($top_line as $key => $value)
        {
            $img_line[$key]=imagecreatetruecolor(self::$img_info[0], $bottom_line[$key]-$top_line[$key]);
            imagecopy($img_line[$key],self::$img,0,0,0,$top_line[$key],self::$img_info[0],$bottom_line[$key]-$top_line[$key]);
        }
        return $img_line;
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
