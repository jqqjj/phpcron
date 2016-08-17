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
                self::_stop();
                self::_start();
                break;
            case 'stop':
                self::_stop();
                break;
            case 'reload':
                break;
            case 'start':
            default :
                self::_start();
                break;
        }
        exit();
    }

    private static function _start()
    {
        if(self::_isRunning())
        {
            echo "phpcron is already running.".PHP_EOL;
            return FALSE;
        }
        
        $pid = pcntl_fork();
        if($pid == -1)
        {
			 echo 'phpcron starts fail:could not fork'.PHP_EOL;
             return FALSE;
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
                echo " SUCCESS!".PHP_EOL;
                return TRUE;
            }
            else
            {
                echo " FAILED!".PHP_EOL;
                return FALSE;
            }
		}
        else
        {
			echo new Daemon();
			exit($pid);
		}
    }
    
    private static function _stop()
    {
        if(!self::_isRunning())
        {
            echo "ERROR! phpcron server PID file could not be found!".PHP_EOL;
            return FALSE;
        }
        
        $pid = file_get_contents(self::_getPidFile());
        
        if(!preg_match('/^\d+$/', $pid))
        {
            echo "ERROR! phpcron server PID is illegal!".PHP_EOL;
            return FALSE;
        }
        
        try {
            posix_kill($pid, SIGTERM);
        } catch (\Exception $ex) {
            echo "ERROR! error message:".$ex->getMessage().PHP_EOL;
            return FALSE;
        }
        
        echo "Stopping phpcron";
        $i = 0;
        do{
            usleep(500000);
            echo '.';
        }while(self::_isRunning() && ++$i<15);
        
        if(!self::_isRunning())
        {
            echo " SUCCESS!".PHP_EOL;
            return TRUE;
        }
        else
        {
            echo " FAILED!".PHP_EOL;
            return FALSE;
        }
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
    
    private static function _getPidFile()
    {
        $configManager = new ConfigManager();
        
        $pid_path = $configManager->getConfig('cli.pid_path');
        $pid_name = $configManager->getConfig('cli.pid_name');
        
        return $pid_path.DIRECTORY_SEPARATOR.$pid_name;
    }
}