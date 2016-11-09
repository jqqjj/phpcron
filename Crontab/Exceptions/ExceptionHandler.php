<?php


namespace Crontab\Exceptions;

use Crontab\Logger\DriverInterface\DriverInterface;

class ExceptionHandler
{
    private static $_exception_log_driver = array();
    
    public static function addHandler(DriverInterface $driver)
    {
        self::$_exception_log_driver[get_class($driver)] = $driver;
    }
    
    public static function handler(\Exception $exception)
    {
        foreach (self::$_exception_log_driver AS $driver)
        {
            $driver->log(self::_errorFormat($exception));
        }
    }
    
    private static function _errorFormat(\Exception $exception)
    {
        $message = 'Phpcron exception: ';
        $message .= $exception->getMessage() . " in ".$exception->getFile().":".$exception->getLine() . PHP_EOL .$exception->getTraceAsString();
        
        return $message;
    }
}