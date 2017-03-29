<?php

namespace Crontab\Config;

class ConfigManager
{
    private static $_config;
    private static $_instance = NULL;
    
    public static function get($index=NULL)
    {
        if(empty(self::$_instance))
        {
            self::$_instance = new self();
        }
        
        return self::$_instance->getConfig($index);
    }
    
    public function __construct()
    {
        if(empty(self::$_config))
        {
            $this->setConfig($this->_loadConfig());
        }
    }
    
    public function setConfig($config)
    {
        self::$_config = $config;
    }
    
    public function getConfig($index=NULL)
    {
        if(empty(self::$_config))
        {
            $this->setConfig($this->_loadConfig());
        }
        
        if(isset($index))
        {
            return $this->getIndexConfig(self::$_config,$index);
        }
        else
        {
            return self::$_config;
        }
    }

    private function _loadConfig()
    {
        $config_file = 'config'.DIRECTORY_SEPARATOR.'config.php';
        $config_file_local = 'config'.DIRECTORY_SEPARATOR.'local'.DIRECTORY_SEPARATOR.'config.php';
        if(!file_exists($config_file))
        {
            exit("Can't find the config file:".realpath($config_file_local).PHP_EOL);
        }
        
        $config_global = include $config_file;
        if(file_exists($config_file_local))
        {
            $config_local = include $config_file_local;
            $config = $this->_mergeConfig($config_global, $config_local);
        }
        else
        {
            $config = $config_global;
        }
        
        return $config;
    }
    
    private function getIndexConfig($config,$index)
    {
        $index_info = explode('.', $index);
        if(empty($index_info) || !is_array($config) || !isset($config[$index_info[0]]))
        {
            return NULL;
        }
        
        $preIndex = array_shift($index_info);
        if(count($index_info)>0)
        {
            return $this->getIndexConfig($config[$preIndex],  implode('.', $index_info));
        }
        else
        {
            return $config[$preIndex];
        }
    }
    
    private function _mergeConfig(Array $main_config,Array $overwrite_config)
    {
        foreach ($overwrite_config AS $key=>$value)
        {
            if(isset($main_config[$key]) && is_array($main_config[$key]) && is_array($overwrite_config[$key]))
            {
                $main_config[$key] = $this->_mergeConfig($main_config[$key], $overwrite_config[$key]);
            }
            else
            {
                $main_config[$key] = $value;
            }
        }
        return $main_config;
    }
}
