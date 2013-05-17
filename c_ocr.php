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
     * Массив с нарезаными строками текста
     * @var array
     */
    public static $img_line;
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
        c_ocr::check_background_brightness();
        return self::$img;
    }

    /**
     * Подсчитываем количество цветов в изображении и их долю в палитре
     * Сбор индексов цвета каждого пикселя
     * @param GD $img
     * @return array
     */
    static function get_colors_index($img)
    {
        $colors_index=array();
        $img_info[0]=imagesx($img);
        $img_info[1]=imagesy($img);
        for($x=0;$x<$img_info[0];$x++)
        {
            for($y=0;$y<$img_info[1];$y++)
            {
                $pixel_index=imagecolorat($img,$x,$y);
                $colors_index['pix'][$x][$y]=$pixel_index;
                if(isset($colors_index['index']) && array_key_exists($pixel_index,$colors_index['index']))
                    $colors_index['index'][$pixel_index]++;
                else $colors_index['index'][$pixel_index]=1;
            }
        }
        arsort($colors_index['index'],SORT_NUMERIC);
        $colors_index['count_pix']=$img_info[0]*$img_info[1];
        foreach ($colors_index['index'] as $key => $value)
        {
            $colors_index['percent'][$key]=($value/$colors_index['count_pix'])*100;
        }
        return $colors_index;
    }

    /**
     * Вычисления цвета фона изображения с текстом, Фон светлее текста или наоборот, если темнее то цвета инвертируются
     */
    static function check_background_brightness()
    {
        $count_colors=self::get_colors_index(self::$img);
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
        $brightness_background=($background_color['red']+$background_color['green']+$background_color['blue'])/3;
        $brightness_text=($mid_color['red']+$mid_color['green']+$mid_color['blue'])/3;
        if($brightness_background<$brightness_text) imagefilter(self::$img,IMG_FILTER_NEGATE); //Инвертируем если фон черный
    }

    /**
     * Разбивает рисунок с текстом на маленькие рисунки с символом
     */
    static function divide_char($img)
    {
        self::divide_to_word($img);

    }

    /**
     *  Разбивает рисунок на строки с текстом
     */
    static function divide_to_line($img)
    {
        // Находим среднее значение яркости каждой пиксельной строки и всего рисунка
        $brightness_lines=array();
        $brightness_img=0;
        $colors_index=get_colors_index($img);
        $img_info[0]=imagesx($img);
        $img_info[1]=imagesy($img);
        for($y=0;$y<$img_info[1];$y++)
        {
            $brightness_lines[$y]=0;
            for($x=0;$x<$img_info[0];$x++) $brightness_lines[$y]+=self::get_brightness_to_index($colors_index['pix'][$x][$y],$img);
            $brightness_lines[$y]/=$img_info[0];
            $brightness_img+=$brightness_lines[$y];
        }
        $brightness_img/=$img_info[1];
        $top_line=array();
        $bottom_line=array();
        //Находим все верхние и нижние границы строк текста
        for($y=2;$y<$img_info[1]-2;$y++)
        {
            //Top
            if( $brightness_lines[$y-2]>$brightness_img &&
                $brightness_lines[$y-1]>$brightness_img &&
                $brightness_lines[$y]>$brightness_img &&
                $brightness_lines[$y+1]<$brightness_img &&
                $brightness_lines[$y+2]<$brightness_img
            )
                $top_line[]=$y;
            elseif($brightness_lines[$y-2]<$brightness_img &&
                $brightness_lines[$y-1]<$brightness_img &&
                $brightness_lines[$y]>$brightness_img &&
                $brightness_lines[$y+1]>$brightness_img &&
                $brightness_lines[$y+2]>$brightness_img
            )
                $bottom_line[]=$y;
        }
        // Находим кратное 2 количество строк
        if(!count($top_line) || !count($bottom_line))
        {
            $top_line[]=0;
            $bottom_line[]=$img_info[1]-1;
        }
        elseif(count($top_line)==count($bottom_line)) /* :| ниче не делаю, сокращаю время выполнения */ ;
        elseif(count($top_line)>count($bottom_line)) $top_line=array_slice($top_line,0,count($bottom_line));
        elseif(count($top_line)<count($bottom_line)) $bottom_line=array_slice($bottom_line,0,count($top_line));
        /** TODO: Сделать подсчет последовательности строк, чтоб избежать двух подряд верхних краев строки **/
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
            if(($bottom_line[$key]+$change_size)<=$img_info[1]) $bottom_line[$key]+=$change_size;
        }
        // Нарезаем на полоски с текстом
        $img_line=array();
        foreach ($top_line as $key => $value)
        {
            $img_line[$key]=imagecreatetruecolor($img_info[0], $bottom_line[$key]-$top_line[$key]);
            imagecopy($img_line[$key],$img,0,0,0,$top_line[$key],$img_info[0],$bottom_line[$key]-$top_line[$key]);
        }
    }

    /**
     * Разбиваем текстовые строки на слова
     */
    static function divide_to_word($img)
    {
        $img_line=self::divide_to_line($img);
        $img_line[0];

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
