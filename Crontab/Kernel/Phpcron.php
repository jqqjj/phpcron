<?php

namespace Crontab\Kernel;

use Crontab\Config\Cli\Option;
use Crontab\Config\ConfigManager;
use Crontab\Process\Daemon;

class Phpcron
{
    public static function main(Option $option)
    {
        self::_init();
        
        $cli_config = $option->getOption();
        switch (strtolower($cli_config[0]))
        {
            case 'restart':
                break;
            case 'stop':
                break;
            case 'reload':
                break;
            case 'start':
            default :
                self::_start();
                break;
        }
    }

    protected static function _start()
    {
        if(self::_isRunning())
        {
            exit("phpcron is already running.".PHP_EOL);
        }
        
        $pid = pcntl_fork();
        if($pid == -1)
        {
			 exit('phpcron starts fail:could not fork');
		}
        elseif($pid>0)
        {
            echo "Starting phpcron";
            $i = 0;
            do{
                usleep(500000);
                echo '.';
            }while(!self::_isRunning() && ++$i<15);
            
            if(self::_isRunning())
            {
                exit(" SUCCESS!".PHP_EOL);
            }
            else
            {
                exit(" FAILED!".PHP_EOL);
            }
		}
        else
        {
			echo new Daemon();
			exit($pid);
		}
    }
    
    protected static function stop()
    {
        $configManager = new ConfigManager();
        
        $pid_path = $configManager->getConfig('cli.pid_path');
        $pid_name = $configManager->getConfig('cli.pid_name');
    }

    private static function _init()
    {
        $configManager = new ConfigManager();
        $pid_path = $configManager->getConfig('cli.pid_path');
        
        if(php_sapi_name()!='cli')
        {
            exit("This Application must be started with cli mode.".PHP_EOL);
        }
        if(!file_exists($pid_path))
        {
            exit("pid path not exists:".getcwd().DIRECTORY_SEPARATOR.$pid_path.PHP_EOL);
        }
        if(!is_writable($pid_path))
        {
            exit("pid path can not be writeable:".getcwd().DIRECTORY_SEPARATOR.$pid_path.PHP_EOL);
        }
        if(!is_readable($pid_path))
        {
            exit("pid path can not be readable:".getcwd().DIRECTORY_SEPARATOR.$pid_path.PHP_EOL);
        }
    }
    
    private static function _isRunning()
    {
        $configManager = new ConfigManager();
        
        $pid_path = $configManager->getConfig('cli.pid_path');
        $pid_name = $configManager->getConfig('cli.pid_name');
        
        return file_exists($pid_path.DIRECTORY_SEPARATOR.$pid_name);
    }
}