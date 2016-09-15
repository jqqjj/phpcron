<?php


namespace Crontab\Process;

use Crontab\Config\ConfigManager;
use Crontab\Task\TaskInterface;
use Crontab\Network\SocketManager;

class Worker
{
    private $_plugins = array();
    private $_socketManager;
    private $_connections = array();
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
            $this->_exchange(array_filter(array_merge($sockets,array($new_connection))));
            
            pcntl_signal_dispatch();
        }
    }
    
    private function _exchange(Array $sockets)
    {
        if(in_array($this->_socketManager->getSocket(), $sockets))
        {
            unset($sockets[array_search($this->_socketManager->getSocket(), $sockets)]);
        }
        
        foreach ($sockets AS $socket)
        {
            //read data
            $data = $this->_socketManager->read($socket);
            if($data===FALSE)
            {
                socket_close($socket);
                unset($this->_connections[array_search($socket, $this->_connections)]);
                continue;
            }
            
            file_put_contents('server.txt', date("Y-m-d H:i:s").':'.$data,FILE_APPEND);
            
            //write data
            $write_data = "receive\n";
            if(FALSE === socket_write($socket, $write_data,strlen($write_data)))
            {
                socket_close($socket);
                unset($this->_connections[array_search($socket, $this->_connections)]);
                continue;
            }
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
