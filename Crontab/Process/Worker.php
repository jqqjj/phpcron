<?php


namespace Crontab\Process;

use Crontab\Config\ConfigManager;
use Crontab\Task\TaskInterface;
use Crontab\Network\SocketManager;

class Worker
{
    private $_plugins = array();
    private $_socket;
    private $_connections = array();
    private $_status = NULL;
    
    public function __construct(SocketManager $socket)
    {
        $this->_socket = $socket;
        $this->_status = 'running';
    }
    
    public function run()
    {
        $this->_registerSignal();
        
        $this->_loadPlugin(ConfigManager::get('plugins'));
        
        while($this->_status == 'running')
        {
            $sockets = $this->_select_read();
            //new connection handler
            if(in_array($this->_socket->getSocket(), $sockets))
            {
                $this->_connections[] = socket_accept($this->_socket->getSocket());
                unset($sockets[array_search($this->_socket->getSocket(), $sockets)]);
            }
            
            foreach ($sockets AS $socket)
            {
                //read data
                $data = socket_read($socket, 1024, PHP_NORMAL_READ);
                if($data===FALSE)
                {
                    socket_close($socket);
                    unset($this->_connections[array_search($socket, $this->_connections)]);
                    continue;
                }
                //write data
                file_put_contents('socket_'.  getmypid().'.txt', socket_read($socket, 512, PHP_NORMAL_READ),FILE_APPEND);
            }
            
            pcntl_signal_dispatch();
        }
    }
    
    private function _select_read()
    {
        $fd = $this->_socket->getSocket();
        $read = array_merge($this->_connections, array($fd));
        $write = array();
        socket_select($read, $write, $except = null, 10);
        
        return $read;
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
