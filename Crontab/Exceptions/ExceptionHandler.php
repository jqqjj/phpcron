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
    
    public static function handler()
    {
        $exception = func_get_args();
        foreach (self::$_exception_log_driver AS $driver)
        {
            if(count($exception)==1 && $exception[0] instanceof \Exception)
            {
                $driver->log(self::_errorFormat($exception[0]));
            }
            elseif(count($exception)==5)
            {
                $message = 'Phpcron error(error code:'.$exception[0].'): ';
                $message .= $exception[1] . " in ".$exception[2].":".$exception[3] . PHP_EOL;
                $driver->log($message);
            }
            elseif(count($exception)==1)
            {
                $driver->log('Phpcron '.$exception[0]);
            }
        }
    }
    
    private static function _errorFormat(\Exception $exception)
    {
        $message = 'Phpcron exception: ';
        $message .= $exception->getMessage() . " in ".$exception->getFile().":".$exception->getLine() . PHP_EOL .$exception->getTraceAsString();
        
        return $message;
    }
}