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
    private $_tasks;
    private $_logger;
    private $_connections;
    private $_stream;
    //waiting、addtask、taskexit、masterexit、taskfinish
    private $_command;
    private $_socketManager;

    public function __construct()
    {
        $this->_logger = LoggerContainer::getDefaultDriver();
        $this->_connections = array();
        $this->_tasks = array();
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
        
        //exit log
        $this->_logger->log("Phpcron normal exits.");
    }
    
    private function _loop()
    {
        while ($this->_command == 'waiting' || !empty($this->_connections) || !empty($this->_tasks))
        {
            $this->_processConnections();
            $this->_processTasks();
            
            pcntl_signal_dispatch();
        }
    }
    
    private function _processTasks()
    {
        $this->_logger->log("Data:".print_r($this->_stream,TRUE));
    }
    
    private function _processConnections()
    {
        $sockets = $this->_socketManager->select( array_merge($this->_connections,array($this->_socketManager->getSocket())) );
        
        if(empty($sockets))
        {
            return ;
        }
        
        $new_connection = NULL;
        if(in_array($this->_socketManager->getSocket(),$sockets))
        {
            $this->_logger->log("New connection is ready.");
            $new_connection = $this->_socketManager->accept();
            unset($sockets[array_search($this->_socketManager->getSocket(), $sockets)]);
        }
        
        if($this->_command != 'waiting' && $new_connection)
        {
            $this->_logger->log("Drop new connection.");
            $this->_closeSocket($new_connection);
        }
        elseif($new_connection)
        {
            $this->_logger->log("Add new connection to pool.");
            $this->_connections[(int)$new_connection] = $new_connection;
        }
        
        foreach ($sockets AS $socket)
        {
            $this->_IO($socket);
        }
    }
    
    private function _IO($socket)
    {
        $this->_logger->log("Exchange data.");
        //first read the content length
        if(!isset($this->_stream[(int)$socket]) || !isset($this->_stream[(int)$socket]['length']) || $this->_stream[(int)$socket]['length']<=0)
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
            if(!preg_match('/\<stream\>([1-9]\d*)\<\/stream\>/', $data, $matches_stream) || !preg_match('/\<command\>([\w\-\_]+)\<\/command\>/', $data,$matches_command))
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
            $this->_stream[(int)$socket]['length'] = $matches_stream[1];
            $this->_stream[(int)$socket]['command'] = $matches_command[1];
        }
        //second read main content
        else
        {
            $stream_length = $this->_stream[(int)$socket]['length'];
            
            $data = $this->_socketManager->read($socket,$stream_length,PHP_BINARY_READ);
            if($data===FALSE)
            {
                $this->_logger->log("Can't read the main data.");
                $this->_closeSocket($socket);
                return FALSE;
            }
            if(!empty($data))
            {
                $this->_stream[(int)$socket]['data'] = $data;
            }
            
            if(FALSE === socket_write($socket, "<stream>".strlen($data)."</stream>\n"))
            {
                $this->_logger->log("Can't write back data.");
                $this->_closeSocket($socket);
            }
            
            //add the mapped plugin task
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
    
    private function _closeSocket($socket)
    {
        socket_close($socket);
        if(array_search($socket, $this->_connections))
        {
            unset($this->_connections[array_search($socket, $this->_connections)]);
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