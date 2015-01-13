<?php
namespace phpOCR;

//
/**
 * Class cOCR
 * Класс для распознования символов по шаблону
 * @package phpOCR\cOCR
 */
class cOCR
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
	protected static $_sizeBorder = 6;

	/**
	 * Погрешность в сравнении с шаблоном в процентах
	 * @var float
	 */
	protected static $infelicity = 10;

	protected static $templateDir = false;

	/**
	 * @param string $imgFile Имя файла с исображением
	 * @return bool|resource
	 */
	public static function openImg($imgFile) {
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
				} elseif ($tmpImg = @imagecreatefromgd($imgFile)) ;
				else return false;
				break;
		}
		$imgInfo[0] = imagesx($tmpImg);
		$imgInfo[1] = imagesy($tmpImg);
		self::$img = imagecreatetruecolor($imgInfo[0] + self::$_sizeBorder, $imgInfo[1] + self::$_sizeBorder);
		$white = imagecolorallocate(self::$img, 255, 255, 255);
		imagefill(self::$img, 0, 0, $white);
		$tmpImg = self::checkBackgroundBrightness($tmpImg);
		imagecopy(self::$img, $tmpImg, self::$_sizeBorder / 2, self::$_sizeBorder / 2, 0, 0, $imgInfo[0], $imgInfo[1]);
		return self::$img;
	}

	/**
	 * Подсчитываем количество цветов в изображении и их долю в палитре
	 * Сбор индексов цвета каждого пикселя
	 * @param resource $img
	 * @return array
	 */
	protected static function getColorsIndex($img) {
		$colorsIndex = array();
		$imgInfo[0] = imagesx($img);
		$imgInfo[1] = imagesy($img);
		for ($x = 0; $x < $imgInfo[0]; $x++) {
			for ($y = 0; $y < $imgInfo[1]; $y++) {
				$pixelIndex = imagecolorat($img, $x, $y);
				$colorsIndex['pix'][$x][$y] = $pixelIndex;
				if (isset($colorsIndex['index'][$pixelIndex])){
					$colorsIndex['index'][$pixelIndex]++;
				} else {
					$colorsIndex['index'][$pixelIndex] = 1;
				}
			}
		}
		arsort($colorsIndex['index'], SORT_NUMERIC);
		$colorsIndex['count_pix'] = $imgInfo[0] * $imgInfo[1];
		foreach ($colorsIndex['index'] as $key => $value) {
			$colorsIndex['percent'][$key] = ($value / $colorsIndex['count_pix']) * 100;
		}
		return $colorsIndex;
	}

	/**
	 * Получаем индексы цветов текста и индекс цвета фона
	 * @param resource $img
	 * @return array
	 */
	protected static function getColorsIndexTextAndBackground($img) {
		$countColors = self::getColorsIndex($img);
		reset($countColors['index']);
		$backgroundIndex = key($countColors['index']);
		unset($countColors['index'][$backgroundIndex]);
		// Собираем все цвета отличные от фона
		$backgroundBrightness = self::getBrightnessToIndex($backgroundIndex, $img);
		$backgroundBrightness = $backgroundBrightness - ($backgroundBrightness * 0.2);
		foreach (array_keys($countColors['index']) as $colorKey) {
			$colorBrightness = self::getBrightnessToIndex($colorKey, $img);
			if ($backgroundBrightness < ($colorBrightness + 50)) unset($countColors['index'][$colorKey]);
		}
		$indexes['text'] = array_keys($countColors['index']);
		$indexes['background'] = $backgroundIndex;
		return $indexes;
	}

	/**
	 * Вычисления цвета фона изображения с текстом, Фон светлее текста или наоборот, если темнее то цвета инвертируются
	 * @param resource $img
	 * @return resource
	 */
	protected static function checkBackgroundBrightness($img) {
		$colorIndexes = self::getColorsIndexTextAndBackground($img);
		$backgroundColor = imagecolorsforindex($img, $colorIndexes['background']);
		$brightnessBackground = ($backgroundColor['red'] + $backgroundColor['green'] + $backgroundColor['blue']) / 3;
		$midColor = self::getMidColorToIndexes($img, $colorIndexes['text']);
		$brightnessText = ($midColor['red'] + $midColor['green'] + $midColor['blue']) / 3;
		if ($brightnessBackground < $brightnessText) imagefilter($img, IMG_FILTER_NEGATE);
		$colorIndexes = self::getColorsIndexTextAndBackground($img);
		$img = self::chengeColor($img, $colorIndexes['background'], 255, 255, 255);
		return $img;
	}

	/**
	 * Подсчитывает средний цвет из массива индексов
	 * @param resource $img
	 * @param array    $arrayIndexes
	 * @return array
	 */
	protected static function getMidColorToIndexes($img, $arrayIndexes) {
		$midColor['red'] = 0;
		$midColor['green'] = 0;
		$midColor['blue'] = 0;
		foreach ($arrayIndexes as $key => $value) {
			$color = imagecolorsforindex($img, $key);
			$midColor['red'] += $color['red'];
			$midColor['green'] += $color['green'];
			$midColor['blue'] += $color['blue'];
		}
		$countIndexes = count($arrayIndexes);
		foreach ($midColor as &$value) $value /= $countIndexes; //Вычисляем средний цвет текста
		unset($value);
		return $midColor;
	}

	public static function getSizeBorder() {
		return self::$_sizeBorder;
	}

	public static function setSizeBotder($val) {
		self::$_sizeBorder = $val;
	}

	public static function getInfelicity() {
		return self::$infelicity;
	}

	public static function setInfelicity($val) {
		self::$infelicity = $val;
	}

	/**
	 * @return boolean|string
	 */
	public static function getTemplateDir() {
		return self::$templateDir;
	}

	/**
	 * @param boolean|string $templateDir
	 */
	public static function setTemplateDir($templateDir) {
		self::$templateDir = $templateDir;
	}

	/**
	 * Разбивает рисунок на строки с текстом
	 * @param resource $img
	 * @return array
	 */
	protected static function divideToLine($img) {
		$imgInfo['x'] = imagesx($img);
		$imgInfo['y'] = imagesy($img);
		$coordinates = self::coordinatesImg($img);
		$topLine = $coordinates['start'];
		$bottomLine = $coordinates['end'];
		// Ищем самую низкую строку для захвата заглавных букв
		$hMin = 99999;
		foreach ($topLine as $key => $value) {
			$hLine = $bottomLine[$key] - $topLine[$key];
			if ($hMin > $hLine) $hMin = $hLine;
		}

		// Увеличим все строки на пятую часть самой маленькой для захвата заглавных букв хвостов букв у и т.д.
		$changeSize = 0.2 * $hMin;
		foreach ($topLine as $key => $value) {
			if (($topLine[$key] - $changeSize) >= 0) $topLine[$key] -= $changeSize;
			if (($bottomLine[$key] + $changeSize) <= ($imgInfo['y'] - 1)) $bottomLine[$key] += $changeSize;
		}
		// Нарезаем на полоски с текстом
		$imgLine = array();
		foreach ($topLine as $key => $value) {
			$width = $imgInfo['x'] + self::$_sizeBorder;
			$hight = $bottomLine[$key] - $topLine[$key] + self::$_sizeBorder;
			$imgLine[$key] = imagecreatetruecolor($width, $hight);
			$white = imagecolorallocate($imgLine[$key], 255, 255, 255);
			imagefill($imgLine[$key], 0, 0, $white);
			$dst_x = self::$_sizeBorder / 2;
			$dst_y = self::$_sizeBorder / 2;
			$src_h = $bottomLine[$key] - $topLine[$key];
			imagecopy($imgLine[$key], $img, $dst_x, $dst_y, 0, $topLine[$key], $imgInfo['x'], $src_h);
		}
		return $imgLine;
	}

	/**
	 * Разбиваем текстовые строки на слова
	 * @param resource $img
	 * @return array
	 */
	protected static function divideToWord($img) {
		$imgLine = self::divideToLine($img);
		$imgWord = array();
		foreach ($imgLine as $lineKey => $lineValue) {
			$imgInfo['x'] = imagesx($lineValue);
			$imgInfo['y'] = imagesy($lineValue);
			$coordinates = self::coordinatesImg($lineValue, true);
			$beginWord = $coordinates['start'];
			$endWord = $coordinates['end'];
			// Нарезаем на слова
			foreach ($beginWord as $beginKey => $beginValue) {
				$width = $endWord[$beginKey] - $beginValue + self::$_sizeBorder;
				$hight = $imgInfo['y'] + self::$_sizeBorder;
				$imgWord[$lineKey][] = imagecreatetruecolor($width, $hight);
				end($imgWord[$lineKey]);
				$keyArrayWord = key($imgWord[$lineKey]);
				$white = imagecolorallocate($imgWord[$lineKey][$keyArrayWord], 255, 255, 255);
				imagefill($imgWord[$lineKey][$keyArrayWord], 0, 0, $white);
				$dst_x = self::$_sizeBorder / 2;
				$dst_y = self::$_sizeBorder / 2;
				$src_w = $endWord[$beginKey] - $beginValue;
				imagecopy($imgWord[$lineKey][$keyArrayWord], $lineValue, $dst_x, $dst_y, $beginValue, 0, $src_w, $imgInfo['y']);
			}
		}
		return $imgWord;
	}

	/**
	 * Разбивает рисунок с текстом на маленькие рисунки с символом
	 * @param resource $img
	 * @return array
	 */
	public static function divideToChar($img) {
		$imgWord = self::divideToWord($img);
		$imgChar = array();
		foreach ($imgWord as $lineKey => $lineValue) {
			foreach ($lineValue as $wordKey => $wordValue) {
				$imgInfo['x'] = imagesx($wordValue);
				$imgInfo['y'] = imagesy($wordValue);
				$coordinates = self::coordinatesImg($wordValue, true, 1);
				$beginChar = $coordinates['start'];
				$endWord = $coordinates['end'];
				// Нарезаем на символы
				foreach ($beginChar as $beginKey => $beginValue) {
					$tmpImg = imagecreatetruecolor($endWord[$beginKey] - $beginValue, $imgInfo['y']);
					$white = imagecolorallocate($tmpImg, 255, 255, 255);
					imagefill($tmpImg, 0, 0, $white);
					imagecopy($tmpImg, $wordValue, 0, 0, $beginValue, 0, $endWord[$beginKey] - $beginValue, $imgInfo['y']);
					$w = imagesx($tmpImg);
					$coordinatesChar = self::coordinatesImg($tmpImg, false, 1);
					$imgChar[$lineKey][$wordKey][] = imagecreatetruecolor($w, $coordinatesChar['end'][0] - $coordinatesChar['start'][0]);
					end($imgChar[$lineKey][$wordKey]);
					$keyArrayWord = key($imgChar[$lineKey][$wordKey]);
					$white = imagecolorallocate($imgChar[$lineKey][$wordKey][$keyArrayWord], 255, 255, 255);
					imagefill($imgChar[$lineKey][$wordKey][$keyArrayWord], 0, 0, $white);
					imagecopy($imgChar[$lineKey][$wordKey][$keyArrayWord], $tmpImg, 0, 0, 0, $coordinatesChar['start'][0], $w, $coordinatesChar['end'][0]);
				}
			}
		}
		return $imgChar;
	}

	/**
	 * Поиск точек разделения изображения
	 * @param resource $img    Изображения для вычесления строк
	 * @param bool     $rotate Поворачивать изображени или нет
	 * @param int      $border Размер границы одной части текста до другой
	 * @return array координаты для обрезания
	 */
	protected static function coordinatesImg($img, $rotate = false, $border = 2) {
		if ($rotate) {
			$white = imagecolorallocate($img, 255, 255, 255);
			$img = imagerotate($img, 270, $white);
		}
		// Находим среднее значение яркости каждой пиксельной строки и всего рисунка
		$brightnessLines = array();
		$brightnessImg = 0;
		$boldImg = self::boldText($img, 'width');
		$colorsIndexBold = self::getColorsIndex($boldImg);
		$colorsIndex = self::getColorsIndex($img);
		$imgInfo['x'] = imagesx($boldImg);
		$imgInfo['y'] = imagesy($boldImg);
		for ($positionY = 0; $positionY < $imgInfo['y']; $positionY++) {
			$brightnessLines[$positionY] = 0;
			$brightnessLinesNormal[$positionY] = 0;
			for ($x = 0; $x < $imgInfo['x']; $x++) {
				$brightnessLines[$positionY] += self::getBrightnessToIndex($colorsIndexBold['pix'][$x][$positionY], $boldImg);
				$brightnessLinesNormal[$positionY] += self::getBrightnessToIndex($colorsIndex['pix'][$x][$positionY], $img);
			}
			$brightnessLines[$positionY] /= $imgInfo['x'];
			$brightnessImg += $brightnessLinesNormal[$positionY] / $imgInfo['x'];
		}
		$brightnessImg /= $imgInfo['y'];
		$coordinates['start'] = array();
		$coordinates['end'] = array();
		//search border of text
		for ($positionY = $border; $positionY < $imgInfo['y'] - $border; $positionY++) {
			if (self::isTopBorder($brightnessLines, $brightnessImg, $positionY, $border))
				$coordinates['start'][] = $positionY;
			elseif (self::isBottomBorder($brightnessLines, $brightnessImg, $positionY, $border))
				$coordinates['end'][] = $positionY;
			elseif (self::isSpaceBetweenLines($brightnessLines, $brightnessImg, $positionY, $border)) {
				$coordinates['start'][] = $positionY;
				$coordinates['end'][] = $positionY;
			}
		}
		return $coordinates;
	}

	protected static function isTopBorder($brightnessLines, $brightnessImg, $positionY, $border){
		return ($brightnessLines[$positionY - $border] > $brightnessImg
			&& ($brightnessLines[$positionY - ($border - 1)] > $brightnessImg || $border == 1)
			&& $brightnessLines[$positionY] > $brightnessImg
			&& ($brightnessLines[$positionY + ($border - 1)] < $brightnessImg || $border == 1)
			&& $brightnessLines[$positionY + $border] < $brightnessImg);
	}

	protected static function isBottomBorder($brightnessLines, $brightnessImg, $positionY, $border){
		return ($brightnessLines[$positionY - $border] < $brightnessImg
			&& ($brightnessLines[$positionY - ($border - 1)] < $brightnessImg || $border == 1)
			&& $brightnessLines[$positionY] > $brightnessImg
			&& ($brightnessLines[$positionY + ($border - 1)] > $brightnessImg || $border == 1)
			&& $brightnessLines[$positionY + $border] > $brightnessImg);
	}

	protected static function isSpaceBetweenLines($brightnessLines, $brightnessImg, $positionY, $border){
		return ($brightnessLines[$positionY - $border] < $brightnessImg
			&& $brightnessLines[$positionY] > $brightnessImg
			&& $brightnessLines[$positionY + $border] < $brightnessImg
			&& $border == 1);
	}

	/**
	 * Вычисляем яркость цвета по его индексу
	 * @param int      $colorIndex
	 * @param resource $img
	 * @return int
	 */
	protected static function getBrightnessToIndex($colorIndex, $img = null) {
		if ($img === null) $img = self::$img;
		$color = imagecolorsforindex($img, $colorIndex);
		return ($color['red'] + $color['green'] + $color['blue']) / 3;
	}

	/**
	 * Заливаем текст для более точного определения по яркости
	 * @param resource $img
	 * @param string   $bType тип утолщения width height
	 * @return resource
	 */
	protected static function boldText($img, $bType = 'width') {
		$colorIndexes = self::getColorsIndexTextAndBackground($img);
		$imgInfo['x'] = imagesx($img);
		$imgInfo['y'] = imagesy($img);
		$blurImg = imagecreatetruecolor($imgInfo['x'], $imgInfo['y']);
		imagecopy($blurImg, $img, 0, 0, 0, 0, $imgInfo['x'], $imgInfo['y']);
		$black = imagecolorallocate($blurImg, 0, 0, 0);
		$boldSize = 10; //Величина утолщения
		for ($x = 0; $x < $imgInfo['x']; $x++) {
			for ($y = 0; $y < $imgInfo['y']; $y++) {
				if (in_array(imagecolorat($img, $x, $y), $colorIndexes['text'])) {
					switch ($bType) {
						case 'width':
							imagefilledrectangle($blurImg, $x - $boldSize, $y, $x + $boldSize, $y, $black);
							break;
						case 'height':
							imagefilledrectangle($blurImg, $x, $y - $boldSize, $x, $y + $boldSize, $black);
							break;
						default:
							break;
					}
				}
			}
		}
		return $blurImg;
	}

	/**
	 * Прапорциональное изменение размера изображения
	 * @param resource $img изображение
	 * @param int      $width   ширина
	 * @param int      $hight   высота
	 * @return resource
	 */
	protected static function resizeImg($img, $width, $hight) {
		$imgInfo['x'] = imagesx($img);
		$imgInfo['y'] = imagesy($img);
		$newImg = imagecreatetruecolor($width, $hight);
		$white = imagecolorallocate($newImg, 255, 255, 255);
		imagefill($newImg, 0, 0, $white);
		if ($imgInfo['x'] < $imgInfo['y']) {
			$width = $imgInfo['x'] * ($hight / $imgInfo['y']);
		} else {
			$hight = $imgInfo['y'] * ($width / $imgInfo['x']);
		}
		imagecopyresampled($newImg, $img, 0, 0, 0, 0, $width, $hight, $imgInfo['x'], $imgInfo['y']);
		return $newImg;
	}

	/**
	 * Изменение цвета в изображении, если изображение открыто через imagecreate,
	 * то можно просто поменять цвет индекса через функцию imagecolorset
	 * @param resource $img
	 * @param int      $colorIndex индекс цвета который нужно изменить
	 * @param int      $red
	 * @param int      $green
	 * @param int      $blue
	 * @return resource
	 */
	protected static function chengeColor($img, $colorIndex, $red = 0, $green = 0, $blue = 0) {
		$imgInfo['x'] = imagesx($img);
		$imgInfo['y'] = imagesy($img);
		$newColor = imagecolorallocate($img, $red, $green, $blue);
		for ($x = 0; $x < $imgInfo['x']; $x++) {
			for ($y = 0; $y < $imgInfo['y']; $y++) {
				if (imagecolorat($img, $x, $y) == $colorIndex) imagesetpixel($img, $x, $y, $newColor);
			}
		}
		return $img;
	}

	/**
	 * Генерация шаблона из одного символа
	 * @param resource $img
	 * @param int      $width
	 * @param int      $hight
	 * @return string
	 */
	public static function generateTemplateChar($img, $width = 15, $hight = 16) {
		$imgInfo['x'] = imagesx($img);
		$imgInfo['y'] = imagesy($img);
		if ($imgInfo['x'] != $width || $imgInfo['y'] != $hight) $img = self::resizeImg($img, $width, $hight);
		$colorIndexes = self::getColorsIndexTextAndBackground($img);
		$line = '';
		for ($y = 0; $y < $hight; $y++) {
			for ($x = 0; $x < $width; $x++) {
				if (array_search(imagecolorat($img, $x, $y), $colorIndexes['text']) !== false) $line .= '1';
				else $line .= '0';
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
	public static function generateTemplate($chars, $imgs) {
		if (count($chars) != count($imgs)) return false;
		$template = array();
		foreach ($chars as $charKey => $charValue) $template[$charValue] = self::generateTemplateChar($imgs[$charKey]);
		return $template;
	}

	/**
	 * Сохранение шаблона в файл
	 * @param string $name     Имя шаблона
	 * @param array  $template шаблон
	 */
	public static function saveTemplate($name, $template) {
		$json = json_encode($template, JSON_FORCE_OBJECT);
		$name = __DIR__ . '/template/' . $name . '.json';
		file_put_contents($name, $json);
	}

	/**
	 * Загрузка шаблона из файла
	 * @param string $name имя шаблона
	 * @return array|bool
	 */
	public static function loadTemplate($name) {
		if(!self::getTemplateDir()){
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
	protected static function defineChar($img, $template) {
		$templateChar = self::generateTemplateChar($img);
		foreach ($template as $key => $value) {
			if (self::compareChar($templateChar, $key)) return $value;
		}
		return "?";
	}

	/**
	 * Сравнивает шаблоны символов на похожесть
	 * @param string $char1 символ 1 в виде шаблона
	 * @param string $char2 символ 1 в виде шаблона
	 * @return bool
	 */
	protected static function compareChar($char1, $char2) {
		$difference = levenshtein($char1, $char2);
		if ($difference < strlen($char1) * (self::$infelicity / 100)) return true;
		else return false;
	}

	/**
	 * Распознование текста на изображении
	 * @param       $imgFile
	 * @param array $template
	 * @return string
	 */
	public static function defineImg($imgFile, $template) {
		$img = self::openImg($imgFile);
		$imgs = self::divideToChar($img);
		$text = '';
		foreach ($imgs as $line) {
			foreach ($line as $word) {
				foreach ($word as $char) {
					$text .= cOCR::defineChar($char, $template);
				}
				if (count($word) > 1) $text .= " ";
			}
			if (count($line) > 1) $text .= "\n";
		}
		return trim($text);
	}

	/**
	 * Находит уникальные символы в массиве символов
	 * @param array $imgs Масси изображений символов
	 * @return array Массив изображений уникальных символов
	 */
	public static function findUniqueChar($imgs) {
		$templateChars = array();
		foreach ($imgs as $key => $value) {
			$templateChars[$key] = self::generateTemplateChar($value);
		}
		$templateChars = array_unique($templateChars);
		$cloneKey = array();
		foreach ($templateChars as $key => $value) {
			foreach ($templateChars as $tmpKey => $tmpValue)
				if (self::compareChar($value, $tmpValue) && $key < $tmpKey) $cloneKey[$tmpKey] = '';
		}
		foreach ($cloneKey as $key => $value) unset($templateChars[$key]);

		$newImgs = array();
		foreach ($templateChars as $key => $value) {
			$newImgs[] = $imgs[$key];
		}
		return $newImgs;
	}
}
