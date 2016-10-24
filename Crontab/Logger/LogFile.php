<?php

namespace Crontab\Logger;


use Crontab\Logger\LoggerInterface\LoggerInterface;

class LogFile implements LoggerInterface
{
    public function log($msg)
    {
        file_put_contents('log.txt', $msg."\r\n", FILE_APPEND);
    }
}