<?php

namespace Crontab\Kernel;

use Crontab\Config\ConfigManager;
use Crontab\Kernel\Worker;
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
    private $_tasks;
    private $_logger;
    private $_connections;
    private $_request;
    //running、exit
    private $_status;
    private $_socketManager;
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
        
        //register singal
        $this->_registerSignal();
        
        //listen task from networks
        if(!$this->_initListener())
        {
            throw new \Exception('Starts failed by error "Can not initiating listener".');
        }
        
        //load and start default tasks
        
        //loop crontab tasks
        $this->_loop();
        
        //exit log
        $this->_logger->log("Phpcron normal exits.");
    }
    
    private function _loop()
    {
        while ($this->_status == 'running' || !empty($this->_connections) || !empty($this->_tasks))
        {
            $this->_processConnections();
            $this->_dropTimeoutConnections();
            $this->_processTasks();
            
            pcntl_signal_dispatch();
        }
    }
    
    private function _processTasks()
    {
        foreach ($this->_request AS $key=>$value)
        {
            //task just will starts within a complete data.
            if($value['data']===NULL)
            {
                continue;
            }
            //drop request if task is running or task is not exists.
            if(!key_exists($value['task'], $this->_plugins) || in_array($value['task'],array_column($this->_tasks, 'name')))
            {
                unset($this->_request[$key]);
                continue;
            }
            
            $worker = new Worker();
            $pid = $worker->run($this->_plugins[$value['task']]['class'],array('data'=>$value['data']));
            if($pid>0)
            {
                $this->_tasks[$pid] = array(
                    'name'=>$value['task'],
                    'instance'=>$worker,
                );
                unset($this->_request[$key]);
            }
        }
    }
    
    private function _processConnections()
    {
        $sockets = $this->_socketManager->select( array_merge($this->_connections,array($this->_socketManager->getSocket())) );
        
        if(empty($sockets))
        {
            return ;
        }
        
        //new connection handler
        if(in_array($this->_socketManager->getSocket(),$sockets))
        {
            $this->_acceptNewConnection();
            unset($sockets[array_search($this->_socketManager->getSocket(), $sockets)]);
        }
        
        foreach ($sockets AS $socket)
        {
            $this->_IO($socket);
        }
    }
    
    private function _IO($socket)
    {
        //first read the content length
        if(!isset($this->_request[(int)$socket]) || !isset($this->_request[(int)$socket]['length']) || $this->_request[(int)$socket]['length']<=0)
        {
            $data = $this->_socketManager->read($socket);
            //data is false means that connection is reset.
            if($data===FALSE)
            {
                $this->_logger->log("Broken connection.");
                $this->_closeSocket($socket);
                return FALSE;
            }
            $matches_stream = array();$matches_command = array();
            //close connection if header content is illegal
            if(!preg_match('/\<stream\>([1-9]\d*)\<\/stream\>/', $data, $matches_stream) || !preg_match('/\<task\>([\w\-\_]+)\<\/task\>/', $data,$matches_command))
            {
                $this->_logger->log("Illegal stream len: ". $data);
                $this->_closeSocket($socket);
                return FALSE;
            }
            //close when write connection false
            if(FALSE === socket_write($socket, "<stream>{$matches_stream[1]}</stream>\n"))
            {
                $this->_logger->log("Can't write back data.");
                $this->_closeSocket($socket);
                return FALSE;
            }
            //record the length of main content.
            $this->_request[(int)$socket] = array(
                'length'=>$matches_stream[1],
                'task'=>$matches_command[1],
                'data'=>NULL,
                'mtime'=>time(),
            );
        }
        //second read main content
        else
        {
            $stream_length = $this->_request[(int)$socket]['length'];
            
            $data = $this->_socketManager->read($socket,$stream_length,PHP_BINARY_READ);
            if($data===FALSE)
            {
                $this->_logger->log("Can't read the main data.");
                $this->_closeSocket($socket);
                return FALSE;
            }
            if(!empty($data) && $this->_request[(int)$socket]['data']===NULL)
            {
                $this->_request[(int)$socket]['data'] = $data;
                $this->_request[(int)$socket]['mtime'] = time();
            }
            
            if(FALSE === socket_write($socket, "<stream>".strlen($data)."</stream>\n"))
            {
                $this->_logger->log("Can't write back data.");
                $this->_closeSocket($socket);
            }
        }
    }
    
    private function _acceptNewConnection()
    {
        $new_connection = $this->_socketManager->accept();
        
        if($new_connection)
        {
            $this->_logger->log("Receive a new connection.");
            if($this->_status != 'running')
            {
                $this->_logger->log("Drop the new connection without running.");
                $this->_closeSocket($new_connection);
            }
            else
            {
                $this->_connections[(int)$new_connection] = $new_connection;
                $this->_request[(int)$new_connection] = array(
                    'length'=>0,
                    'task'=>'',
                    'data'=>NULL,
                    'mtime'=>time(),
                );
            }
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
                    if(key_exists($pid, $this->_tasks))
                    {
                        trigger_error("Task <{$this->_tasks[$pid]['name']}> crash exits.",E_USER_WARNING);
                        unset($this->_tasks[$pid]);
                    }
                    else
                    {
                        $this->_logger->log("Task normal exits.");
                    }
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