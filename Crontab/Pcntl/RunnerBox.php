<?php

namespace Crontab\Pcntl;

class RunnerBox
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