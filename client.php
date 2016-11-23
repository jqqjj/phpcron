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

$str = "Hello,this is the socket test.";
sleep(4);
//send header
echo date("Y/m/d H:i:s")." write 1 \n";
socket_write($socket,"<task>echoTime</task><stream>".strlen($str)."</stream>\n");
//receive the header back
$header_back = socket_read($socket, 1024, PHP_NORMAL_READ);

sleep(10);

//send main stream
echo date("Y/m/d H:i:s")." write 2 \n";
socket_write($socket,$str);
$content_back = socket_read($socket, 1024, PHP_NORMAL_READ);

//close socket
socket_close($socket);