<?php


namespace Crontab\Exceptions;

use Crontab\Logger\Container\Logger AS LoggerContainer;
use Crontab\Config\ConfigManager;

class ExceptionHandler
{
    private $_default_exception_driver;
    
    public function __construct()
    {
        $default_driver_name = ConfigManager::get('exception.default_logger_driver');
        $this->_default_exception_driver = new $default_driver_name;
    }
    
    public function handler(\Exception $exception)
    {
        //if user set up a custom logger,send the message to it.
        if(LoggerContainer::hasDefaultDriver())
        {
            LoggerContainer::getDefaultDriver()->log($exception->getMessage());
        }
        
        $this->_default_exception_driver->log(print_r($exception->getTrace(),true));
        $this->_default_exception_driver->log($exception->getFile()."\t".$exception->getLine()."\r\n".$exception->getMessage());
    }
}