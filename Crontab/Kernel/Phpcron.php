<?php

namespace Crontab\Kernel;

use Crontab\Input\Argvs;
use Crontab\Config\ConfigManager;
use Crontab\Process\Master;
use Crontab\Helper\Runner;
use Crontab\Logger\Terminal AS TerminalLogger;
use Crontab\Logger\LogFile AS LogFileLogger;

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
            self::daemon($cli_config);
        }
        else
        {
            //terminal
            new Master(new TerminalLogger());
        }
    }
    
    public static function daemon($options)
    {
        $runner = new Runner();
        $runner->run(function() use ($options){
            if(in_array('stop', $options))
            {
                self::_stop();
            }
            elseif(in_array('reload', $options) || in_array('restart', $options))
            {
                self::_reload();
            }
            else
            {
                self::_start();
            }
        });
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
                sleep(1);
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
			exit(getmypid());
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
            sleep(1);
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
    
    private static function _reload()
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
            posix_kill($pid, SIGUSR1);
        } catch (\Exception $ex) {
            echo "ERROR! error message:".$ex->getMessage().PHP_EOL;
            return FALSE;
        }
        
        echo "phpcron has been reloaded".PHP_EOL;
        
        return TRUE;
    }

    private static function _init()
    {
        $pid_path = ConfigManager::get('base.pid_path');
        
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
        $pid_path = ConfigManager::get('base.pid_path');
        $pid_name = ConfigManager::get('base.pid_name');
        
        return file_exists($pid_path.DIRECTORY_SEPARATOR.$pid_name);
    }
    
    private static function _getPidFile()
    {
        $pid_path = ConfigManager::get('base.pid_path');
        $pid_name = ConfigManager::get('base.pid_name');
        
        return $pid_path.DIRECTORY_SEPARATOR.$pid_name;
    }
}