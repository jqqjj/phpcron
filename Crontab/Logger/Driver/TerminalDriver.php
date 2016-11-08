<?php

namespace Crontab\Logger\Driver;

use Crontab\Logger\LoggerInterface\LoggerInterface;

class TerminalDriver implements LoggerInterface
{
    public function log($msg)
    {
        echo date("Y-m-d H:i:s").':'.PHP_EOL.$msg.PHP_EOL;
    }
}