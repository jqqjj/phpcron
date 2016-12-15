<?php

/* 
 * Copyright (C) 2016 phpcron
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace TaskPlugin;

use Crontab\Task\TaskInterface;
use Crontab\Logger\Container\Logger AS LoggerContainer;

class EchoTime implements TaskInterface
{
    private $_params;
    private $count = 0;
    
    public function canWork()
    {
        LoggerContainer::getDefaultDriver()->log('Run EchoTime canWork function.');
        return $this->count<=3;
    }
    
    public function getConfig()
    {
        return "16-50/4 * * * 2-5/3 1-4";
    }
    
    public function onStart(array $data)
    {
        $this->_params = $data;
        LoggerContainer::getDefaultDriver()->log('Run EchoTime onStart function.');
    }
    
    public function onStop()
    {
        LoggerContainer::getDefaultDriver()->log('Run EchoTime onStop function.');
    }
    
    public function onWork()
    {
        $this->count++;
        LoggerContainer::getDefaultDriver()->log('Run EchoTime onWork function.');
    }
}