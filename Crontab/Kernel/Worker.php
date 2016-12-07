<?php


namespace Crontab\Kernel;

use Crontab\Logger\Container\Logger AS LoggerContainer;
use Crontab\Task\TaskInterface;
use Crontab\Helper\TaskRule;

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
        
        LoggerContainer::getDefaultDriver()->log("Task {$this->_task} starts.");
        
        while ($this->_status=='running')
        {
            sleep(10);
            pcntl_signal_dispatch();
        }
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
