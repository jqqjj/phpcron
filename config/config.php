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
    'plugins'=>array(
        #The best practices is set the plugins record in the local config.php
        'curl'=>array(
            'class'=>'TaskPlugin\Curl',
            'enabled'=>TRUE,
            'params'=>array(
                'method'=>'GET',
                'url'=>'www.google.com',
            ),
        ),
    ),
);