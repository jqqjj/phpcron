<?php

namespace Crontab\Logger\Driver;

use Crontab\Logger\DriverInterface\DriverInterface;

class TerminalDriver implements DriverInterface
{
    public function log($msg)
    {
        echo date("Y/m/d H:i:s").':'.PHP_EOL.$msg.PHP_EOL;
    }
}