<?php


namespace Crontab\Kernel;

use Crontab\Logger\Container\Logger AS LoggerContainer;
use Crontab\Task\TaskInterface;
use Crontab\Task\TaskRule;

class Worker
{
    private $_task;
    private $_params;
    private $_status;
    private $_instance;
    
    public function __construct($name,Array $params=array())
    {
        $this->_task = $name;
        $this->_status = NULL;
        $this->_instance = NULL;
        $this->_params = $params;
    }
    
    public function setParams(Array $params)
    {
        $this->_params = $params;
    }
    
    public function run()
    {
        $this->_init();
        $this->_status = 'running';
        
        $this->_instance = new $this->_task;
        if(!$this->_instance instanceof TaskInterface)
        {
            throw new \Exception("Task of {$this->_task} is not implement the interface.");
        }
        
        $rule = $this->_instance->getConfig();
        $taskRule = new TaskRule($rule);
        if(!$taskRule->verify())
        {
            throw new \Exception("Task regulation of {$this->_task} is not correct.");
        }
        
        $this->_instance->onStart($this->_params);
        
        while ($this->_status=='running')
        {
            $now = microtime(TRUE);
            $nextWorkTime = !empty($nextWorkTime) ? $nextWorkTime : $taskRule->getNextWorkTime(intval($now));
            if(empty($nextWorkTime))
            {
                sleep(60);
            }
            elseif(intval($now)>=$nextWorkTime)
            {
                if($this->_instance->canWork())
                {
                    $this->_instance->onWork();
                }
                else
                {
                    posix_kill(getmypid(), SIGUSR2);
                }
                unset($nextWorkTime);
            }
            else
            {
                usleep(($nextWorkTime-$now)*1000*1000);
            }
            
            pcntl_signal_dispatch();
        }
        
        $this->_instance->onStop();
    }
    
    private function _init()
    {
        //register singal
        $this->_registerSignal();
    }
    
    private function _registerSignal()
    {
        //register exit signal handler
        pcntl_signal(SIGINT, array($this,'_signalHandler'));
        pcntl_signal(SIGQUIT, array($this,'_signalHandler'));
        pcntl_signal(SIGTERM, array($this,'_signalHandler'));
        pcntl_signal(SIGUSR2, array($this,'_signalHandler'));
        //override master signal handler
        pcntl_signal(SIGCHLD, array($this,'_signalHandler'));
    }
    
    private function _signalHandler($signo)
    {
        switch ($signo)
        {
            case SIGUSR2:
                $this->_status = 'exit';
                break;
            
            case SIGHUP:
            case SIGINT:
            case SIGQUIT:
            case SIGTERM:
            default :
                break;
        }
    }
}
