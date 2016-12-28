<?php

namespace Crontab\Kernel;

use Crontab\Config\ConfigManager;
use Crontab\Kernel\Worker;
use Crontab\Logger\Container\Logger AS LoggerContainer;
use Crontab\Helper\RunnerBox;

/**
 * 目标：
 * master 监听新任务的到来，及子任务的完成，子任务的异常退出，子任务以独立进程运行，正常完成任务时向master发送完成信号，
 *        若master收到子进程退出信号但没收到子进程发送的完成信号，可视为异常退出。
 * master 有四个状态，一个是等待任务的到来，二是收到任务时处理，三是子进程完成信号处理,四是子进程退出处理
 */

class Master
{
    private $_tasks;
    private $_logger;
    private $_connections;
    private $_request;
    //running、exit
    private $_status;
    private $_plugins;

    public function __construct()
    {
        $this->_logger = LoggerContainer::getDefaultDriver();
        $this->_connections = array();
        $this->_tasks = array();
        $this->_request = array();
        $this->_plugins = ConfigManager::get('plugins');
    }
    
    public function run()
    {
        $this->_logger->log("Phpcron starts.");
        
        $this->_status = 'running';
        
        //load and start default tasks
        $this->_loadTasks();
        
        //register singal
        $this->_registerSignal();
        
        //loop crontab tasks
        $this->_loop();
        
        //exit log
        $this->_logger->log("Phpcron normal exits.");
    }
    
    private function _loop()
    {
        while($this->_status == 'running' || ($this->_status == 'exit'&&!empty($this->_tasks)) )
        {
            if($this->_status == 'exit')
            {
                $this->_killTasks();
            }
            sleep(1);
            pcntl_signal_dispatch();
        }
    }
    
    private function _loadTasks()
    {
        foreach ($this->_plugins AS $key=>$value)
        {
            if(!empty($value['enabled']))
            {
                $this->_runTask($key, $value['class']);
            }
        }
    }
    
    private function _runTask($task,$taskClass)
    {
        if(in_array($task,  array_column($this->_tasks, 'name')))
        {
            $this->_logger->log("Task <{$task}> is already running.");
            return FALSE;
        }
        
        $params = isset($this->_plugins[$task]['params']) ? $this->_plugins[$task]['params'] : array();
        
        $runnerBox = new RunnerBox();
        $pid = $runnerBox->run(function() use ($taskClass,$params){
            $worker = new Worker($taskClass,$params);
            $worker->run();
        });
        
        if($pid>0)
        {
            $this->_tasks[$pid] = array(
                'name'=>$task,
            );
            return $pid;
        }else
        {
            return FALSE;
        }
    }
    
    private function _dropTimeoutConnections()
    {
        foreach ($this->_request AS $key=>$value)
        {
            if($value['mtime']+6<=time() && isset($this->_connections[$key]))
            {
                $this->_logger->log("Drop timeout connection,connection ID:".$key);
                $this->_closeSocket($this->_connections[$key]);
            }
        }
    }
    
    private function _killTasks()
    {
        foreach ($this->_tasks AS $key=>$value)
        {
            posix_kill($key, SIGUSR2);
        }
    }
    
    private function _dropAllConnections()
    {
        foreach ($this->_connections AS $socket)
        {
            $this->_closeSocket($socket);
        }
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
                $this->_status = 'exit';
                break;
            
            //task exit handler
            case SIGCHLD:
                $status = NULL;
                while(($pid=pcntl_waitpid(-1, $status, WNOHANG)) > 0)
                {
                    if(!key_exists($pid, $this->_tasks))
                    {
                        continue;
                    }
                    
                    if(pcntl_wifsignaled($status))
                    {
                        trigger_error("Task <{$this->_tasks[$pid]['name']}> crash exits.",E_USER_WARNING);
                    }
                    else
                    {
                        $this->_logger->log("Task <{$this->_tasks[$pid]['name']}> normal exits.");
                    }
                    unset($this->_tasks[$pid]);
                }
                
                break;
            
            default :
                break;
        }
    }
    
    private function _closeSocket($socket)
    {
        if(array_search($socket, $this->_connections))
        {
            unset($this->_connections[array_search($socket, $this->_connections)]);
        }
        if(isset($this->_request[(int)$socket]))
        {
            unset($this->_request[(int)$socket]);
        }
        socket_close($socket);
    }
    
    private function _registerSignal()
    {
        //register master exit signal handler(when master receive a exit command)
        pcntl_signal(SIGHUP, array($this,'_signalHandler'));
        pcntl_signal(SIGINT, array($this,'_signalHandler'));
        pcntl_signal(SIGQUIT, array($this,'_signalHandler'));
        pcntl_signal(SIGTERM, array($this,'_signalHandler'));
        
        //register task exit signal handler
        pcntl_signal(SIGCHLD, array($this,'_signalHandler'));
    }
}