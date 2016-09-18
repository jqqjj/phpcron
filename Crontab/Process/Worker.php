<?php


namespace Crontab\Process;

use Crontab\Config\ConfigManager;
use Crontab\Task\TaskInterface;
use Crontab\IO\SocketManager;

class Worker
{
    private $_plugins = array();
    private $_socketManager;
    private $_connections = array();
    private $_stream = array();
    private $_status = NULL;
    
    public function __construct($socket)
    {
        $this->_socketManager = new SocketManager($socket);
        $this->_status = 'running';
    }
    
    public function run()
    {
        $this->_registerSignal();
        
        $this->_loadPlugin(ConfigManager::get('plugins'));
        
        while($this->_status == 'running' || !empty($this->_connections))
        {
            $new_connection = FALSE;
            $fd = $this->_socketManager->getSocket();
            
            //if receives a stop command,it will not accepts new connecitons.
            if($this->_status != 'running')
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
            //read and write data
            $this->_socketsIO(array_filter(array_merge($sockets,array($new_connection))));
            
            pcntl_signal_dispatch();
        }
    }
    
    private function _socketsIO(Array $sockets)
    {
        if(in_array($this->_socketManager->getSocket(), $sockets))
        {
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
        if(!isset($this->_stream[(int)$socket]) || $this->_stream[(int)$socket]<=0)
        {
            $data = $this->_socketManager->read($socket);
            $matches_stream = array();$matches_command = array();
            //close connection if header content is illegal
            if(!preg_match('/\<stream\>([1-9]\d*)\<\/stream\>/', $data, $matches_stream) || !preg_match('/\<command\>[\w\-\_]+\<\/command\>/', $data,$matches_command))
            {
                $this->_closeSocket($socket);
                return FALSE;
            }
            //close when write connection false
            if(FALSE === socket_write($socket, "<stream>{$matches_stream[1]}</stream>\n"))
            {
                $this->_closeSocket($socket);
                return FALSE;
            }
            //record the length of main content.
            $this->_stream[(int)$socket] = $matches_stream[1];
        }
        //second read main content
        else
        {
            $stream_length = $this->_stream[(int)$socket];
            unset($this->_stream[(int)$socket]);
            
            $data = $this->_socketManager->read($socket,$stream_length,PHP_BINARY_READ);
            if($data===FALSE)
            {
                $this->_closeSocket($socket);
                return FALSE;
            }
            
            if(FALSE === socket_write($socket, "<stream>".strlen($data)."</stream>\n"))
            {
                socket_close($socket);
                unset($this->_connections[array_search($socket, $this->_connections)]);
            }
            
            //add the mapped plugin task
        }
    }
    
    private function _closeSocket($socket)
    {
        if(in_array($socket, $this->_connections))
        {
            socket_close($socket);
            unset($this->_connections[array_search($socket, $this->_connections)]);
            return TRUE;
        }
        else
        {
            return FALSE;
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
                $this->_status = 'stopping';
                break;
            
            default :
                break;
        }
    }
}
