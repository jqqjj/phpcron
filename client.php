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

//send header
socket_write($socket,"<command>-_-</command><stream>".strlen($str)."</stream>\n");
//receive the header back
$header_back = socket_read($socket, 1024, PHP_NORMAL_READ);

sleep(5);

//send main stream
socket_write($socket,$str);
$content_back = socket_read($socket, 1024, PHP_NORMAL_READ);

//close socket
socket_close($socket);