<?php


namespace Crontab\Kernel;

use Crontab\Logger\Container\Logger AS LoggerContainer;
use Crontab\Task\TaskInterface;
use Crontab\Helper\TaskRule;

class Worker
{
    private $_parent_id;
    private $_task;
    private $_params;
    private $_status;
    private $_instance;
    private $_history = array();
    
    public function __construct($name,$parent_id,Array $params=array())
    {
        $this->_parent_id = $parent_id;
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
        
        LoggerContainer::getDefaultDriver()->log("Task {$this->_task} starts.");
        $this->_instance->onStart($this->_params);
        
        while ($this->_status=='running')
        {
            $now = time();
            $nextWorkTime = $taskRule->getNextWorkTime();
            if(in_array($nextWorkTime, $this->_history) || $now<$nextWorkTime)
            {
                continue;
            }
            if($this->_instance->canWork())
            {
                $this->_instance->onWork();
                array_unshift($this->_history, $nextWorkTime);
                $this->_history = array_slice($this->_history, 0, 50);
            }
            else
            {
                posix_kill($this->_parent_id, SIGCHLD);
            }
            
            sleep($nextWorkTime-$now);
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
    }
    
    private function _signalHandler($signo)
    {
        switch ($signo)
        {
            case SIGHUP:
            case SIGINT:
            case SIGQUIT:
            case SIGTERM:
                $this->_status = 'exit';
                break;
            
            default :
                break;
        }
    }
}
