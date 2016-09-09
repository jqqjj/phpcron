<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
$con=socket_connect($socket,'127.0.0.1',6174);
if(!$con){socket_close($socket);exit;}
echo "Link\n";

$str = "Hello,this is the socket test.\n";
file_put_contents('client.txt', "");

for($i=0;$i<strlen($str)-1;$i++)
{
    socket_write($socket,$str{$i}."\n");
    $data = socket_read($socket, 1024, PHP_NORMAL_READ);
    if($data !== FALSE)
    {
        file_put_contents('client.txt', date("Y-m-d H:i:s").':'."==============#{$data}#=============",FILE_APPEND);
    }
    sleep(1);
}
socket_close($socket);