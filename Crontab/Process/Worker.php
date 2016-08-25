<?php


namespace Crontab\Process;

use Crontab\Config\ConfigManager;

class Worker
{
    private $_plugins = array();

    public function __construct()
    {
        //$this->_run();
        $this->_registerSignal();
        //loop until receive stop command or workers is empty
        while(TRUE)
        {
            pcntl_signal_dispatch();
            sleep(10);
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
        file_put_contents('child_signal.txt',date('Y-m-d H:i:s')."\t",FILE_APPEND);
        file_put_contents('child_signal.txt',"pid:".getmypid()."\t",FILE_APPEND);
        file_put_contents('child_signal.txt',"signal:{$signo}\r\n",FILE_APPEND);
        
        switch ($signo)
        {
            //exit handler
            case SIGHUP:
            case SIGINT:
            case SIGQUIT:
            case SIGTERM:
                exit(0);
                break;
            
            default :
                break;
        }
    }
}
