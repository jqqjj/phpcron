<?php


namespace Crontab\Process;

use Crontab\Helper\RunnerBox;

class Worker
{
    private $_task;
    
    public function __construct($task)
    {
        $this->_task = $task;
    }
    
    public function run()
    {
        $runner = new RunnerBox();
        return $runner->run(function(){
            
        });
    }
}
