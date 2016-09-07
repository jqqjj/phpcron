<?php


namespace Crontab\Process;

use Crontab\Config\ConfigManager;
use Crontab\Task\TaskInterface;

class Worker
{
    private $_plugins = array();
    private $_socket;
    
    public function __construct($socket)
    {
        $this->_socket = $socket;
    }
    
    public function run()
    {
        $this->_registerSignal();
        
        $this->_loadPlugin(ConfigManager::get('plugins'));
        
        if($socket = socket_accept($this->_socket))
        {
            while(socket_recv($socket, $buf, 1, MSG_WAITALL))
            {
                file_put_contents(getmypid().'.txt', $buf,FILE_APPEND);
                file_put_contents(getmypid().'.txt', "\r\n\r\n",FILE_APPEND);
            }
            file_put_contents(getmypid().'.txt', "finish\r\n",FILE_APPEND);
        }
        
        //run tasks
        while(TRUE)
        {
            sleep(10);
            pcntl_signal_dispatch();
        }
    }

    private function _loadPlugin($list)
    {
        foreach ($list AS $key=>$value)
        {
            $plugin = new $value;
            if($plugin instanceof TaskInterface)
            {
                $this->_plugins[$key] = $plugin;
            }
        }
    }
    
    private function _registerSignal()
    {
        //register exit signal
        pcntl_signal(SIGHUP, array($this,'_signalHandler'));
        pcntl_signal(SIGINT, array($this,'_signalHandler'));
        pcntl_signal(SIGQUIT, array($this,'_signalHandler'));
        pcntl_signal(SIGTERM, array($this,'_signalHandler'));
    }
    
    private function _signalHandler($signo)
    {
        switch ($signo)
        {
            //exit handler
            case SIGHUP:
            case SIGINT:
            case SIGQUIT:
            case SIGTERM:
                exit(0);
                break;
            
            default :
                break;
        }
    }
}
