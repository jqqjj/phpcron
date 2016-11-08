<?php

namespace Crontab\Kernel;

use Crontab\Input\Argvs;
use Crontab\Kernel\Master;
use Crontab\Helper\DaemonManager;
use Crontab\Logger\Driver\TerminalDriver;
use Crontab\Logger\Container\Logger AS LoggerContainer;
use Crontab\Exceptions\ExceptionHandler;

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
        LoggerContainer::setDefaultDriver(new TerminalDriver());
        $master = new Master();
        $master->run();
    }

    private static function _init()
    {
        //set exception handler
        set_exception_handler(array(new ExceptionHandler,'handler'));
        
        if(php_sapi_name()!='cli')
        {
            exit("This Application must be started with cli mode.".PHP_EOL);
        }
    }
}