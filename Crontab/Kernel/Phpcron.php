<?php

namespace Crontab\Kernel;

use Crontab\Input\Argvs;
use Crontab\Kernel\Master;
use Crontab\Daemon\DaemonManager;
use Crontab\Logger\Driver\DebugDriver;
use Crontab\Logger\Driver\ErrorDriver;
use Crontab\Logger\Driver\TerminalDriver;
use Crontab\Exceptions\ExceptionHandler;
use Crontab\Logger\Container\Logger AS LoggerContainer;

class Phpcron
{
    public static function main($argvs)
    {
        self::_init();
        
        $input = new Argvs($argvs);
        $cli_config = $input->getOption();
        
        if(in_array('-d',$cli_config))
        {
            //daemon
            self::_daemon($cli_config);
        }
        else
        {
            //terminal
            self::_terminal();
        }
        exit(0);
    }
    
    private static function _daemon($cli_config)
    {
        $daemon = new DaemonManager();
        if(in_array('start',$cli_config))
        {
            $daemon->start();
        }
        elseif(in_array('stop',$cli_config))
        {
            
        }
        elseif(in_array('restart',$cli_config))
        {
            
        }
        elseif(in_array('reload',$cli_config))
        {
            
        }
    }
    
    private static function _terminal()
    {
        $terminal_driver = new TerminalDriver();
        //set the running log driver
        LoggerContainer::setDefaultDriver($terminal_driver);
        //add an additional ExceptionHandler to show error in terminal
        ExceptionHandler::addHandler($terminal_driver);
        
        $master = new Master();
        $master->run();
    }

    private static function _init()
    {
        $error_driver = new ErrorDriver();
        //add exception logger driver
        ExceptionHandler::addHandler($error_driver);
        //set exception handler
        set_error_handler(array('Crontab\Exceptions\ExceptionHandler','handler'));
        set_exception_handler(array('Crontab\Exceptions\ExceptionHandler','handler'));
        
        if(php_sapi_name()!='cli')
        {
            echo 'This Application must be started with cli mode.'.PHP_EOL;
            throw new \Exception('This Application must be started with cli mode.');
        }
    }
}