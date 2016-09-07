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
        $port = ConfigManager::get('listen.listen_port');
        
        $display_errors = ini_get('display_errors');
        ini_set('display_errors',0);
        
        try
        {
            if(!$this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))
            {
                throw new \Exception("can not create socket");
            }

            if(!socket_set_option($this->_socket, SOL_SOCKET, SO_REUSEADDR, 1))
            {
                socket_close($this->_socket);
                throw new \Exception("can not set socket option");
            }

            if(!socket_bind($this->_socket, $address, $port))
            {
                socket_close($this->_socket);
                throw new \Exception("can not bind socket");
            }

            if(!socket_listen($this->_socket, 5))
            {
                socket_close($this->_socket);
                throw new \Exception("can not listen");
            }
            
        }
        catch (\Exception $ex)
        {
            ini_set('display_errors',$display_errors);
            $this->_message[] = $ex->getMessage();
            return FALSE;
        }
        
        ini_set('display_errors',$display_errors);
        return TRUE;
    }
    
}