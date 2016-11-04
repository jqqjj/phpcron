<?php

namespace Crontab\Process;

use Crontab\Config\ConfigManager;
use Crontab\Process\Worker;
use Crontab\IO\SocketManager;
use Crontab\Logger\LoggerInterface\LoggerInterface;

class Master
{
    private $logger;
    private $_status;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    public function run()
    {
        $this->_status = 'running';
        $this->_daemon();
    }
    
    private function _daemon()
    {
        //register singal
        $this->_registerSignal();
        
        //listen
        if(!$this->_listen())
        {
            $this->_unHoldPidFile();
            exit("can not create listener.".PHP_EOL);
        }
        
        //workers starts to work.
        $workers = $this->_addWorkers(ConfigManager::get('worker.number'));
        if(ConfigManager::get('worker.number') != count($workers))
        {
            $this->_killWorkers($workers);
            $this->_unHoldPidFile();
            exit("start workers error.".PHP_EOL);
        }
        $this->_workers = $workers;
        
        //loop until receive stop command or workers is empty
        while(!empty($this->_workers))
        {
            sleep(10);
            pcntl_signal_dispatch();
            switch ($this->_status)
            {
                case 'reloading':
                    $this->_reloadWorkers();
                    $this->_status = 'running';
                    break;
                case 'running':
                    $this->_autoMaintainWorkers();
                    break;
                case 'stopping':
                    $this->_killWorkers($this->_workers);
                    break;
            }
        }
        
        $this->_unHoldPidFile();
        exit("phpcron exit normally.pid:".getmypid().PHP_EOL);
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
                $this->_status = 'stopping';
                break;
            
            //reload handler
            case SIGUSR1:
                $this->_status = 'reloading';
                break;
            
            //child crash handler
            case SIGCHLD:
                $this->_recoupWorkers();
                
                break;
            
            default :
                break;
        }
    }
    
    /**
     * reload signal handler
     */
    private function _reloadWorkers()
    {
        echo "starting reload workers.".PHP_EOL;
        
        if(!$this->_updateConfig())
        {
            echo "phpcron config is not correct when reloading".PHP_EOL;
            return false;
        }
        
        $new_workers = $this->_addWorkers(ConfigManager::get('worker.number'));
        if(count($new_workers) != intval(ConfigManager::get('worker.number')))
        {
            $this->_killWorkers($new_workers);
            echo "phpcron reloads fail".PHP_EOL;
            return FALSE;
        }
        
        $this->_killWorkers($this->_workers);
        $this->_workers = $new_workers;
        
        echo "reloads workers success.".PHP_EOL;
    }
    
    private function _recoupWorkers()
    {
        $status = NULL;
        while(($pid=pcntl_waitpid(-1, $status, WNOHANG)) > 0)
        {
            $this->_workers = array_diff($this->_workers, array($pid));
        }
    }
    
    private function _autoMaintainWorkers()
    {
        $i = 0;
        while(intval(ConfigManager::get('worker.number')) > count($this->_workers) && $i++<3)
        {
            $this->_workers = array_merge($this->_workers,$this->_addWorkers(intval(ConfigManager::get('worker.number')) - count($this->_workers)));
        }
        
        if(count($this->_workers)!=intval(ConfigManager::get('worker.number')))
        {
            //resurrect worker error handler
            echo "resurrect workers error.".PHP_EOL;
        }
    }

    private function _addWorkers($worker_number)
    {
        if(!empty($worker_number) && intval($worker_number)>=1)
        {
            $worker_number = intval($worker_number);
        }
        else
        {
            $worker_number = 1;
        }
        
        $workers = array();
        for($i=0;$i<$worker_number;$i++)
        {
            $pid = pcntl_fork();
            
            if($pid==-1)
            {
                echo "ERROR ! Add worker fail.".PHP_EOL;
            }
            elseif($pid>0)
            {
                $workers[] = $pid;
            }
            else
            {
                $worker = new Worker($this->_socket);
                $worker->run();
                exit(getmypid());
            }
        }
        
        return $workers;
    }
    
    private function _listen()
    {
        $socketManager = new SocketManager();
        
        if($socketManager->generate() && $socketManager->set_block_mode(0))
        {
            $this->_socket = $socketManager->getSocket();
            return TRUE;
        }
        else
        {
            return FALSE;
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
    
    private function _killWorkers($workers)
    {
        while($worker = array_shift($workers))
        {
            $i = 0;
            while(!posix_kill($worker, SIGTERM) && $i<3)
            {
                $i++;
            }
        }
        
        return TRUE;
    }
    
    /**
     * update the real-time config
     * @return bool
     */
    private function _updateConfig()
    {
        $configManager = new ConfigManager();
        $config = $configManager->getConfig();
        
        //reset config
        $configManager->setConfig(NULL);
        
        if(preg_match('/^[1-9]\d*$/', ConfigManager::get('worker.number')) && ConfigManager::get('worker.number')>0)
        {
            return TRUE;
        }
        else
        {
            //recover config
            $configManager->setConfig($config);
            return FALSE;
        }
    }
}