<?php

/* 
 * Copyright (C) 2016 phpcron
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace TaskPlugin;

use Crontab\Task\TaskInterface;

class EchoTime implements TaskInterface
{
    private $_params;
    
    public function canWork()
    {
        return TRUE;
    }
    
    public function getConfig()
    {
        return "0-30/6,* * * * * *";
    }
    
    public function onStart(array $data)
    {
        $this->_params = $data;
    }
    
    public function onStop()
    {
        
    }
    
    public function onWork()
    {
        file_put_contents('echotime.txt', date("Y-m-d H:i:s").'##'.$this->_params['data'].PHP_EOL,FILE_APPEND);
    }
}