<?php


namespace Crontab\Task;


interface TaskInterface
{
    public function getConfig();
    
    public function work();
}