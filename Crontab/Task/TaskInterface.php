<?php


namespace Crontab\Task;


interface TaskInterface
{
    public function work(array $data);
}