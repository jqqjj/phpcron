<?php


namespace Crontab\IO;

use Crontab\Config\ConfigManager;

class SocketManager
{
    private $_socket;
    private $_message = array();
    private $_debug = false;

    public function __construct($socket=NULL)
    {
        $this->_debug = (bool)ConfigManager::get('listen.display_errors');
        if(is_resource($socket))
        {
            $this->setSocket($socket);
        }
    }
    
    public function getSocket()
    {
        if(empty($this->_socket))
        {
            $this->generate();
        }
        return $this->_socket;
    }
    
    public function setSocket($socket)
    {
        if(is_resource($socket))
        {
            $this->_socket = $socket;
            return TRUE;
        }
        else
        {
            return FALSE;
        }
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
    
    public function generate()
    {
        if(is_resource($this->_socket))
        {
            return TRUE;
        }
        
        $address = ConfigManager::get('listen.listen_addr');
        $port = ConfigManager::get('listen.listen_port');
        
        $this->_debug('prefix');
        
        $socket = NULL;
        
        try
        {
            if(!$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))
            {
                throw new \Exception(socket_strerror(socket_last_error($socket)));
            }
            
            if(!socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1))
            {
                throw new \Exception(socket_strerror(socket_last_error($socket)));
            }
            
            if(!socket_bind($socket, $address, $port))
            {
                throw new \Exception(socket_strerror(socket_last_error($socket)));
            }
            
            if(!socket_listen($socket, 5))
            {
                throw new \Exception(socket_strerror(socket_last_error($socket)));
            }
        }
        catch (\Exception $ex)
        {
            if(is_resource($socket))
            {
                socket_close($socket);
            }
            
            $this->_message[] = $ex->getMessage();
            
            $this->_debug('suffix');
            
            return FALSE;
        }
        
        $this->_socket = $socket;
        
        $this->_debug('suffix');
        
        return TRUE;
    }
    
    public function set_block_mode($mode)
    {
        if(!is_resource($this->_socket))
        {
            return FALSE;
        }
        
        $this->_debug('prefix');
        
        if($mode)
        {
            $result = socket_set_block($this->_socket);
        }
        else
        {
            $result = socket_set_nonblock($this->_socket);
        }
        
        $this->_debug('suffix');
        
        return $result;
    }
    
    public function accept()
    {
        if(!is_resource($this->_socket))
        {
            return FALSE;
        }
        
        $this->_debug('prefix');
        
        $connection = socket_accept($this->_socket);
        
        $this->_debug('suffix');
        
        if($connection!==FALSE)
        {
            return $connection;
        }
        
        return FALSE;
    }
    
    public function select($read)
    {
        $this->_debug('prefix');
        
        $write = array();
        $except=NULL;
        if(empty($read) || FALSE===socket_select($read, $write, $except, 5))
        {
            //here you will get Success by using socket_last_error function to get the error.(PHP 7.0.9)
            $result = array();
        }
        else
        {
            $result = $read;
        }
        
        $this->_debug('suffix');
        
        return $result;
    }
    
    public function read($socket,$len=2048,$type=PHP_NORMAL_READ)
    {
        $this->_debug('prefix');
        
        $data = socket_read($socket, $len, $type);
        
        $this->_debug('suffix');
        
        return $data;
    }
    
    public function __destruct()
    {
        if(is_resource($this->_socket))
        {
            socket_close($this->_socket);
        }
    }
    
    private function _debug($mode=NULL)
    {
        static $sysconfig = NULL;
        if(empty($sysconfig))
        {
            $sysconfig = ini_get('display_errors');
        }
        
        switch ($mode)
        {
            case "prefix":
                ini_set('display_errors',  $this->_debug);
                break;
            case "suffix":
            default :
                ini_set('display_errors',$sysconfig);
                break;
        }
    }
}