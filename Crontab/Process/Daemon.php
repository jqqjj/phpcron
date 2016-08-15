<?php

namespace Crontab\Process;

use Crontab\Config\ConfigManager;

class Daemon
{
    private static $_workers = array();
    private $_message = array();
    private $_pidFileHandle = NULL;
    
    public function __construct()
    {
        if($this->_isRunning())
        {
            $this->_message[] = "phpcron is already running.";
        }
        elseif(!$this->_holdPidFile())
        {
            $this->_message[] = "phpcron can't not update pid file.";
        }
        else
        {
            $this->_daemon();
        }
    }
    
    public function __toString()
    {
        return implode("\r\n", $this->_message);
    }
    
    private function _daemon()
    {
        //register singal
        $this->_registerSignal();
        //loop
        do
        {
            pcntl_signal_dispatch();
            file_put_contents('master.txt', date("Y-m-d H:i:s")."\r\n",FILE_APPEND);
            sleep(10);
        }while(true);
    }
    
    private static function generateWorker()
    {
        $pid = pcntl_fork();
        if($pid==-1)
        {
            exit("can not fork.");
        }
        elseif($pid>0)
        {
            self::$_workers[] = $pid;
        }
        else
        {
            
        }
    }
    
    private function _isRunning()
    {
        $configManager = new ConfigManager();
        
        $pid_path = $configManager->getConfig('cli.pid_path');
        $pid_name = $configManager->getConfig('cli.pid_name');
        
        return file_exists($pid_path.DIRECTORY_SEPARATOR.$pid_name);
    }
    
    private function _registerSignal()
    {
        //register exit signal
        pcntl_signal(SIGHUP, array($this,'_exitSignal'));
        pcntl_signal(SIGINT, array($this,'_exitSignal'));
        pcntl_signal(SIGQUIT, array($this,'_exitSignal'));
        pcntl_signal(SIGTERM, array($this,'_exitSignal'));
        //register reload signal
        pcntl_signal(SIGUSR1, array($this,'_reloadSignal'));
    }
    
    private function _exitSignal()
    {
        file_put_contents('master.txt', 'stop '.getmypid()."\r\n",FILE_APPEND);
        $this->_unHoldPidFile();
        exit(0);
    }
    
    private function _reloadSignal()
    {
        file_put_contents('master.txt', 'reload '.getmypid()."\r\n",FILE_APPEND);
        $this->_unHoldPidFile();
        exit(0);
    }
    
    private function _holdPidFile()
    {
        $configManager = new ConfigManager();
        $pid_path = $configManager->getConfig('cli.pid_path');
        $pid_name = $configManager->getConfig('cli.pid_name');
        
        try
        {
            $this->_pidFileHandle = @fopen($pid_path.DIRECTORY_SEPARATOR.$pid_name, 'w+');
            @fwrite($this->_pidFileHandle, getmypid());
            @flock($this->_pidFileHandle, LOCK_EX);
        } catch (Exception $ex)
        {
            try
            {
                fclose($this->_pidFileHandle);
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
        $configManager = new ConfigManager();
        $pid_path = $configManager->getConfig('cli.pid_path');
        $pid_name = $configManager->getConfig('cli.pid_name');
        
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