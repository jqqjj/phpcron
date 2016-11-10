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
    //waiting、addtask、taskexit、masterexit、taskfinish
    private $_command;
    private $_workers;
    private $_logger;
    private $_connections;
    private $_socketManager;

    public function __construct()
    {
        $this->_logger = LoggerContainer::getDefaultDriver();
        $this->_connections = array();
    }
    
    public function run()
    {
        $this->_logger->log("Phpcron starts.");
        
        $this->_command = 'waiting';
        
        //register singal
        $this->_registerSignal();
        
        //listen task from networks
        if(!$this->_initListener())
        {
            throw new \Exception('Starts failed by error "Can not initiating listener".');
        }
        
        //loop crontab tasks
        $this->_loop();
    }
    
    private function _loop()
    {
        while (TRUE)
        {
            $new_connection = FALSE;
            $fd = $this->_socketManager->getSocket();
            
            //if receives a stop command,it will not accepts new connecitons.
            if($this->_status != 'waiting')
            {
                $sockets = $this->_socketManager->select($this->_connections);
            }
            else
            {
                $sockets = $this->_socketManager->select(array_merge($this->_connections, array($fd)));
            }
            
            //new connection handler
            if(in_array($fd, $sockets))
            {
                $new_connection = $this->_socketManager->accept();
            }
            
            if(!empty($new_connection))
            {
                $this->_connections[(int)$new_connection] = $new_connection;
            }
        }
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
            //master exit handler
            case SIGHUP:
            case SIGINT:
            case SIGQUIT:
            case SIGTERM:
                $this->_command = 'masterexit';
                break;
            
            //task finish handler
            case SIGUSR1:
                $this->_command = 'taskfinish';
                break;
            
            //task exit handler
            case SIGCHLD:
                $this->_command = 'taskexit';
                
                break;
            
            default :
                break;
        }
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
        //register master exit signal handler(when master receive a exit command)
        pcntl_signal(SIGHUP, array($this,'_signalHandler'));
        pcntl_signal(SIGINT, array($this,'_signalHandler'));
        pcntl_signal(SIGQUIT, array($this,'_signalHandler'));
        pcntl_signal(SIGTERM, array($this,'_signalHandler'));
        
        //register task finish signal handler
        pcntl_signal(SIGUSR1, array($this,'_signalHandler'));
        
        //register task exit signal handler
        pcntl_signal(SIGCHLD, array($this,'_signalHandler'));
    }
}