<?php


namespace Crontab\Exceptions;

use Crontab\Logger\DriverInterface\DriverInterface;
use Crontab\Exceptions\MailNotifier;

class ExceptionHandler
{
    private static $_exception_log_driver = array();
    private static $_mail_notifier = NULL;
    
    public static function addHandler(DriverInterface $driver)
    {
        self::$_exception_log_driver[get_class($driver)] = $driver;
    }
    
    public static function handler()
    {
        $message = self::_getErrorMessage(func_get_args());
        foreach (self::$_exception_log_driver AS $driver)
        {
            $driver->log($message);
        }
        
        //mail notify
        if(!self::$_mail_notifier instanceof MailNotifier)
        {
            self::$_mail_notifier = new MailNotifier();
        }
        try
        {
            self::$_mail_notifier->send(nl2br($message));
        }
        catch (\Exception $ex)
        {
            $mail_message = 'Phpcron exception: ';
            $mail_message .= $ex->getMessage() . " in ".$ex->getFile().":".$ex->getLine() . PHP_EOL .$ex->getTraceAsString();
            foreach (self::$_exception_log_driver AS $driver)
            {
                $driver->log($mail_message);
            }
        }
    }
    
    private static function _getErrorMessage($exception)
    {
        if(count($exception)==1 && $exception[0] instanceof \Exception)
        {
            $message = self::_errorFormat($exception[0]);
        }
        elseif(count($exception)==5)
        {
            $message = 'Phpcron error(error code:'.$exception[0].'): ';
            $message .= $exception[1] . " in ".$exception[2].":".$exception[3] . PHP_EOL;
        }
        elseif(count($exception)>=1)
        {
            $message = 'Phpcron '.$exception[0];
        }
        else
        {
            $message = "Unkown Error.";
        }
        return $message;
    }
    
    private static function _errorFormat(\Exception $exception)
    {
        $message = 'Phpcron exception: ';
        $message .= $exception->getMessage() . " in ".$exception->getFile().":".$exception->getLine() . PHP_EOL .$exception->getTraceAsString();
        
        return $message;
    }
}