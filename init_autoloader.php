<?php


define("PHPCRON_PATH", realpath(getcwd()));

spl_autoload_register('Crontab_Auto_Loader');

function Crontab_Auto_Loader($class)
{
    $class_info = explode('\\', $class);
    $ext = '.php';
    $file = implode(DIRECTORY_SEPARATOR, $class_info).$ext;
    if(file_exists(PHPCRON_PATH.DIRECTORY_SEPARATOR.$file))
    {
        require $file;
    }
}