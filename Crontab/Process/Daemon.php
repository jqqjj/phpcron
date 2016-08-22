<?php

namespace Crontab\Process;

use Crontab\Config\ConfigManager;
use Crontab\Process\Worker;

class Daemon
{
    private $_workers = array();
    private $_message = array();
    private $_pidFileHandle = NULL;
    private $_status = NULL;

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
            $this->_status = 'running';
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
        if(!preg_match('/^[1-9]\d*$/', $worker_number) || $worker_number<=0)
        {
            $this->_message[] = 'ERROR ! can\'t not found config "worker.number"';
            return FALSE;
        }
        
        $this->_generateWorker($worker_number);
        
        //loop until receive stop command
        while(!$this->_status=='stopping')
        {
            if($this->_status=='reloading')
            {
                $this->_reloadChildren();
            }
            pcntl_signal_dispatch();
            sleep(10);
        }
        exit(getmypid());
    }
    
    /**
     * exit signal handler
     */
    private function _signalHandler($signo)
    {
        switch ($signo)
        {
            //exit handler
            case SIGHUP:
            case SIGINT:
            case SIGQUIT:
            case SIGTERM:
                $this->_killChildren();
                $this->_unHoldPidFile();
                $this->_status = 'stopping';
                break;
            
            //reload handler
            case SIGUSR1:
                $this->_status = 'reloading';
                break;
            
            //child exit handler
            case SIGCHLD:
                while(($pid=pcntl_waitpid(-1, $status, WNOHANG)) > 0)
                {
                    $this->_workers = array_diff($this->_workers, array($pid));
                    do{
                        $this->_generateWorker(1);
                    }while(++$i<3);
                    if(count($this->_workers)!=intval(ConfigManager::get('worker.number')))
                    {
                        //regenerate worker error handler
                    }
                }
                
                break;
            
            default :
                break;
        }
    }
    
    /**
     * reload signal handler
     */
    private function _reloadChildren()
    {
        file_put_contents('master.txt', 'reload '.getmypid()."\r\n",FILE_APPEND);
    }
    
    private function _generateWorker($worker_number)
    {
        $is_first = count($this->_workers)==0;
        
        if(!empty($worker_number) && intval($worker_number)>=1)
        {
            $worker_number = intval($worker_number);
        }
        else
        {
            $worker_number = 1;
        }
        
        for($i=0;$i<$worker_number;$i++)
        {
            $pid = pcntl_fork();
            //the first time generating fail will kill all children
            if($pid==-1 && $is_first)
            {
                $this->_killChildren();
                exit("generate workers error.");
            }
            elseif($pid==-1)
            {
                echo "ERROR ! Auto restart child fail.".PHP_EOL;
            }
            elseif($pid>0)
            {
                $this->_workers[] = $pid;
            }
            else
            {
                new Worker();
                exit(getmypid());
            }
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
        pcntl_signal(SIGHUP, array($this,'_signalHandler'));
        pcntl_signal(SIGINT, array($this,'_signalHandler'));
        pcntl_signal(SIGQUIT, array($this,'_signalHandler'));
        pcntl_signal(SIGTERM, array($this,'_signalHandler'));
        //register reload signal
        pcntl_signal(SIGUSR1, array($this,'_signalHandler'));
        //register children exit signal
        pcntl_signal(SIGCHLD, array($this,'_signalHandler'));
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
    
    private function _killChildren()
    {
        while($worker = array_shift($this->_workers))
        {
            posix_kill($worker, SIGTERM);
        }
        
        return TRUE;
    }
}