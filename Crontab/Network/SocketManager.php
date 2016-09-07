<?php


namespace Crontab\Network;

use Crontab\Config\ConfigManager;

class SocketManager
{
    private $_socket;
    private $_message = array();

    public function __construct()
    {
        
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

    public function init()
    {
        if(is_resource($this->_socket))
        {
            return TRUE;
        }
        
        $address = ConfigManager::get('listen.listen_addr');
        $port = ConfigManager::get('listen.listen_port');
        
        $display_errors = ini_get('display_errors');
        ini_set('display_errors',0);
        
        try
        {
            if(!$this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))
            {
                throw new \Exception(socket_strerror(socket_last_error($this->_socket)));
            }

            if(!socket_set_option($this->_socket, SOL_SOCKET, SO_REUSEADDR, 1))
            {
                throw new \Exception(socket_strerror(socket_last_error($this->_socket)));
            }

            if(!socket_bind($this->_socket, $address, $port))
            {
                throw new \Exception(socket_strerror(socket_last_error($this->_socket)));
            }

            if(!socket_listen($this->_socket, 5))
            {
                throw new \Exception(socket_strerror(socket_last_error($this->_socket)));
            }
        }
        catch (\Exception $ex)
        {
            if(is_resource($this->_socket))
            {
                socket_close($this->_socket);
            }
            
            $this->_message[] = $ex->getMessage();
            
            ini_set('display_errors',$display_errors);
            return FALSE;
        }
        
        ini_set('display_errors',$display_errors);
        return TRUE;
    }
    
    public function __destruct()
    {
        if(is_resource($this->_socket))
        {
            socket_close($this->_socket);
        }
    }
}