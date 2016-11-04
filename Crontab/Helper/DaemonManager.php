<?php


namespace Crontab\Helper;

use Crontab\Config\ConfigManager;
use Crontab\Helper\RunnerBox;
use Crontab\Logger\LogFile AS LogFileLogger;

class DaemonManager
{
    public function __construct()
    {
        if($this->_isRunning())
        {
            $this->_message[] = "phpcron pid file is already exists.";
        }
        elseif(!$this->_holdPidFile())
        {
            $this->_message[] = "phpcron can't not write pid file.";
        }
        elseif(!$this->_updateConfig())
        {
            $this->_message[] = 'phpcron config is not correct.';
        }
        else
        {
            $this->_status = 'running';
            $this->_daemon();
        }
    }
    
    public function start()
    {
        $runner = new RunnerBox();
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
    
    private function _reload()
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
    
    private function _isRunning()
    {
        $pid_path = ConfigManager::get('base.pid_path');
        $pid_name = ConfigManager::get('base.pid_name');
        
        return file_exists($pid_path.DIRECTORY_SEPARATOR.$pid_name);
    }
    
    private function _getPidFile()
    {
        $pid_path = ConfigManager::get('base.pid_path');
        $pid_name = ConfigManager::get('base.pid_name');
        
        return $pid_path.DIRECTORY_SEPARATOR.$pid_name;
    }
    
    private function _holdPidFile()
    {
        $pid_path = ConfigManager::get('base.pid_path');
        $pid_name = ConfigManager::get('base.pid_name');
        
        try
        {
            $this->_pidFileHandle = @fopen($pid_path.DIRECTORY_SEPARATOR.$pid_name, 'w+');
            @fwrite($this->_pidFileHandle, getmypid());
            @flock($this->_pidFileHandle, LOCK_EX);
        } catch (Exception $ex)
        {
            try
            {
                @fclose($this->_pidFileHandle);
            } catch (Exception $ex)
            {
                return FALSE;
            }
            return FALSE;
        }
        
        return TRUE;
    }
    
    private function _unHoldPidFile()
    {
        $pid_path = ConfigManager::get('base.pid_path');
        $pid_name = ConfigManager::get('base.pid_name');
        
        if(!$this->_pidFileHandle)
        {
            return TRUE;
        }
        
        try
        {
            @flock($this->_pidFileHandle, LOCK_UN);
            @fclose($this->_pidFileHandle);
            @unlink($pid_path.DIRECTORY_SEPARATOR.$pid_name);
        } catch (Exception $ex)
        {
            return FALSE;
        }
        
        return TRUE;
    }
}