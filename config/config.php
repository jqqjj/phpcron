<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

return array(
    'base'=>array(
        'pid_path'=>'tmp',
        'pid_name'=>'phpcron.pid',
    ),
    'exception'=>array(
        'default_logger_driver'=>'Crontab\Logger\Driver\FileDriver',
    ),
    'worker'=>array(
        'number'=>3,
    ),
    'listen'=>array(
        'listen_port'=>6174,
        'listen_addr'=>'127.0.0.1',
        'display_errors'=>FALSE,
    ),
);