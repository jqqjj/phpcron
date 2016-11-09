<?php

namespace Crontab\Logger\DriverInterface;


interface DriverInterface
{
    public function log($msg);
}