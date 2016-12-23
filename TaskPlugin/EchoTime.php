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
    
    public function getConfig()
    {
        return "*/2 * * * * *";
    }
    
    public function canWork()
    {
        return TRUE;
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
        
    }
}