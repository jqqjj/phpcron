<?php


namespace Crontab\Network;

use Crontab\Config\ConfigManager;

class Socket
{
    private $_socket;
    private $_message = array();

    public function __construct()
    {
        $this->_create();
    }
    
    public function getSocket()
    {
        return $this->_socket;
    }
    
    public function getMessage()
    {
        if(!empty($this->_message))
        {
            return array_pop($this->_message);
        }
        else
        {
            return FALSE;
        }
    }

    private function _create()
    {
        $address = ConfigManager::get('listen.listen_addr');
        $port = ConfigManager::get('listen.listen_addr');
        
        if(!$this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))
        {
            $this->_message[] = "can not create socket";
            return FALSE;
        }
        
        if(!socket_set_option($this->_socket, SOL_SOCKET, SO_REUSEADDR, 1))
        {
            socket_close($this->_socket);
            $this->_message[] = "can not set socket option";
            return FALSE;
        }
        
        if(!socket_bind($this->_socket, $address, $port))
        {
            socket_close($this->_socket);
            $this->_message[] = "can not bind socket";
            return false;
        }
        
        if(!socket_listen($this->_socket, 5))
        {
            socket_close($this->_socket);
            $this->_message[] = "can not listen";
            return false;
        }
    }
    
}