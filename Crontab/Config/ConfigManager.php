<?php

namespace Crontab\Config;

class ConfigManager
{
    private static $_config;
    
    public function __construct()
    {
        if(empty(self::$_config))
        {
            self::_loadConfig();
        }
    }
    
    public function getConfig($index=NULL)
    {
        if(isset($index))
        {
            return $this->getIndexConfig(self::$_config,$index);
        }
        else
        {
            return self::$_config;
        }
    }

    private static function _loadConfig()
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
            $config = self::_mergeConfig($config_global, $config_local);
        }
        else
        {
            $config = $config_global;
        }
        
        return self::$_config = $config;
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
    
    private static function _mergeConfig(Array $main_config,Array $overwrite_config)
    {
        foreach ($overwrite_config AS $key=>$value)
        {
            if(is_array($main_config[$key]) && is_array($overwrite_config[$key]))
            {
                $main_config[$key] = self::_mergeConfig($main_config[$key], $overwrite_config[$key]);
            }
            else
            {
                $main_config[$key] = $value;
            }
        }
        return $main_config;
    }
}