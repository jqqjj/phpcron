<?php

namespace Crontab\Process;

use Crontab\Config\ConfigManager;

class Daemon
{
    private $_workers = array();
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
        
        //workers starts to work.
        $worker_number = ConfigManager::get('worker.number');
        $this->_generateWorker($worker_number);
        
        //loop
        do
        {
            pcntl_signal_dispatch();
            file_put_contents('master.txt', date("Y-m-d H:i:s")."\r\n",FILE_APPEND);
            sleep(10);
        }while(true);
    }
    
    private function _generateWorker($worker_number)
    {
        $pid = pcntl_fork();
        if($pid==-1)
        {
            exit("can not fork.");
        }
        elseif($pid>0)
        {
            $this->_workers[] = $pid;
        }
        else
        {
            exit($pid);
        }
    }
    
    private function _isRunning()
    {
        $pid_path = ConfigManager::get('base.pid_path');
        $pid_name = ConfigManager::get('base.pid_name');
        
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
    
    /**
     * exit signal handler
     */
    private function _exitSignal()
    {
        file_put_contents('master.txt', 'stop '.getmypid()."\r\n",FILE_APPEND);
        $this->_unHoldPidFile();
        exit(0);
    }
    
    /**
     * reload signal handler
     */
    private function _reloadSignal()
    {
        file_put_contents('master.txt', 'reload '.getmypid()."\r\n",FILE_APPEND);
        $this->_unHoldPidFile();
        exit(0);
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