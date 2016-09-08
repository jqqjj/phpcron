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
            pcntl_signal_dispatch();
            
            file_put_contents('log.txt', date("Y-m-d H:i:s").':',FILE_APPEND);
            file_put_contents('log.txt', 'after dispatch'.PHP_EOL,FILE_APPEND);
            
            $fd = $this->_socket->getSocket();
            $read = array_merge($this->_connections, array($fd));
            $write = array();
            
            file_put_contents('log.txt', date("Y-m-d H:i:s").':',FILE_APPEND);
            file_put_contents('log.txt', 'count read:'.count($read).PHP_EOL,FILE_APPEND);
            
            if(!socket_select($read, $write, $except = null, 10))
            {
                file_put_contents('log.txt', date("Y-m-d H:i:s").':',FILE_APPEND);
                file_put_contents('log.txt', 'select continue'.PHP_EOL,FILE_APPEND);
                continue;
            }
            file_put_contents('log.txt', date("Y-m-d H:i:s").':',FILE_APPEND);
            file_put_contents('log.txt', 'select true'.PHP_EOL,FILE_APPEND);
            
            if(in_array($fd, $read))
            {
                file_put_contents('log.txt', date("Y-m-d H:i:s").':',FILE_APPEND);
                file_put_contents('log.txt', 'new connection comes'.PHP_EOL,FILE_APPEND);
                $connection = socket_accept($fd);
                $this->_connections[] = $connection;
            }
            
            unset($read[array_search($fd, $read)]);
            
            foreach ($read AS $socket)
            {
                file_put_contents('socket_'.  getmypid().'.txt', socket_read($socket, 512, PHP_NORMAL_READ),FILE_APPEND);
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
