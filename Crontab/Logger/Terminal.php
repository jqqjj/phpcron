<?php

namespace Crontab\Logger;

use Crontab\Logger\LoggerInterface\LoggerInterface;

class Terminal implements LoggerInterface
{
    public function log($msg)
    {
        echo $msg;
    }
}