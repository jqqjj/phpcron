<?php

namespace Crontab\Logger\Driver;


use Crontab\Logger\DriverInterface\DriverInterface;
use Crontab\Config\ConfigManager;

class ErrorDriver implements DriverInterface
{
    private $_handler = NULL;
    
    public function __construct()
    {
        $log_file = ConfigManager::get('exception.error_log');
        if($this->_checkFile($log_file))
        {
            $this->_handler = fopen($log_file, "ab+");
        }
    }

    public function log($msg)
    {
        if($this->_handler)
        {
            $header = sprintf("============================%s============================",date("Y/m/d H:i:s"));
            fwrite($this->_handler, $header."\r\n".$msg."\r\n");
        }
    }
    
    private function _checkFile($file)
    {
        if(file_exists($file) && is_readable($file) && is_writable($file))
        {
            return TRUE;
        }
        
        $log_path = dirname($file);
        if(file_exists($log_path) && is_readable($log_path) && is_writable($log_path))
        {
            return TRUE;
        }
        
        return FALSE;
    }
    
    public function __destruct()
    {
        if($this->_handler)
        {
            fclose($this->_handler);
        }
    }
}