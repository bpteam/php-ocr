<?php
namespace php_ocr\c_ocr;

//
/**
 * Class c_ocr
 * Класс для распознования символов по шаблону
 * @package php_ocr\c_ocr
 */
class c_ocr
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
    private static $size_border = 6;

    /**
     * Погрешность в сравнении с шаблоном в процентах
     * @var float
     */
    private static $infelicity = 10;
    /**
     * @param string $img_file Имя файла с исображением
     * @return bool|resource
     */
    static function open_img($img_file)
    {
        $info=@getimagesize($img_file);
        switch($info[2])
        {
            case IMAGETYPE_PNG :
                $tmp_img2=imagecreatefrompng($img_file);
                $tmp_img=imagecreatetruecolor($info[0], $info[1]);
                $white=imagecolorallocate($tmp_img, 255, 255, 255);
                imagefill($tmp_img, 0, 0, $white);
                imagecopy($tmp_img, $tmp_img2, 0, 0, 0, 0, $info[0], $info[1]);
                imagedestroy($tmp_img2);
                break;
            case IMAGETYPE_JPEG :
                $tmp_img = imagecreatefromjpeg($img_file);
                break;
            case IMAGETYPE_GIF :
                $tmp_img = imagecreatefromgif($img_file);
                break;
            default:
                if($tmp_img2 = @imagecreatefromstring($img_file))
                {
                    $info[0]=imagesx($tmp_img2);
                    $info[1]=imagesy($tmp_img2);
                    $tmp_img=imagecreatetruecolor($info[0], $info[1]);
                    $white=imagecolorallocate($tmp_img, 255, 255, 255);
                    imagefill($tmp_img, 0, 0, $white);
                    imagecopy($tmp_img, $tmp_img2, 0, 0, 0, 0, $info[0], $info[1]);
                    imagedestroy($tmp_img2);
                }
                elseif($tmp_img = @imagecreatefromgd($img_file));
                else return false;
                break;
        }
        $img_info[0]=imagesx($tmp_img);
        $img_info[1]=imagesy($tmp_img);
        //Увеличиваем с каждой стороны на 4 пикселя чтоб избежать начала текста близко к краю изображения
        self::$img = imagecreatetruecolor($img_info[0]+self::$size_border, $img_info[1]+self::$size_border);
        $white=imagecolorallocate(self::$img, 255, 255, 255);
        imagefill(self::$img, 0, 0, $white);
        $tmp_img=self::check_background_brightness($tmp_img);
        imagecopy(self::$img, $tmp_img, self::$size_border/2, self::$size_border/2, 0, 0, $img_info[0], $img_info[1]);
        return self::$img;
    }

    /**
     * Подсчитываем количество цветов в изображении и их долю в палитре
     * Сбор индексов цвета каждого пикселя
     * @param resource $img
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
     * Получаем индексы цветов текста и индекс цвета фона
     * @param resource $img
     * @return array
     */
    static function get_colors_index_text_and_background($img)
    {
        $count_colors=self::get_colors_index($img);
        reset($count_colors['index']);
        $background_index=key($count_colors['index']);
        unset($count_colors['index'][$background_index]);
        // Собираем все цвета отличные от фона
        $background_brightness=self::get_brightness_to_index($background_index,$img);
        $background_brightness=$background_brightness-($background_brightness*0.2);
        foreach ($count_colors['index'] as $key => $value)
        {
            $color_brightness=self::get_brightness_to_index($key,$img);
            if($background_brightness<($color_brightness+50)) unset($count_colors['index'][$key]);
        }
        $indexes['text']=array_keys($count_colors['index']);
        $indexes['background']=$background_index;
        return $indexes;
    }

    /**
     * Вычисления цвета фона изображения с текстом, Фон светлее текста или наоборот, если темнее то цвета инвертируются
     * @param resource $img
     * @return resource
     */
    static function check_background_brightness($img)
    {
        $color_indexes=self::get_colors_index_text_and_background($img);
        $background_color=imagecolorsforindex($img, $color_indexes['background']);
        $brightness_background=($background_color['red']+$background_color['green']+$background_color['blue'])/3;
        $mid_color=self::get_mid_color_to_indexes($img,$color_indexes['text']);
        $brightness_text=($mid_color['red']+$mid_color['green']+$mid_color['blue'])/3;
        if($brightness_background<$brightness_text) imagefilter($img,IMG_FILTER_NEGATE); //Инвертируем если фон темнее чем текст
        $color_indexes=self::get_colors_index_text_and_background($img);
        $img=self::chenge_color($img,$color_indexes['background'],255,255,255);
        return $img;
    }

    /**
     * Подсчитывает средний цвет из массива индексов
     * @param resource $img
     * @param array $array_indexes
     * @return array
     */
    static function get_mid_color_to_indexes($img,$array_indexes)
    {
        $mid_color['red']=0;
        $mid_color['green']=0;
        $mid_color['blue']=0;
        foreach ($array_indexes as $key => $value)
        {
            $color=imagecolorsforindex($img, $key);
            $mid_color['red']+=$color['red'];
            $mid_color['green']+=$color['green'];
            $mid_color['blue']+=$color['blue'];
        }
        $count_indexes=count($array_indexes);
        foreach ($mid_color as &$value) $value/=$count_indexes; //Вычисляем средний цвет текста
        unset($value);
        return $mid_color;
    }

    public static function get_size_border()
    {
        return self::$size_border;
    }

    public static function set_size_botder($val)
    {
        self::$size_border = $val;
    }

    public static function get_infelicity()
    {
        return self::$infelicity;
    }

    public static function set_infelicity($val)
    {
        self::$infelicity = $val;
    }

    /**
     * Разбивает рисунок на строки с текстом
     * @param resource $img
     * @return array
     */
    static function divide_to_line($img)
    {
        $img_info['x']=imagesx($img);
        $img_info['y']=imagesy($img);
        $coordinates=self::coordinates_img($img);
        $top_line=$coordinates['start'];
        $bottom_line=$coordinates['end'];
        // Ищем самую низкую строку для захвата заглавных букв
        $h_min=99999;
        foreach ($top_line as $key => $value)
        {
            $h_line=$bottom_line[$key]-$top_line[$key];
            if($h_min>$h_line) $h_min=$h_line;
        }

        // Увеличим все строки на пятую часть самой маленькой для захвата заглавных букв м хвостов букв
        $change_size=0.2*$h_min;
        foreach ($top_line as $key => $value)
        {
            if(($top_line[$key]-$change_size)>=0) $top_line[$key]-=$change_size;
            if(($bottom_line[$key]+$change_size)<=($img_info['y']-1))$bottom_line[$key]+=$change_size;
        }
        // Нарезаем на полоски с текстом
        $img_line=array();
        foreach ($top_line as $key => $value)
        {
            $img_line[$key]=imagecreatetruecolor($img_info['x']+self::$size_border, $bottom_line[$key]-$top_line[$key]+self::$size_border);
            $white=imagecolorallocate($img_line[$key], 255, 255, 255);
            imagefill($img_line[$key], 0, 0, $white);
            imagecopy($img_line[$key],$img,self::$size_border/2,self::$size_border/2,0,$top_line[$key],$img_info['x'],$bottom_line[$key]-$top_line[$key]);
        }
        return $img_line;
    }

    /**
     * Разбиваем текстовые строки на слова
     * @param resource $img
     * @return array
     */
    static function divide_to_word($img)
    {
        $img_line=self::divide_to_line($img);
        $img_word=array();
        foreach ($img_line as $line_key => $line_value)
        {
            $img_info['x']=imagesx($line_value);
            $img_info['y']=imagesy($line_value);
            $coordinates=self::coordinates_img($line_value,true);
            $begin_word=$coordinates['start'];
            $end_word=$coordinates['end'];
            // Нарезаем на слова
            foreach ($begin_word as $begin_key => $begin_value)
            {
                $img_word[$line_key][]=imagecreatetruecolor($end_word[$begin_key]-$begin_value+self::$size_border, $img_info['y']+self::$size_border);
                end($img_word[$line_key]);
                $key_array_word=key($img_word[$line_key]);
                $white=imagecolorallocate($img_word[$line_key][$key_array_word], 255, 255, 255);
                imagefill($img_word[$line_key][$key_array_word], 0, 0, $white);
                imagecopy($img_word[$line_key][$key_array_word],$line_value,self::$size_border/2,self::$size_border/2,$begin_value,0,$end_word[$begin_key]-$begin_value,$img_info['y']);
            }
        }
        return $img_word;
    }

    /**
     * Разбивает рисунок с текстом на маленькие рисунки с символом
     * @param resource $img
     * @return array
     */
    static function divide_to_char($img)
    {
        $img_word=self::divide_to_word($img);
        $img_char=array();
        foreach ($img_word as $line_key => $line_value)
        {
            foreach ($line_value as $word_key => $word_value)
            {
                $img_info['x']=imagesx($word_value);
                $img_info['y']=imagesy($word_value);
                $coordinates=self::coordinates_img($word_value,true,1);
                $begin_char=$coordinates['start'];
                $end_word=$coordinates['end'];
                // Нарезаем на символы
                foreach ($begin_char as $begin_key => $begin_value)
                {
                    $tmp_img=imagecreatetruecolor($end_word[$begin_key]-$begin_value, $img_info['y']);
                    $white=imagecolorallocate($tmp_img, 255, 255, 255);
                    imagefill($tmp_img, 0, 0, $white);
                    imagecopy($tmp_img,$word_value,0,0,$begin_value,0,$end_word[$begin_key]-$begin_value,$img_info['y']);
                    $w=imagesx($tmp_img);
                    $coordinates_char=self::coordinates_img($tmp_img,false,1);
                    $img_char[$line_key][$word_key][]=imagecreatetruecolor($w,$coordinates_char['end'][0]-$coordinates_char['start'][0]);
                    end($img_char[$line_key][$word_key]);
                    $key_array_word=key($img_char[$line_key][$word_key]);
                    $white=imagecolorallocate($img_char[$line_key][$word_key][$key_array_word], 255, 255, 255);
                    imagefill($img_char[$line_key][$word_key][$key_array_word], 0, 0, $white);
                    imagecopy($img_char[$line_key][$word_key][$key_array_word],$tmp_img,0,0,0,$coordinates_char['start'][0],$w,$coordinates_char['end'][0]);
                }
            }
        }
        return $img_char;
    }

    /**
     * Поиск точек разделения изображения
     * @param resource $img Изображения для вычесления строк
     * @param bool $rotate Поворачивать изображени или нет
     * @param int $border Размер границы одной части текста до другой
     * @return array координаты для обрезания
     */
    static function coordinates_img($img,$rotate=false,$border=2)
    {
        if($rotate)
        {
            $white=imagecolorallocate($img, 255, 255, 255);
            $img=imagerotate($img , 270 , $white);
        }
        // Находим среднее значение яркости каждой пиксельной строки и всего рисунка
        $brightness_lines=array();
        $brightness_img=0;
        $bold_img=self::bold_text($img,'width');
        $colors_index_bold=self::get_colors_index($bold_img);
        $colors_index=self::get_colors_index($img);
        $img_info['x']=imagesx($bold_img);
        $img_info['y']=imagesy($bold_img);
        for($y=0;$y<$img_info['y'];$y++)
        {
            $brightness_lines[$y]=0;
            $brightness_lines_normal[$y]=0;
            for($x=0;$x<$img_info['x'];$x++)
            {
                $brightness_lines[$y]+=self::get_brightness_to_index($colors_index_bold['pix'][$x][$y],$bold_img);
                $brightness_lines_normal[$y]+=self::get_brightness_to_index($colors_index['pix'][$x][$y],$img);
            }
            $brightness_lines[$y]/=$img_info['x'];
            $brightness_img+=$brightness_lines_normal[$y]/$img_info['x'];
        }
        $brightness_img/=$img_info['y'];
        $coordinates['start']=array();
        $coordinates['end']=array();
        //Находим все верхние и нижние границы строк текста
        for($y=$border;$y<$img_info['y']-$border;$y++)
        {
            //Top
            if( $brightness_lines[$y-$border]>$brightness_img &&
                ($brightness_lines[$y-($border-1)]>$brightness_img || $border==1) &&
                $brightness_lines[$y]>$brightness_img &&
                ($brightness_lines[$y+($border-1)]<$brightness_img || $border==1) &&
                $brightness_lines[$y+$border]<$brightness_img
            )
                $coordinates['start'][]=$y;
            //Bottom
            elseif($brightness_lines[$y-$border]<$brightness_img &&
                ($brightness_lines[$y-($border-1)]<$brightness_img || $border==1) &&
                $brightness_lines[$y]>$brightness_img &&
                ($brightness_lines[$y+($border-1)]>$brightness_img || $border==1) &&
                $brightness_lines[$y+$border]>$brightness_img
            )
                $coordinates['end'][]=$y;
            elseif($brightness_lines[$y-$border]<$brightness_img &&
                $brightness_lines[$y]>$brightness_img &&
                $brightness_lines[$y+$border]<$brightness_img &&
                $border==1
            )
            {
                $coordinates['start'][]=$y;
                $coordinates['end'][]=$y;
            }
        }
        return $coordinates;
    }
    /**
     * Вычисляем яркость цвета по его индексу
     * @param int $color_index
     * @param resource $img
     * @return int
     */
    static function get_brightness_to_index($color_index,$img=null)
    {
        if($img===null) $img=self::$img;
        $color=imagecolorsforindex($img, $color_index);
        return ($color['red']+$color['green']+$color['blue'])/3;
    }

    /**
     * Заливаем текст для более точного определения по яркости
     * @param resource $img
     * @param string $b_type тип утолщения width height
     * @return resource
     */
    static function bold_text($img,$b_type='width')
    {
        $color_indexes=self::get_colors_index_text_and_background($img);
        $img_info['x']=imagesx($img);
        $img_info['y']=imagesy($img);
        $blur_img=imagecreatetruecolor($img_info['x'],$img_info['y']);
        imagecopy($blur_img, $img, 0, 0, 0, 0, $img_info['x'], $img_info['y']);
        $black=imagecolorallocate($blur_img, 0, 0, 0);
        $bold_size=10; //Величина утолщения
        for($x=0;$x<$img_info['x'];$x++)
        {
            for($y=0;$y<$img_info['y'];$y++)
            {
                if(array_search(imagecolorat($img,$x,$y),$color_indexes['text'])!==false)
                {
                    switch ($b_type)
                    {
                        case 'width': imagefilledrectangle($blur_img,$x-$bold_size,$y,$x+$bold_size,$y,$black);
                            break;
                        case 'height': imagefilledrectangle($blur_img,$x,$y-$bold_size,$x,$y+$bold_size,$black);
                            break;
                        default: break;
                    }
                }
            }
        }
        return $blur_img;
    }

    /**
     * Прапорциональное изменение размера изображения
     * @param resource $img изображение
     * @param int $w ширина
     * @param int $h высота
     * @return resource
     */
    static function resize_img($img,$w,$h)
    {
        $img_info['x']=imagesx($img);
        $img_info['y']=imagesy($img);
        $new_img=imagecreatetruecolor($w,$h);
        $white=imagecolorallocate($new_img, 255, 255, 255);
        imagefill($new_img, 0, 0, $white);
        if ($img_info['x']<$img_info['y'])$w=$img_info['x']*($h/$img_info['y']);
        else $h=$img_info['y']*($w/$img_info['x']);
        imagecopyresampled($new_img, $img, 0, 0, 0, 0, $w, $h, $img_info['x'], $img_info['y']);
        return $new_img;
    }

    /**
     * Изменение цвета в изображении, если изображение открыто через imagecreate,
     * то можно просто поменять цвет индекса через функцию imagecolorset
     * @param resource $img
     * @param int $color_index индекс цвета который нужно изменить
     * @param int $red
     * @param int $green
     * @param int $blue
     * @return resource
     */
    static function chenge_color($img,$color_index,$red=0,$green=0,$blue=0)
    {
        $img_info['x']=imagesx($img);
        $img_info['y']=imagesy($img);
        $new_color=imagecolorallocate($img, $red, $green, $blue);
        for($x=0;$x<$img_info['x'];$x++)
        {
            for($y=0;$y<$img_info['y'];$y++)
            {
                if(imagecolorat($img,$x,$y)==$color_index) imagesetpixel($img,$x,$y,$new_color);
            }
        }
        return $img;
    }

    /**
     * Генерация шаблона из одного символа
     * @param resource $img
     * @param int $w
     * @param int $h
     * @return string
     */
    static function generate_template_char($img,$w=15,$h=16)
    {
        $img_info['x']=imagesx($img);
        $img_info['y']=imagesy($img);
        if($img_info['x']!=$w || $img_info['y']!=$h) $img=self::resize_img($img,$w,$h);
        $color_indexes=self::get_colors_index_text_and_background($img);
        $line='';
        for($y=0;$y<$h;$y++)
        {
            for($x=0;$x<$w;$x++)
            {
                if(array_search(imagecolorat($img,$x,$y),$color_indexes['text'])!==false) $line.='1';
                else $line.='0';
            }
        }
        return $line;
    }

    /**
     * Генерация шаблона для распознования
     * @param array $chars Массив string из символов в последовательности как на картинках
     * @param array $imgs Массив resource из изображений для создания шаблона
     * @return array|bool
     */
    static function generate_template($chars,$imgs)
    {
        if(count($chars)!=count($imgs)) return false;
        $tamplate=array();
        foreach ($chars as $char_key => $char_value) $tamplate["{$char_value}"]=self::generate_template_char($imgs[$char_key]);
        return $tamplate;
    }

    /**
     * Сохранение шаблона в файл
     * @param string $name Имя шаблона
     * @param array $template шаблон
     */
    static function save_template($name,$template)
    {
        $json=json_encode($template,JSON_FORCE_OBJECT);
        $name=dirname(__FILE__).'/template/'.$name.'.json';
        $fh=fopen($name,'w');
        fwrite($fh,$json);
        fclose($fh);
    }

    /**
     * Загрузка шаблона из файла
     * @param string $name имя шаблона
     * @return array|bool
     */
    static function load_template($name)
    {
        $name=dirname(__FILE__).'/template/'.$name.'.json';
        $json=file_get_contents($name);
        return json_decode($json,true);
    }

    /**
     * Распознование символа по шаблону
     * @param resource $img
     * @param array $template
     * @return int|string
     */
    static function define_char($img,$template)
    {
        $template_char=self::generate_template_char($img);
        foreach ($template as $key => $value)
        {
            if(self::compare_char($template_char,$value)) return $key;
        }
        return "?";
    }

    /**
     * Сравнивает шаблоны символов на похожесть
     * @param string $char1 символ 1 в виде шаблона
     * @param string $char2 символ 1 в виде шаблона
     * @return bool
     */
    static function compare_char($char1,$char2)
    {
        $difference=levenshtein($char1,$char2);
        if($difference<strlen($char1)*(self::$infelicity/100)) return true;// Разница на количество символов в строке в процентах изменяется похожесть символа
        else return false;
    }
    /**
     * Распознование текста на изображении
     * @param resource $img
     * @param array $template
     * @return string
     */
    static function define_img($img,$template)
    {
        $imgs=self::divide_to_char($img);
        $text='';
        foreach ($imgs as $line)
        {
            foreach ($line as $word)
            {
                foreach ($word as $char)
                {
                    $text.=c_ocr::define_char($char,$template);
                }
                if(count($word)>1) $text.=" ";
            }
            if(count($line)>1) $text.="\n";
        }
        return trim($text);
    }

    /**
     * Находит уникальные символы в массиве символов
     * @param array $imgs Масси изображений символов
     * @return array Массив изображений уникальных символов
     */
    static function find_unique_char($imgs)
    {
        $template_chars=array();
        foreach ($imgs as $key => $value)
        {
            $template_chars[$key]=self::generate_template_char($value);
        }
        $template_chars=array_unique($template_chars);
        //$clone=$template_chars;
        $clone_key=array();
        foreach ($template_chars as $key => $value)
        {
            foreach($template_chars as $tmp_key => $tmp_value)
                if(self::compare_char($value,$tmp_value) && $key<$tmp_key) $clone_key[$tmp_key]='';
        }
        foreach ($clone_key as $key => $value) unset($template_chars[$key]);

        $new_imgs=array();
        foreach ($template_chars as $key => $value)
        {
            $new_imgs[]=$imgs[$key];
        }
        return $new_imgs;
    }
}
