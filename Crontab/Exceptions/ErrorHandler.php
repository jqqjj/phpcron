<?php


namespace Crontab\Exceptions;

use Crontab\Logger\DriverInterface\DriverInterface;

class ErrorHandler
{
    private static $_error_log_driver = array();
    
    public static function addHandler(DriverInterface $driver)
    {
        self::$_error_log_driver[get_class($driver)] = $driver;
    }
    
    public static function handler($errno, $errstr, $errfile, $errline)
    {
        foreach (self::$_error_log_driver AS $driver)
        {
            $message = 'Phpcron error(error code:'.$errno.'): ';
            $message .= $errstr . " in ".$errfile.":".$errline . PHP_EOL;
            $driver->log($message);
        }
    }
}