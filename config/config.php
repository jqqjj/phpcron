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
    'worker'=>array(
        'number'=>1,
    ),
    'socket'=>array(
        'listen_port'=>6174,
        'listen_addr'=>'127.0.0.1',
    ),
);