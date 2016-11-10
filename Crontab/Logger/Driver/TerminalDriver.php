<?php

namespace Crontab\Logger\Driver;

use Crontab\Logger\DriverInterface\DriverInterface;

class TerminalDriver implements DriverInterface
{
    public function log($msg)
    {
        echo date("Y/m/d H:i:s").': '.$msg.PHP_EOL;
    }
}