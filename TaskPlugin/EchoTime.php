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
    public function work(array $data)
    {
        file_put_contents('echotime.txt', date("Y-m-d H:i:s").'##'.$data['data'].PHP_EOL,FILE_APPEND);
    }
}