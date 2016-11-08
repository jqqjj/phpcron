<?php

namespace Crontab\Logger\Driver;


use Crontab\Logger\LoggerInterface\LoggerInterface;

class FileDriver implements LoggerInterface
{
    public function log($msg)
    {
        file_put_contents('log.txt', $msg."\r\n", FILE_APPEND);
    }
}