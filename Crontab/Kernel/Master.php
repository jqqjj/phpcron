<?php

namespace Crontab\Kernel;

use Crontab\Config\ConfigManager;
use Crontab\Kernel\Worker;
use Crontab\Logger\Container\Logger AS LoggerContainer;
use Crontab\Pcntl\RunnerBox;

/**
 * 目标：
 * master 监听新任务的到来，及子任务的完成，子任务的异常退出，子任务以独立进程运行，正常完成任务时向master发送完成信号，
 *        若master收到子进程退出信号但没收到子进程发送的完成信号，可视为异常退出。
 * master 有四个状态，一个是等待任务的到来，二是收到任务时处理，三是子进程完成信号处理,四是子进程退出处理
 */

class Master
{
    private $_workers;
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
        $this->_workers = array();
        $this->_request = array();
        $this->_plugins = ConfigManager::get('plugins');
    }
    
    public function run()
    {
        $this->_logger->log("Phpcron starts.");
        
        $this->_status = 'running';
        
        //register singal
        $this->_registerSignal();
        
        //load and start default tasks
        $this->_loadTasks();
        
        //loop crontab tasks
        $this->_loop();
        
        //exit log
        $this->_logger->log("Phpcron normal exits.");
    }
    
    private function _loop()
    {
        while($this->_status == 'running' || ($this->_status == 'exit'&&!empty($this->_workers)) )
        {
            if($this->_status == 'exit')
            {
                $this->_killTasks();
            }
            
            $this->_reviveTasks();
            
            sleep(1);
            pcntl_signal_dispatch();
        }
    }
    
    private function _loadTasks()
    {
        foreach ($this->_plugins AS $task_name=>$value)
        {
            if(empty($value['enabled']))
            {
                continue;
            }
            $pid = $this->_runTasks($task_name, $value['class']);
            if($pid>0)
            {
                $this->_workers[$pid] = array(
                    'name'=>$task_name,
                    'alive'=>TRUE,
                    'revive'=>array(),
                );
            }
        }
    }
    
    private function _reviveTasks()
    {
        foreach ($this->_workers AS $pid=>$value)
        {
            if($value['alive'])
            {
                continue;
            }
            //maximum number of times limit in one minute
            if(count($this->_workers[$pid]['revive'])>60 && $this->_workers[$pid]['revive'][60]>=time()-60)
            {
                unset($this->_workers[$pid]);
                continue;
            }
            
            $new_pid = $this->_runTasks($value['name'], $this->_plugins[$value['name']]['class']);
            if($new_pid<=0)
            {
                continue;
            }
            unset($this->_workers[$pid]);
            $this->_workers[$new_pid] = array(
                'name'=>$value['name'],
                'alive'=>TRUE,
                'revive'=>array_merge(array(time()),  array_slice($value['revive'], 0, 60)),
            );
        }
    }
    
    private function _runTasks($taskName,$taskClass)
    {
        foreach ($this->_workers AS $value)
        {
            if($taskName == $value['name'] && $value['alive'])
            {
                $this->_logger->log("Task <{$taskName}> is already running.");
                return FALSE;
            }
        }
        
        $params = isset($this->_plugins[$taskName]['params']) ? $this->_plugins[$taskName]['params'] : array();
        
        $runnerBox = new RunnerBox();
        return $runnerBox->run(function() use ($taskClass,$params){
            $worker = new Worker($taskClass,$params);
            $worker->run();
        });
    }
    
    private function _killTasks()
    {
        foreach ($this->_workers AS $key=>$value)
        {
            if($value['alive'])
            {
                posix_kill($key, SIGUSR2);
            }
            else
            {
                unset($this->_workers[$key]);
            }
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
                    if(!key_exists($pid, $this->_workers))
                    {
                        continue;
                    }
                    if(pcntl_wstopsig($status)<=0)
                    {
                        $this->_workers[$pid]['alive'] = FALSE;
                        trigger_error("Task <{$this->_workers[$pid]['name']}> crash exits.",E_USER_ERROR);
                    }
                    else
                    {
                        $this->_logger->log("Task <{$this->_workers[$pid]['name']}> normal exits.");
                        unset($this->_workers[$pid]);
                    }
                }
                
                break;
            
            default :
                break;
        }
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