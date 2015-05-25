<?php
/**
 * Created by PhpStorm.
 * User: ec
 * Date: 25.05.15
 * Time: 1:14
 * Project: gd2-php-ocr
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */

spl_autoload_register(function ($class)
{
    $prefix = 'bpteam';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});