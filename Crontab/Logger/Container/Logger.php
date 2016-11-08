<?php


namespace Crontab\Logger\Container;

use Crontab\Logger\LoggerInterface\LoggerInterface;

abstract class Logger
{
    private static $_default_logger;
    
    public static function setDefaultDriver(LoggerInterface $driver)
    {
        self::$_default_logger = $driver;
    }
    
    public static function getDefaultDriver()
    {
        if(!self::$_default_logger instanceof LoggerInterface)
        {
            throw new \Exception("Default logger driver not set");
        }
        return self::$_default_logger;
    }
    
    public static function hasDefaultDriver()
    {
        return self::$_default_logger instanceof LoggerInterface;
    }
}