<?php


namespace Crontab\Task;


interface TaskInterface
{
    /**
     * 任务执行间隔规则
     * @return String rules
     */
    public function getConfig();
    /**
     * 任务启动时执行
     * @param array $data
     */
    public function onStart(array $data);
    /**
     * 退出任务前执行代码
     */
    public function onStop();
    /**
     * @return bool 是否可以执行任务
     */
    public function canWork();
    /**
     * 任务执行主代码
     */
    public function onWork();
}