<?php

namespace Crontab\Kernel;

use Crontab\Config\ConfigManager;
use Crontab\Process\Worker;
use Crontab\IO\SocketManager;
use Crontab\Logger\Container\Logger AS LoggerContainer;

/**
 * 目标：
 * master 监听新任务的到来，及子任务的完成，子任务的异常退出，子任务以独立进程运行，正常完成任务时向master发送完成信号，
 *        若master收到子进程退出信号但没收到子进程发送的完成信号，可视为异常退出。
 * master 有四个状态，一个是等待任务的到来，二是收到任务时处理，三是子进程完成信号处理,四是子进程退出处理
 */

class Master
{
    //waiting、addtask、taskexit、exit
    private $_command;
    private $_workers;
    private $_logger;
    private $_socketManager;

    public function __construct()
    {
        $this->_logger = LoggerContainer::getDefaultDriver();
    }
    
    public function run()
    {
        $this->_command = 'waiting';
        $this->_logger->log("can not initiating listener.");
        trigger_error('aaaa',E_USER_WARNING);
        throw new \Exception('test');
        //register singal
        $this->_registerSignal();
        
        //listen task from networks
        if(!$this->_initListener())
        {
            $this->_logger->log("can not initiating listener.");
            exit();
        }
        
        /*
        ob_start();
        var_dump($this->_socketManager->getSocket());
        $this->_logger->log(ob_get_clean());
        */
        
        //$this->_socketManager->accept();
        //loop crontab tasks
        //$this->_loop();
    }
    
    private function _loop()
    {
        //loop until workers is empty
        while(!empty($this->_workers))
        {
            sleep(10);
            pcntl_signal_dispatch();
            switch ($this->_status)
            {
                case 'exit':
                    $this->_killWorkers($this->_workers);
                    break;
                case 'running':
                    $this->_autoMaintainWorkers();
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
                $this->_command = 'taskexit';
                break;
            
            //reload handler
            case SIGUSR1:
                $this->_command = 'reloading';
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
    
    private function _initListener()
    {
        $this->_socketManager = new SocketManager();
        if($this->_socketManager->generate() && $this->_socketManager->set_block_mode(0))
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }
    
    private function _registerSignal()
    {
        //register master exit signal(when master receive a exit command)
        pcntl_signal(SIGHUP, array($this,'_signalHandler'));
        pcntl_signal(SIGINT, array($this,'_signalHandler'));
        pcntl_signal(SIGQUIT, array($this,'_signalHandler'));
        pcntl_signal(SIGTERM, array($this,'_signalHandler'));
        
        //register worker finish signal
        pcntl_signal(SIGUSR1, array($this,'_signalHandler'));
        
        //register worker exit signal(task finish)
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