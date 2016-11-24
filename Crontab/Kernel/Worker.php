<?php


namespace Crontab\Kernel;

use Crontab\Helper\RunnerBox;
use Crontab\Logger\Container\Logger AS LoggerContainer;

class Worker
{
    public function run($task,$data)
    {
        LoggerContainer::getDefaultDriver()->log('runbox:'.$task);
        $runner = new RunnerBox();
        return $runner->run(function() use ($task,$data){
            $plugin = new $task;
            $plugin->work($data);
        });
    }
}
