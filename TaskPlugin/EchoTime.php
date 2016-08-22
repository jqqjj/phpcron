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
    public function getConfig()
    {
        return "* * * * * *";
    }

    public function work()
    {
        file_put_contents('work.txt', date("Y-m-d H:i:s").PHP_EOL);
    }
}