<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

return array(
    'daemon'=>array(
        'pid_path'=>'tmp',
        'pid_name'=>'phpcron.pid',
    ),
    'exception'=>array(
        'run_log'=>'tmp/run_log.txt',
        'error_log'=>'tmp/error.txt',
    ),
    'listen'=>array(
        'listen_port'=>6174,
        'display_errors'=>FALSE,
    ),
    'plugins'=>array(
        'echoTime'=>'TaskPlugin\EchoTime',
    ),
);