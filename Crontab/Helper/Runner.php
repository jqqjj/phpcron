<?php

namespace Crontab\Helper;

class Runner
{
    public function run(\Closure $function)
    {
        $pid = pcntl_fork();
        if($pid==-1)
        {
            return FALSE;
        }
        elseif($pid>0)
        {
            return $pid;
        }
        else
        {
            $function();
            exit(getmypid());
        }
    }
}