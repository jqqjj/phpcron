<?php


namespace Crontab\Process;

use Crontab\Config\ConfigManager;

class Worker
{
    private $plugins = array();

    public function __construct()
    {
        $this->_run();
        while (true)
        {
            sleep(mt_rand(5, 15));
        }
    }
    
    private function _run()
    {
        //load tasks
        file_put_contents('log.txt',"Start:".PHP_EOL);
        try
        {
            $path = ConfigManager::get('worker.plugin_path');
            if(file_exists($path) && is_dir($path))
            {
                //load plugin
                $this->_loadPlugin($path);
            }
        } catch (\Exception $ex)
        {
            file_put_contents('log.txt', $ex->getMessage().PHP_EOL,FILE_APPEND);
        }
        
        //run tasks
    }
    
    private function _loadPlugin($path)
    {
        $res = opendir($path);
        while ($file=readdir($res))
        {
            file_put_contents('log.txt', $file.PHP_EOL,FILE_APPEND);
            if($file == '.' || $file == '..')
            {
                file_put_contents('log.txt', 'system file'.PHP_EOL,FILE_APPEND);
                continue;
            }
            
            if(is_dir(rtrim($path,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file))
            {
                file_put_contents('log.txt', 'is dir'.PHP_EOL,FILE_APPEND);
                continue;
            }
        }
    }
}
